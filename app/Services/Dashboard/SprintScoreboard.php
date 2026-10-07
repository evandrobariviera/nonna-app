<?php

namespace App\Services\Dashboard;

use App\Models\CampaignLog;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\SprintPoints;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Placar de pontos de sprint (modo Execução) — pedido do usuário pra criar um clima de
 * "competitividade" saudável: cada um vê os próprios pontos (sprint, semana, mês), o total
 * da agência (lançado × feito na sprint) e o ranking.
 *
 * Regras (revistas em 2026-10-07):
 *  - Tarefa PONTUA quando chega no status configurado pro tipo dela (Configurações → Pontos
 *    de Sprint; Criação = Revisão Interna, resto = Concluído) ou em qualquer status depois
 *    dele no fluxo — ver SprintPoints::scoreStatuses(). Conta a PRIMEIRA vez: voltar pra
 *    Ajuste não tira o ponto, reentregar não dá de novo.
 *  - Só pontua tarefa que passou por "Em Produção" ANTES disso — quem não puxa a tarefa pra
 *    execução não pontua (decisão do usuário). Cancelada não pontua.
 *  - Na Sprint conta a tarefa da sprint ativa que pontuou, sem olhar a data.
 *  - Cada otimização de campanha marcada ("Marcar otimização feita", campaign_logs
 *    type=otimizacao) vale SprintPoints::optimizationPoints() pra quem marcou. Na Sprint,
 *    conta a que caiu entre o início e o fim da sprint ativa.
 *  - Os pontos de tarefa vão pro executor (pivot "executor", ou executor_id como herança).
 *  - Tarefa sem pontos calculados vale 1 (mesmo fallback das outras telas).
 */
