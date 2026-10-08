<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskAttachment;
use Illuminate\Support\Collection;

/**
 * Central de Aprovações do Portal: agrupa por projeto tudo que passou pelo
 * processo de aprovação (Central → Projeto → Peça), com a situação da rodada
 * mais recente de cada tarefa.
 *
 * A decisão continua sendo POR TAREFA (TaskApprovalRound) — essas páginas só
 * agregam, nunca criam uma aprovação própria do projeto.
 */
class ProjectApprovalPageService
{
    /**
     * Cards da Página do Projeto.
     *
     * @return Collection<int, array{task: Task, round: TaskApprovalRound, status: string, thumb: ?TaskAttachment, first_file: ?TaskAttachment, deliverable_count: int, is_highlight: bool}>
     */
    public function items(Project $project): Collection
    {
        return $this->buildItems(
            $this->latestRounds(Task::where('project_id', $project->id)->select('id')),
            $project->highlight_task_id,
        );
    }

    /**
     * Cards de "Peças avulsas": tarefas do cliente sem projeto (inclui chamados).
     */
    public function looseItems(Client $client): Collection
    {
        return $this->buildItems(
            $this->latestRounds(Task::where('client_id', $client->id)->whereNull('project_id')->select('id')),
            null,
        );
    }

    /**
     * Cards da Central: um por projeto com algo em aprovação + um grupo de
     * peças avulsas. Quem tem peça aguardando o cliente vem primeiro.
     *
     * @return Collection<int, array{project: ?Project, title: string, type_label: string, url: string, summary: array, thumb: ?TaskAttachment, finished: bool, last_at: mixed}>
     */
    public function central(Client $client): Collection
    {
        $items = $this->buildItems(
            $this->latestRounds(Task::where('client_id', $client->id)->select('id')),
            null,
        );

        $projects = Project::whereIn('id', $items->pluck('task.project_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $items
            ->groupBy(fn ($i) => $projects->has($i['task']->project_id) ? $i['task']->project_id : 'avulsas')
            ->map(function (Collection $group, string $key) use ($projects) {
                $project = $projects->get($key);
                $summary = $this->summary($group);

                // Miniatura do card: a do destaque, senão a primeira imagem que tiver.
                $highlight = $project?->highlight_task_id
                    ? $group->first(fn ($i) => $i['task']->id === $project->highlight_task_id)
                    : null;
                $thumb = $highlight['thumb'] ?? $group->pluck('thumb')->filter()->first();

                return [
                    'project'    => $project,
                    'title'      => $project?->title ?? 'Peças avulsas',
                    'type_label' => $project?->typeLabel() ?? 'Avulsas',
                    'url'        => $project
                        ? route('portal.approvals.project', $project)
                        : route('portal.approvals.loose'),
                    'summary'    => $summary,
                    'thumb'      => $thumb,
                    'finished'   => $summary['approved'] === $summary['total'],
                    'last_at'    => $group->max(fn ($i) => $i['round']->sent_at ?? $i['round']->submitted_at),
                ];
            })
            ->sortBy([
                fn ($a, $b) => ($b['summary']['pending'] > 0) <=> ($a['summary']['pending'] > 0),
                fn ($a, $b) => $b['last_at'] <=> $a['last_at'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array>  $items
     * @return array{total: int, approved: int, pending: int, changes: int, percent: int}
     */
    public function summary(Collection $items): array
    {
        $total    = $items->count();
        $approved = $items->where('status', 'approved')->count();

        return [
            'total'    => $total,
            'approved' => $approved,
            'pending'  => $items->where('status', 'pending')->count(),
            'changes'  => $items->where('status', 'changes_requested')->count(),
            'percent'  => $total ? (int) round($approved / $total * 100) : 0,
        ];
    }

    /**
     * Quantas peças esperam decisão do cliente — mesmo critério dos cards
     * (badge da lateral do Portal).
     */
    public function pendingCount(Client $client): int
    {
        return $this->visibleRounds(Task::where('client_id', $client->id)->select('id'))
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Rodadas que de fato chegaram no cliente. Fica de fora: aviso (não pede
     * decisão), cancelada e "Aguardando Envio" (pending sem sent_at — cliente
     * ainda não recebeu). Rodada antiga, de antes de existir sent_at, conta
     * se já foi resolvida.
     */
    private function visibleRounds($taskIds)
    {
        return TaskApprovalRound::query()
            ->whereIn('task_id', $taskIds)
            ->where('type', 'aprovacao')
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNotNull('sent_at')->orWhere('status', '!=', 'pending'));
    }

    // A rodada mais recente de cada tarefa é a que representa a tarefa no card.
    private function latestRounds($taskIds): Collection
    {
        return $this->visibleRounds($taskIds)
            ->with('task')
            ->orderByDesc('round_number')
            ->get()
            ->unique('task_id');
    }

    private function buildItems(Collection $rounds, ?string $highlightTaskId): Collection
    {
        // Entregáveis de todas as rodadas numa query só (em vez de deliverables()
        // por rodada) — casados por tarefa + número da rodada logo abaixo.
        $attachments = TaskAttachment::whereIn('task_id', $rounds->pluck('task_id'))
            ->where('is_deliverable', true)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($a) => $a->task_id . '#' . $a->round_number);

        return $rounds
            ->map(function (TaskApprovalRound $round) use ($attachments, $highlightTaskId) {
                $files = $attachments->get($round->task_id . '#' . $round->round_number, collect());

                return [
                    'task'              => $round->task,
                    'round'             => $round,
                    'status'            => $round->status,
                    'thumb'             => $files->first(fn ($f) => $f->isImage()),
                    'first_file'        => $files->first(),
                    'deliverable_count' => $files->count(),
                    'is_highlight'      => $highlightTaskId !== null && $highlightTaskId === $round->task_id,
                ];
            })
            ->sortBy(fn ($i) => $i['task']->created_at)
            ->values();
    }
}
