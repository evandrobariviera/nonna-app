<?php

namespace App\Services\Dashboard;

use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cockpit do modo Distribuição da Dashboard — a ferramenta do Head pra decidir quem faz o
 * quê e quando. Regra central (combinada com o usuário em 2026-10-06):
 *
 *  - O que DISTRIBUIR é recortado pelo Head logado: tarefas em que ele é Responsável
 *    (task_executors.role = responsavel). As sem Responsável nenhum aparecem só numa
 *    linha discreta (não são de ninguém, mas hoje são quase todas de Estratégia).
 *  - O trabalho principal do Head é REBALANCEAR (tarefas já nascem com executor e data
 *    no lançamento): mover da célula cheia/atrasada pra outra pessoa ou dia — por isso
 *    cada tarefa da grade carrega o que precisa pra ser movida (ver a view).
 *  - A CARGA de cada pessoa é sempre o total real dela, de qualquer Head. Contar só as
 *    tarefas do Head logado faria ele jogar trabalho em quem já está cheio com outro Head.
 *  - Time do Head = pessoas dos setores dele + quem já executa tarefas dele.
 *  - Carga = número de tarefas (pontos de sprint vêm depois e substituem essa conta).
 *
 * Dia de uma tarefa = data de APROVAÇÃO, a mesma régua da Semana de Produção e da Sprint.
 */
class DistributionCockpit
{
    private const CLOSED = ['concluido', 'cancelado'];

    // Status que contam como carga na grade quando ninguém mexeu no filtro.
    public const DEFAULT_LOAD_STATUSES = ['backlog', 'ajuste_alteracao'];

