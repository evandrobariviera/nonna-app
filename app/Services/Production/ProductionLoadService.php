<?php

namespace App\Services\Production;

use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carga de Produção — quanto foi executado (e está em execução) por cliente, período,
 * executor, responsável e tipo de tarefa. É o "histórico com período"; o Painel de
 * Produção continua sendo o "retrato de agora".
 *
 * Regras (combinadas com o Evandro, 2026-09-30):
 *  - "Executada" = concluída DENTRO do período, pela data real da conclusão (última
 *    transição pra "concluido" em task_status_transitions — confiável desde 29/07/2026).
 *    Tarefa reaberta depois não conta: exige status atual = concluido.
 *  - Tarefa com duas pessoas conta +1 pra cada uma; o total do cliente conta 1.
 *  - Mede quantidade de tarefas, não esforço (tarefa não tem campo de tamanho).
 */
class ProductionLoadService
{
    public const FECHADOS = ['concluido', 'cancelado'];

    public const PERIODOS = [
        'mes_atual' => 'Este mês',
        'mes_passado' => 'Mês passado',
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        'custom' => 'Datas livres',
    ];

    // Primeiro dia com histórico de conclusão confiável (antes disso, tarefa veio do ClickUp sem data).
    public const HISTORICO_DESDE = '2026-07-29';

    public array $filtros = [];

    public Carbon $inicio;

    public Carbon $fim;

