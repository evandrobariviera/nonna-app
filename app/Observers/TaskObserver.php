<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskStatusTransition;
use App\Services\AutomationEngine;
use App\Services\TaskApprovalService;
use Illuminate\Support\Facades\Auth;

class TaskObserver
{
    // Campos "de enum" — vira TaskActivity com rótulo resolvido via
    // Task::labelForField(). Client/Projeto/Sprint são relacionamentos (UUID
    // pra nome), tratados à parte logo abaixo. Título/Descrição/Legenda ficam
    // de fora de propósito — histórico é de AÇÃO, não de conteúdo de texto.
    private const TRACKED_ENUM_FIELDS = [
        'status'      => 'status_changed',
        'situation'   => 'situation_changed',
        'priority'    => 'priority_changed',
        'destination' => 'destination_changed',
        'task_type'   => 'task_type_changed',
    ];

    public function updated(Task $task): void
    {
        foreach (self::TRACKED_ENUM_FIELDS as $field => $action) {
            if ($task->wasChanged($field)) {
                TaskActivity::log(
                    $task,
                    $action,
                    Task::labelForField($field, $task->getOriginal($field)),
                    Task::labelForField($field, $task->$field)
                );
            }
        }

        if ($task->wasChanged('client_id')) {
            TaskActivity::log(
                $task,
                'client_changed',
                Client::find($task->getOriginal('client_id'))?->displayName(),
                $task->client?->displayName()
            );
        }
        if ($task->wasChanged('project_id')) {
            TaskActivity::log(
                $task,
                'project_changed',
                Project::find($task->getOriginal('project_id'))?->title,
                $task->project?->title
            );
        }
        if ($task->wasChanged('sprint_id')) {
            TaskActivity::log(
                $task,
                'sprint_changed',
                Sprint::find($task->getOriginal('sprint_id'))?->title,
                $task->sprint?->title
            );
        }

        if ($task->wasChanged('status')) {
            TaskStatusTransition::create([
                'task_id'     => $task->id,
                'from_status' => $task->getOriginal('status'),
                'to_status'   => $task->status,
                'changed_by'  => Auth::id(),
                'changed_at'  => now(),
            ]);

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
        TaskStatusTransition::create([
            'task_id'     => $task->id,
            'from_status' => null,
            'to_status'   => $task->status,
            'changed_by'  => Auth::id() ?? $task->created_by,
            'changed_at'  => now(),
        ]);

        TaskActivity::log($task, 'created', null, null, Auth::id() ?? $task->created_by);

        AutomationEngine::evaluate('created', $task);
    }
}
