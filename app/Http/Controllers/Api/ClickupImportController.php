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
    // ClickUp status name → app status key (espelho 1:1 dos 8 status reais da lista de Produção/Sprint)
    private const STATUS_MAP = [
        // Backlog / A Fazer
        'backlog'                 => 'backlog',
        'a fazer'                 => 'backlog',
        'backlog / a fazer'       => 'backlog',
        'backlog/a fazer'         => 'backlog',
        'to do'                   => 'backlog',
        'em planejamento'         => 'backlog',
        'triagem'                 => 'backlog',
        // Em Produção
        'em produção'             => 'em_producao',
        'em producao'             => 'em_producao',
        'in progress'             => 'em_producao',
        'em andamento'            => 'em_producao',
        'em atendimento'          => 'em_producao',
        'em criação'              => 'em_producao',
        'em criacao'              => 'em_producao',
        'em copy'                 => 'em_producao',
        'copy'                    => 'em_producao',
        // Revisão Interna
        'revisão interna'         => 'revisao_interna',
        'revisao interna'         => 'revisao_interna',
        'em revisão'              => 'revisao_interna',
        'em revisao'              => 'revisao_interna',
        'revisão'                 => 'revisao_interna',
        'revisao'                 => 'revisao_interna',
        'review'                  => 'revisao_interna',
        // Ajuste / Alteração
        'ajuste'                  => 'ajuste_alteracao',
        'ajuste / alteração'      => 'ajuste_alteracao',
        'ajuste/alteração'        => 'ajuste_alteracao',
        'alteração'               => 'ajuste_alteracao',
        'alteracao'               => 'ajuste_alteracao',
        'em ajuste'               => 'ajuste_alteracao',
        // Aprovação
        'aprovação'               => 'aprovacao',
        'aprovacao'               => 'aprovacao',
        'aguardando aprovação'    => 'aprovacao',
        'aguardando aprovacao'    => 'aprovacao',
        'aguardando envio'        => 'aprovacao',
        'aguardando resposta'     => 'aprovacao',
        'aguardando cliente'      => 'aprovacao',
        // Despacho / Agendamento
        'despacho'                       => 'despacho_agendamento',
        'agendamento'                    => 'despacho_agendamento',
        'despacho / agendamento'         => 'despacho_agendamento',
        'despacho/agendamento'           => 'despacho_agendamento',
        // Concluído
        'concluído'               => 'concluido',
        'concluido'               => 'concluido',
        'done'                    => 'concluido',
        'complete'                => 'concluido',
        'aprovado'                => 'concluido',
        'approved'                => 'concluido',
        // Cancelado
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
        if ($secret && !hash_equals($secret, (string) $request->header('X-Import-Secret'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tasks  = $request->input('tasks', []);
        $result = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $db = DB::connection('pgsql');

            // Usa DB::table() em todos os lookups para bypassar qualquer Eloquent scope
            $usersByEmail = $db->table('users')->pluck('id', 'email')->all();

            $fallbackUserId = $db->table('users')->whereNull('client_id')->value('id');
            $organizationId = $db->table('organizations')->value('id');

            // client_id é NOT NULL em tasks (regra de negócio: toda tarefa tem cliente).
            // Se a tarefa do ClickUp não tiver 'cliente_relacionado' preenchido, cai aqui.
            $fallbackClientId = $db->table('clients')
                ->where('company_name', 'Nonna Agência Digital')
                ->value('id');

            $projectsByList = $db->table('projects')
                ->whereNotNull('clickup_list_id')
                ->pluck('id', 'clickup_list_id')
                ->all();

            $clientsByClickup = $db->table('clients')
                ->whereNotNull('clickup_task_id')
                ->pluck('id', 'clickup_task_id')
                ->all();

            // Match de Sprint pelo nome da Lista de origem no ClickUp (sincronização em tempo real)
            $sprintsByTitle = $db->table('sprints')
                ->get(['id', 'title'])
                ->mapWithKeys(fn ($s) => [mb_strtolower(trim($s->title)) => $s->id])
                ->all();

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao inicializar lookups: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }

        foreach ($tasks as $index => $row) {
            try {
                $this->importTask($row, $usersByEmail, $projectsByList, $clientsByClickup, $sprintsByTitle, $fallbackUserId, $fallbackClientId, $organizationId, $result);
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
        array $sprintsByTitle,
        ?string $fallbackUserId,
        ?string $fallbackClientId,
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
            $exists = Task::on('pgsql')->withoutGlobalScopes()
                ->where('clickup_task_id', $clickupTaskId)->exists();

            if ($exists) {
                Task::on('pgsql')->withoutGlobalScopes()
                    ->where('clickup_task_id', $clickupTaskId)
                    ->update(['status' => 'cancelado']);
                $result['updated']++;
            } else {
                $result['skipped']++;
            }

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
        // Fallback: client_id é NOT NULL — tarefa sem 'cliente_relacionado' no ClickUp
        // (tipicamente interna/administrativa) vai pro cliente interno da própria agência.
        if (!$clientId) {
            $clientId = $fallbackClientId;
        }

        // Mapear status. Fallback é 'concluido', não 'backlog': um status sem mapeamento
        // normalmente vem de convenção antiga do ClickUp (workspace foi refeito em 2026-03),
        // e assumir "ativo"/backlog jogaria tarefa legada pra dentro da Fila corrente.
        $rawStatus = mb_strtolower(trim($row['clickup_status'] ?? 'concluido'));
        $status    = self::STATUS_MAP[$rawStatus] ?? 'concluido';

        // Mapear priority
        $rawPriority = mb_strtolower(trim($row['clickup_priority'] ?? 'normal'));
        $priority    = self::PRIORITY_MAP[$rawPriority] ?? 'normal';

        // Mapear task_type
        $taskType = $row['task_type'] ?? 'criacao';
        if (!array_key_exists($taskType, Task::$types)) {
            $taskType = 'criacao';
        }

        // Resolver created_by: criador do ClickUp → executor → fallback sistema
        $createdBy = $usersByEmail[$row['creator_email'] ?? '']
                  ?? $usersByEmail[$row['executor_email'] ?? '']
                  ?? $fallbackUserId;

        // is_ticket reflete a Lista ATUAL no ClickUp, não a origem histórica (campo 'origin',
        // que não muda quando o card se move de lista) — uma tarefa que nasceu como Ticket mas
        // já foi movida pra "Filas (Backlog)" (ou pra uma Sprint) não deve mais aparecer em
        // /atendimento, só em /filas. Sem list_name (workflow de migração manual antigo, que não
        // manda esse campo), mantém o comportamento anterior baseado em origin.
        $isTicket = !empty($row['list_name'])
            ? mb_strtolower(trim($row['list_name'])) === 'chamados (tickets)'
            : (bool) ($row['is_ticket'] ?? false);

        // withoutGlobalScopes: evita que OrganizationScope filtre tasks com organization_id=null
        $exists = Task::on('pgsql')->withoutGlobalScopes()
            ->where('clickup_task_id', $clickupTaskId)->exists();

        $task = Task::on('pgsql')->withoutGlobalScopes()->updateOrCreate(
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
                'publish_date'    => !empty($row['publish_date']) ? $row['publish_date'] : null,
                'approval_method' => array_key_exists($row['approval_method'] ?? '', Task::$approvalMethods) ? $row['approval_method'] : null,
                'internal_approval' => (bool) ($row['internal_approval'] ?? false),
                'is_ticket'       => $isTicket,
                'requester_name'  => $row['requester_name'] ?? null,
                'requester_whatsapp' => $row['requester_whatsapp'] ?? null,
                'requester_channel'  => $row['requester_channel'] ?? null,
                'created_by'           => $createdBy,
                'organization_id'      => $organizationId,
                'clickup_attachments'  => !empty($row['attachments']) ? $row['attachments'] : null,
                'launched_at'          => now(),
            ]
        );

        // Sincronizar executor e responsável
        $this->syncPersonnel($task, $row, $usersByEmail);

        // Vincula à Sprint pelo nome da Lista de origem no ClickUp — só se a
        // tarefa ainda não tiver sprint_id, pra nunca sobrescrever organização
        // manual já feita no App (mesma cautela aplicada a project_id).
        if (!$task->sprint_id && !empty($row['list_name'])) {
            $sprintId = $sprintsByTitle[mb_strtolower(trim($row['list_name']))] ?? null;
            if ($sprintId) {
                $task->update(['sprint_id' => $sprintId]);
            }
        }

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

    // Resync "leve": diferente de import(), que reescreve a tarefa inteira (título,
    // descrição, cliente, projeto, tipo, origem/destino, datas...), este endpoint só toca em
    // status/situação/responsável/executor/data de aprovação de tarefas QUE JÁ EXISTEM no App
    // (casadas por clickup_task_id). Nunca cria tarefa nova (não tem os campos obrigatórios pra
    // isso, ex: title/client_id) e nunca mexe em project_id/client_id/sprint_id — pensado
    // exatamente pra rodar contra o workspace inteiro do ClickUp sem o risco de vínculo errado
    // que já causou incidente com o endpoint de import completo (ver clickup-import-api.md).
    public function syncFields(Request $request): JsonResponse
    {
        $secret = config('app.import_secret');
        if ($secret && !hash_equals($secret, (string) $request->header('X-Import-Secret'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rows   = $request->input('tasks', []);
        $result = ['updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $usersByEmail = DB::connection('pgsql')->table('users')->pluck('id', 'email')->all();
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao inicializar lookups: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }

        foreach ($rows as $index => $row) {
            try {
                $this->syncTaskFields($row, $usersByEmail, $result);
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

    private function syncTaskFields(array $row, array $usersByEmail, array &$result): void
    {
        $clickupTaskId = $row['clickup_task_id'] ?? null;
        if (!$clickupTaskId) {
            $result['skipped']++;
            return;
        }

        // withoutGlobalScopes: mesma razão do import() — evita OrganizationScope filtrando
        // tasks com organization_id null.
        $task = Task::on('pgsql')->withoutGlobalScopes()
            ->where('clickup_task_id', $clickupTaskId)->first();

        if (!$task) {
            $result['skipped']++;
            return;
        }

        // Status sem mapeamento conhecido mantém o valor atual — diferente do fallback
        // 'concluido' do import() (pensado pra migração de tarefa nova/legada), aqui a tarefa
        // já existe e está classificada; um status vazio/desconhecido não deveria fechá-la.
        $rawStatus = mb_strtolower(trim($row['clickup_status'] ?? ''));
        $status    = self::STATUS_MAP[$rawStatus] ?? $task->status;

        $task->update([
            'status'        => $status,
            'situation'     => $row['situation'] ?? null,
            'approval_date' => !empty($row['approval_date']) ? $row['approval_date'] : null,
        ]);

        $this->syncPersonnel($task, $row, $usersByEmail);

        $result['updated']++;
    }
}
