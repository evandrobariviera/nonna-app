<?php

namespace App\Services\Projects;

use App\Models\CampaignInsight;
use App\Models\Project;
use App\Models\TaskApprovalRound;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pilar "Sistema" da conferência (etapa E, 24/11): o que é OBJETIVO e o App sabe sozinho,
 * sem IA. Lê o documento em campos (metas e sinais de alerta) e os dados da campanha/projeto
 * (tarefas, ajustes pedidos nas aprovações, pendências do cliente, alertas e números dos anúncios).
 * O coordenador só avalia; nada aqui é digitado.
 */
class CheckinSystem
{
    /**
     * @return array{
     *   kind: string, ritmo: ?int, metas: array, cuidados: array, andamento: array, anuncios: ?array,
     *   proxima: ?string, checkin_dias: ?int
     * }
     */
    public function build(Project $project): array
    {
        $doc     = $project->fieldDocument()->first();
        $content = $doc?->content ?? [];
        $kind    = $doc?->kind ?? ($project->type === 'campanha' ? 'campanha' : 'projeto');
        $last    = $project->checkins()->first();
        $lastMetas = collect($last?->metas ?? [])->keyBy('key');

        [$from, $to] = $this->period($kind, $content, $project);
        $ritmo = $this->elapsed($from, $to);

        $metas = [];
        if ($kind === 'campanha') {
            if (filled($content['metrica'] ?? null)) {
                $metas[] = $this->meta('principal', $content['metrica'], $content['metrica_hoje'] ?? null, $content['metrica_meta'] ?? null,
                    $content['metrica_entrada'] ?? null, $lastMetas, $ritmo, true);
            }
            foreach ($content['apoio'] ?? [] as $a) {
                if (filled($a['nome'] ?? null)) {
                    $metas[] = $this->meta('apoio-' . $a['id'], $a['nome'], $a['hoje'] ?? null, $a['meta'] ?? null, $a['entrada'] ?? null, $lastMetas, $ritmo, false);
                }
            }
        } else {
            if (filled($content['pronto_quando'] ?? null)) {
                // Entrega não tem número: só a avaliação do coordenador.
                $metas[] = ['key' => 'entrega', 'nome' => 'Entrega: ' . $content['pronto_quando'], 'partida' => null, 'meta' => null,
                    'entrada' => 'avaliado', 'principal' => true, 'numeric' => false, 'ultimo' => $lastMetas['entrega']['atual'] ?? null, 'semaforo' => null];
            }
            if (filled($content['beneficio'] ?? null)) {
                $metas[] = $this->meta('beneficio', $content['beneficio'], $content['beneficio_hoje'] ?? null, $content['beneficio_esperado'] ?? null,
                    'informado', $lastMetas, $ritmo, false);
            }
        }

        $cuidados = collect($content['alertas'] ?? [])->filter(fn ($a) => filled($a['se'] ?? null))
            ->map(fn ($a) => ['id' => $a['id'], 'se' => $a['se'], 'fazemos' => $a['fazemos'] ?? null, 'quem' => $a['quem'] ?? null])->values()->all();

        $dias  = (int) ($content['checkin_dias'] ?? 0) ?: null;
        $sched = self::schedule($kind, $content, $last, $project->checkins()->where('is_closing', true)->exists());

        // Campos do Fechamento do documento (seção 'fech'), para a conferência de fechamento.
        $fech = [];
        if ($doc) {
            foreach ($doc->schema()->sections() as $section) {
                if ($section['key'] === 'fech') {
                    foreach ($section['blocks'] as $b) {
                        if ($b['type'] === 'field') {
                            $fech[] = $b + ['value' => $content[$b['key']] ?? null];
                        }
                    }
                }
            }
        }

        return [
            'fechamento'   => $sched['closing']?->toDateString(),
            'hora_fechar'  => $sched['closing_due'],
            'fechada'      => $sched['closed'],
            'fech_fields'  => $fech,
            'kind'         => $kind,
            'ritmo'        => $ritmo,
            'periodo'      => [$from?->toDateString(), $to?->toDateString()],
            'metas'        => $metas,
            'cuidados'     => $cuidados,
            'andamento'    => $this->andamento($project),
            'anuncios'     => $this->anuncios($project, $from),
            'checkin_dias' => $dias,
            'proxima'      => $sched['next']?->toDateString(),
        ];
    }

