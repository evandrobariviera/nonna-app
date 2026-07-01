<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskExecutor;
use App\Models\User;
use App\Models\Project;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClickupImportController extends Controller
{
    // ClickUp status name → app status key
    private const STATUS_MAP = [
        'backlog'                 => 'backlog',
        'a fazer'                 => 'backlog',
        'to do'                   => 'backlog',
        'em copy'                 => 'em_copy',
        'copy'                    => 'em_copy',
        'pronto p/ produção'      => 'pronto_producao',
        'pronto producao'         => 'pronto_producao',
        'pronto p/produção'       => 'pronto_producao',
        'em produção'             => 'em_producao',
        'em producao'             => 'em_producao',
        'in progress'             => 'em_producao',
        'em andamento'            => 'em_producao',
        'em revisão'              => 'revisao',
        'em revisao'              => 'revisao',
        'revisão'                 => 'revisao',
        'revisao'                 => 'revisao',
        'review'                  => 'revisao',
        'aguardando envio'        => 'aguardando_envio',
        'aguardando resposta'     => 'aguardando_resposta',
        'aguardando cliente'      => 'aguardando_resposta',
        'em ajuste'               => 'ajuste',
        'ajuste'                  => 'ajuste',
        'aguardando aprovação'    => 'aguardando_aprovacao',
        'aguardando aprovacao'    => 'aguardando_aprovacao',
        'concluído'               => 'concluido',
        'concluido'               => 'concluido',
        'done'                    => 'concluido',
        'complete'                => 'concluido',
        'aprovado'                => 'aprovado',
        'approved'                => 'aprovado',
        'cancelado'               => 'cancelado',
        'cancelled'               => 'cancelado',
    ];

    // ClickUp priority → app priority
    private const PRIORITY_MAP = [
        'urgent' => 'urgente',
        '1'      => 'urgente',
        'high'   => 'medio',
        '2'      => 'medio',
        'normal' => 'normal',
        '3'      => 'normal',
        'low'    => 'normal',
        '4'      => 'normal',
    ];

    public function import(Request $request): JsonResponse
    {
        // Autenticação por token simples
        $secret = config('app.import_secret');
        if ($secret && $request->header('X-Import-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tasks  = $request->input('tasks', []);
        $result = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        // Cache de usuários por email para evitar N+1
        $usersByEmail = User::on('pgsql')->pluck('id', 'email')->all();

        // Cache de projetos por clickup_list_id
        $projectsByList = Project::on('pgsql')
            ->whereNotNull('clickup_list_id')
            ->pluck('id', 'clickup_list_id')
            ->all();

        // Cache de clientes por clickup_task_id
        $clientsByClickup = Client::on('pgsql')
            ->whereNotNull('clickup_task_id')
            ->pluck('id', 'clickup_task_id')
            ->all();

        foreach ($tasks as $index => $row) {
            try {
                $this->importTask($row, $usersByEmail, $projectsByList, $clientsByClickup, $result);
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'index'          => $index,
                    'clickup_task_id'=> $row['clickup_task_id'] ?? null,
                    'error'          => $e->getMessage(),
                ];
            }
        }

        return response()->json($result);
    }

    private function importTask(
        array $row,
        array $usersByEmail,
        array $projectsByList,
        array $clientsByClickup,
        array &$result
    ): void {
        $clickupTaskId = $row['clickup_task_id'] ?? null;

        if (!$clickupTaskId) {
            $result['skipped']++;
            return;
        }

        // Resolver project_id via list_id (ou direto se vier no payload)
        $projectId = $row['project_id']
            ?? $projectsByList[$row['list_id'] ?? '']
            ?? null;

        // Resolver client_id via clickup client task id
        $clientId = null;
        if (!empty($row['client_clickup_id'])) {
            $clientId = $clientsByClickup[$row['client_clickup_id']] ?? null;
        }
        if (!$clientId && $projectId) {
            $clientId = Project::on('pgsql')->find($projectId)?->client_id;
        }

        // Mapear status
        $rawStatus = mb_strtolower(trim($row['clickup_status'] ?? 'backlog'));
        $status    = self::STATUS_MAP[$rawStatus] ?? 'backlog';

        // Mapear priority
        $rawPriority = mb_strtolower(trim($row['clickup_priority'] ?? 'normal'));
        $priority    = self::PRIORITY_MAP[$rawPriority] ?? 'normal';

        // Mapear task_type
        $taskType = $row['task_type'] ?? 'criacao';
        if (!array_key_exists($taskType, Task::$types)) {
            $taskType = 'criacao';
        }

        $exists = Task::on('pgsql')->where('clickup_task_id', $clickupTaskId)->exists();

        $task = Task::on('pgsql')->updateOrCreate(
            ['clickup_task_id' => $clickupTaskId],
            [
                'project_id'      => $projectId,
                'client_id'       => $clientId,
                'title'           => $row['title'] ?? 'Sem título',
                'description'     => $row['description'] ?? null,
                'status'          => $status,
                'priority'        => $priority,
                'task_type'       => $taskType,
                'origin'          => $row['origin'] ?? 'projeto',
                'destination'     => $row['destination'] ?? null,
                'situation'       => $row['situation'] ?? null,
                'due_date'        => !empty($row['due_date']) ? $row['due_date'] : null,
                'approval_date'   => !empty($row['approval_date']) ? $row['approval_date'] : null,
                'is_ticket'       => (bool) ($row['is_ticket'] ?? false),
                'requester_name'  => $row['requester_name'] ?? null,
                'requester_whatsapp' => $row['requester_whatsapp'] ?? null,
                'requester_channel'  => $row['requester_channel'] ?? null,
                'launched_at'     => now(),
            ]
        );

        // Sincronizar executor e responsável
        $this->syncPersonnel($task, $row, $usersByEmail);

        $exists ? $result['updated']++ : $result['imported']++;
    }

    private function syncPersonnel(Task $task, array $row, array $usersByEmail): void
    {
        $executorId    = isset($row['executor_email'])    ? ($usersByEmail[$row['executor_email']] ?? null)    : null;
        $responsavelId = isset($row['responsavel_email']) ? ($usersByEmail[$row['responsavel_email']] ?? null) : null;

        if (!$executorId && !$responsavelId) {
            return;
        }

        TaskExecutor::where('task_id', $task->id)
            ->whereIn('role', ['executor', 'responsavel'])
            ->delete();

        $synced = [];

        if ($executorId) {
            $task->executors()->attach($executorId, ['role' => 'executor']);
            $task->updateQuietly(['executor_id' => $executorId]);
            $synced[] = $executorId;
        }

        if ($responsavelId && !in_array($responsavelId, $synced)) {
            $task->executors()->attach($responsavelId, ['role' => 'responsavel']);
        }
    }
}
