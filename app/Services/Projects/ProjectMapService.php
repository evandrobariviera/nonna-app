<?php

namespace App\Services\Projects;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mapa de Projetos — a visão do gestor de projetos. Responde, pra cada projeto/campanha
 * aberto: está no prazo? onde está o trabalho (fora da sprint, na sprint, produção,
 * cliente, concluído)? alguém está mexendo nele? E, acima de tudo, o que está vindo das
 * reuniões de Macro/Kick-off pra ser lançado.
 *
 * Tudo em poucas consultas agregadas (nada de consulta dentro de laço) — a carteira
 * inteira cabe numa tela só.
 */
class ProjectMapService
{
    // Projeto "aberto" pro gestor. Concluído/cancelado saem da tela.
    public const ABERTOS = ['em_planejamento', 'aprovacao', 'em_execucao', 'stand_by'];

    // Tarefa parada há N dias úteis vira "estagnada"; projeto sem nenhuma movimentação
    // em N dias corridos vira "estagnado". Chute inicial combinado com o Evandro.
    public const TAREFA_ESTAGNADA_DIAS_UTEIS = 5;
    public const PROJETO_ESTAGNADO_DIAS = 7;

    // Peça de campanha "ainda não aprovada" — tudo antes de despacho/concluído.
    private const PECA_PENDENTE = ['backlog', 'em_producao', 'revisao_interna', 'ajuste_alteracao', 'aprovacao'];

    // Janela da linha do tempo: um pouco pra trás (contexto) e ~2,5 meses pra frente.
    public const JANELA_ANTES = 14;
    public const JANELA_DEPOIS = 76;

    private Carbon $hoje;

    public function __construct()
    {
        $this->hoje = today();
    }

    public function build(bool $incluirInativos = false): array
    {
        $projetos = Project::with(['client:id,company_name,nickname,status', 'macroPlan:id,title,client_id', 'macroPlan.client:id,company_name,nickname,status'])
            ->whereIn('status', self::ABERTOS)
            ->get();

        if (! $incluirInativos) {
            $projetos = $projetos->filter(function (Project $p) {
                $cliente = $p->client ?? $p->macroPlan?->client;

                return ! $cliente || $cliente->status !== 'inactive';
            })->values();
        }

        $ids = $projetos->pluck('id');

        $tarefas = Task::with(['executor:id,name', 'executors:id,name'])
            ->whereIn('project_id', $ids)
            ->where('status', '!=', 'cancelado')
            ->get(['id', 'project_id', 'title', 'status', 'situation', 'due_date', 'sprint_id', 'executor_id', 'created_at'])
            ->groupBy('project_id');

        $sprintsVivas = Sprint::where('status', '!=', 'closed')->pluck('id')->flip();
        $ultimoMovimento = $this->ultimoMovimentoPorTarefa($ids);

        $linhas = $projetos->map(fn (Project $p) => $this->linha(
            $p,
            $tarefas->get($p->id, collect()),
            $sprintsVivas,
            $ultimoMovimento,
        ));

        return [
            'projetos' => $this->ordenar($linhas)->values()->all(),
            'contagens' => $this->contagens($linhas),
            'janela' => $this->janela(),
            'radar' => $this->radar(),
        ];
    }

    /**
     * Última movimentação de cada tarefa: qualquer ação registrada no histórico
     * (status, responsável, datas...), comentário ou anexo. Não usa tasks.updated_at
     * de propósito — o sync/imports mexem nele sem ninguém ter trabalhado na tarefa.
     */
    private function ultimoMovimentoPorTarefa(Collection $projectIds): Collection
    {
        $tarefasDosProjetos = Task::select('id')->whereIn('project_id', $projectIds);

        $fontes = collect(['task_activities', 'task_comments', 'task_attachments'])
            ->map(fn ($tabela) => DB::connection('pgsql')->table($tabela)
                ->select('task_id', DB::raw('max(created_at) as quando'))
                ->whereIn('task_id', $tarefasDosProjetos)
                ->groupBy('task_id')
                ->get());

        return $fontes->flatten(1)
            ->groupBy('task_id')
            ->map(fn ($linhas) => Carbon::parse($linhas->max('quando')));
    }

