<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskStatusTransition;
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductivityDashboardController extends Controller
{
    public static array $periods = [
        '7'  => 'Últimos 7 dias',
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
    ];

    public function index(Request $request): View
    {
        $period = $request->get('period', '30');
        if (!array_key_exists($period, self::$periods)) {
            $period = '30';
        }
        $since = now()->subDays((int) $period);

        // ── Quem entrega mais (Executor, tarefas concluídas no período) ──
        $completedTaskIds = TaskStatusTransition::where('to_status', 'concluido')
            ->where('changed_at', '>=', $since)
            ->pluck('task_id')
            ->unique();

        $completedTasks = Task::whereIn('id', $completedTaskIds)
            ->with(['client', 'executor', 'executors'])
            ->get();

        $byExecutor = $this->groupByExecutor($completedTasks);

        // ── Tipo de tarefa mais entregue ──
        $byType = $completedTasks->groupBy('task_type')
            ->map(fn ($group, $type) => (object) [
                'label' => Task::$types[$type] ?? ($type ?: 'Sem tipo'),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();

        // ── Cliente que mais consome a agência (demanda: tarefas abertas no período) ──
        $byClient = Task::where('created_at', '>=', $since)
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->filter(fn ($group) => $group->first()->client !== null)
            ->map(fn ($group) => (object) [
                'client' => $group->first()->client,
                'count'  => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(10);

        // ── Tarefas atrasadas, por executor (estado atual, não recorte de período) ──
        $overdueTasks = Task::whereNotIn('status', ['concluido', 'cancelado'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->with(['executor', 'executors'])
            ->get();

        $overdueByExecutor = $this->groupByExecutor($overdueTasks);

        // ── Tempo médio em cada status, em dias úteis (todo o histórico disponível) ──
        $avgByStatus = $this->averageTimeInStatus();

        $instrumentationStartedAt = TaskStatusTransition::oldest('changed_at')->value('changed_at');

        return view('productivity.index', compact(
            'period', 'byExecutor', 'byType', 'byClient', 'overdueByExecutor', 'avgByStatus', 'instrumentationStartedAt'
        ));
    }

    /**
     * Agrupa por Executor usando a mesma regra de Task::executorGroupKey()
     * (papel 'executor' na task_executors, com fallback pro executor_id
     * direto) — consistente com o agrupamento já usado em Filas/Sprint.
     */
    private function groupByExecutor(Collection $tasks): Collection
    {
        return $tasks->groupBy(fn (Task $t) => Task::executorGroupKey($t))
            ->map(function ($group, $key) {
                [, $name] = explode('|', $key, 2);
                return (object) ['name' => $name, 'count' => $group->count()];
            })
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Duração média (dias úteis) que as tarefas passam em cada status, só
     * considerando intervalos já FECHADOS (a tarefa já saiu daquele status) —
     * uma tarefa ainda parada no status atual não entra na média, senão um
     * caso recém-aberto puxaria o número artificialmente pra baixo.
     */
    private function averageTimeInStatus(): Collection
    {
        // TaskStatusTransition não tem organization_id próprio (é escopada via
        // task_id, mesmo padrão de TaskApprovalToken/DeliverableFeedback) — pra
        // não misturar dado de outra organização na média, filtra explicitamente
        // pelos IDs de tarefa que o OrganizationScope de Task já resolveria.
        $orgTaskIds = Task::pluck('id');

        $transitions = TaskStatusTransition::whereIn('task_id', $orgTaskIds)
            ->orderBy('task_id')->orderBy('changed_at')
            ->get(['task_id', 'to_status', 'changed_at']);

        $secondsByStatus = [];

        foreach ($transitions->groupBy('task_id') as $taskTransitions) {
            $sorted = $taskTransitions->values();
            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $curr = $sorted[$i];
                $next = $sorted[$i + 1];
                $secondsByStatus[$curr->to_status][] = BusinessTime::secondsBetween($curr->changed_at, $next->changed_at);
            }
        }

        return collect($secondsByStatus)
            ->map(function ($seconds, $status) {
                $avg = (int) round(array_sum($seconds) / count($seconds));
                return (object) [
                    'label'       => Task::$statuses[$status]['label'] ?? $status,
                    'color'       => Task::$statuses[$status]['color'] ?? 'muted',
                    'avg_seconds' => $avg,
                    'avg_human'   => BusinessTime::humanize($avg),
                    'sample_size' => count($seconds),
                ];
            })
            ->sortByDesc('avg_seconds')
            ->values();
    }
}
