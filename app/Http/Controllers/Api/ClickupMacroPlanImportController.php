<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MacroPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClickupMacroPlanImportController extends Controller
{
    // Nomes reais dos status personalizados da Lista "Planejamentos (Roadmaps)" no ClickUp
    private const STATUS_MAP = [
        'em planejamento' => 'em_planejamento',
        'revisão interna' => 'revisao_interna',
        'revisao interna' => 'revisao_interna',
        'aprovação'       => 'aprovacao',
        'aprovacao'       => 'aprovacao',
        'em execução'     => 'em_execucao',
        'em execucao'     => 'em_execucao',
        'concluído'       => 'concluido',
        'concluido'       => 'concluido',
    ];

    public function import(Request $request): JsonResponse
    {
        $secret = config('app.import_secret');
        if ($secret && !hash_equals($secret, (string) $request->header('X-Import-Secret'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $plans  = $request->input('macroplans', []);
        $result = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $db = DB::connection('pgsql');

            $usersByEmail     = $db->table('users')->pluck('id', 'email')->all();
            $fallbackUserId   = $db->table('users')->whereNull('client_id')->value('id');
            $organizationId   = $db->table('organizations')->value('id');
            $clientsByClickup = $db->table('clients')
                ->whereNotNull('clickup_task_id')
                ->pluck('id', 'clickup_task_id')
                ->all();

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao inicializar lookups: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }

        foreach ($plans as $index => $row) {
            try {
                $this->importPlan($row, $usersByEmail, $clientsByClickup, $fallbackUserId, $organizationId, $result);
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'index'           => $index,
                    'clickup_task_id' => $row['clickup_task_id'] ?? null,
                    'error'           => $e->getMessage(),
                ];
            }
        }

        return response()->json($result);
    }

    private function importPlan(
        array $row,
        array $usersByEmail,
        array $clientsByClickup,
        ?string $fallbackUserId,
        ?string $organizationId,
        array &$result
    ): void {
        $clickupTaskId = $row['clickup_task_id'] ?? null;

        if (!$clickupTaskId) {
            $result['skipped']++;
            return;
        }

        // Mirror de exclusão/arquivamento no ClickUp: só cancela, não deleta a linha
        if (!empty($row['deleted'])) {
            $exists = MacroPlan::on('pgsql')->withoutGlobalScopes()
                ->where('clickup_task_id', $clickupTaskId)->exists();

            if ($exists) {
                MacroPlan::on('pgsql')->withoutGlobalScopes()
                    ->where('clickup_task_id', $clickupTaskId)
                    ->update(['status' => 'concluido']);
                $result['updated']++;
            } else {
                $result['skipped']++;
            }

            return;
        }

        $clientId = !empty($row['client_clickup_id'])
            ? ($clientsByClickup[$row['client_clickup_id']] ?? null)
            : null;

        if (!$clientId) {
            $result['skipped']++;
            $result['errors'][] = [
                'clickup_task_id' => $clickupTaskId,
                'error'           => 'client_id não resolvido (client_clickup_id: ' . ($row['client_clickup_id'] ?? 'vazio') . ')',
            ];
            return;
        }

        $rawStatus = mb_strtolower(trim($row['clickup_status'] ?? 'em planejamento'));
        $status    = self::STATUS_MAP[$rawStatus] ?? 'em_planejamento';

        $createdBy = $usersByEmail[$row['creator_email'] ?? '']
                  ?? $usersByEmail[$row['responsible_email'] ?? '']
                  ?? $fallbackUserId;

        $responsibleId = isset($row['responsible_email'])
            ? ($usersByEmail[$row['responsible_email']] ?? null)
            : null;

        // Roadmaps do ClickUp geralmente não têm datas — usar fallback ajustável depois
        $periodStart = !empty($row['period_start']) ? $row['period_start'] : now()->toDateString();
        $periodEnd   = !empty($row['period_end'])   ? $row['period_end']   : now()->addDays(90)->toDateString();

        $exists = MacroPlan::on('pgsql')->withoutGlobalScopes()
            ->where('clickup_task_id', $clickupTaskId)->exists();

        // Descrição do ClickUp → bloco2 "Contexto e Estratégia"
        $bloco2 = null;
        if (!empty($row['description'])) {
            $bloco2 = ['conteudo' => $row['description']];
        }

        MacroPlan::on('pgsql')->withoutGlobalScopes()->updateOrCreate(
            ['clickup_task_id' => $clickupTaskId],
            [
                'organization_id'     => $organizationId,
                'client_id'           => $clientId,
                'responsible_id'      => $responsibleId,
                'created_by'          => $createdBy,
                'title'               => $row['title'] ?? 'Sem título',
                'period_start'        => $periodStart,
                'period_end'          => $periodEnd,
                'status'              => $status,
                'bloco2'              => $bloco2,
                'clickup_attachments' => !empty($row['attachments']) ? $row['attachments'] : null,
                'launched_at'         => now(),
            ]
        );

        $exists ? $result['updated']++ : $result['imported']++;
    }
}
