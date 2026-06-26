<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\AutomationEngine;

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
    }

    public function created(Task $task): void
    {
        AutomationEngine::evaluate('created', $task);
    }
}