    /** Uma linha de meta: números do documento + último valor lançado + semáforo pelo ritmo. */
    private function meta(string $key, string $nome, ?string $partida, ?string $meta, ?string $entrada, $last, ?int $ritmo, bool $principal): array
    {
        $ultimo = $last[$key]['atual'] ?? null;

        return [
            'key'       => $key,
            'nome'      => $nome,
            'partida'   => $partida,
            'meta'      => $meta,
            'entrada'   => $entrada,
            'principal' => $principal,
            'numeric'   => self::number($partida) !== null && self::number($meta) !== null,
            'ultimo'    => $ultimo,
            'semaforo'  => self::light($partida, $meta, $ultimo, $ritmo),
        ];
    }

    /**
     * Semáforo pelo ritmo: quanto do caminho (ponto de partida → quanto queremos) já foi feito,
     * comparado com quanto do tempo já passou. Serve para número que sobe e que desce (custo).
     * verde = no ritmo (≥ 90% do esperado), amarelo = ≥ 60%, vermelho = abaixo. null = sem número.
     */
    public static function light(?string $partida, ?string $meta, ?string $atual, ?int $ritmo): ?string
    {
        $p = self::number($partida);
        $m = self::number($meta);
        $a = self::number($atual);
        if ($p === null || $m === null || $a === null || $m == $p || $ritmo === null) {
            return null;
        }
        $feito    = ($a - $p) / ($m - $p);
        $esperado = max($ritmo / 100, 0.1);
        if ($feito >= 1) {
            return 'verde';
        }
        $r = $feito / $esperado;

        return $r >= 0.9 ? 'verde' : ($r >= 0.6 ? 'amarelo' : 'vermelho');
    }

    /** Primeiro número de um texto ("25 pedidos por mês", "R$ 1.234,50", "3,5%"). */
    public static function number(?string $text): ?float
    {
        if ($text === null || !preg_match('/-?\d[\d.,]*/', $text, $m)) {
            return null;
        }
        $n = rtrim($m[0], '.,');
        if (str_contains($n, ',')) {
            $n = str_replace(['.', ','], ['', '.'], $n);          // 1.234,50 → 1234.50
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $n)) {
            $n = str_replace('.', '', $n);                       // 1.234 → 1234
        }

