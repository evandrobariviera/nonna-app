<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\TaskApprovalRound;
use Illuminate\Support\Collection;

/**
 * Aba "Por cliente" da Central de Aprovações interna: o mesmo recorte que o
 * cliente vê na Central dele (Central → Projeto → Peça), mais o que só a
 * agência precisa saber — o que ainda nem foi enviado e há quanto tempo cada
 * coisa espera o cliente. Só aparecem clientes com algo em aberto.
 *
 * Cada tarefa é representada pela rodada de aprovação mais recente.
 */
class ApprovalOverviewService
{
    // A partir de quantos dias esperando o cliente vale cobrar / é urgente.
    public const WARN_DAYS   = 3;
    public const URGENT_DAYS = 7;

    /**
     * @return Collection<int, array{client: Client, groups: Collection, totals: array, oldest_days: ?int}>
     */
    public function byClient(): Collection
    {
        $openClientIds = TaskApprovalRound::query()
            ->where('type', 'aprovacao')
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($q) => $q->where('status', 'changes_requested')->whereNull('handled_at')))
            ->whereHas('task')
            ->with('task:id,client_id')
            ->get()
            ->pluck('task.client_id')
            ->filter()
            ->unique();

        if ($openClientIds->isEmpty()) {
            return collect();
        }

        $rounds = TaskApprovalRound::query()
            ->where('type', 'aprovacao')
            ->where('status', '!=', 'cancelled')
            ->whereHas('task', fn ($q) => $q->whereIn('client_id', $openClientIds))
            ->with('task:id,client_id,project_id,title,created_at')
            ->orderByDesc('round_number')
            ->get()
            ->unique('task_id');

        $projects = Project::whereIn('id', $rounds->pluck('task.project_id')->filter()->unique())
            ->get(['id', 'title', 'type', 'macro_plan_id'])
            ->keyBy('id');

        $clients = Client::whereIn('id', $openClientIds)
            ->get(['id', 'company_name', 'nickname'])
            ->keyBy('id');

        return $rounds
            ->groupBy('task.client_id')
            ->map(function (Collection $clientRounds, string $clientId) use ($projects, $clients) {
                $groups = $clientRounds
                    ->groupBy(fn ($r) => $projects->has($r->task->project_id) ? $r->task->project_id : 'avulsas')
                    ->map(fn (Collection $rs, string $key) => $this->group($rs, $projects->get($key), $clientId))
                    ->filter(fn ($g) => $g['open'])
                    ->sortByDesc(fn ($g) => [$g['oldest_days'] ?? -1, $g['pending']])
                    ->values();

                return [
                    'client'      => $clients->get($clientId),
                    'groups'      => $groups,
                    'totals'      => [
                        'pending'       => $groups->sum('pending'),
                        'awaiting_send' => $groups->sum('awaiting_send'),
                        'changes'       => $groups->sum('changes'),
                    ],
                    'oldest_days' => $groups->max('oldest_days'),
                ];
            })
            ->filter(fn ($c) => $c['client'] && $c['groups']->isNotEmpty())
            ->sortByDesc(fn ($c) => [$c['oldest_days'] ?? -1, $c['totals']['pending']])
            ->values();
    }

    private function group(Collection $rounds, ?Project $project, string $clientId): array
    {
        $pending      = $rounds->filter(fn ($r) => $r->status === 'pending' && $r->sent_at);
        $awaitingSend = $rounds->filter(fn ($r) => $r->status === 'pending' && !$r->sent_at);
        $changes      = $rounds->filter(fn ($r) => $r->status === 'changes_requested' && !$r->handled_at);
        $oldestSent   = $pending->min('sent_at');

        return [
            'project'       => $project,
            'title'         => $project?->title ?? 'Peças avulsas',
            'type_label'    => $project?->typeLabel() ?? 'Avulsas',
            'url'           => match (true) {
                $project && $project->macro_plan_id => route('macroplans.projects.show', [$project->macro_plan_id, $project->id]),
                (bool) $project                     => route('projects.showDirect', $project),
                default                             => route('approvals.index', ['client_id' => $clientId, 'view' => 'list']),
            },
            'total'         => $rounds->count(),
            'approved'      => $rounds->where('status', 'approved')->count(),
            'pending'       => $pending->count(),
            'awaiting_send' => $awaitingSend->count(),
            'changes'       => $changes->count(),
            'oldest_days'   => $oldestSent ? (int) $oldestSent->diffInDays(now()) : null,
            'open'          => $pending->isNotEmpty() || $awaitingSend->isNotEmpty() || $changes->isNotEmpty(),
        ];
    }
}
