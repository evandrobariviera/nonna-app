<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskApprovalRound;
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

        // Transições pra "concluido" no período — usa a última, se uma tarefa
        // foi concluída mais de uma vez no recorte (reaberta e concluída de novo).
        $completedTransitions = TaskStatusTransition::whereIn('task_id', Task::pluck('id'))
            ->where('to_status', 'concluido')
            ->where('changed_at', '>=', $since)
            ->get(['task_id', 'changed_at'])
            ->sortBy('changed_at');

        $completedAtByTask = $completedTransitions->keyBy('task_id')->map(fn ($t) => $t->changed_at);

        $completedTasks = Task::whereIn('id', $completedAtByTask->keys())
            ->with(['client', 'executor', 'executors'])
            ->get();

        // ── Quem entrega mais (Executor) ──
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

        // ── Carga de trabalho atual (WIP), por executor — estado atual ──
        $wipByExecutor = $this->groupByExecutor(
            Task::whereNotIn('status', ['concluido', 'cancelado'])
                ->with(['executor', 'executors'])
                ->get()
        );

        // ── Tempo médio em cada status, em dias úteis (todo o histórico disponível) ──
        $avgByStatus = $this->averageTimeInStatus();
        $instrumentationStartedAt = TaskStatusTransition::oldest('changed_at')->value('changed_at');

        // ── Taxa de retrabalho (passou por "Ajuste / Alteração" pelo menos 1x) ──
        $reworkRate = $this->reworkRate($completedTasks);

        // ── Cumprimento de prazo (das concluídas no período, quantas foram no prazo) ──
        $onTimeRate = $this->onTimeRate($completedTasks, $completedAtByTask);

        // ── Mix de origem (quanto do que foi entregue veio de Ticket avulso vs planejado) ──
        $originMix = $this->originMix($completedTasks);

        // ── Saúde da aprovação (rodadas resolvidas no período) ──
        $approvalRounds = TaskApprovalRound::whereIn('status', ['approved', 'changes_requested'])
            ->where('resolved_at', '>=', $since)
            ->with('task.client')
            ->get();
        $approvalOverall = $this->approvalOverallStats($approvalRounds);
        $approvalByClient = $this->approvalHealthByClient($approvalRounds);

        // ── Matriz Tipo de Tarefa × Executor ──
        $typeExecutorMatrix = $this->typeExecutorMatrix($completedTasks);

        // ── Tendência semanal (últimas 8 semanas, concluídas) ──
        $weeklyTrend = $this->weeklyTrend();

        return view('productivity.index', [
            'period'                   => $period,
            'byExecutor'               => $byExecutor,
            'byType'                   => $byType,
            'byClient'                 => $byClient,
            'overdueByExecutor'        => $overdueByExecutor,
            'wipByExecutor'            => $wipByExecutor,
            'avgByStatus'              => $avgByStatus,
            'instrumentationStartedAt' => $instrumentationStartedAt,
            'reworkRate'               => $reworkRate,
            'onTimeRate'               => $onTimeRate,
            'originMix'                => $originMix,
            'approvalOverall'          => $approvalOverall,
            'approvalByClient'         => $approvalByClient,
            'typeExecutorMatrix'       => $typeExecutorMatrix,
            'weeklyTrend'              => $weeklyTrend,
        ]);
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

    /**
     * "Ajuste / Alteração" é o status que o próprio fluxo usa pra sinalizar
     * que algo precisou voltar (revisão interna ou cliente pediu ajuste) — em
     * vez de inferir "andou pra trás" a partir da ordem do array de status
     * (frágil, não é um DAG garantido), uso esse status como o sinal direto
     * de retrabalho.
     */
    private function reworkRate(Collection $completedTasks): array
    {
        $taskIds = $completedTasks->pluck('id');

        $reworkedIds = TaskStatusTransition::whereIn('task_id', $taskIds)
            ->where('to_status', 'ajuste_alteracao')
            ->distinct()
            ->pluck('task_id');

        $total = $completedTasks->count();
        $reworkedCount = $taskIds->intersect($reworkedIds)->count();

        $byExecutor = $completedTasks->groupBy(fn (Task $t) => Task::executorGroupKey($t))
            ->map(function ($group, $key) use ($reworkedIds) {
                [, $name] = explode('|', $key, 2);
                $groupTotal = $group->count();
                $groupReworked = $group->filter(fn ($t) => $reworkedIds->contains($t->id))->count();

                return (object) [
                    'name'        => $name,
                    'total'       => $groupTotal,
                    'rework_rate' => $groupTotal > 0 ? round($groupReworked / $groupTotal * 100) : 0,
                ];
            })
            ->sortByDesc('rework_rate')
            ->values();

        return [
            'overall_rate' => $total > 0 ? round($reworkedCount / $total * 100) : null,
            'total'        => $total,
            'by_executor'  => $byExecutor,
        ];
    }

    /**
     * Das tarefas concluídas no período que tinham prazo definido, quantas
     * foram entregues até a data de vencimento. Tarefas sem due_date ficam de
     * fora (não dá pra julgar SLA sem prazo).
     */
    private function onTimeRate(Collection $completedTasks, Collection $completedAtByTask): array
    {
        $withDueDate = $completedTasks->filter(fn (Task $t) => $t->due_date !== null);

        $onTime = $withDueDate->filter(function (Task $t) use ($completedAtByTask) {
            $completedAt = $completedAtByTask->get($t->id);
            return $completedAt && $completedAt->toDateString() <= $t->due_date->toDateString();
        });

        $total = $withDueDate->count();

        return [
            'total'   => $total,
            'on_time' => $onTime->count(),
            'rate'    => $total > 0 ? round($onTime->count() / $total * 100) : null,
        ];
    }

    /**
     * Quanto do volume entregue veio de chamado avulso (Ticket) vs trabalho
     * planejado (Onboarding/Projeto/Roadmap) — pergunta clássica de gestão:
     * "estamos sendo reativos demais?".
     */
    private function originMix(Collection $completedTasks): Collection
    {
        $total = $completedTasks->count();

        return $completedTasks->groupBy(fn (Task $t) => $t->origin ?: '')
            ->map(fn ($group, $origin) => (object) [
                'label' => Task::$origins[$origin] ?? ($origin ?: 'Sem origem'),
                'count' => $group->count(),
                'pct'   => $total > 0 ? round($group->count() / $total * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values();
    }

    private function approvalOverallStats(Collection $rounds): object
    {
        $total = $rounds->count();
        $firstTime = $rounds->filter(fn ($r) => $r->round_number === 1 && $r->status === 'approved')->count();

        $responseSeconds = $rounds->filter(fn ($r) => $r->sent_at && $r->resolved_at)
            ->map(fn ($r) => BusinessTime::secondsBetween($r->sent_at, $r->resolved_at));

        return (object) [
            'total'           => $total,
            'first_time_rate' => $total > 0 ? round($firstTime / $total * 100) : null,
            'avg_response'    => $responseSeconds->isNotEmpty()
                ? BusinessTime::humanize((int) round($responseSeconds->avg()))
                : null,
        ];
    }

    /**
     * % de rodadas aprovadas de primeira (sem pedido de ajuste) e tempo médio
     * que o CLIENTE demora pra responder (sent_at → resolved_at) — separado
     * de propósito do tempo interno de produção, pra não confundir "nossa
     * lentidão" com "lentidão do cliente pra aprovar".
     */
    private function approvalHealthByClient(Collection $rounds): Collection
    {
        return $rounds->groupBy(fn ($r) => $r->task->client_id)
            ->filter(fn ($group) => $group->first()->task->client !== null)
            ->map(function ($group) {
                $client = $group->first()->task->client;
                $total = $group->count();
                $firstTime = $group->filter(fn ($r) => $r->round_number === 1 && $r->status === 'approved')->count();

                $responseSeconds = $group->filter(fn ($r) => $r->sent_at && $r->resolved_at)
                    ->map(fn ($r) => BusinessTime::secondsBetween($r->sent_at, $r->resolved_at));

                return (object) [
                    'client'          => $client,
                    'total'           => $total,
                    'first_time_rate' => $total > 0 ? round($firstTime / $total * 100) : 0,
                    'avg_response'    => $responseSeconds->isNotEmpty()
                        ? BusinessTime::humanize((int) round($responseSeconds->avg()))
                        : null,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Cruza tipo de tarefa × executor pra mostrar especialização real (ex:
     * só uma pessoa cobrindo um tipo inteiro = risco de gargalo). Limita a
     * 8 executores (maior volume primeiro) pra não estourar a tabela.
     */
    private function typeExecutorMatrix(Collection $completedTasks): array
    {
        $totals = $completedTasks->groupBy(fn (Task $t) => Task::executorGroupKey($t))
            ->map(fn ($group, $key) => (object) [
                'key'   => $key,
                'name'  => explode('|', $key, 2)[1],
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(8);

        $executorKeys = $totals->pluck('key');
        $executorNames = $totals->pluck('name');

        $rows = $completedTasks->groupBy('task_type')
            ->map(function ($tasksOfType, $type) use ($executorKeys) {
                $cells = [];
                foreach ($executorKeys as $key) {
                    $cells[$key] = $tasksOfType->filter(fn (Task $t) => Task::executorGroupKey($t) === $key)->count();
                }

                return (object) [
                    'label' => Task::$types[$type] ?? ($type ?: 'Sem tipo'),
                    'cells' => $cells,
                    'total' => $tasksOfType->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return [
            'executor_keys'  => $executorKeys,
            'executor_names' => $executorNames,
            'rows'           => $rows,
        ];
    }

    /**
     * Throughput semanal das últimas 8 semanas — janela fixa, independente do
     * filtro de período do resto do painel, pra dar visão de tendência.
     */
    private function weeklyTrend(): Collection
    {
        $weeksBack = 8;
        $start = now()->subWeeks($weeksBack - 1)->startOfWeek();

        $transitions = TaskStatusTransition::whereIn('task_id', Task::pluck('id'))
            ->where('to_status', 'concluido')
            ->where('changed_at', '>=', $start)
            ->get(['changed_at']);

        $counts = $transitions->groupBy(fn ($t) => $t->changed_at->startOfWeek()->toDateString());

        $weeks = collect();
        for ($i = 0; $i < $weeksBack; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weeks->push((object) [
                'label' => $weekStart->format('d/m'),
                'count' => $counts->get($weekStart->toDateString())?->count() ?? 0,
            ]);
        }

        return $weeks;
    }
}