    private function linha(Project $p, Collection $tarefas, Collection $sprintsVivas, Collection $ultimoMovimento): array
    {
        $cliente = $p->client ?? $p->macroPlan?->client;
        $abertas = $tarefas->where('status', '!=', 'concluido');
        $emSprint = fn (Task $t) => $t->sprint_id && $sprintsVivas->has($t->sprint_id);

        // Onde o trabalho está. Status manda: tarefa em produção fora de sprint conta
        // como produção — "fora da sprint" é só o que ainda nem foi planejado.
        $etapas = ['fora' => 0, 'sprint' => 0, 'producao' => 0, 'cliente' => 0, 'despacho' => 0, 'concluido' => 0];
        foreach ($tarefas as $t) {
            $etapa = match ($t->status) {
                'backlog' => $emSprint($t) ? 'sprint' : 'fora',
                'em_producao', 'revisao_interna', 'ajuste_alteracao' => 'producao',
                'aprovacao' => 'cliente',
                'despacho_agendamento' => 'despacho',
                'concluido' => 'concluido',
                default => 'fora',
            };
            $etapas[$etapa]++;
        }

        $movimentoDe = fn (Task $t) => $ultimoMovimento->get($t->id) ?? $t->created_at;

        // Tarefa estagnada: aberta, com alguém devendo movimento. Fica de fora quem está
        // com o cliente (a trava não é interna) e quem ainda nem entrou numa sprint
        // (esperar é o esperado).
        $estagnadas = $abertas
            ->filter(fn (Task $t) => $t->status !== 'aprovacao' && ! ($t->status === 'backlog' && ! $emSprint($t)))
            ->map(fn (Task $t) => [$t, $movimentoDe($t)->diffInWeekdays($this->hoje)])
            ->filter(fn ($par) => $par[1] >= self::TAREFA_ESTAGNADA_DIAS_UTEIS)
            ->sortByDesc(fn ($par) => $par[1])
            ->map(fn ($par) => $this->tarefaResumo($par[0]) + ['parada_dias' => (int) $par[1]])
            ->values();

        // Projeto parado: nenhuma tarefa (nem as concluídas) se mexeu na janela. Só faz
        // sentido pra projeto andando — pausado é pausado de propósito, e projeto sem
        // tarefa aberta já tem apontamento próprio.
        $ultimoDoProjeto = $tarefas->map($movimentoDe)->max();
        $diasParado = $ultimoDoProjeto ? (int) $ultimoDoProjeto->diffInDays($this->hoje) : null;
        $soComCliente = $abertas->isNotEmpty() && $abertas->every(fn (Task $t) => $t->status === 'aprovacao');
        $estagnado = $p->status !== 'stand_by'
            && $abertas->isNotEmpty()
            && ! $soComCliente
            && $diasParado !== null
            && $diasParado >= self::PROJETO_ESTAGNADO_DIAS;

        $atrasadas = $abertas->filter(fn (Task $t) => $t->due_date && $t->due_date->lt($this->hoje))->count();
        [$farol, $motivo] = $p->type === 'campanha'
            ? $this->farolCampanha($p, $tarefas, $atrasadas)
            : $this->farolProjeto($p, $tarefas, $atrasadas);

        $total = $tarefas->count();

        return [
            'id' => $p->id,
            'title' => $p->title,
            'type' => $p->type ?? 'projeto',
            'status' => $p->status,
            'status_label' => $p->statusLabel(),
            'client_id' => $cliente?->id,
            'client_name' => $cliente?->displayName() ?? 'Sem cliente',
            'macroplan_title' => $p->macroPlan?->title,
            'url' => route('projects.showDirect', $p->id),
            'quick_url' => route('projects.quickUpdate', $p->id),
            'start' => $p->start_date?->toDateString(),
            'end' => $p->end_date?->toDateString(),
            'pieces' => $p->pieces_due_date ? Carbon::parse($p->pieces_due_date)->toDateString() : null,
            'datas_texto' => $this->datasTexto($p),
            'farol' => $farol,
            'motivo' => $motivo,
            'estagnado' => $estagnado,
            'dias_parado' => $diasParado,
            'sem_tarefa_aberta' => $abertas->isEmpty(),
            'nunca_teve_tarefa' => $total === 0,
            'total' => $total,
            'concluidas' => $etapas['concluido'],
            'atrasadas' => $atrasadas,
            'etapas' => $etapas,
            'proximas' => $abertas
                ->filter(fn (Task $t) => $t->due_date)
                ->sortBy('due_date')
                ->take(6)
                ->map(fn (Task $t) => $this->tarefaResumo($t))
                ->values(),
            'travadas' => $abertas
                ->filter(fn (Task $t) => $t->status === 'aprovacao' || $t->situation === 'Pendente de Informações')
                ->map(fn (Task $t) => $this->tarefaResumo($t) + [
                    'trava' => $t->status === 'aprovacao' ? 'Esperando o cliente aprovar' : 'Pendente de informações',
                ])
                ->values(),
            'estagnadas' => $estagnadas,
        ];
    }

