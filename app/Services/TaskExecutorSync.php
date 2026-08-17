<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskExecutor;

class TaskExecutorSync
{
    // Sincroniza Executores/Responsável a partir de executor_ids[]/executor_roles[]/
    // responsavel_id — formato usado pelos formulários de criação/edição de Tarefa e
    // Ticket. Ponto único de propósito: sem isso, cada controller reimplementava essa
    // lógica e um deles (TicketController) nunca gravava tasks.executor_id (coluna
    // legada ainda lida em vários lugares — cabeçalho da tarefa, form de edição,
    // filtro de executor da Fila/Tickets) — o pivot salvava certo, mas esses pontos
    // continuavam mostrando "sem executor".
    public static function sync(Task $task, array $data): void
    {
        $ids           = $data['executor_ids'] ?? [];
        $roles         = $data['executor_roles'] ?? [];
        $responsavelId = $data['responsavel_id'] ?? null;

        // Rede de segurança — a validação (Task::executorRoleCapRule()) já devia ter
        // barrado 2+ pessoas no mesmo papel capado antes de chegar aqui. Mantém só a
        // primeira de cada papel (executor/responsavel) na ordem de submissão.
        $seenCappedRole = [];
        foreach ($ids as $key => $userId) {
            $role = $roles[$userId] ?? 'executor';
            if (in_array($role, ['executor', 'responsavel'], true)) {
                if (isset($seenCappedRole[$role])) {
                    unset($ids[$key]);
                    continue;
                }
                $seenCappedRole[$role] = true;
            }
        }
        $ids = array_values($ids);

        if (empty($ids) && !$responsavelId) {
            return;
        }

        // Papel de responsável é independente dos demais (executor/aprovador) — a mesma
        // pessoa pode acumular os dois na mesma tarefa (task_executors permite 1 linha por
        // (task_id, user_id, role)). sync() não dava conta disso (só 1 linha por user_id no
        // array), então troca-se pra 2 blocos delete+attach separados por papel.
        $before = TaskExecutor::where('task_id', $task->id)->get(['user_id', 'role'])
            ->map(fn ($e) => "{$e->role}:{$e->user_id}");

        $newlyAdded = [];

        TaskExecutor::where('task_id', $task->id)->where('role', '!=', 'responsavel')->delete();
        foreach ($ids as $userId) {
            $role = $roles[$userId] ?? 'executor';
            $task->executors()->attach($userId, ['role' => $role]);
            if (!$before->contains("{$role}:{$userId}")) {
                $newlyAdded[] = ['role' => $role, 'user_id' => $userId];
            }
        }

        TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->delete();
        if ($responsavelId) {
            $task->executors()->attach($responsavelId, ['role' => 'responsavel']);
            if (!$before->contains("responsavel:{$responsavelId}")) {
                $newlyAdded[] = ['role' => 'responsavel', 'user_id' => $responsavelId];
            }
        }

        if (!$task->executor_id && !empty($ids)) {
            $task->updateQuietly(['executor_id' => $ids[0]]);
        }

        foreach ($newlyAdded as $added) {
            AutomationEngine::evaluate('executor_added', $task, $added);
        }
    }
}
