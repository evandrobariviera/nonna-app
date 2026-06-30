<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportClickupProjetos extends Command
{
    protected $signature = 'clickup:import-projetos
                            {--dry-run : Simula sem inserir no banco}
                            {--force : Re-importa mesmo os que já existem (atualiza)}';

    protected $description = 'Importa projetos ativos do ClickUp como projects no App';

    // Lista "Projetos (Projects)" no OPERACIONAL / Fluxo
    const LIST_ID = '901326341887';
    const ORG_ID  = '83050dbf-8eee-4214-bcd4-e919353aafe4';

    const CLOSED_STATUSES = ['concluído', 'concluido', 'finalizado', 'encerrado', 'cancelado', 'cancelada', 'closed'];

    const STATUS_MAP = [
        'em execução'    => 'active',
        'em planejamento'=> 'draft',
        'stand by'       => 'draft',
        'stand-by'       => 'draft',
    ];

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $force   = $this->option('force');
        $totalAll = DB::connection('pgsql')->table('clickup_tasks')->where('list_id', self::LIST_ID)->count();

        $this->info('Buscando projetos ativos no ClickUp...');

        // Pré-carrega macro_plans importados: client_id → macro_plan id
        // Para linkar projetos ao planejamento do mesmo cliente quando existir
        $macroPlansByClient = DB::connection('pgsql')
            ->table('macro_plans')
            ->whereNotNull('clickup_task_id') // apenas os importados do ClickUp
            ->where('status', 'active')
            ->pluck('id', 'client_id')
            ->toArray();

        $closedSql = $this->closedStatusesSql();

        $projects = DB::connection('pgsql')->select("
            WITH extracted AS (
                SELECT
                    ct.task_id,
                    ct.task_name,
                    ct.status,
                    ct.description,
                    ct.date_created,
                    ct.date_updated,
                    (
                        SELECT elem->'value'->0->>'id'
                        FROM jsonb_array_elements(ct.custom_fields::jsonb) AS elem
                        WHERE elem->>'name' LIKE '%cliente_relacionado%'
                          AND elem->'value' IS NOT NULL AND elem->>'value' NOT IN ('null','[]')
                        LIMIT 1
                    ) AS client_task_id,
                    (
                        SELECT elem->'value'->>'name'
                        FROM jsonb_array_elements(ct.custom_fields::jsonb) AS elem
                        WHERE elem->>'name' = 'Situação' AND elem->'value' IS NOT NULL
                        LIMIT 1
                    ) AS situacao
                FROM clickup_tasks ct
                WHERE ct.list_id = :list_id
                  AND LOWER(ct.status) NOT IN ({$closedSql})
            )
            SELECT e.*, c.id AS app_client_id, c.company_name
            FROM extracted e
            INNER JOIN clients c ON c.clickup_task_id = e.client_task_id
            ORDER BY e.date_created ASC
        ", ['list_id' => self::LIST_ID]);

        $total = count($projects);
        $this->info("Encontrados {$total} projetos ativos com cliente vinculado.");

        if ($dryRun) {
            $this->warn('[DRY RUN] Nenhum dado será inserido.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = $updated = $skipped = 0;
        $positionByClient = [];

        foreach ($projects as $project) {
            $exists = DB::connection('pgsql')
                ->table('projects')
                ->where('clickup_task_id', $project->task_id)
                ->exists();

            if ($exists && !$force) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $status    = self::STATUS_MAP[strtolower($project->status)] ?? 'draft';
            $created   = $project->date_created ? Carbon::parse($project->date_created) : now();
            $updatedAt = $project->date_updated ? Carbon::parse($project->date_updated) : now();

            // Posição sequencial por cliente
            $positionByClient[$project->app_client_id] = ($positionByClient[$project->app_client_id] ?? 0) + 1;
            $position = $positionByClient[$project->app_client_id];

            // Link ao macro_plan do mesmo cliente se existir
            $macroPlanId = $macroPlansByClient[$project->app_client_id] ?? null;

            $row = [
                'organization_id' => self::ORG_ID,
                'macro_plan_id'   => $macroPlanId,
                'client_id'       => $project->app_client_id,
                'position'        => $position,
                'title'           => mb_substr($project->task_name, 0, 200),
                'objective'       => $project->description,
                'disciplines'     => null,
                'briefing_criacao'=> null,
                'briefing_web'    => null,
                'briefing_trafego'=> null,
                'briefing_setup'  => null,
                'briefing_social' => null,
                'briefing_seo'    => null,
                'briefing_email'  => null,
                'status'          => $status,
                'clickup_task_id' => $project->task_id,
                'launched_at'     => $created,
                'created_at'      => $created,
                'updated_at'      => $updatedAt,
            ];

            if (!$dryRun) {
                if ($exists) {
                    DB::connection('pgsql')->table('projects')
                        ->where('clickup_task_id', $project->task_id)
                        ->update(array_merge($row, ['updated_at' => now()]));
                    $updated++;
                } else {
                    DB::connection('pgsql')->table('projects')
                        ->insert(array_merge(['id' => Str::uuid()->toString()], $row));
                    $imported++;
                }
            } else {
                $macroPlanLabel = $macroPlanId ? 'com plano' : 'sem plano';
                $this->newLine();
                $this->line("  [DRY] {$project->task_name} → {$status} | {$project->company_name} | {$macroPlanLabel}");
                $imported++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $withPlan  = count(array_filter(array_keys($positionByClient), fn($cid) => isset($macroPlansByClient[$cid])));
        $this->table(
            ['Status', 'Quantidade'],
            [
                ['Importados' . ($dryRun ? ' (simulado)' : ''), $imported],
                ['Atualizados (--force)', $updated],
                ['Já existiam (pulados)', $skipped],
                ['Fechados / sem cliente (pulados)', $totalAll - $total],
                ['Clientes com macro_plan vinculado', $withPlan],
                ['Clientes sem macro_plan (macro_plan_id null)', $total - $withPlan],
            ]
        );

        return self::SUCCESS;
    }

    private function closedStatusesSql(): string
    {
        return implode(', ', array_map(fn($s) => "'" . addslashes($s) . "'", self::CLOSED_STATUSES));
    }
}