    /**
     * Projeto: compara quanto do tempo já passou com quanto do trabalho já foi feito.
     * Tempo muito à frente do trabalho = risco. Prazo vencido = atrasado, sempre.
     */
    private function farolProjeto(Project $p, Collection $tarefas, int $atrasadas): array
    {
        if ($p->status === 'stand_by') {
            return ['pausado', 'Em Stand By'];
        }
        if (! $p->end_date) {
            return ['sem_data', 'Sem data de término'];
        }
        if ($p->end_date->lt($this->hoje)) {
            return ['atrasado', 'Prazo venceu há ' . $this->dias($p->end_date->diffInDays($this->hoje))];
        }

        $total = $tarefas->count();
        $feito = $total > 0 ? $tarefas->where('status', 'concluido')->count() / $total : 0;

        if ($p->start_date && $total > 0 && $p->end_date->gt($p->start_date)) {
            $tempo = min(1, max(0, $p->start_date->diffInDays($this->hoje, false) / $p->start_date->diffInDays($p->end_date)));
            if ($tempo - $feito > 0.25) {
                return ['risco', sprintf('%d%% do prazo usado, %d%% do trabalho pronto', round($tempo * 100), round($feito * 100))];
            }
        }
        if ($atrasadas > 0) {
            return ['risco', $atrasadas . ' tarefa(s) com entrega vencida'];
        }
        $faltam = (int) $this->hoje->diffInDays($p->end_date);
        if ($faltam <= 7 && $total > 0 && $feito < 0.8) {
            return ['risco', 'Termina em ' . $this->dias($faltam) . ' com ' . round($feito * 100) . '% pronto'];
        }

        return ['ok', 'No prazo'];
    }

