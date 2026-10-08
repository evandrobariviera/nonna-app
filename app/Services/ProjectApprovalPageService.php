<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TaskApprovalRound;
use App\Models\TaskAttachment;
use Illuminate\Support\Collection;

/**
 * Monta a Página do Projeto do Portal (Central de Aprovações): os cards de
 * tudo que passou pelo processo de aprovação dentro de um projeto, com a
 * situação da rodada mais recente de cada tarefa.
 *
 * A decisão continua sendo POR TAREFA (TaskApprovalRound) — essa página só
 * agrega, nunca cria uma aprovação própria do projeto.
 */
class ProjectApprovalPageService
{
    /**
     * Rodada que representa a tarefa na página: a mais recente que de fato
     * chegou no cliente. Fica de fora: aviso (não pede decisão), cancelada e
     * "Aguardando Envio" (pending sem sent_at — cliente ainda não recebeu).
     * Rodada antiga, de antes de existir sent_at, conta se já foi resolvida.
     *
     * @return Collection<int, array{task: \App\Models\Task, round: TaskApprovalRound, status: string, thumb: ?TaskAttachment, first_file: ?TaskAttachment, deliverable_count: int, is_highlight: bool}>
     */
    public function items(Project $project): Collection
    {
        $rounds = TaskApprovalRound::query()
            ->whereIn('task_id', $project->tasks()->select('tasks.id'))
            ->where('type', 'aprovacao')
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNotNull('sent_at')->orWhere('status', '!=', 'pending'))
            ->with('task')
            ->orderByDesc('round_number')
            ->get()
            ->unique('task_id');

        // Entregáveis de todas as rodadas numa query só (em vez de deliverables()
        // por rodada) — casados por tarefa + número da rodada logo abaixo.
        $attachments = TaskAttachment::whereIn('task_id', $rounds->pluck('task_id'))
            ->where('is_deliverable', true)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($a) => $a->task_id . '#' . $a->round_number);

        return $rounds
            ->map(function (TaskApprovalRound $round) use ($attachments, $project) {
                $files = $attachments->get($round->task_id . '#' . $round->round_number, collect());

                return [
                    'task'              => $round->task,
                    'round'             => $round,
                    'status'            => $round->status,
                    'thumb'             => $files->first(fn ($f) => $f->isImage()),
                    'first_file'        => $files->first(),
                    'deliverable_count' => $files->count(),
                    'is_highlight'      => $project->highlight_task_id === $round->task_id,
                ];
            })
            ->sortBy(fn ($i) => $i['task']->created_at)
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
}
