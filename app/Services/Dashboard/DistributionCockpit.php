<?php

namespace App\Services\Dashboard;

use App\Models\AttentionDigest;
use App\Models\OrganizationUser;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\ExecutorQueue;
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

    // Status que contam como carga na grade quando ninguém mexeu no filtro = a fila do executor
    // (ExecutorQueue): o Head abre a grade e vê exatamente o que cada pessoa vê na Execução.
    // Os outros status (revisão, aprovação...) e o Backlog esperando insumo são contexto que o
    // Head liga quando quiser.
    public const DEFAULT_LOAD_STATUSES = ExecutorQueue::STATUSES;

    // Opção extra do filtro (não é status): inclui o Backlog esperando copy/informação.
    public const WAITING_INPUT_OPTION = 'aguardando_insumo';

    public function build(int $userId, int $weekOffset, array $loadStatuses = self::DEFAULT_LOAD_STATUSES): array
    {
        $withWaiting  = in_array(self::WAITING_INPUT_OPTION, $loadStatuses, true);
        $loadStatuses = array_values(array_intersect($loadStatuses, array_keys(Task::$statuses))) ?: self::DEFAULT_LOAD_STATUSES;
        $sprint       = ExecutorQueue::activeSprint();

        $monday = now()->startOfWeek(CarbonInterface::MONDAY)->addWeeks($weekOffset)->startOfDay();
        $friday = $monday->copy()->addDays(4);

        [$team, $teamCustom, $outsiders] = $this->team($userId);
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
        // Mesma base da fila do executor (ExecutorQueue: sprint ativa, cliente ativo, sem o
        // Backlog esperando insumo) nos status escolhidos no filtro — padrão = a fila inteira.
        // Atrasada = data de aprovação antes de HOJE (a mesma régua da Execução e do "Seu
        // foco"): o que venceu ontem sai da coluna do dia e vai pra "Atrasadas".
        $gridBase = fn (array $statuses) => ExecutorQueue::activeClients(
                ExecutorQueue::executedBy(Task::whereIn('status', $statuses), $teamIds))
            ->when($sprint, fn ($q) => $q->where('sprint_id', $sprint->id))
            ->when(! $withWaiting, fn ($q) => ExecutorQueue::withoutWaitingInput($q))
            ->with(['client', 'executor', 'executors', 'responsibles']);

        $weekTasks = $gridBase($loadStatuses)
            ->whereDate('approval_date', '>=', $monday)
            ->whereDate('approval_date', '<=', $friday)
            ->where(fn ($q) => $q->where('status', 'concluido')->orWhereDate('approval_date', '>=', today()))
            ->tap(fn ($q) => ExecutorQueue::prioritized($q)) // Trava primeiro, igual à Execução
            ->get();

        $overdueTasks = $gridBase(array_values(array_diff($loadStatuses, self::CLOSED)))
            ->whereDate('approval_date', '<', today())
            ->tap(fn ($q) => ExecutorQueue::prioritized($q)) // Trava primeiro, igual à Execução
            ->get();

        // Tarefas com data até o fim da semana que o executor NÃO vê, porque estão fora da
        // sprint ativa (sprint futura ou nenhuma) — contexto pro Head mover pra sprint.
        $outsideSprint = $sprint
            ? ExecutorQueue::activeClients(ExecutorQueue::executedBy(Task::whereIn('status', ExecutorQueue::STATUSES), $teamIds))
                ->where(fn ($q) => $q->whereNull('sprint_id')->orWhere('sprint_id', '!=', $sprint->id))
                ->whereDate('approval_date', '<=', $friday)
                ->with(['client', 'executor', 'executors', 'sprint'])
                ->orderBy('approval_date')
                ->get()
            : collect();

        // "Seu foco" de cada pessoa do time (último resumo gerado) → tarefa => posição, pra
        // grade mostrar ao Head o que o executor foi orientado a atacar primeiro.
        $focusRank = $this->focusRank($team->pluck('id')->all());

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
                $maxCell = max($maxCell, $cell['total']); // cor pela quantidade de tarefas — ênfase do momento (pontos aparecem discretos)
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
            'teamCustom'        => $teamCustom, // true = a pessoa escolheu quem acompanha
            'outsiders'         => $outsiders,  // executam tarefas dela mas estão fora do time escolhido
            'days'              => $days,
            'grid'              => $grid,
            'maxCell'           => $maxCell,
            'weekOffset'        => $weekOffset,
            'loadStatuses'      => $loadStatuses,
            'withWaiting'       => $withWaiting,
            'outsideSprint'     => $outsideSprint,
            'focusRank'         => $focusRank,
            'sprint'            => $sprint,
        ];
    }

    /**
     * Posição de cada tarefa no "Seu foco" atual de cada pessoa: [userId => [taskId => n]].
     * O foco guarda o link da tarefa (tasks.show), não o id — o id é o último trecho da URL.
     */
    private function focusRank(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        $latest = AttentionDigest::whereIn('user_id', $userIds)
            ->whereIn('id', AttentionDigest::selectRaw('distinct on (user_id) id')
                ->whereIn('user_id', $userIds)
                ->orderBy('user_id')->orderByDesc('generated_at'))
            ->get(['id', 'user_id', 'focus']);

        $rank = [];
        foreach ($latest as $digest) {
            foreach (array_values($digest->focus ?? []) as $i => $item) {
                if (preg_match('~/tarefas/([0-9a-f-]{36})~i', (string) ($item['url'] ?? ''), $m)) {
                    $rank[$digest->user_id][$m[1]] = $i + 1;
                }
            }
        }

        return $rank;
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
        // Recorte pelo tipo do LIMITE (vídeo conta no de criação) — quem cuida de vídeo vê o
        // limite de criação do cliente, e as tarefas de vídeo entram na conta dele.
        $myTypes = $typeCounts->filter(fn ($n) => $n >= 0.10 * $typeCounts->sum())->keys()
            ->map(fn ($t) => \App\Models\Client::QUOTA_TYPE_ALIASES[$t] ?? $t)->unique()->values()->all();
        $myTaskTypes = collect($myTypes)->flatMap(fn ($t) => \App\Models\Client::taskTypesForQuota($t))->all();

        $rows = $inMonth(Task::where('status', '!=', 'cancelado')->whereIn('client_id', $clients->pluck('id')))
            ->when($myTaskTypes, fn ($q) => $q->whereIn('task_type', $myTaskTypes))
            ->selectRaw('client_id, ' . \App\Models\Client::quotaTypeSql() . ' as task_type, status, count(*) as total')
            ->groupByRaw('client_id, ' . \App\Models\Client::quotaTypeSql() . ', status')
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
    /**
     * Quem aparece na grade. Se a pessoa escolheu (organization_users.distribution_team),
     * vale exatamente a escolha dela — pedido do usuário: cada Head acompanha quem quiser
     * (Alessandra/Vitor/Patrick não precisam ver a pauta do Evandro, Marlon, Alisson).
     * Sem escolha: automático = setores dela + quem executa tarefas em que ela é Responsável.
     *
     * Também devolve os "de fora": quem executa tarefa aberta dela mas não está no time
     * escolhido — pra escolha não esconder trabalho dela sem avisar.
     *
     * @return array{0: Collection, 1: bool, 2: Collection}
     */
    private function team(int $userId): array
    {
        $myTasks = $this->open()
            ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->with('executors')
            ->get(['id', 'executor_id']);
        $myExecutorCounts = $myTasks->flatMap(fn ($t) => $this->executorIds($t))->countBy();

        $chosen = app()->has('currentOrganization')
            ? OrganizationUser::where('organization_id', app('currentOrganization')->id)
                ->where('user_id', $userId)->value('distribution_team')
            : null;
        $chosen = is_string($chosen) ? json_decode($chosen, true) : $chosen;

        if (is_array($chosen)) {
            $ids = array_map('intval', $chosen);
        } else {
            $sectorPeers = Sector::whereHas('users', fn ($q) => $q->where('users.id', $userId))
                ->with('users:id,name')
                ->get()
                ->flatMap(fn ($s) => $s->users->pluck('id'));
            $ids = $sectorPeers->merge($myExecutorCounts->keys())->unique()->filter()->all();
        }

        $team = User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);

        $outsiderIds = $myExecutorCounts->keys()->diff($team->pluck('id'));
        $outsiders = User::whereIn('id', $outsiderIds)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['user' => $u, 'count' => $myExecutorCounts[$u->id]]);

        return [$team, is_array($chosen), $outsiders];
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
        return ExecutorQueue::executedBy($query, $userIds);
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