    /**
     * Campanha: dois marcos que não se negociam — peças prontas (pieces_due_date) e
     * ir ao ar (start_date). "Peça pronta" = tarefa que já passou da aprovação.
     */
    private function farolCampanha(Project $p, Collection $tarefas, int $atrasadas): array
    {
        if ($p->status === 'stand_by') {
            return ['pausado', 'Em Stand By'];
        }

        $pecas = $p->pieces_due_date ? Carbon::parse($p->pieces_due_date) : null;
        $noAr = $p->start_date;
        $pendentes = $tarefas->whereIn('status', self::PECA_PENDENTE)->count();

        if ($p->end_date && $p->end_date->lt($this->hoje)) {
            return ['atrasado', 'Período da campanha já terminou'];
        }
        if ($pecas && $pecas->lt($this->hoje) && $pendentes > 0) {
            return ['atrasado', "Entrega das peças venceu há {$this->dias($pecas->diffInDays($this->hoje))} — {$pendentes} ainda não aprovada(s)"];
        }
        if ($noAr && $noAr->lt($this->hoje) && $pendentes > 0 && ! $pecas) {
            return ['atrasado', "Já devia estar no ar — {$pendentes} peça(s) não aprovada(s)"];
        }
        if (! $pecas || ! $noAr) {
            return ['sem_data', ! $pecas && ! $noAr ? 'Sem data de peças nem de ir ao ar' : (! $pecas ? 'Sem data de entrega das peças' : 'Sem data de ir ao ar')];
        }
        if ($pecas->gte($this->hoje) && $this->hoje->diffInDays($pecas) <= 3 && $pendentes > 0) {
            return ['risco', "Peças vencem em {$this->dias($this->hoje->diffInDays($pecas))} — {$pendentes} não aprovada(s)"];
        }
        if ($noAr->gte($this->hoje) && $this->hoje->diffInDays($noAr) <= 5 && $pendentes > 0) {
            return ['risco', "Vai ao ar em {$this->dias($this->hoje->diffInDays($noAr))} — {$pendentes} peça(s) não aprovada(s)"];
        }
        if ($atrasadas > 0) {
            return ['risco', $atrasadas . ' tarefa(s) com entrega vencida'];
        }

        return ['ok', $noAr->lte($this->hoje) ? 'No ar' : 'No prazo'];
    }

    private function datasTexto(Project $p): array
    {
        $fmt = fn (?Carbon $d) => $d?->format('d/m');
        $contagem = function (?Carbon $d, string $antes, string $depois) {
            if (! $d) {
                return null;
            }
            $n = (int) $this->hoje->diffInDays($d, false);

            return match (true) {
                $n > 0 => "{$antes} em " . $this->dias($n),
                $n === 0 => "{$antes} hoje",
                default => "{$depois} há " . $this->dias(-$n),
            };
        };

        if ($p->type === 'campanha') {
            $pecas = $p->pieces_due_date ? Carbon::parse($p->pieces_due_date) : null;

            return array_values(array_filter([
                $pecas ? '📦 peças ' . $fmt($pecas) . ' · ' . $contagem($pecas, 'vence', 'venceu') : '📦 peças sem data',
                $p->start_date ? '🚀 no ar ' . $fmt($p->start_date) . ' · ' . $contagem($p->start_date, 'vai ao ar', 'no ar') : '🚀 ir ao ar sem data',
            ]));
        }

        return array_values(array_filter([
            $p->start_date || $p->end_date ? trim(($fmt($p->start_date) ?? '?') . ' → ' . ($fmt($p->end_date) ?? '?')) : null,
            $contagem($p->end_date, 'termina', 'venceu'),
        ]));
    }

    private function tarefaResumo(Task $t): array
    {
        $pessoas = $t->executors->pluck('name');
        if ($pessoas->isEmpty() && $t->executor) {
            $pessoas = collect([$t->executor->name]);
        }

        return [
            'id' => $t->id,
            'title' => $t->title,
            'url' => route('tasks.show', $t->id),
            'status_label' => Task::$statuses[$t->status]['label'] ?? $t->status,
            'due' => $t->due_date?->format('d/m'),
            'vencida' => $t->due_date && $t->due_date->lt($this->hoje),
            'pessoas' => $pessoas->map(fn ($n) => explode(' ', $n)[0])->unique()->implode(', ') ?: 'sem responsável',
        ];
    }

    private function ordenar(Collection $linhas): Collection
    {
        $peso = ['atrasado' => 0, 'risco' => 1, 'sem_data' => 2, 'ok' => 3, 'pausado' => 4];

        // Cliente com o problema mais grave sobe; dentro dele, o mais grave primeiro.
        return $linhas
            ->groupBy('client_name')
            ->sortBy(fn ($grupo, $nome) => sprintf('%d-%s', $grupo->min(fn ($l) => $peso[$l['farol']]), mb_strtolower($nome)))
            ->flatMap(fn ($grupo) => $grupo->sortBy(fn ($l) => sprintf('%d-%s', $peso[$l['farol']], $l['end'] ?? $l['start'] ?? '9999')));
    }

