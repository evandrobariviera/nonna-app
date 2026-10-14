<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskExecutor;
use App\Models\User;
use App\Services\AutomationEngine;

/**
 * Troca de Executor / Responsável de uma tarefa — a mesma regra pra edição manual na
 * página da tarefa (TaskController) e pro Assistente de Lançamento aplicando alterações
 * (ProjectTaskAssistantController): trava de carga em Produção, automação "Responsável/
 * Executor adicionado" só pra pessoa nova, histórico de quem mudou.
 */
class TaskPeople
{
    /** @return string|null motivo do bloqueio (trava de 2 em Produção) ou null se aplicou */
    public static function setExecutor(Task $task, ?int $userId): ?string
    {
        $oldUser = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->first()?->user;
        $wasAlready = $userId && $oldUser?->id === $userId;

        if ($task->status === 'em_producao' && $userId && ! $wasAlready) {
            if ($reason = Task::wipBlockReason($userId, $task->id)) {
                return $reason;
            }
        }

        TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->delete();

        if ($userId) {
            // Mesma pessoa pode já ser responsável — não mexe na linha dela, só na de executor.
            $task->executors()->attach($userId, ['role' => 'executor']);
            $task->updateQuietly(['executor_id' => $userId]);

            if (! $wasAlready) {
                AutomationEngine::evaluate('executor_added', $task, ['role' => 'executor', 'user_id' => $userId]);
            }
        } else {
            $task->updateQuietly(['executor_id' => null]);
        }

        if (($oldUser?->id) !== $userId) {
            TaskActivity::log($task, 'executor_changed', $oldUser?->name, $userId ? User::find($userId)?->name : null);
        }

        return null;
    }

    public static function setResponsavel(Task $task, ?int $userId): void
    {
        // Guarda quem já era responsável antes de mexer, pra só disparar a automação de
        // "Responsável/Executor adicionado" quando for alguém novo — re-salvar o mesmo
        // responsável não é adição. O mesmo registro serve pro rótulo "de" do histórico
        // (pivot task_executors não passa pelo TaskObserver).
        $oldUser = TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->first()?->user;
        $wasAlready = $userId && $oldUser?->id === $userId;

        TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->delete();

        if ($userId) {
            // A mesma pessoa pode ser executor E responsável (1 linha por task/user/papel).
            $task->executors()->attach($userId, ['role' => 'responsavel']);

            if (! $wasAlready) {
                AutomationEngine::evaluate('executor_added', $task, ['role' => 'responsavel', 'user_id' => $userId]);
            }
        }

        if (($oldUser?->id) !== $userId) {
            TaskActivity::log($task, 'responsavel_changed', $oldUser?->name, $userId ? User::find($userId)?->name : null);
        }
    }
}