    public function fromRequest(Request $request): self
    {
        $periodo = array_key_exists($request->get('periodo'), self::PERIODOS) ? $request->get('periodo') : 'mes_atual';

        [$this->inicio, $this->fim] = match ($periodo) {
            'mes_passado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            '30' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            '90' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            'custom' => $this->datasLivres($request),
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        // Id torto na querystring vira "sem filtro", nunca erro de tipo do Postgres.
        $this->filtros = [
            'periodo' => $periodo,
            'cliente' => rescue(fn () => Client::find($request->get('cliente'))?->id, null, false),
            'executor' => rescue(fn () => User::find($request->get('executor'))?->id, null, false),
            'responsavel' => rescue(fn () => User::find($request->get('responsavel'))?->id, null, false),
            'tipo' => array_key_exists($request->get('tipo'), Task::$types) ? $request->get('tipo') : null,
            'inativos' => $request->boolean('inativos'),
        ];

        return $this;
    }

    private function datasLivres(Request $request): array
    {
        $inicio = rescue(fn () => Carbon::parse($request->get('inicio'))->startOfDay(), null, false) ?? now()->startOfMonth();
        $fim = rescue(fn () => Carbon::parse($request->get('fim'))->endOfDay(), null, false) ?? now()->endOfDay();

        return $inicio->lte($fim) ? [$inicio, $fim] : [$fim->copy()->startOfDay(), $inicio->copy()->endOfDay()];
    }

    /** Filtros de cliente/pessoa/tipo — valem pra tudo; o período cada consulta aplica do seu jeito. */
    private function base(): Builder
    {
        $f = $this->filtros;
        $q = Task::query()->where('tasks.status', '!=', 'cancelado');

        if (! $f['inativos']) {
            $q->where(fn ($w) => $w->whereNull('tasks.client_id')
                ->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')));
        }
        if ($f['cliente']) {
            $q->where('tasks.client_id', $f['cliente']);
        }
        if ($f['tipo']) {
            $q->where('tasks.task_type', $f['tipo']);
        }
        if ($f['executor']) {
            // Mesma regra do Painel de Produção: pivot "executor", ou tasks.executor_id
            // (herança) quando a tarefa não tem executor no pivot.
            $q->where(fn ($w) => $w
                ->whereHas('executors', fn ($e) => $e->where('users.id', $f['executor'])->where('task_executors.role', 'executor'))
                ->orWhere(fn ($o) => $o->where('tasks.executor_id', $f['executor'])
                    ->whereDoesntHave('executors', fn ($e) => $e->where('task_executors.role', 'executor'))));
        }
        if ($f['responsavel']) {
            $q->whereHas('executors', fn ($e) => $e->where('users.id', $f['responsavel'])->where('task_executors.role', 'responsavel'));
        }

        return $q;
    }

    public function executadas(): Collection
    {
        $conclusoes = DB::connection('pgsql')->table('task_status_transitions')
            ->select('task_id', DB::raw('max(changed_at) as concluida_em'))
            ->where('to_status', 'concluido')
            ->groupBy('task_id');

        return $this->base()
            ->where('tasks.status', 'concluido')
            ->joinSub($conclusoes, 'c', 'c.task_id', '=', 'tasks.id')
            ->whereBetween('c.concluida_em', [$this->inicio, $this->fim])
            ->select('tasks.*', 'c.concluida_em')
            ->with(['client:id,company_name,nickname,production_quota', 'executor:id,name', 'executors:id,name'])
            ->orderByDesc('c.concluida_em')
            ->get();
    }

    public function emExecucao(): Collection
    {
        return $this->base()
            ->whereNotIn('tasks.status', self::FECHADOS)
            ->with(['client:id,company_name,nickname,production_quota', 'executor:id,name', 'executors:id,name'])
            ->orderBy('tasks.due_date')
            ->get();
    }

    public function pedidasPorCliente(): Collection
    {
        return $this->base()
            ->whereBetween('tasks.created_at', [$this->inicio, $this->fim])
            ->selectRaw('tasks.client_id, count(*) as n')
            ->groupBy('tasks.client_id')
            ->toBase()->get()
            ->pluck('n', 'client_id');
    }

    /** Quem executou: pivot "executor"; sem pivot, o executor_id herdado. */
    public static function executoresDe(Task $t): Collection
    {
        $pivot = $t->executors->filter(fn ($u) => $u->pivot->role === 'executor');

        return $pivot->isNotEmpty() ? $pivot->values() : collect([$t->executor])->filter();
    }

    public static function responsaveisDe(Task $t): Collection
    {
        return $t->executors->filter(fn ($u) => $u->pivot->role === 'responsavel')->values();
    }

    /** Período é exatamente um mês do calendário? Só aí dá pra comparar com o volume mensal combinado. */
    public function mesInteiro(): bool
    {
        return $this->inicio->isSameDay($this->inicio->copy()->startOfMonth())
            && $this->fim->isSameDay($this->inicio->copy()->endOfMonth());
    }

    public function build(): array
    {
        $executadas = $this->executadas();
        $abertas = $this->emExecucao();
        $pedidas = $this->pedidasPorCliente();

        $comPrazo = $executadas->filter(fn (Task $t) => $t->due_date);
        $noPrazo = $comPrazo->filter(fn (Task $t) => Carbon::parse($t->concluida_em)->toDateString() <= $t->due_date->toDateString());

        return [
            'resumo' => [
                'executadas' => $executadas->count(),
                'em_execucao' => $abertas->count(),
                'pedidas' => (int) $pedidas->sum(),
                'no_prazo_pct' => $comPrazo->count() ? (int) round($noPrazo->count() / $comPrazo->count() * 100) : null,
                'com_prazo' => $comPrazo->count(),
            ],
            'matriz' => $this->matriz($executadas, $abertas, $pedidas),
            'pessoas' => $this->pessoas($executadas, $abertas),
            'executadas' => $executadas,
            'abertas' => $abertas,
        ];
    }

    /** Cliente × tipo: quanto de cada tipo cada cliente consumiu no período. */
    private function matriz(Collection $executadas, Collection $abertas, Collection $pedidas): array
    {
        $mesInteiro = $this->mesInteiro();
        $tipos = collect(array_keys(Task::$types))
            ->filter(fn ($k) => $executadas->contains('task_type', $k) || $abertas->contains('task_type', $k))
            ->values();
        $semTipo = $executadas->contains(fn ($t) => ! $t->task_type) || $abertas->contains(fn ($t) => ! $t->task_type);

        $chaves = $executadas->pluck('client_id')->merge($abertas->pluck('client_id'))->merge($pedidas->keys())->unique();
        $clientes = Client::whereIn('id', $chaves->filter()->all())->get(['id', 'company_name', 'nickname', 'production_quota'])->keyBy('id');

        $linhas = $chaves->map(function ($clientId) use ($executadas, $abertas, $pedidas, $tipos, $clientes, $mesInteiro, $semTipo) {
            $cliente = $clientId ? $clientes->get($clientId) : null;
            $doCliente = $executadas->where('client_id', $clientId);
            $cota = $mesInteiro ? ($cliente?->production_quota ?? []) : [];

            $celulas = $tipos->mapWithKeys(fn ($tipo) => [$tipo => [
                'n' => $doCliente->where('task_type', $tipo)->count(),
                'cota' => (int) ($cota[$tipo] ?? 0) ?: null,
            ]])->all();
            if ($semTipo) {
                $celulas[''] = ['n' => $doCliente->filter(fn ($t) => ! $t->task_type)->count(), 'cota' => null];
            }

            return [
                'id' => $clientId,
                'nome' => $cliente?->displayName() ?? 'Interno (sem cliente)',
                'celulas' => $celulas,
                'executadas' => $doCliente->count(),
                'em_execucao' => $abertas->where('client_id', $clientId)->count(),
                'pedidas' => (int) ($pedidas[$clientId] ?? 0),
                'cota_total' => $mesInteiro ? array_sum(array_map('intval', $cota)) ?: null : null,
            ];
        })->sortByDesc(fn ($l) => [$l['executadas'], $l['em_execucao']])->values();

        $colunas = $tipos->mapWithKeys(fn ($k) => [$k => Task::$types[$k]])->all();
        if ($semTipo) {
            $colunas[''] = 'Sem tipo';
        }

        return ['colunas' => $colunas, 'linhas' => $linhas, 'mes_inteiro' => $mesInteiro];
    }

    /**
     * Carga por pessoa. Tarefa de duas pessoas conta pras duas — por isso a soma das
     * pessoas pode passar do total (a tela avisa).
     */
    private function pessoas(Collection $executadas, Collection $abertas): Collection
    {
        $p = [];
        $soma = function (Task $t, string $campo, Collection $users) use (&$p) {
            foreach ($users as $u) {
                $p[$u->id] ??= ['id' => $u->id, 'nome' => $u->name, 'exec' => 0, 'resp' => 0, 'abertas' => 0, 'clientes' => []];
                $p[$u->id][$campo]++;
                if ($campo !== 'resp') {
                    $nome = $t->client?->displayName() ?? 'Interno (sem cliente)';
                    $p[$u->id]['clientes'][$nome][$campo] = ($p[$u->id]['clientes'][$nome][$campo] ?? 0) + 1;
                }
            }
        };

        foreach ($executadas as $t) {
            $soma($t, 'exec', self::executoresDe($t));
            $soma($t, 'resp', self::responsaveisDe($t));
        }
        foreach ($abertas as $t) {
            $soma($t, 'abertas', self::executoresDe($t));
        }

        return collect($p)
            ->map(function ($l) {
                arsort($l['clientes']);
                $l['clientes'] = collect($l['clientes'])
                    ->map(fn ($v, $nome) => ['nome' => $nome, 'exec' => $v['exec'] ?? 0, 'abertas' => $v['abertas'] ?? 0])
                    ->sortByDesc(fn ($c) => [$c['exec'], $c['abertas']])
                    ->values()->all();

                return $l;
            })
            ->sortByDesc(fn ($l) => [$l['exec'], $l['abertas']])
            ->values();
    }
}
