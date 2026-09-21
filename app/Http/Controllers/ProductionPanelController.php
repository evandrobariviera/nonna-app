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
    private int|string|null $direcaoCriativaId = null; // idem — filtra por Client::creative_lead_id

    public function index(Request $request): View
    {
        [$clienteSel, $executorSel, $direcaoSel] = $this->resolverFiltrosGlobais($request);
        $incluirInativos = $this->incluirInativos;

        $termometro = $this->termometro();
        $sprints    = $this->porSprint();
        $pessoas    = $this->porExecutor();
        $clientes   = $this->porCliente();
        $tipos      = $this->porTipo();
        $volume     = $this->volumeDoMes($clienteSel);
        $semana     = $this->semanaKanban($request);

        // Listas dos selects do topo — só quem realmente aparece na produção.
        $opcoesClientes = Client::query()
            ->when(! $this->incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('company_name')->get(['id', 'nickname', 'company_name']);
        $opcoesExecutores = User::whereIn('id', $this->idsDeExecutores())
            ->orderBy('name')->get(['id', 'name']);
        $opcoesDirecaoCriativa = User::whereIn('id', Client::whereNotNull('creative_lead_id')
            ->distinct()->pluck('creative_lead_id'))
            ->orderBy('name')->get(['id', 'name']);

        return view('producao.index', compact(
            'termometro', 'sprints', 'pessoas', 'clientes', 'tipos', 'volume', 'semana',
            'incluirInativos', 'clienteSel', 'executorSel', 'direcaoSel',
            'opcoesClientes', 'opcoesExecutores', 'opcoesDirecaoCriativa'
        ));
    }

    /** Fragmento AJAX da semana — mesmo padrão de results() (live-filter.js). */
    public function weekResults(Request $request): View
    {
        $this->resolverFiltrosGlobais($request);
        $semana = $this->semanaKanban($request);

        return view('producao._week-results', $semana);
    }

    /**
     * Lê cliente/executor/inativos da querystring pra dentro das propriedades da instância.
     * Chamado tanto por index() (primeira carga) quanto por weekResults() (refresh AJAX da
     * semana) — os dois precisam do MESMO recorte, senão arrastar um card na semana afetaria
     * um conjunto de tarefas diferente do que a tabela/termômetro estão mostrando.
     */
    private function resolverFiltrosGlobais(Request $request): array
    {
        $this->incluirInativos = $request->boolean('inativos');

        // Um link torto (id com formato errado) tem que virar "sem filtro", não erro de
        // tipo do Postgres na cara do usuário — daí o rescue em vez de confiar no valor.
        // Cliente é uuid e usuário é inteiro, então nenhuma checagem de formato única serve.
        $clienteSel  = rescue(fn () => Client::find($request->get('cliente')), null, false);
        $executorSel = rescue(fn () => User::find($request->get('executor')), null, false);
        $direcaoSel  = rescue(fn () => User::find($request->get('direcao_criativa')), null, false);

        // Filtro que não resolve pra ninguém vira filtro nenhum, senão a tela zera inteira
        // sem explicar por quê.
        $this->clienteId         = $clienteSel?->id;
        $this->executorId        = $executorSel?->id;
        $this->direcaoCriativaId = $direcaoSel?->id;

        return [$clienteSel, $executorSel, $direcaoSel];
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

        if ($this->direcaoCriativaId) {
            // Direção criativa é do cliente, não da tarefa — tarefa interna (sem client_id)
            // não pertence a ninguém aqui, então some do recorte quando esse filtro está ativo.
            $query->whereHas('client', fn ($c) => $c->where('creative_lead_id', $this->direcaoCriativaId));
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

    /** Os mesmos filtros de cliente/direção criativa, mas pra consultas que partem do
     *  próprio Client (porCliente(), volumeDoMes()) em vez de Task. */
    private function comFiltrosDeCliente(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->when($this->clienteId, fn ($q) => $q->where('id', $this->clienteId))
            ->when($this->direcaoCriativaId, fn ($q) => $q->where('creative_lead_id', $this->direcaoCriativaId));
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

        $clientes = $this->comFiltrosDeCliente(Client::query()
            ->when(! $this->incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive')))
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
        $clientes = $this->comFiltrosDeCliente(Client::query()
            ->when(! $this->incluirInativos, fn ($q) => $q->where('status', '!=', 'inactive')))
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
     * Semana de produção: kanban com uma coluna por dia útil (segunda a sexta), cada card na
     * coluna da sua data de APROVAÇÃO — a mesma data que define sprint e volume do mês, e o
     * mesmo recorte da aba "Semana" da Sprint (que o Evandro pediu explicitamente pra
     * reaproveitar aqui). Arrastar um card muda a data de aprovação na hora.
     *
     * Ao contrário do antigo calendário mensal (que cortava em 3 cards por dia + "+N no
     * dia"), aqui TODA tarefa aberta aparece — é justamente o problema que essa tela resolve.
     * Isso é viável porque a janela é uma semana, não um mês inteiro: mesmo com a agência
     * inteira aberta (~390 tarefas), o total continua pequeno pra carregar como model.
     *
     * "Semana anterior" reúne toda tarefa aberta com aprovação antes da segunda em exibição —
     * é o que precisa de decisão (empurrar pra uma data real). Tarefa sem data de aprovação,
     * ou com data muito à frente, fica de fora do quadro e só entra na contagem informativa.
     */
    private function semanaKanban(Request $request): array
    {
        $weekOffset = (int) $request->get('week_offset', 0);
        $monday = now()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addWeeks($weekOffset);

        $weekDays = collect();
        for ($d = $monday->copy(); $d->lte($monday->copy()->addDays(4)); $d->addDay()) {
            $weekDays->push($d->copy());
        }

        // Card reduzido de propósito (sem miniatura, sem ícone de tipo — ver a view): com
        // ~400+ tarefas na tela, carregar imagem por card é o que mais pesava o navegador.
        // Só o essencial pra decidir "pra quando mover isso" entra aqui, então o eager load
        // também fica mais magro — nem 'attachments' nem relações de Projeto/Planejamento
        // entram, já que o card não mostra nenhum dos dois (só Sprint × Fila).
        $tasks = $this->abertas()
            ->with(['client', 'executor', 'executors'])
            ->get();

        $weekDateStrings = $weekDays->map->toDateString();
        $tasksBeforeWeek = $tasks->filter(fn ($t) => $t->approval_date && $t->approval_date->lt($monday));
        $tasksInWeek     = $tasks->filter(fn ($t) => $t->approval_date && $weekDateStrings->contains($t->approval_date->toDateString()));
        $grouped         = $tasksInWeek->groupBy(fn ($t) => $t->approval_date->toDateString());

        // Mesma ordem da aba Semana da Sprint: prioridade primeiro, Ajuste/Alteração antes de
        // Backlog dentro da mesma prioridade, resto na ordem de Task::$statuses.
        $priorityOrder = array_flip(array_keys(Task::$priorities));
        $statusOrder   = array_flip(array_unique(array_merge(['ajuste_alteracao', 'backlog'], array_keys(Task::$statuses))));
        $sortColumn = fn ($colTasks) => $colTasks
            ->sortBy(fn ($t) => ($priorityOrder[$t->priority ?? 'normal'] ?? count($priorityOrder)) * 100
                + ($statusOrder[$t->status] ?? count($statusOrder)))
            ->values();

        $weekKanban = ['atrasadas' => $sortColumn($tasksBeforeWeek)];
        foreach ($weekDays as $day) {
            $weekKanban[$day->toDateString()] = $sortColumn($grouped->get($day->toDateString()) ?? collect());
        }

        $semData = $tasks->filter(fn ($t) => ! $t->approval_date)->count();
        $depois  = $tasks->count() - $tasksInWeek->count() - $tasksBeforeWeek->count() - $semData;

        return [
            'weekDays'       => $weekDays,
            'weekKanban'     => $weekKanban,
            'beforeWeekDate' => $monday->copy()->subDay(),
            'weekOffset'     => $weekOffset,
            'semDataCount'   => $semData,
            'depoisCount'    => $depois,
        ];
    }
}
