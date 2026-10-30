<?php

namespace App\Services;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\MacroPlanComment;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\Task;
use App\Models\TaskComment;
use App\Support\RichTextSanitizer;
use Illuminate\Support\Collection;

/**
 * Coluna "Discussões" da entrada do Portal: junta os comentários de tarefas, chamados,
 * campanhas, projetos e planejamentos do cliente. Só entra o que a equipe marcou
 * "Visível pro cliente" e o que o próprio cliente escreveu.
 */
class PortalDiscussionFeed
{
    // Comentário pode carregar imagem em base64: a coluna só precisa do começo do texto.
    private const BODY_CHARS = 6000;

    /** @return Collection<int, array{author: string, at: \Carbon\Carbon, text: string, place: string, url: ?string}> */
    public function for(Client $client, int $limit = 12): Collection
    {
        $body = 'left(body, ' . self::BODY_CHARS . ') as body';

        $plans    = MacroPlan::where('client_id', $client->id)->get(['id', 'client_id', 'period_start', 'period_end', 'title']);
        $projects = Project::where('client_id', $client->id)->get(['id', 'type', 'title', 'macro_plan_id']);

        $tasks = TaskComment::where('visible_to_client', true)
            ->whereIn('task_id', Task::where('client_id', $client->id)->select('id'))
            ->with(['task:id,title,is_ticket', 'user:id,name', 'contact:id,name'])
            ->select('id', 'task_id', 'user_id', 'contact_id', 'created_at')->selectRaw($body)
            ->latest()->limit($limit)->get()
            ->map(fn (TaskComment $c) => $this->row(
                $c,
                $c->task?->title ?? 'Tarefa',
                $c->task ? route($c->task->is_ticket ? 'portal.tickets.show' : 'portal.tasks.show', $c->task_id) : null,
            ));

        $onProjects = ProjectComment::where('visible_to_client', true)
            ->whereIn('project_id', $projects->pluck('id'))
            ->with('user:id,name')
            ->select('id', 'project_id', 'user_id', 'created_at')->selectRaw($body)
            ->latest()->limit($limit)->get()
            ->map(function (ProjectComment $c) use ($projects) {
                $project = $projects->firstWhere('id', $c->project_id);

                return $this->row(
                    $c,
                    $project->typeLabel() . ': ' . $project->title,
                    $project->macro_plan_id ? route('portal.projects.show', $project->macro_plan_id) : null,
                );
            });

        $onPlans = MacroPlanComment::where('visible_to_client', true)
            ->whereIn('macro_plan_id', $plans->pluck('id'))
            ->with('user:id,name')
            ->select('id', 'macro_plan_id', 'user_id', 'created_at')->selectRaw($body)
            ->latest()->limit($limit)->get()
            ->map(function (MacroPlanComment $c) use ($plans, $client) {
                $plan = $plans->firstWhere('id', $c->macro_plan_id)->setRelation('client', $client);

                return $this->row($c, $plan->clientTitle(), route('portal.projects.show', $plan->id));
            });

        return $tasks->concat($onProjects)->concat($onPlans)->sortByDesc('at')->take($limit)->values();
    }

    private function row(TaskComment|ProjectComment|MacroPlanComment $comment, string $place, ?string $url): array
    {
        $contact = $comment instanceof TaskComment ? $comment->contact : null;

        return [
            'author' => $comment->user?->name ?? $contact?->name ?? 'Nonna',
            'at'     => $comment->created_at,
            'text'   => RichTextSanitizer::toPlainText($comment->body) ?: 'Enviou uma imagem.',
            'place'  => $place,
            'url'    => $url,
        ];
    }
}
