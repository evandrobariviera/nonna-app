<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportClickupChamados extends Command
{
    protected $signature = 'clickup:import-chamados
                            {--dry-run : Simula sem inserir no banco}
                            {--force : Re-importa mesmo os que já existem (atualiza)}';

    protected $description = 'Importa chamados/tickets do ClickUp (lista Chamados Tickets) para o App';

    const LIST_ID = '900701505618';
    const ORG_ID  = '83050dbf-8eee-4214-bcd4-e919353aafe4';

    const STATUS_MAP = [
        'triagem'          => 'backlog',
        'em atendimento'   => 'em_producao',
        'em criação'       => 'em_producao',
        'aprovação'        => 'aguardando_resposta',
        'alteração'        => 'ajuste',
        'finalizado'       => 'concluido',
    ];

    // Statuses considerados encerrados — não importar
    const CLOSED_STATUSES = [
        'finalizado', 'concluido', 'concluído', 'encerrada', 'encerrado',
        'realizada', 'realizado', 'cancelado', 'cancelada', 'fechado', 'closed',
    ];

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $force   = $this->option('force');
        $adminId  = DB::connection('pgsql')->table('users')->orderBy('id')->value('id');
        $totalAll = DB::connection('pgsql')->table('clickup_tasks')->where('list_id', self::LIST_ID)->count();

        $this->info('Buscando chamados no ClickUp...');

        $tickets = DB::connection('pgsql')->select("
            WITH extracted AS (
                SELECT
                    ct.task_id,
                    ct.task_name,
                    ct.description,
                    ct.status,
                    ct.solicitante_nome,
                    ct.solicitante_whatsapp,
                    ct.date_created,
                    ct.date_updated,
                    (
                        SELECT elem->'value'->0->>'id'
                        FROM jsonb_array_elements(ct.custom_fields::jsonb) AS elem
                        WHERE elem->>'name' LIKE '%cliente_relacionado%'
                          AND elem->'value' IS NOT NULL
                          AND elem->>'value' NOT IN ('null', '[]')
                        LIMIT 1
                    ) AS client_task_id,
                    (
                        SELECT
                            CASE
                                WHEN elem->>'value' ~ '^[0-9]+$'
                                THEN TO_TIMESTAMP((elem->>'value')::BIGINT / 1000)::date
                            END
                        FROM jsonb_array_elements(ct.custom_fields::jsonb) AS elem
                        WHERE elem->>'name' = 'Deadline'
                          AND elem->>'value' IS NOT NULL
                        LIMIT 1
                    ) AS due_date,
                    (
                        SELECT elem->'value'->>'name'
                        FROM jsonb_array_elements(ct.custom_fields::jsonb) AS elem
                        WHERE elem->>'name' = 'Local de Aprovação'
                          AND elem->'value' IS NOT NULL
                        LIMIT 1
                    ) AS approval_location
                FROM clickup_tasks ct
                WHERE ct.list_id = :list_id
            )
            SELECT e.*, c.id AS app_client_id, c.company_name
            FROM extracted e
            INNER JOIN clients c ON c.clickup_task_id = e.client_task_id
            WHERE LOWER(e.status) NOT IN (" . $this->closedStatusesSql() . ")
        ", ['list_id' => self::LIST_ID]);

        $total = count($tickets);
        $this->info("Encontrados {$total} chamados com cliente vinculado.");

        if ($dryRun) {
            $this->warn('[DRY RUN] Nenhum dado será inserido.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($tickets as $ticket) {
            $exists = DB::connection('pgsql')
                ->table('tasks')
                ->where('clickup_task_id', $ticket->task_id)
                ->exists();

            if ($exists && !$force) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $status   = self::STATUS_MAP[strtolower($ticket->status)] ?? 'backlog';
            $created  = $ticket->date_created ? Carbon::parse($ticket->date_created) : now();
            $updatedAt = $ticket->date_updated ? Carbon::parse($ticket->date_updated) : now();

            $requesterChannel = null;
            if ($ticket->solicitante_whatsapp) {
                $requesterChannel = 'whatsapp';
            }

            $row = [
                'organization_id'   => self::ORG_ID,
                'client_id'         => $ticket->app_client_id,
                'project_id'        => null,
                'macro_plan_id'     => null,
                'title'             => mb_substr($ticket->task_name, 0, 300),
                'description'       => $ticket->description,
                'task_type'         => 'administrativo',
                'status'            => $status,
                'situation'         => $ticket->status,
                'executor_id'       => null,
                'created_by'        => $adminId,
                'due_date'          => $ticket->due_date,
                'approval_date'     => null,
                'publish_date'      => null,
                'approval_location' => $ticket->approval_location,
                'approval_method'   => null,
                'internal_approval' => false,
                'requester_name'    => $ticket->solicitante_nome,
                'requester_whatsapp'=> $ticket->solicitante_whatsapp,
                'requester_channel' => $requesterChannel,
                'origin'            => 'ticket',
                'is_ticket'         => true,
                'clickup_task_id'   => $ticket->task_id,
                'launched_at'       => $created,
                'custom_fields'     => null,
                'created_at'        => $created,
                'updated_at'        => $updatedAt,
            ];

            if (!$dryRun) {
                if ($exists) {
                    DB::connection('pgsql')
                        ->table('tasks')
                        ->where('clickup_task_id', $ticket->task_id)
                        ->update(array_merge($row, ['updated_at' => now()]));
                    $updated++;
                } else {
                    DB::connection('pgsql')
                        ->table('tasks')
                        ->insert(array_merge(['id' => Str::uuid()->toString()], $row));
                    $imported++;
                }
            } else {
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
                ['Sem cliente ou já fechados (pulados)', $totalAll - $total],
            ]
        );

        return self::SUCCESS;
    }

    private function closedStatusesSql(): string
    {
        $quoted = array_map(fn($s) => "'" . addslashes($s) . "'", self::CLOSED_STATUSES);
        return implode(', ', $quoted);
    }
}
