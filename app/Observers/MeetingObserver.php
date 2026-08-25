<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Services\AutomationEngine;

class MeetingObserver
{
    public function updated(Meeting $meeting): void
    {
        if ($meeting->wasChanged('status')) {
            AutomationEngine::evaluate('status_changed', $meeting, [
                'field' => 'status',
                'from'  => $meeting->getOriginal('status'),
                'to'    => $meeting->status,
            ]);
        }

        // Tarefa nascida da Reunião (Task::meeting()) não tem Planejamento no início —
        // quando a Reunião entra num Macro depois, as tarefas acompanham automaticamente
        // (é assim que elas aparecem em "Tarefas Soltas do Planejamento").
        if ($meeting->wasChanged('macro_plan_id')) {
            $meeting->tasks()->update(['macro_plan_id' => $meeting->macro_plan_id]);
        }
    }
}