        return is_numeric($n) ? (float) $n : null;
    }

    /** % do tempo da campanha/projeto que já passou (null se faltar data). */
    private function elapsed(?Carbon $from, ?Carbon $to): ?int
    {
        if (!$from || !$to || $to->lte($from)) {
            return null;
        }
        $pct = $from->diffInDays(now()->startOfDay(), false) / $from->diffInDays($to);

        return (int) round(max(0, min(1, $pct)) * 100);
    }

    /** Campanha: vai ao ar → "até quando" da métrica (ou término). Projeto: começo → conferir o ganho. */
    private function period(string $kind, array $content, Project $project): array
    {
        $d = fn ($v) => filled($v) ? Carbon::parse($v)->startOfDay() : null;
        $from = $d($content['inicio'] ?? null) ?? ($project->start_date ? $project->start_date->copy()->startOfDay() : null);
        $to = $kind === 'campanha'
            ? ($d($content['metrica_ate'] ?? null) ?? $d($content['fim'] ?? null) ?? $d($content['fechamento'] ?? null))
            : ($d($content['conferir_beneficio'] ?? null) ?? $d($content['fim'] ?? null));
        $to ??= $project->end_date ? $project->end_date->copy()->startOfDay() : null;

        return [$from, $to];
    }

    /**
     * Agenda da conferência (sem consulta pesada — usada também pelo comando diário):
     * next = próxima conferência (nunca depois do fechamento); closing = data do fechamento
     * (campanha: "Data do fechamento" ou término; projeto: "Conferimos se melhorou em");
     * closing_due = a próxima conferência já é a de fechamento; closed = já houve o fechamento.
     *
     * @return array{next: ?Carbon, closing: ?Carbon, closing_due: bool, closed: bool}
     */
    public static function schedule(string $kind, array $content, $last, bool $closed): array
    {
        $d = fn ($v) => filled($v) ? Carbon::parse($v)->startOfDay() : null;
        $closing = $kind === 'campanha'
            ? ($d($content['fechamento'] ?? null) ?? $d($content['fim'] ?? null))
            : $d($content['conferir_beneficio'] ?? null);
        if ($closed) {
            return ['next' => null, 'closing' => $closing, 'closing_due' => false, 'closed' => true];
        }
        $dias = (int) ($content['checkin_dias'] ?? 0) ?: null;
        $next = self::next($content, $last, $dias);
        if ($closing && (!$next || $next->gte($closing))) {
            $next = $closing;
        }

        return ['next' => $next, 'closing' => $closing, 'closing_due' => $closing !== null && $next !== null && $next->eq($closing), 'closed' => false];
    }

    /** Próxima conferência: última + N dias (ou a primeira combinada / começo + N). */
    private static function next(array $content, $last, ?int $dias): ?Carbon
    {
        if (!$dias) {
            return null;
        }
        if ($last) {
            return $last->checked_on->copy()->addDays($dias);
        }
        $first = $content['checkin_inicio'] ?? null;
        if ($first) {
            return Carbon::parse($first);
        }
        $start = $content['inicio'] ?? null;

        return $start ? Carbon::parse($start)->addDays($dias) : null;
    }

    /** Produção e cliente: tarefas, ajustes pedidos nas aprovações, pendências e alertas abertos. */
    private function andamento(Project $project): array
    {
        $tasks = $project->tasks()->get(['id', 'status', 'due_date', 'title']);
        $open  = $tasks->whereNotIn('status', ['concluido', 'cancelado']);
        $late  = $open->filter(fn ($t) => $t->due_date && $t->due_date->lt(today()));
        $done  = $tasks->where('status', 'concluido');

        $ajustes = $tasks->isEmpty() ? 0 : TaskApprovalRound::whereIn('task_id', $tasks->pluck('id'))
            ->where('status', 'changes_requested')->count();

        $pend = $project->pendencies()->open()->get(['id', 'title', 'owed_by', 'due_date']);
        $pendCliente = $pend->where('owed_by', 'cliente');

        $adIds = $project->adCampaigns()->pluck('id');
        $alertas = $adIds->isEmpty() ? collect() : CampaignInsight::whereIn('ad_campaign_id', $adIds)
            ->whereIn('status', ['novo', 'lido'])->latest('generated_at')->limit(5)->get(['id', 'kind', 'title', 'severity']);

        return [
            'tarefas_total'      => $tasks->whereNotIn('status', ['cancelado'])->count(),
            'tarefas_concluidas' => $done->count(),
            'tarefas_atrasadas'  => $late->count(),
            'atrasadas'          => $late->sortBy('due_date')->take(5)->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'due' => $t->due_date->format('d/m')])->values()->all(),
            'ajustes_pedidos'    => $ajustes,
            'pendencias_cliente' => $pendCliente->count(),
            'pendencias_equipe'  => $pend->count() - $pendCliente->count(),
            'pend_cliente_lista' => $pendCliente->take(5)->map(fn ($p) => ['title' => $p->title, 'due' => $p->due_date?->format('d/m')])->values()->all(),
            'alertas_central'    => $alertas->map(fn ($a) => ['title' => $a->title ?: $a->kindLabel(), 'severity' => $a->severity])->values()->all(),
        ];
    }

    /** Números dos anúncios ligados à campanha, desde que ela foi ao ar (Central de Performance). */
    private function anuncios(Project $project, ?Carbon $from): ?array
    {
        $ext = $project->adCampaigns()->pluck('external_id')->filter();
        if ($ext->isEmpty()) {
            return null;
        }
        $since = ($from && $from->lt(now())) ? $from : now()->subDays(30);
        $r = DB::connection('pgsql')->table('ad_daily_snapshots')
            ->where('entity_level', 'campaign')->whereIn('entity_id', $ext)
            ->where('snapshot_date', '>=', $since->toDateString())
            ->selectRaw('sum(spend) as spend, sum(conversions) as conv, sum(clicks) as clk, sum(impressions) as imp')->first();
        if (!$r || (float) $r->spend == 0.0 && (int) $r->imp === 0) {
            return null;
        }

        return [
            'desde'     => $since->format('d/m'),
            'gasto'     => (float) $r->spend,
            'resultados' => (int) $r->conv,
            'custo'     => $r->conv > 0 ? round($r->spend / $r->conv, 2) : null,
            'ctr'       => $r->imp > 0 ? round(100 * $r->clk / $r->imp, 2) : null,
        ];
    }
}
