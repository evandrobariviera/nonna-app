<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Painel de Produção — a visão geral da agência, igual pra todo mundo (não é um painel
 * por head). Responde quatro perguntas numa tela só: quanto trabalho está aberto, como
 * ele está distribuído entre as sprints, quanto cada pessoa está carregando e como cada
 * cliente está sendo atendido.
 *
 * Tudo aqui é contado no banco (agregado), nunca carregando as tarefas como model — são
 * ~400 tarefas abertas e a tela lista a carteira inteira. A regra é: nenhuma consulta
 * dentro de laço.
 */
class ProductionPanelController extends Controller
{
    // Status que significam "saiu da mesa de alguém". Concluído e cancelado não contam
    // como carga, mas concluído continua contando na cota do mês (já consumiu produção).
    private const FECHADOS = ['concluido', 'cancelado'];

    public function index(Request $request): View
    {
        $incluirInativos = $request->boolean('inativos');

        $termometro = $this->termometro($incluirInativos);
        $sprints    = $this->porSprint($incluirInativos);
        $pessoas    = $this->porExecutor($incluirInativos);
        $clientes   = $this->porCliente($incluirInativos);
        $tipos      = $this->porTipo($incluirInativos);

        return view('producao.index', compact(
            'termometro', 'sprints', 'pessoas', 'clientes', 'tipos', 'incluirInativos'
        ));
    }

    /**
     * Base de tudo: tarefas abertas. Cliente inativo fica de fora por padrão (mesmo
     * critério de leitura das outras telas), mas tarefa sem cliente continua aparecendo —
     * é trabalho interno que também ocupa alguém.
     */
    private function abertas(bool $incluirInativos): \Illuminate\Database\Eloquent\Builder
    {
        $query = Task::whereNotIn('status', self::FECHADOS);

        if (! $incluirInativos) {
            $query->where(fn ($q) => $q
                ->whereNull('client_id')
                ->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')));
        }

        return $query;
    }

    /** Os números do topo — o que precisa de olho hoje. */
    private function termometro(bool $incluirInativos): array
    {
        $linha = $this->abertas($incluirInativos)
            ->selectRaw('count(*) as abertas')
            ->selectRaw('count(*) filter (where sprint_id is not null) as em_sprint')
            ->selectRaw('count(*) filter (where sprint_id is null) as fora_de_sprint')
            ->selectRaw('count(*) filter (where due_date = current_date) as hoje')
            ->selectRaw('count(*) filter (where due_date > current_date and due_date <= current_date + 7) as semana')
            ->selectRaw('count(*) filter (where due_date < current_date) as atrasadas')
            ->selectRaw('count(*) filter (where due_date is null) as sem_data')
            ->toBase()->first();

        // Sem executor precisa olhar o pivot também — ver porExecutor().
        $semExecutor = $this->abertas($incluirInativos)
            ->whereNull('executor_id')
            ->whereDoesntHave('executors', fn ($q) => $q->where('task_executors.role', 'executor'))
            ->count();

        return [
            'abertas'        => (int) $linha->abertas,
            'em_sprint'      => (int) $linha->em_sprint,
            'fora_de_sprint' => (int) $linha->fora_de_sprint,
            'hoje'           => (int) $linha->hoje,
            'semana'         => (int) $linha->semana,
            'atrasadas'      => (int) $linha->atrasadas,
            'sem_data'       => (int) $linha->sem_data,
            'sem_executor'   => $semExecutor,
        ];
    }

    /**
     * Distribuição entre as sprints: quanto de trabalho aberto está em cada quinzena, mais
     * a Fila (tarefa sem sprint). Só sprints que ainda importam — as fechadas com trabalho
     * aberto dentro entram também, porque sprint fechada com tarefa aberta é justamente o
     * que ninguém está vendo hoje.
     */
    private function porSprint(bool $incluirInativos): array
    {
        $contagem = $this->abertas($incluirInativos)
            ->selectRaw('sprint_id')
            ->selectRaw('count(*) as abertas')
            ->selectRaw('count(*) filter (where due_date < current_date) as atrasadas')
            ->selectRaw("count(*) filter (where status = 'backlog') as nao_iniciadas")
            ->groupBy('sprint_id')
            ->toBase()->get()->keyBy('sprint_id');

        // Total (incluindo fechadas) pra dar a noção de "quanto já foi entregue".
        $totais = Task::selectRaw('sprint_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = 'concluido') as concluidas")
            ->whereNotNull('sprint_id')
            ->groupBy('sprint_id')
            ->toBase()->get()->keyBy('sprint_id');

        $comAberta = $contagem->keys()->filter()->all();

        $sprints = Sprint::where(fn ($q) => $q
                ->whereIn('id', $comAberta)
                ->orWhere('status', '!=', 'closed'))
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'status']);

        $linhas = [];
        $vazias = 0; // sprints futuras ainda sem nada dentro — viram uma linha só

        foreach ($sprints as $sprint) {
            $c = $contagem->get($sprint->id);
            $t = $totais->get($sprint->id);

            if ((int) ($c->abertas ?? 0) === 0 && (int) ($t->total ?? 0) === 0) {
                $vazias++;
                continue;
            }

            $linhas[] = [
                'sprint'        => $sprint,
                'abertas'       => (int) ($c->abertas ?? 0),
                'atrasadas'     => (int) ($c->atrasadas ?? 0),
                'nao_iniciadas' => (int) ($c->nao_iniciadas ?? 0),
                'total'         => (int) ($t->total ?? 0),
                'concluidas'    => (int) ($t->concluidas ?? 0),
            ];
        }

        $fila = $contagem->get(null);

        return [
            'linhas' => $linhas,
            'vazias' => $vazias,
            'fila'   => [
                'abertas'       => (int) ($fila->abertas ?? 0),
                'atrasadas'     => (int) ($fila->atrasadas ?? 0),
                'nao_iniciadas' => (int) ($fila->nao_iniciadas ?? 0),
            ],
            'maior' => max(1, collect($linhas)->max('abertas') ?? 1, (int) ($fila->abertas ?? 0)),
        ];
    }

