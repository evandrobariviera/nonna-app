<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
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
        // Join com tasks em vez de whereHas + with('task'): a escopo de organização
        // vem do Task (Tenantable) e cada passo é uma consulta só.
        $openClientIds = Task::query()
            ->join('task_approval_rounds as r', 'r.task_id', '=', 'tasks.id')
            ->where('r.type', 'aprovacao')
            ->where(fn ($q) => $q->where('r.status', 'pending')
                ->orWhere(fn ($q) => $q->where('r.status', 'changes_requested')->whereNull('r.handled_at')))
            ->whereNotNull('tasks.client_id')
            ->distinct()
            ->pluck('tasks.client_id');

        if ($openClientIds->isEmpty()) {
            return collect();
        }

        // Uma linha por rodada, já com o que precisa da tarefa; a mais recente
        // de cada tarefa fica (unique depois do orderByDesc).
        $rounds = Task::query()
            ->join('task_approval_rounds as r', 'r.task_id', '=', 'tasks.id')
            ->whereIn('tasks.client_id', $openClientIds)
            ->where('r.type', 'aprovacao')
            ->where('r.status', '!=', 'cancelled')
            ->orderByDesc('r.round_number')
            ->get(['tasks.id as task_id', 'tasks.client_id', 'tasks.project_id', 'r.status', 'r.sent_at', 'r.handled_at', 'r.submitted_at'])
            ->unique('task_id')
            ->map(fn ($row) => (object) [
                'client_id'  => $row->client_id,
                'project_id' => $row->project_id,
                'status'     => $row->status,
                'sent_at'    => $row->sent_at ? \Illuminate\Support\Carbon::parse($row->sent_at) : null,
                'handled_at'   => $row->handled_at,
                'submitted_at' => $row->submitted_at ? \Illuminate\Support\Carbon::parse($row->submitted_at) : null,
            ]);

        $projects = Project::whereIn('id', $rounds->pluck('project_id')->filter()->unique())
            ->get(['id', 'title', 'type', 'macro_plan_id'])
            ->keyBy('id');

        $clients = Client::whereIn('id', $openClientIds)
            ->get(['id', 'company_name', 'nickname'])
            ->keyBy('id');

        return $rounds
            ->groupBy('client_id')
            ->map(function (Collection $clientRounds, string $clientId) use ($projects, $clients) {
                $groups = $clientRounds
                    ->groupBy(fn ($r) => $projects->has($r->project_id) ? $r->project_id : 'avulsas')
                    ->map(fn (Collection $rs, string $key) => $this->group($rs, $projects->get($key), $clientId))
                    ->filter(fn ($g) => $g['open'])
                    ->sortByDesc(fn ($g) => [max($g['oldest_days'] ?? -1, $g['queued_days'] ?? -1), $g['pending']])
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
                    'queued_days' => $groups->max('queued_days'),
                ];
            })
            ->filter(fn ($c) => $c['client'] && $c['groups']->isNotEmpty())
            // Mais parado primeiro — esperando o cliente OU esperando a gente enviar.
            ->sortByDesc(fn ($c) => [max($c['oldest_days'] ?? -1, $c['queued_days'] ?? -1), $c['totals']['pending']])
            ->values();
    }

    private function group(Collection $rounds, ?Project $project, string $clientId): array
    {
        $pending      = $rounds->filter(fn ($r) => $r->status === 'pending' && $r->sent_at);
        $awaitingSend = $rounds->filter(fn ($r) => $r->status === 'pending' && !$r->sent_at);
        $changes      = $rounds->filter(fn ($r) => $r->status === 'changes_requested' && !$r->handled_at);
        $oldestSent   = $pending->min('sent_at');
        $oldestQueued = $awaitingSend->min('submitted_at');

        return [
            'project'       => $project,
            'title'         => $project?->title ?? 'Peças avulsas',
            'type_label'    => $project?->typeLabel() ?? 'Avulsas',
            // Sempre a Lista de aprovações, filtrada por cliente + projeto (ou avulsas).
            'url'           => route('approvals.index', [
                'client_id'  => $clientId,
                'project_id' => $project?->id ?? 'avulsas',
                'view'       => 'list',
            ]),
            'total'         => $rounds->count(),
            'approved'      => $rounds->where('status', 'approved')->count(),
            'pending'       => $pending->count(),
            'awaiting_send' => $awaitingSend->count(),
            'changes'       => $changes->count(),
            'oldest_days'   => $oldestSent ? (int) $oldestSent->diffInDays(now()) : null,
            // Há quanto tempo a rodada mais antiga espera A GENTE clicar "Enviar pro Cliente".
            'queued_days'   => $oldestQueued ? (int) $oldestQueued->diffInDays(now()) : null,
            'open'          => $pending->isNotEmpty() || $awaitingSend->isNotEmpty() || $changes->isNotEmpty(),
        ];
    }
}