class SprintScoreboard
{
    public function build(int $userId): array
    {
        $org = app('currentOrganization');
        $sprint = Sprint::where('status', 'active')->first();
        $weekStart  = now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $monthStart = now()->startOfMonth();
        $since = $weekStart->lt($monthStart) ? $weekStart : $monthStart;

        $earned = $this->earnedSubquery($org->id);

        $done = Task::where('tasks.status', '!=', 'cancelado')
            ->joinSub($earned, 'd', 'd.task_id', '=', 'tasks.id')
            ->where(fn ($q) => $q->where('d.earned_at', '>=', $since)
                ->when($sprint, fn ($w) => $w->orWhere('tasks.sprint_id', $sprint->id)))
            ->select('tasks.id', 'tasks.sprint_id', 'tasks.executor_id', 'tasks.sprint_points', 'd.earned_at')
            ->with('executors')
            ->get();

        // Otimizações de campanha desde o começo do período mais antigo (semana, mês ou sprint).
        $optSince = $sprint && $sprint->starts_at && $sprint->starts_at->lt($since) ? $sprint->starts_at->copy()->startOfDay() : $since;
        $optimizations = CampaignLog::where('organization_id', $org->id)
            ->where('type', 'otimizacao')
            ->whereNotNull('logged_by')
            ->where('created_at', '>=', $optSince)
            ->get(['logged_by', 'created_at']);
        $optPoints = SprintPoints::optimizationPoints($org);

        $inSprint = fn ($at) => $sprint && $sprint->starts_at && $at >= $sprint->starts_at->copy()->startOfDay()
            && (! $sprint->ends_at || $at <= $sprint->ends_at->copy()->endOfDay());

        $periods = [
            'semana' => [
                $done->filter(fn ($t) => $t->earned_at >= $weekStart->toDateTimeString()),
                $optimizations->filter(fn ($o) => $o->created_at >= $weekStart),
            ],
            'sprint' => [
                $sprint ? $done->where('sprint_id', $sprint->id) : collect(),
                $optimizations->filter(fn ($o) => $inSprint($o->created_at)),
            ],
            'mes' => [
                $done->filter(fn ($t) => $t->earned_at >= $monthStart->toDateTimeString()),
                $optimizations->filter(fn ($o) => $o->created_at >= $monthStart),
            ],
        ];

        $rankings = array_map(fn ($p) => $this->ranking($p[0], $p[1], $optPoints), $periods);
        $userIds = collect($rankings)->flatMap(fn ($r) => $r->keys())->push($userId)->unique();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'avatar_path', 'avatar_disk'])->keyBy('id');

        $empty = ['points' => 0, 'tasks' => 0, 'opts' => 0];
        $out = [];
        foreach ($rankings as $period => $ranking) {
            $rows = $ranking->map(fn ($r, $uid) => $r + ['user' => $users[$uid] ?? null])
                ->filter(fn ($r) => $r['user'])
                ->sortByDesc('points')->values();
            $myPos = $rows->search(fn ($r) => $r['user']->id === $userId);
            $out[$period] = [
                'rows'   => $rows,
                'mine'   => $myPos === false ? $empty : $rows[$myPos],
                'myRank' => $myPos === false ? null : $myPos + 1,
                'total'  => $rows->sum('points'),
            ];
        }

        // Agência na sprint: o que foi lançado × o que já pontuou (mesma regra do ranking).
        // Otimização não entra aqui — não é lançada na sprint, então não tem "lançado".
        $agency = ['launched' => 0, 'done' => 0, 'launchedTasks' => 0, 'doneTasks' => 0];
        if ($sprint) {
            $row = Task::where('sprint_id', $sprint->id)->where('tasks.status', '!=', 'cancelado')
                ->leftJoinSub($earned, 'd', 'd.task_id', '=', 'tasks.id')
                ->selectRaw('count(*) as tasks, sum(coalesce(sprint_points, 1)) as points')
                ->selectRaw('count(*) filter (where d.earned_at is not null) as done_tasks')
                ->selectRaw('sum(coalesce(sprint_points, 1)) filter (where d.earned_at is not null) as done_points')
                ->toBase()->first();
            $agency = [
                'launched'      => (int) $row->points,
                'done'          => (int) $row->done_points,
                'launchedTasks' => (int) $row->tasks,
                'doneTasks'     => (int) $row->done_tasks,
            ];
        }

        return [
            'sprint'  => $sprint,
            'periods' => $out,
            'agency'  => $agency,
            'labels'  => [
                'semana' => 'Semana',
                'sprint' => 'Sprint',
                'mes'    => ucfirst(now()->locale('pt_BR')->translatedFormat('F')),
            ],
        ];
    }

    /**
     * task_id → earned_at: 1ª vez que a tarefa chegou num status que pontua pro tipo dela,
     * contando só depois da 1ª entrada em "Em Produção" (sem passar por lá, não aparece).
     */
    private function earnedSubquery(string $organizationId): Builder
    {
        $statuses = SprintPoints::scoreStatuses($organizationId);
        $db = DB::connection('pgsql');

        $production = $db->table('task_status_transitions')
            ->where('to_status', 'em_producao')
            ->groupBy('task_id')
            ->selectRaw('task_id, min(changed_at) as started_at');

        return $db->table('task_status_transitions as t')
            ->join('tasks as k', 'k.id', '=', 't.task_id')
            ->joinSub($production, 'p', 'p.task_id', '=', 't.task_id')
            ->where('k.organization_id', $organizationId)
            ->whereColumn('t.changed_at', '>=', 'p.started_at')
            ->where(function (Builder $q) use ($statuses) {
                foreach ($statuses['byType'] as $type => $list) {
                    $q->orWhere(fn (Builder $w) => $w->where('k.task_type', $type)->whereIn('t.to_status', $list));
                }
                // Tipo fora da lista (ou vazio): regra padrão, pontua na conclusão.
                $q->orWhere(fn (Builder $w) => $w
                    ->where(fn (Builder $x) => $x->whereNull('k.task_type')->orWhereNotIn('k.task_type', array_keys($statuses['byType'])))
                    ->whereIn('t.to_status', $statuses['default']));
            })
            ->groupBy('t.task_id')
            ->selectRaw('t.task_id, min(t.changed_at) as earned_at');
    }

    /** [user_id => ['points' => int, 'tasks' => int, 'opts' => int]] */
    private function ranking(Collection $tasks, Collection $optimizations, int $optPoints): Collection
    {
        $acc = [];
        $zero = ['points' => 0, 'tasks' => 0, 'opts' => 0];

        foreach ($tasks as $t) {
            foreach ($this->executorIds($t) as $uid) {
                $acc[$uid] ??= $zero;
                $acc[$uid]['points'] += $t->sprint_points ?? 1;
                $acc[$uid]['tasks']++;
            }
        }
        foreach ($optimizations as $o) {
            $acc[$o->logged_by] ??= $zero;
            $acc[$o->logged_by]['points'] += $optPoints;
            $acc[$o->logged_by]['opts']++;
        }

        return collect($acc);
    }

    private function executorIds(Task $task): array
    {
        $fromPivot = $task->executors->filter(fn ($u) => ($u->pivot->role ?? null) === 'executor')->pluck('id')->all();

        return $fromPivot ?: array_filter([$task->executor_id]);
    }
}