    /**
     * Carga por executor. Quem é o executor mora em dois lugares (o pivot task_executors
     * com papel "executor" e, como herança, tasks.executor_id) — o COALESCE resolve os
     * dois no mesmo agrupamento, igual ao que a Carga da Sprint faz em PHP.
     */
    private function porExecutor(bool $incluirInativos): array
    {
        $linhas = $this->abertas($incluirInativos)
            ->leftJoin('task_executors', function ($join) {
                $join->on('task_executors.task_id', '=', 'tasks.id')
                     ->where('task_executors.role', '=', 'executor');
            })
            ->selectRaw('coalesce(task_executors.user_id, tasks.executor_id) as pessoa_id')
            ->selectRaw('count(*) as abertas')
            ->selectRaw('count(*) filter (where tasks.due_date < current_date) as atrasadas')
            ->selectRaw('count(*) filter (where tasks.due_date = current_date) as hoje')
            ->selectRaw('count(*) filter (where tasks.due_date > current_date and tasks.due_date <= current_date + 7) as semana')
            ->selectRaw("count(*) filter (where tasks.status = 'em_producao') as em_producao")
            ->groupBy('pessoa_id')
            ->toBase()->get();

        $pessoas = User::whereIn('id', $linhas->pluck('pessoa_id')->filter()->all())
            ->get(['id', 'name', 'avatar_path', 'avatar_disk'])->keyBy('id');

        return [
            'linhas' => $linhas->map(fn ($l) => [
                'pessoa'      => $l->pessoa_id ? $pessoas->get($l->pessoa_id) : null, // null = sem executor
                'abertas'     => (int) $l->abertas,
                'atrasadas'   => (int) $l->atrasadas,
                'hoje'        => (int) $l->hoje,
                'semana'      => (int) $l->semana,
                'em_producao' => (int) $l->em_producao,
            ])->sortByDesc('abertas')->values(),
            'maior' => max(1, (int) $linhas->max('abertas')),
        ];
    }

