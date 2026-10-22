<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Services\AutomationEngine;
use App\Services\MeetingAtaService;
use App\Services\Projects\ProjectDiscussionMeetingService;

class MeetingObserver
{
    public function created(Meeting $meeting): void
    {
        AutomationEngine::evaluate('created', $meeting);
    }

    public function updated(Meeting $meeting): void
    {
        // ATA mudou (botão da IA ou à mão) → resolve "Registrar a ATA" do organizador e avisa
        // os participantes ("✓ Li a ATA" — ver MeetingAtaService::notifyParticipants).
        if ($meeting->wasChanged('ata') && filled($meeting->ata)) {
            $actorId = MeetingAtaService::$actingUserId ?? auth()->id();
            MeetingAtaService::resolveAtaPendencies($meeting, $actorId);
            app(MeetingAtaService::class)->notifyParticipants($meeting, blank($meeting->getOriginal('ata')));
        }

        if ($meeting->wasChanged('status') && $meeting->status === 'realizada') {
            ProjectDiscussionMeetingService::startDiscussedProjects($meeting);
        }

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
