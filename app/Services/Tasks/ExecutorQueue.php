<?php

namespace App\Services\Tasks;

use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * A FILA DO EXECUTOR — fonte única do "o que está na mão de quem executa". Pedido do Evandro
 * (2026-10-13): o Head tem que ver exatamente o que o executor vê, com a mesma noção de
 * prioridade. Por isso Execução (quadro + "Atrasadas comigo"), "Seu foco" (AttentionDigest)
 * e a grade da Distribuição montam a fila daqui — mudou a regra, muda nas três.
 *
 *  - Executor: pivot task_executors (papel executor); tasks.executor_id só vale quando a
 *    tarefa não tem executor no pivot (herança do ClickUp).
 *  - Fila: sprint ativa; Backlog pronto pra produzir (sem esperar insumo), Em Produção e
 *    Ajuste/Alteração; cliente inativo fica de fora (regra de leitura do app).
 *  - Esperando insumo (copy / informação do cliente) NÃO está na fila do executor — é
 *    contexto do Head (a grade da Distribuição liga isso à parte).
 *  - Atrasada = data de aprovação ANTES de hoje. Vence hoje não é atrasada.
 */
class ExecutorQueue
{
    public const STATUSES = ['backlog', 'em_producao', 'ajuste_alteracao'];

    // Situações de Backlog que ainda dependem de alguém antes de dar pra produzir.
    public const WAITING_INPUT = ['Pendente de Informações', 'Aguardando Copy / Redação'];

    /** Tarefas em que $userIds executam (uma pessoa ou várias). */
    public static function executedBy(Builder $query, int|array $userIds): Builder
    {
        $ids = (array) $userIds;

        return $query->where(fn ($q) => $q
            ->whereHas('executors', fn ($e) => $e->whereIn('users.id', $ids)->where('task_executors.role', 'executor'))
            ->orWhere(fn ($o) => $o
                ->whereIn('executor_id', $ids)
                ->whereDoesntHave('executors', fn ($e) => $e->where('task_executors.role', 'executor'))));
    }

    /** Backlog esperando insumo fica de fora; os outros status passam direto. */
    public static function withoutWaitingInput(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('status', '!=', 'backlog')
            ->orWhereNull('situation')
            ->orWhereNotIn('situation', self::WAITING_INPUT));
    }

    /** Só o Backlog esperando insumo (contexto do Head). */
    public static function onlyWaitingInput(Builder $query): Builder
    {
        return $query->where('status', 'backlog')->whereIn('situation', self::WAITING_INPUT);
    }

    /** Cliente inativo some (tarefa interna, sem cliente, fica). */
    public static function activeClients(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNull('client_id')
            ->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')));
    }

    public static function activeSprint(): ?Sprint
    {
        return Sprint::where('status', 'active')->first();
    }

    /** A fila de $userIds: sprint ativa, status do executor, sem esperar insumo. */
    public static function query(int|array $userIds, ?Sprint $sprint = null): Builder
    {
        $sprint ??= self::activeSprint();

        return self::withoutWaitingInput(
            self::activeClients(self::executedBy(Task::whereIn('status', self::STATUSES), $userIds))
                ->when($sprint, fn ($q) => $q->where('sprint_id', $sprint->id))
        );
    }

    /** Atrasada: data de aprovação antes de hoje (hoje ainda não é atraso). */
    public static function isOverdue(Task $task): bool
    {
        return $task->approval_date !== null
            && $task->approval_date->lt(today())
            && ! in_array($task->status, ['concluido', 'cancelado'], true);
    }
}