    /**
     * Uma linha por cliente ativo: quanto está aberto, quanto está atrasado, quanto da cota
     * do mês já foi usada e quão preenchido está o cadastro. É a linha que liga produção a
     * contrato — cliente com cota cheia e fila vazia é sinal de material a pedir.
     */
    private function porCliente(bool $incluirInativos): \Illuminate\Support\Collection
    {
        $carga = $this->abertas($incluirInativos)
            ->whereNotNull('client_id')
            ->selectRaw('client_id')
            ->selectRaw('count(*) as abertas')
            ->selectRaw('count(*) filter (where due_date < current_date) as atrasadas')
            ->selectRaw('count(*) filter (where due_date <= current_date + 7) as semana')
            ->selectRaw('count(*) filter (where sprint_id is null) as na_fila')
            ->groupBy('client_id')
            ->toBase()->get()->keyBy('client_id');

        // Consumo do mês por tipo, pra confrontar com a cota — mesma regra de
        // Client::productionUsage(), mas de uma vez pra carteira toda.
        $consumo = Task::whereNotNull('client_id')
            ->where('status', '!=', 'cancelado')
            ->whereRaw("date_trunc('month', coalesce(approval_date, created_at)) = date_trunc('month', current_date)")
            ->selectRaw('client_id, task_type, count(*) as total')
            ->groupBy('client_id', 'task_type')
            ->toBase()->get()
            ->groupBy('client_id')
            ->map(fn ($linhas) => $linhas->pluck('total', 'task_type'));

        $clientes = Client::query()
            ->when(! $incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive'))
            ->with('creativeLead:id,name,avatar_path,avatar_disk')
            ->orderBy('company_name')
            ->get();

        Client::primeCompleteness($clientes);

        return $clientes->map(function (Client $client) use ($carga, $consumo) {
            $c     = $carga->get($client->id);
            $quota = array_filter($client->production_quota ?? [], fn ($v) => (int) $v > 0);
            $usado = $consumo->get($client->id) ?? collect();

            $cotaTotal = array_sum(array_map('intval', $quota));
            $cotaUsada = 0;
            foreach ($quota as $tipo => $qtd) {
                $cotaUsada += min((int) $qtd, (int) ($usado[$tipo] ?? 0));
            }

            return [
                'client'     => $client,
                'abertas'    => (int) ($c->abertas ?? 0),
                'atrasadas'  => (int) ($c->atrasadas ?? 0),
                'semana'     => (int) ($c->semana ?? 0),
                'na_fila'    => (int) ($c->na_fila ?? 0),
                'cota_total' => $cotaTotal,
                'cota_usada' => $cotaUsada,
                'nota'       => $client->completeness()['score'],
            ];
        })->sortByDesc('abertas')->values();
    }

    /** Mix de produção: que tipo de trabalho a agência está carregando. */
    private function porTipo(bool $incluirInativos): array
    {
        $linhas = $this->abertas($incluirInativos)
            ->selectRaw('task_type, count(*) as abertas')
            ->selectRaw('count(*) filter (where due_date < current_date) as atrasadas')
            ->groupBy('task_type')
            ->toBase()->get()
            ->map(fn ($l) => [
                'tipo'      => $l->task_type,
                'label'     => Task::$types[$l->task_type] ?? ($l->task_type ?: 'Sem tipo'),
                'icone'     => Task::$typeIcons[$l->task_type] ?? 'package',
                'abertas'   => (int) $l->abertas,
                'atrasadas' => (int) $l->atrasadas,
            ])
            ->sortByDesc('abertas')->values();

        return ['linhas' => $linhas, 'maior' => max(1, (int) $linhas->max('abertas'))];
    }
}
