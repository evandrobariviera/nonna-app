<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\AutomationEngine;
use App\Services\TaskApprovalService;

class TaskObserver
{
    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            AutomationEngine::evaluate('status_changed', $task, [
                'field' => 'status',
                'from'  => $task->getOriginal('status'),
                'to'    => $task->status,
            ]);
        }

        if ($task->wasChanged('situation')) {
            AutomationEngine::evaluate('field_updated', $task, [
                'field' => 'situation',
                'from'  => $task->getOriginal('situation'),
                'to'    => $task->situation,
            ]);
        }

        // Aprovação automática: status "Aprovação" + situação "Enviar para o
        // cliente" ao mesmo tempo, vindo de qualquer lugar que edite a tarefa.
        if ($task->wasChanged('status') || $task->wasChanged('situation')) {
            app(TaskApprovalService::class)->maybeAutoSubmitOnApprovalTransition($task);
        }
    }

    public function created(Task $task): void
    {
        AutomationEngine::evaluate('created', $task);
    }
}
