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
 *    (task_executors.role = responsavel), mais as que estão sem Responsável nenhum — essas
 *    não são de ninguém e cairiam no vão se cada Head só enxergasse as próprias.
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

    public function build(int $userId, int $weekOffset): array
    {
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

        // ── A distribuir: minhas (ou sem Responsável) que ainda não têm executor ou data ──
        $toDistributeQuery = $this->open()
            ->where(fn ($q) => $q
                ->whereHas('responsibles', fn ($r) => $r->where('users.id', $userId))
                ->orWhereDoesntHave('responsibles'))
            ->where(fn ($q) => $q
                ->whereNull('approval_date')
                ->orWhere(fn ($w) => $this->withoutExecutor($w)));

        $toDistributeCount = (clone $toDistributeQuery)->count();
        $toDistribute = $toDistributeQuery
            ->with(['client', 'executor', 'executors', 'responsibles'])
            ->orderByRaw('approval_date asc nulls last')
            ->orderBy('created_at')
            ->limit(60)
            ->get();

        // ── Grade Pessoa × Dia ──
        // Toda tarefa não cancelada do time na semana (inclui concluída: o dia já "gastou"
        // aquela capacidade). Atrasadas abertas de antes da semana viram uma coluna à parte.
        $weekTasks = $this->forExecutors(Task::where('status', '!=', 'cancelado'), $teamIds)
            ->whereDate('approval_date', '>=', $monday)
            ->whereDate('approval_date', '<=', $friday)
            ->with(['client', 'executor', 'executors', 'responsibles'])
            ->get();

        $overdueTasks = $this->forExecutors($this->open(), $teamIds)
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
                $maxCell = max($maxCell, $cell['total']);
                $row['days'][$day->toDateString()] = $cell;
            }
            $row['week_total'] = array_sum(array_map(fn ($c) => $c['total'], $row['days']));
            $grid[] = $row;
        }

        return [
            'numbers'           => $numbers,
            'toDistribute'      => $toDistribute,
            'toDistributeCount' => $toDistributeCount,
            'team'              => $team,
            'days'              => $days,
            'grid'              => $grid,
            'maxCell'           => $maxCell,
            'weekOffset'        => $weekOffset,
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
