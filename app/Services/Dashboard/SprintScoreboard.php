<?php

namespace App\Services\Dashboard;

use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Placar de pontos de sprint (modo Execução) — pedido do usuário pra criar um clima de
 * "competitividade" saudável: cada um vê os próprios pontos (sprint, semana, mês), o total
 * da agência (lançado × feito na sprint) e o ranking.
 *
 * Regras:
 *  - Ponto FEITO = tarefa concluída. A data que conta é a da última transição pra
 *    "concluido" (task_status_transitions — mesma régua da Carga de Produção e de
 *    Client::productionUsage()); reaberta e concluída de novo conta na data nova.
 *  - Na Sprint conta a tarefa da sprint ativa concluída, sem olhar a data.
 *  - Os pontos vão pro executor (pivot "executor", ou executor_id como herança). Hoje não
 *    existe tarefa com mais de um executor; se aparecer, cada um leva os pontos inteiros.
 *  - Tarefa sem pontos calculados vale 1 (mesmo fallback das outras telas).
 */
class SprintScoreboard
{
    public function build(int $userId): array
    {
        $sprint = Sprint::where('status', 'active')->first();
        $weekStart  = now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $monthStart = now()->startOfMonth();

        // Concluídas desde o começo do mês ou da semana (o que vier antes) — com a data da
        // última conclusão — mais todas as concluídas da sprint ativa.
        $since = $weekStart->lt($monthStart) ? $weekStart : $monthStart;
        $doneAt = DB::connection('pgsql')->table('task_status_transitions')
            ->where('to_status', 'concluido')
            ->groupBy('task_id')
            ->selectRaw('task_id, max(changed_at) as done_at');

        $done = Task::where('tasks.status', 'concluido')
            ->leftJoinSub($doneAt, 'd', 'd.task_id', '=', 'tasks.id')
            ->where(fn ($q) => $q->where('d.done_at', '>=', $since)
                ->when($sprint, fn ($w) => $w->orWhere('tasks.sprint_id', $sprint->id)))
            ->select('tasks.id', 'tasks.sprint_id', 'tasks.executor_id', 'tasks.sprint_points', 'd.done_at')
            ->with('executors')
            ->get();

        $periods = [
            'semana' => $done->filter(fn ($t) => $t->done_at && $t->done_at >= $weekStart->toDateTimeString()),
            'sprint' => $sprint ? $done->where('sprint_id', $sprint->id) : collect(),
            'mes'    => $done->filter(fn ($t) => $t->done_at && $t->done_at >= $monthStart->toDateTimeString()),
        ];

        $rankings = array_map(fn ($tasks) => $this->ranking($tasks), $periods);
        $userIds = collect($rankings)->flatMap(fn ($r) => $r->keys())->push($userId)->unique();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'avatar_path', 'avatar_disk'])->keyBy('id');

        $out = [];
        foreach ($rankings as $period => $ranking) {
            $rows = $ranking->map(fn ($r, $uid) => $r + ['user' => $users[$uid] ?? null])
                ->filter(fn ($r) => $r['user'])
                ->sortByDesc('points')->values();
            $myPos = $rows->search(fn ($r) => $r['user']->id === $userId);
            $out[$period] = [
                'rows'   => $rows,
                'mine'   => $myPos === false ? ['points' => 0, 'tasks' => 0] : $rows[$myPos],
                'myRank' => $myPos === false ? null : $myPos + 1,
                'total'  => $rows->sum('points'),
            ];
        }

        // Agência na sprint: o que foi lançado × o que já foi feito.
        $agency = ['launched' => 0, 'done' => 0, 'launchedTasks' => 0, 'doneTasks' => 0];
        if ($sprint) {
            $row = Task::where('sprint_id', $sprint->id)->where('status', '!=', 'cancelado')
                ->selectRaw('count(*) as tasks, sum(coalesce(sprint_points, 1)) as points')
                ->selectRaw("count(*) filter (where status = 'concluido') as done_tasks")
                ->selectRaw("sum(coalesce(sprint_points, 1)) filter (where status = 'concluido') as done_points")
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

    /** [user_id => ['points' => int, 'tasks' => int]] */
    private function ranking(Collection $tasks): Collection
    {
        $acc = [];
        foreach ($tasks as $t) {
            foreach ($this->executorIds($t) as $uid) {
                $acc[$uid]['points'] = ($acc[$uid]['points'] ?? 0) + ($t->sprint_points ?? 1);
                $acc[$uid]['tasks']  = ($acc[$uid]['tasks'] ?? 0) + 1;
            }
        }

        return collect($acc);
    }

    private function executorIds(Task $task): array
    {
        $fromPivot = $task->executors->filter(fn ($u) => ($u->pivot->role ?? null) === 'executor')->pluck('id')->all();

        return $fromPivot ?: array_filter([$task->executor_id]);
    }
}
