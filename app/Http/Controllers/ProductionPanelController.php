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

    // Filtros globais da tela — moldam TODOS os blocos, não só a tabela.
    private bool $incluirInativos = false;
    private ?string $clienteId = null;
    private int|string|null $executorId = null; // usuário é id inteiro; cliente é uuid

    public function index(Request $request): View
    {
        $this->incluirInativos = $request->boolean('inativos');
        $incluirInativos = $this->incluirInativos;

        // Um link torto (id com formato errado) tem que virar "sem filtro", não erro de
        // tipo do Postgres na cara do usuário — daí o rescue em vez de confiar no valor.
        // Cliente é uuid e usuário é inteiro, então nenhuma checagem de formato única serve.
        $clienteSel  = rescue(fn () => Client::find($request->get('cliente')), null, false);
        $executorSel = rescue(fn () => User::find($request->get('executor')), null, false);

        // Filtro que não resolve pra ninguém vira filtro nenhum, senão a tela zera inteira
        // sem explicar por quê.
        $this->clienteId  = $clienteSel?->id;
        $this->executorId = $executorSel?->id;

        $termometro = $this->termometro();
        $sprints    = $this->porSprint();
        $pessoas    = $this->porExecutor();
        $clientes   = $this->porCliente();
        $tipos      = $this->porTipo();
        $volume     = $this->volumeDoMes($clienteSel);
        $calendario = $this->calendario($request);

        // Listas dos selects do topo — só quem realmente aparece na produção.
        $opcoesClientes = Client::query()
            ->when(! $this->incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('company_name')->get(['id', 'nickname', 'company_name']);
        $opcoesExecutores = User::whereIn('id', $this->idsDeExecutores())
            ->orderBy('name')->get(['id', 'name']);

        return view('producao.index', compact(
            'termometro', 'sprints', 'pessoas', 'clientes', 'tipos', 'volume', 'calendario',
            'incluirInativos', 'clienteSel', 'executorSel', 'opcoesClientes', 'opcoesExecutores'
        ));
    }

    /**
     * Base de tudo: tarefas abertas, já com os filtros globais aplicados. Cliente inativo
     * fica de fora por padrão (mesmo critério de leitura das outras telas), mas tarefa sem
     * cliente continua aparecendo — é trabalho interno que também ocupa alguém.
     */
    private function abertas(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->comFiltros(Task::whereNotIn('status', self::FECHADOS));
    }

    /** Os filtros globais, aplicáveis a qualquer consulta de tarefa da tela. */
    private function comFiltros(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! $this->incluirInativos) {
            $query->where(fn ($q) => $q
                ->whereNull('client_id')
                ->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')));
        }

        if ($this->clienteId) {
            $query->where('client_id', $this->clienteId);
        }

        if ($this->executorId) {
            // Executor mora em dois lugares: o pivot (papel "executor") e, como herança,
            // tasks.executor_id — que só vale quando não há pivot de executor.
            $query->where(fn ($q) => $q
                ->whereHas('executors', fn ($e) => $e
                    ->where('users.id', $this->executorId)
                    ->where('task_executors.role', 'executor'))
                ->orWhere(fn ($o) => $o
                    ->where('executor_id', $this->executorId)
                    ->whereDoesntHave('executors', fn ($e) => $e->where('task_executors.role', 'executor'))));
        }

        return $query;
    }

    /** Quem tem tarefa aberta hoje — alimenta o select de executor. */
    private function idsDeExecutores(): array
    {
        $doPivot = Task::whereNotIn('status', self::FECHADOS)
            ->join('task_executors', function ($join) {
                $join->on('task_executors.task_id', '=', 'tasks.id')
                     ->where('task_executors.role', '=', 'executor');
            })
            ->distinct()->pluck('task_executors.user_id');

        $doCampo = Task::whereNotIn('status', self::FECHADOS)
            ->whereNotNull('executor_id')->distinct()->pluck('executor_id');

        return $doPivot->merge($doCampo)->unique()->filter()->all();
    }

    /** Os números do topo — o que precisa de olho hoje. */
    private function termometro(): array
    {
        $linha = $this->abertas()
            ->selectRaw('count(*) as abertas')
            ->selectRaw('count(*) filter (where sprint_id is not null) as em_sprint')
            ->selectRaw('count(*) filter (where sprint_id is null) as fora_de_sprint')
            ->selectRaw('count(*) filter (where due_date = current_date) as hoje')
            ->selectRaw('count(*) filter (where due_date > current_date and due_date <= current_date + 7) as semana')
            ->selectRaw('count(*) filter (where due_date < current_date) as atrasadas')
            ->selectRaw('count(*) filter (where due_date is null) as sem_data')
            ->toBase()->first();

        // Sem executor precisa olhar o pivot também — ver porExecutor().
        $semExecutor = $this->abertas()
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
    private function porSprint(): array
    {
        $contagem = $this->abertas()
            ->selectRaw('sprint_id')
            ->selectRaw('count(*) as abertas')
            ->selectRaw('count(*) filter (where due_date < current_date) as atrasadas')
            ->selectRaw("count(*) filter (where status = 'backlog') as nao_iniciadas")
            ->groupBy('sprint_id')
            ->toBase()->get()->keyBy('sprint_id');

        // Total (incluindo fechadas) pra dar a noção de "quanto já foi entregue".
        $totais = $this->comFiltros(Task::query())
            ->selectRaw('sprint_id')
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
    private function porExecutor(): array
    {
        $linhas = $this->abertas()
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
    private function porCliente(): \Illuminate\Support\Collection
    {
        $carga = $this->abertas()
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
        $consumo = $this->comFiltros(Task::query())
            ->whereNotNull('client_id')
            ->where('status', '!=', 'cancelado')
            ->whereRaw("date_trunc('month', coalesce(approval_date, created_at)) = date_trunc('month', current_date)")
            ->selectRaw('client_id, task_type, count(*) as total')
            ->groupBy('client_id', 'task_type')
            ->toBase()->get()
            ->groupBy('client_id')
            ->map(fn ($linhas) => $linhas->pluck('total', 'task_type'));

        $clientes = Client::query()
            ->when(! $this->incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($this->clienteId, fn ($q) => $q->where('id', $this->clienteId))
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
    private function porTipo(): array
    {
        $linhas = $this->abertas()
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

    /**
     * Volume combinado × volume produzido no mês — "o que ainda falta entregar pra cumprir
     * o que foi vendido". Com um cliente selecionado mostra o detalhe dele por tipo; sem
     * seleção, soma a carteira inteira pra dar o panorama da agência.
     *
     * Só entra na conta o cliente que tem cota configurada pra aquele tipo — senão o
     * produzido inflaria com trabalho de quem não tem volume combinado e o painel mentiria.
     *
     * "Produzido" segue a mesma régua de Client::productionUsage(): tudo que está planejado
     * pro mês (pela data de aprovação), menos o cancelado. Concluído conta, porque já
     * consumiu o volume do mês.
     */
    private function volumeDoMes(?Client $clienteSel): array
    {
        $clientes = Client::query()
            ->when(! $this->incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($this->clienteId, fn ($q) => $q->where('id', $this->clienteId))
            ->get(['id', 'nickname', 'company_name', 'production_quota']);

        // [tipo => cota] somado, e quais clientes têm cota de cada tipo
        $cotaPorTipo    = [];
        $clientesDoTipo = [];
        foreach ($clientes as $c) {
            foreach (array_filter($c->production_quota ?? [], fn ($v) => (int) $v > 0) as $tipo => $qtd) {
                $cotaPorTipo[$tipo]      = ($cotaPorTipo[$tipo] ?? 0) + (int) $qtd;
                $clientesDoTipo[$tipo][] = $c->id;
            }
        }

        if (! $cotaPorTipo) {
            return ['configurado' => false, 'sem_cota' => $clientes->count(), 'cliente' => $clienteSel];
        }

        $produzido = $this->comFiltros(Task::query())
            ->where('status', '!=', 'cancelado')
            ->whereRaw("date_trunc('month', coalesce(approval_date, created_at)) = date_trunc('month', current_date)")
            ->selectRaw('client_id, task_type')
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = 'concluido') as entregues")
            ->groupBy('client_id', 'task_type')
            ->toBase()->get();

        $linhas = [];
        foreach ($cotaPorTipo as $tipo => $cota) {
            $ids  = $clientesDoTipo[$tipo];
            $doTipo = $produzido->where('task_type', $tipo)->whereIn('client_id', $ids);

            $planejado = (int) $doTipo->sum('total');
            $entregue  = (int) $doTipo->sum('entregues');

            $linhas[] = [
                'tipo'      => $tipo,
                'label'     => Task::$types[$tipo] ?? $tipo,
                'icone'     => Task::$typeIcons[$tipo] ?? 'package',
                'cota'      => (int) $cota,
                'planejado' => $planejado,
                'entregue'  => $entregue,
                'falta'     => max(0, (int) $cota - $planejado), // ainda nem foi pedido
                'excedente' => max(0, $planejado - (int) $cota),
                'clientes'  => count($ids),
            ];
        }

        usort($linhas, fn ($a, $b) => $b['cota'] <=> $a['cota']);

        $cota      = array_sum(array_column($linhas, 'cota'));
        $planejado = array_sum(array_column($linhas, 'planejado'));
        $entregue  = array_sum(array_column($linhas, 'entregue'));

        // O que falta é a soma dos buracos tipo a tipo, nunca a diferença dos totais:
        // sobra de criação não preenche vaga de tráfego, e comparar só os totais faria a
        // tela dizer "está tudo certo" com um tipo inteiro zerado.
        $falta     = array_sum(array_column($linhas, 'falta'));
        $excedente = array_sum(array_column($linhas, 'excedente'));

        // Pra barra, o que conta é o quanto de cada cota foi de fato coberto (nada de um
        // tipo estourado empurrar a barra pra 100%).
        $naCota       = array_sum(array_map(fn ($l) => min($l['planejado'], $l['cota']), $linhas));
        $entregueNaCota = array_sum(array_map(fn ($l) => min($l['entregue'], $l['cota']), $linhas));

        return [
            'configurado' => true,
            'cliente'     => $clienteSel,
            'linhas'      => $linhas,
            'cota'        => $cota,
            'planejado'   => $planejado,
            'entregue'    => $entregue,
            'falta'       => $falta,
            'excedente'   => $excedente,
            'pct_plan'    => $cota > 0 ? min(100, (int) round($naCota / $cota * 100)) : 0,
            'pct_entr'    => $cota > 0 ? min(100, (int) round($entregueNaCota / $cota * 100)) : 0,
            'com_cota'    => $clientes->filter(fn ($c) => array_filter($c->production_quota ?? [], fn ($v) => (int) $v > 0))->count(),
            'sem_cota'    => $clientes->filter(fn ($c) => ! array_filter($c->production_quota ?? [], fn ($v) => (int) $v > 0))->count(),
            'dias_restantes' => now()->daysInMonth - now()->day,
        ];
    }

    /**
     * Calendário do mês — o que cai em cada dia. A data usada é escolhida na tela: entrega
     * (due_date, "quando tem que estar pronto") ou aprovação (approval_date, a data que
     * define a sprint e o volume do mês). São perguntas diferentes e o time usa as duas.
     */
    private function calendario(Request $request): array
    {
        $campo = $request->get('cal_data') === 'aprovacao' ? 'approval_date' : 'due_date';

        // Sem o formato exato aaaa-mm o Carbon aceita quase qualquer coisa e devolve uma
        // data sem sentido (um "-01" sozinho vira 1969) — melhor cair no mês corrente.
        $pedido = (string) $request->get('cal_mes');
        $mes    = preg_match('/^\d{4}-\d{2}$/', $pedido)
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $pedido . '-01')->startOfMonth()
            : now()->startOfMonth();

        $inicio = $mes->copy()->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $fim    = $mes->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);

        $tarefas = $this->comFiltros(Task::query())
            ->where('status', '!=', 'cancelado')
            ->whereBetween($campo, [$inicio->toDateString(), $fim->toDateString()])
            ->with(['client:id,nickname,company_name'])
            ->orderBy($campo)
            ->get(['id', 'title', 'status', 'task_type', 'client_id', 'due_date', 'approval_date'])
            ->groupBy(fn ($t) => $t->{$campo}->toDateString());

        $dias = [];
        for ($d = $inicio->copy(); $d->lte($fim); $d->addDay()) {
            $chave = $d->toDateString();
            $dias[] = [
                'data'    => $d->copy(),
                'do_mes'  => $d->month === $mes->month,
                'hoje'    => $d->isToday(),
                'tarefas' => $tarefas->get($chave) ?? collect(),
            ];
        }

        return [
            'campo'    => $campo === 'approval_date' ? 'aprovacao' : 'entrega',
            'mes'      => $mes,
            'anterior' => $mes->copy()->subMonth()->format('Y-m'),
            'proximo'  => $mes->copy()->addMonth()->format('Y-m'),
            'dias'     => $dias,
            'total'    => $tarefas->flatten()->count(),
        ];
    }
}