    private function contagens(Collection $linhas): array
    {
        return [
            'atrasado' => $linhas->where('farol', 'atrasado')->count(),
            'risco' => $linhas->where('farol', 'risco')->count(),
            'estagnado' => $linhas->where('estagnado', true)->count(),
            'sem_tarefa' => $linhas->where('sem_tarefa_aberta', true)->count(),
            'sem_data' => $linhas->where('farol', 'sem_data')->count(),
            'ok' => $linhas->where('farol', 'ok')->count(),
            'pausado' => $linhas->where('farol', 'pausado')->count(),
            'todos' => $linhas->count(),
        ];
    }

    private function janela(): array
    {
        $inicio = $this->hoje->copy()->subDays(self::JANELA_ANTES);
        $fim = $this->hoje->copy()->addDays(self::JANELA_DEPOIS);
        $semanas = [];
        for ($d = $inicio->copy()->next(Carbon::MONDAY); $d->lt($fim); $d->addWeek()) {
            $semanas[] = ['data' => $d->toDateString(), 'label' => $d->format('d/m')];
        }

        return [
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'hoje' => $this->hoje->toDateString(),
            'semanas' => $semanas,
        ];
    }

    /**
     * Radar de Macroplanejamento — as duas etapas da reunião de Macro/Kick-off que
     * interessam ao gestor de projetos: (1) Revisão Interna (vem trabalho aí) e
     * (2) Despacho (ATA + planejamento prontos, ele distribui). Em Despacho mostra o
     * que do planejamento ainda não tem tarefa. Quando ele termina, marca Finalizada.
     */
    private function radar(): array
    {
        $tipos = ['kickoff_estrategico', 'macroplanejamento'];

        $emRevisao = Meeting::with('client:id,company_name,nickname')
            ->whereIn('type', $tipos)
            ->where('status', 'revisao_ata')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Meeting $m) => $this->reuniaoResumo($m));

        $despacho = Meeting::with(['client:id,company_name,nickname', 'macroPlan:id,title'])
            ->whereIn('type', $tipos)
            ->where('status', 'despacho')
            ->orderBy('scheduled_at')
            ->get();

        $projetos = Project::withCount('tasks')
            ->whereIn('macro_plan_id', $despacho->pluck('macro_plan_id')->filter())
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->get(['id', 'title', 'type', 'macro_plan_id'])
            ->groupBy('macro_plan_id');

        $emDespacho = $despacho->map(function (Meeting $m) use ($projetos) {
            $doPlano = $m->macro_plan_id ? $projetos->get($m->macro_plan_id, collect()) : collect();
            $semTarefa = $doPlano->where('tasks_count', 0);

            return $this->reuniaoResumo($m) + [
                'planejamento' => $m->macroPlan?->title,
                'plano_url' => $m->macro_plan_id ? route('macroplans.edit', [$m->macro_plan_id, 'bloco' => 'bloco3']) : null,
                'total_itens' => $doPlano->count(),
                'lancados' => $doPlano->count() - $semTarefa->count(),
                'itens' => $doPlano->sortBy('tasks_count')->map(fn ($p) => [
                    'title' => $p->title,
                    'type' => $p->type,
                    'tarefas' => $p->tasks_count,
                    'url' => route('projects.showDirect', $p->id),
                ])->values(),
            ];
        });

        return ['em_revisao' => $emRevisao->values(), 'em_despacho' => $emDespacho->values()];
    }

    private function reuniaoResumo(Meeting $m): array
    {
        return [
            'title' => $m->title,
            'client' => $m->client?->displayName() ?? '—',
            'tipo' => Meeting::$types[$m->type] ?? $m->type,
            'quando' => $m->scheduled_at ? Carbon::parse($m->scheduled_at)->format('d/m') : null,
            'dias' => $m->scheduled_at ? (int) Carbon::parse($m->scheduled_at)->startOfDay()->diffInDays($this->hoje) : null,
            'url' => route('meetings.show', $m->id),
        ];
    }

    private function dias(int|float $n): string
    {
        $n = (int) $n;

        return $n === 1 ? '1 dia' : "{$n} dias";
    }
}