    public function build(int $userId, int $weekOffset, array $loadStatuses = self::DEFAULT_LOAD_STATUSES): array
    {
        $loadStatuses = array_values(array_intersect($loadStatuses, array_keys(Task::$statuses))) ?: self::DEFAULT_LOAD_STATUSES;

        $monday = now()->startOfWeek(CarbonInterface::MONDAY)->addWeeks($weekOffset)->startOfDay();
        $friday = $monday->copy()->addDays(4);

        $team = $this->team($userId);
        $teamIds = $team->pluck('id')->all();

        // ── Meus números como Responsável ──
        $mine = fn () => $this->open()->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId));
        $numbers = [
            'abertas'       => $mine()->count(),
            'sem_executor'  => $this->withoutExecutor($mine())->count(),
            'sem_data'      => $mine()->whereNull('approval_date')->count(),
            'atrasadas'     => $mine()->whereDate('approval_date', '<', today())->count(),
            'revisao'       => $mine()->where('status', 'revisao_interna')->count(),
        ];

        // ── A distribuir: minhas (Responsável = eu) que ainda não têm executor ou data ──
        // As sem Responsável ficam à parte, discretas: na prática são tarefas de Estratégia
        // geradas pela automação de reuniões (Agendar/Revisar Macroplanejamento), não
        // produção — misturadas aqui elas tomavam a caixa inteira do Head.
        $pending = fn (Builder $q) => $q->where(fn ($w) => $w
            ->whereNull('approval_date')
            ->orWhere(fn ($x) => $this->withoutExecutor($x)));

        $toDistributeQuery = $pending($this->open()->whereHas('responsibles', fn ($r) => $r->where('users.id', $userId)));
        $toDistributeCount = (clone $toDistributeQuery)->count();
        $toDistribute = $toDistributeQuery
            ->with(['client', 'executor', 'executors', 'responsibles'])
            ->orderByRaw('approval_date asc nulls last')
            ->orderBy('created_at')
            ->limit(60)
            ->get();

        $noResponsible = $pending($this->open()->whereDoesntHave('responsibles'))
            ->with('client')
            ->orderBy('created_at')
            ->limit(30)
            ->get();

        // ── Grade Pessoa × Dia ──
        // Só os status escolhidos no filtro da grade — padrão Backlog + Ajuste/Alteração (o
        // que ainda vai ocupar o executor): tarefa em revisão, aprovação ou despacho já saiu da
        // mão dele e inflava a carga (decisão do usuário). Atrasadas de antes da semana, nos
        // mesmos status, viram uma coluna à parte.
        $weekTasks = $this->forExecutors(Task::whereIn('status', $loadStatuses), $teamIds)
            ->whereDate('approval_date', '>=', $monday)
            ->whereDate('approval_date', '<=', $friday)
            ->with(['client', 'executor', 'executors', 'responsibles'])
            ->get();

        $overdueTasks = $this->forExecutors($this->open()->whereIn('status', $loadStatuses), $teamIds)
            ->whereDate('approval_date', '<', $monday)
            ->with(['client', 'executor', 'executors', 'responsibles'])
            ->orderBy('approval_date')
            ->get();

        $days = [];
        for ($d = $monday->copy(); $d->lte($friday); $d->addDay()) {
            $days[] = $d->copy();
        }

        $grid = [];
        $maxCell = 1;
        foreach ($team as $person) {
            $row = ['user' => $person, 'overdue' => $this->cell($overdueTasks, $person->id, $userId), 'days' => []];
            foreach ($days as $day) {
                $dayTasks = $weekTasks->filter(fn ($t) => $t->approval_date->isSameDay($day));
                $cell = $this->cell($dayTasks, $person->id, $userId);
                $maxCell = max($maxCell, $cell['points']); // cor da célula pela carga em pontos
                $row['days'][$day->toDateString()] = $cell;
            }
            $row['week_total']  = array_sum(array_map(fn ($c) => $c['total'], $row['days']));
            $row['week_points'] = array_sum(array_map(fn ($c) => $c['points'], $row['days']));
            $grid[] = $row;
        }

        return [
            'clientBalance'     => $this->clientBalance($userId),
            'numbers'           => $numbers,
            'toDistribute'      => $toDistribute,
            'toDistributeCount' => $toDistributeCount,
            'noResponsible'     => $noResponsible,
            'team'              => $team,
            'days'              => $days,
            'grid'              => $grid,
            'maxCell'           => $maxCell,
            'weekOffset'        => $weekOffset,
            'loadStatuses'      => $loadStatuses,
        ];
    }

    /**
     * Equilíbrio de produção entre os clientes do Head, no mês corrente — uma barra por
     * cliente, por status (igual à barra da Sprint), pra ver quem está andando e quem ficou
     * pra trás. Mesma régua do "Volume do mês" do Painel de Produção / Client::productionUsage():
     * tarefa do mês = não cancelada com COALESCE(approval_date, created_at) no mês. Cliente com
     * volume combinado (production_quota) só conta os tipos combinados e ganha o trecho
     * "faltam pedir" (combinado − já pedido, tipo a tipo); sem volume, conta tudo do mês.
     *
     * Clientes do Head = têm tarefa do mês em que ele é Responsável, ou ele é a Direção
     * Criativa do cliente (clients.creative_lead_id).
     */
    private function clientBalance(int $userId): array
    {
        $monthStart = now()->startOfMonth();
        $inMonth = fn ($q) => $q->whereRaw("date_trunc('month', coalesce(approval_date, created_at)) = date_trunc('month', ?::date)", [$monthStart->toDateString()]);

        $clientIds = $inMonth(Task::where('status', '!=', 'cancelado')->whereNotNull('client_id'))
            ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->distinct()->pluck('client_id')
            ->merge(\App\Models\Client::where('creative_lead_id', $userId)->pluck('id'))
            ->unique()->values();

        $clients = \App\Models\Client::whereIn('id', $clientIds)->where('status', '!=', 'inactive')
            ->get(['id', 'nickname', 'company_name', 'production_quota']);

        // Tipos que o Head cuida — os que somam ≥ 10% das tarefas em que ele foi Responsável
        // nos últimos 90 dias. Sem isso o Head de Tecnologia (175 web × 10 criação) via
        // "faltam pedir 8" de Criação de um cliente só porque tinha um site dele lá; o corte
        // de 10% tira as exceções esporádicas. Sem histórico nenhum, não recorta.
        $typeCounts = Task::whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->where('created_at', '>=', now()->subDays(90))
            ->whereNotNull('task_type')
            ->selectRaw('task_type, count(*) as total')
            ->groupBy('task_type')
            ->pluck('total', 'task_type');
        $myTypes = $typeCounts->filter(fn ($n) => $n >= 0.10 * $typeCounts->sum())->keys()->all();

        $rows = $inMonth(Task::where('status', '!=', 'cancelado')->whereIn('client_id', $clients->pluck('id')))
            ->when($myTypes, fn ($q) => $q->whereIn('task_type', $myTypes))
            ->selectRaw('client_id, task_type, status, count(*) as total')
            ->groupBy('client_id', 'task_type', 'status')
            ->toBase()->get()
            ->groupBy('client_id');

        $statusOrder = array_keys(Task::$statuses);
        $out = [];
        foreach ($clients as $client) {
            $quota = array_filter($client->production_quota ?? [], fn ($v) => (int) $v > 0);
            if ($myTypes) {
                $quota = array_intersect_key($quota, array_flip($myTypes));
            }
            $clientRows = collect($rows->get($client->id, []))
                ->when($quota, fn ($c) => $c->filter(fn ($r) => isset($quota[$r->task_type])));

            $byStatus = [];
            foreach ($statusOrder as $s) {
                $n = (int) $clientRows->where('status', $s)->sum('total');
                if ($n > 0) {
                    $byStatus[$s] = $n;
                }
            }
            $planned = array_sum($byStatus);

            // "Faltam pedir" tipo a tipo — sobra de um tipo não cobre falta de outro.
            $toRequest = 0;
            foreach ($quota as $type => $qtd) {
                $toRequest += max(0, (int) $qtd - (int) $clientRows->where('task_type', $type)->sum('total'));
            }

            $quotaTotal = array_sum(array_map('intval', $quota));
            $scale = max($planned + $toRequest, 1);
            $done = $byStatus['concluido'] ?? 0;

            if ($planned === 0 && $quotaTotal === 0) {
                continue;
            }

            $out[] = [
                'client'     => $client,
                'byStatus'   => $byStatus,
                'planned'    => $planned,
                'done'       => $done,
                'open'       => $planned - $done,
                'toRequest'  => $toRequest,
                'quota'      => $quotaTotal,
                'scale'      => $scale,
                'progress'   => $done / $scale,
            ];
        }

        usort($out, fn ($a, $b) => $a['progress'] <=> $b['progress'] ?: $b['scale'] <=> $a['scale']);

        return [
            'rows'      => $out,
            'daysLeft'  => now()->daysInMonth - now()->day,
            'monthName' => now()->locale('pt_BR')->translatedFormat('F'),
        ];
    }

    /** Pessoas dos setores do Head + quem executa alguma tarefa aberta dele. */
    private function team(int $userId): Collection
    {
        $sectorPeers = Sector::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->with('users:id,name')
            ->get()
            ->flatMap(fn ($s) => $s->users->pluck('id'));

        $myTasks = $this->open()
            ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->with('executors')
            ->get(['id', 'executor_id']);

        $myExecutors = $myTasks->flatMap(fn ($t) => $this->executorIds($t));

        return User::whereIn('id', $sectorPeers->merge($myExecutors)->unique()->filter()->all())
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path', 'avatar_disk']);
    }

    /** Uma célula da grade: total da pessoa + quantas dessas são do Head logado. */
    private function cell(Collection $tasks, int $personId, int $headId): array
    {
        $theirs = $tasks->filter(fn ($t) => in_array($personId, $this->executorIds($t), true))->values();

        return [
            'total' => $theirs->count(),
            // Pontos de sprint (SprintPoints) — métrica híbrida: a tela mostra pontos E quantidade.
            'points' => (int) $theirs->sum(fn ($t) => $t->sprint_points ?? 1),
            'mine'  => $theirs->filter(fn ($t) => $t->responsibles->contains('id', $headId))->count(),
            'done'  => $theirs->where('status', 'concluido')->count(),
            'tasks' => $theirs,
        ];
    }

    /**
     * Executor mora em dois lugares: o pivot (papel "executor") e, como herança,
     * tasks.executor_id — que só vale quando não há pivot de executor (mesma regra do
     * Painel de Produção).
     */
    private function executorIds(Task $task): array
    {
        $fromPivot = $task->executors->filter(fn ($u) => $u->pivot->role === 'executor')->pluck('id')->all();

        return $fromPivot ?: array_filter([$task->executor_id]);
    }

    private function forExecutors(Builder $query, array $userIds): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('executors', fn ($e) => $e->whereIn('users.id', $userIds)->where('task_executors.role', 'executor'))
            ->orWhere(fn ($o) => $o
                ->whereIn('executor_id', $userIds)
                ->whereDoesntHave('executors', fn ($e) => $e->where('task_executors.role', 'executor'))));
    }

    private function withoutExecutor(Builder $query): Builder
    {
        return $query
            ->whereNull('executor_id')
            ->whereDoesntHave('executors', fn ($e) => $e->where('task_executors.role', 'executor'));
    }

    /** Abertas, sem cliente inativo (tarefa interna, sem cliente, continua contando). */
    private function open(): Builder
    {
        return Task::whereNotIn('status', self::CLOSED)
            ->where(fn ($q) => $q
                ->whereNull('client_id')
                ->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')));
    }
}
