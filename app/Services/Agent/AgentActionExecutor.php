<?php

namespace App\Services\Agent;

use App\Models\AgentMessage;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\MeetingText;
use App\Services\TaskDraftService;
use App\Services\TaskExecutorSync;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Executa a proposta (tarefa/reunião) quando a pessoa clica "Confirmar" no cartão.
 * Revalida tudo de novo pelo AgentToolbox (o cliente pode ter mudado entre a
 * proposta e o clique) e cria pelos mesmos caminhos das telas normais.
 */
class AgentActionExecutor
{
    public function __construct(private User $user) {}

    /** @return array{label: string, url: ?string} */
    public function confirm(AgentMessage $message): array
    {
        $proposal = $message->proposal ?? [];
        $tool = $proposal['type'] === 'meeting' ? 'propor_reuniao' : 'propor_tarefa';

        // Revalidação: mesmos critérios de quando a IA propôs.
        $check = (new AgentToolbox($this->user))->call($tool, $proposal['data'] ?? []);
        if (!empty($check['result']['erro'])) {
            throw new \RuntimeException($check['result']['erro']);
        }
        $data = $check['proposal']['data'];

        return DB::connection('pgsql')->transaction(fn () => $proposal['type'] === 'meeting'
            ? $this->createMeeting($data)
            : $this->createTask($data));
    }

    private function createTask(array $data): array
    {
        if (!empty($data['project_id'])) {
            // Dentro de projeto/campanha: mesmo caminho do Assistente de Lançamento
            // (herda planejamento, sincroniza executor/responsável).
            $project = Project::findOrFail($data['project_id']);
            $task = app(TaskDraftService::class)
                ->createFromDrafts($project, [$data], $this->user->id, 'agente')['tasks']->first();
        } else {
            // Sem projeto = chamado avulso, igual TicketController::store() — fica em
            // Tickets pra triagem (queued_at null).
            $task = Task::create([
                'client_id'   => $data['client_id'],
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'task_type'   => $data['task_type'],
                'priority'    => $data['priority'] ?? 'normal',
                'due_date'    => $data['due_date'] ?? null,
                'is_ticket'   => true,
                'origin'      => 'agente',
                'status'      => 'backlog',
                'created_by'  => $this->user->id,
            ]);

            $executorId = $data['executor_user_id'] ?? null;
            TaskExecutorSync::sync($task, [
                'executor_ids'   => $executorId ? [$executorId] : [],
                'executor_roles' => $executorId ? [$executorId => 'executor'] : [],
                'responsavel_id' => $data['responsavel_user_id'] ?? null,
            ]);
        }

        return ['label' => "Tarefa criada: {$task->title}", 'url' => route('tasks.show', $task), 'id' => $task->id];
    }

    private function createMeeting(array $data): array
    {
        // Mesmos campos de MeetingController::store().
        $meeting = Meeting::create([
            'title'            => $data['title'],
            'type'             => $data['type'],
            'modality'         => $data['modality'],
            'status'           => 'agendada',
            'scheduled_at'     => Carbon::createFromFormat('Y-m-d H:i', $data['scheduled_at']),
            'duration_minutes' => $data['duration_minutes'],
            'client_id'        => $data['client_id'] ?? null,
            'organized_by'     => $data['organized_by_user_id'],
            'location'         => $data['location'] ?? null,
            'online_link'      => $data['online_link'] ?? null,
            'agenda'           => MeetingText::fromAi($data['agenda'] ?? null),
            'created_by'       => $this->user->id,
        ]);

        if (!empty($data['participant_user_ids'])) {
            $meeting->participants()->sync($data['participant_user_ids']);
        }

        return ['label' => "Reunião agendada: {$meeting->title}", 'url' => route('meetings.show', $meeting), 'id' => $meeting->id];
    }
}
