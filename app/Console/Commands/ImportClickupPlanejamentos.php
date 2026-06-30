<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportClickupPlanejamentos extends Command
{
    protected $signature = 'clickup:import-planejamentos
                            {--dry-run : Simula sem inserir no banco}
                            {--force : Re-importa mesmo os que já existem (atualiza)}';

    protected $description = 'Importa Roadmaps ativos do ClickUp como macro_plans no App';

    // Lista "Planejamentos (Roadmaps)" no OPERACIONAL
    const LIST_ID = '901326341797';
    const ORG_ID  = '83050dbf-8eee-4214-bcd4-e919353aafe4';

    const CLOSED_STATUSES = ['concluído', 'concluido', 'finalizado', 'encerrado', 'cancelado', 'cancelada', 'closed'];

    const STATUS_MAP = [
        'em execução'    => 'active',
        'em planejamento'=> 'draft',
        'stand by'       => 'draft',
    ];

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $force   = $this->option('force');
        $adminId = DB::connection('pgsql')->table('users')->orderBy('id')->value('id');
        $totalAll = DB::connection('pgsql')->table('clickup_tasks')->where('list_id', self::LIST_ID)->count();

        $this->info('Buscando planejamentos (Roadmaps) ativos no ClickUp...');

        $closedSql = $this->closedStatusesSql();

        $plans = DB::connection('pgsql')->select("
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
                    ) AS client_task_id
                FROM clickup_tasks ct
                WHERE ct.list_id = :list_id
                  AND LOWER(ct.status) NOT IN ({$closedSql})
            )
            SELECT e.*, c.id AS app_client_id, c.company_name
            FROM extracted e
            INNER JOIN clients c ON c.clickup_task_id = e.client_task_id
        ", ['list_id' => self::LIST_ID]);

        $total = count($plans);
        $this->info("Encontrados {$total} planejamentos ativos com cliente vinculado.");

        if ($dryRun) {
            $this->warn('[DRY RUN] Nenhum dado será inserido.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = $updated = $skipped = 0;

        foreach ($plans as $plan) {
            $exists = DB::connection('pgsql')
                ->table('macro_plans')
                ->where('clickup_task_id', $plan->task_id)
                ->exists();

            if ($exists && !$force) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $status    = self::STATUS_MAP[strtolower($plan->status)] ?? 'draft';
            $created   = $plan->date_created ? Carbon::parse($plan->date_created) : now();
            $updatedAt = $plan->date_updated ? Carbon::parse($plan->date_updated) : now();

            // Sem período explícito no ClickUp — usa data de criação + 90 dias como padrão
            $periodStart = $created->toDateString();
            $periodEnd   = $created->copy()->addDays(90)->toDateString();

            $row = [
                'organization_id' => self::ORG_ID,
                'client_id'       => $plan->app_client_id,
                'responsible_id'  => null,
                'created_by'      => $adminId,
                'title'           => mb_substr($plan->task_name, 0, 200),
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
                'status'          => $status,
                'bloco1'          => null,
                'bloco2'          => null,
                'bloco4'          => null,
                'bloco5'          => null,
                'launched_at'     => $created,
                'clickup_task_id' => $plan->task_id,
                'created_at'      => $created,
                'updated_at'      => $updatedAt,
            ];

            if (!$dryRun) {
                if ($exists) {
                    DB::connection('pgsql')->table('macro_plans')
                        ->where('clickup_task_id', $plan->task_id)
                        ->update(array_merge($row, ['updated_at' => now()]));
                    $updated++;
                } else {
                    DB::connection('pgsql')->table('macro_plans')
                        ->insert(array_merge(['id' => Str::uuid()->toString()], $row));
                    $imported++;
                }
            } else {
                $this->newLine();
                $this->line("  [DRY] {$plan->task_name} → {$status} | cliente: {$plan->company_name} | período: {$periodStart} → {$periodEnd}");
                $imported++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Quantidade'],
            [
                ['Importados' . ($dryRun ? ' (simulado)' : ''), $imported],
                ['Atualizados (--force)', $updated],
                ['Já existiam (pulados)', $skipped],
                ['Sem cliente ou fechados (pulados)', $totalAll - $total],
            ]
        );

        return self::SUCCESS;
    }

    private function closedStatusesSql(): string
    {
        return implode(', ', array_map(fn($s) => "'" . addslashes($s) . "'", self::CLOSED_STATUSES));
    }
}
