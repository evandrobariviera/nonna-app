<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClickupProjectImportController extends Controller
{
    // Nomes reais dos status personalizados da Lista "Projetos (Projects)" no ClickUp
    private const STATUS_MAP = [
        'em planejamento' => 'em_planejamento',
        'aprovação'       => 'aprovacao',
        'aprovacao'       => 'aprovacao',
        'em execução'     => 'em_execucao',
        'em execucao'     => 'em_execucao',
        'stand by'        => 'stand_by',
        'concluído'       => 'concluido',
        'concluido'       => 'concluido',
    ];

    public function import(Request $request): JsonResponse
    {
        $secret = config('app.import_secret');
        if ($secret && !hash_equals($secret, (string) $request->header('X-Import-Secret'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $projects = $request->input('projects', []);
        $result   = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $db = DB::connection('pgsql');

            $organizationId   = $db->table('organizations')->value('id');
            $clientsByClickup = $db->table('clients')
                ->whereNotNull('clickup_task_id')
                ->pluck('id', 'clickup_task_id')
                ->all();

            $macrosByClickup = $db->table('macro_plans')
                ->whereNotNull('clickup_task_id')
                ->pluck('id', 'clickup_task_id')
                ->all();

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao inicializar lookups: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }

        foreach ($projects as $index => $row) {
            try {
                $this->importProject($row, $clientsByClickup, $macrosByClickup, $organizationId, $result);
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

    private function importProject(
        array $row,
        array $clientsByClickup,
        array $macrosByClickup,
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
            $exists = Project::on('pgsql')->withoutGlobalScopes()
                ->where('clickup_task_id', $clickupTaskId)->exists();

            if ($exists) {
                Project::on('pgsql')->withoutGlobalScopes()
                    ->where('clickup_task_id', $clickupTaskId)
                    ->update(['status' => 'cancelado']);
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

        // macro_plan_id é nullable — pode ser linkado manualmente depois
        $macroPlanId = !empty($row['macro_plan_clickup_id'])
            ? ($macrosByClickup[$row['macro_plan_clickup_id']] ?? null)
            : null;

        $rawStatus = mb_strtolower(trim($row['clickup_status'] ?? 'em planejamento'));
        $status    = self::STATUS_MAP[$rawStatus] ?? 'em_planejamento';

        $exists = Project::on('pgsql')->withoutGlobalScopes()
            ->where('clickup_task_id', $clickupTaskId)->exists();

        Project::on('pgsql')->withoutGlobalScopes()->updateOrCreate(
            ['clickup_task_id' => $clickupTaskId],
            [
                'organization_id'     => $organizationId,
                'client_id'           => $clientId,
                'macro_plan_id'       => $macroPlanId,
                'clickup_list_id'     => $row['clickup_list_id'] ?? null,
                'title'               => $row['title'] ?? 'Sem título',
                'objective'           => $row['objective'] ?? null,
                'clickup_attachments' => !empty($row['attachments']) ? $row['attachments'] : null,
                'status'              => $status,
                'launched_at'         => now(),
            ]
        );

        $exists ? $result['updated']++ : $result['imported']++;
    }
}
