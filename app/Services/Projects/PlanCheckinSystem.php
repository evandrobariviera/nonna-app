<?php

namespace App\Services\Projects;

use App\Models\Document;
use App\Models\MacroPlan;
use App\Models\MacroPlanCheckin;
use App\Models\Project;
use App\Models\ProjectCheckin;
use App\Services\Documents\PlanItemsLauncher;
use Carbon\Carbon;

/**
 * Pilar "Sistema" da conferência das METAS do planejamento (24/11). Para cada meta do ciclo
 * (seção 02 do documento): números da meta + semáforo pelo ritmo (do começo do ciclo até o
 * "até quando" da meta) + as campanhas/projetos que ajudam nela (seção 04) com a última
 * conferência de cada um. Sem IA. Agenda: a cada 30 dias, revisão do meio e fechamento.
 */
class PlanCheckinSystem
{
    public function build(MacroPlan $plan): array
    {
        $doc     = $plan->fieldDocument()->first();
        $content = $doc?->content ?? [];
        $last    = $plan->checkins()->first();
        $lastMetas = collect($last?->metas ?? [])->keyBy('key');
        $start   = $plan->period_start?->copy()->startOfDay();
        $end     = $plan->period_end?->copy()->startOfDay();

        // Campanhas/projetos de verdade, por item do documento.
        $created = $doc ? app(PlanItemsLauncher::class)->created($doc) : [];
        $itens   = collect($content['itens'] ?? []);

        $metas = [];
        foreach ($content['metas'] ?? [] as $m) {
            if (blank($m['nome'] ?? null)) {
                continue;
            }
            $key   = 'meta-' . $m['id'];
            $ate   = filled($m['ate'] ?? null) ? Carbon::parse($m['ate'])->startOfDay() : $end;
            $ritmo = self::elapsed($start, $ate);
            $ultimo = $lastMetas[$key]['atual'] ?? null;

            $carriers = $itens->filter(fn ($i) => in_array($m['id'], (array) ($i['metas'] ?? []), true))
                ->map(fn ($i) => $this->carrier($i, $created[$i['id']] ?? null))->values()->all();

            $metas[] = [
                'key'      => $key,
                'nome'     => $m['nome'],
                'partida'  => $m['hoje'] ?? null,
                'meta'     => $m['alvo'] ?? null,
                'ate'      => $ate?->toDateString(),
                'fonte'    => $m['fonte'] ?? null,
                'ritmo'    => $ritmo,
                'ultimo'   => $ultimo,
                'semaforo' => CheckinSystem::light($m['hoje'] ?? null, $m['alvo'] ?? null, $ultimo, $ritmo),
                'quem'     => $carriers,
            ];
        }

        $closed = $plan->checkins()->where('is_closing', true)->exists();
        $sched  = self::schedule($plan, $content, $last, $closed);

        // Campos do Fechamento do documento (menos o resultado das metas, que se monta sozinho).
        $fech = [];
        if ($doc) {
            foreach ($doc->schema()->sections() as $section) {
                if ($section['key'] === 'fech') {
                    foreach ($section['blocks'] as $b) {
                        if ($b['type'] === 'field' && $b['key'] !== 'resultado_metas') {
                            $fech[] = $b + ['value' => $content[$b['key']] ?? null];
                        }
                    }
                }
            }
        }

        return [
            'has_doc'     => (bool) $doc,
            'ritmo'       => self::elapsed($start, $end),
            'metas'       => $metas,
            'proxima'     => $sched['next']?->toDateString(),
            'momento'     => $sched['moment'],
            'revisao'     => $sched['meio']?->toDateString(),
            'fechamento'  => $sched['closing']?->toDateString(),
            'fechada'     => $closed,
            'fech_fields' => $fech,
        ];
    }

    /** Uma campanha/projeto que ajuda na meta, com a última conferência dela. */
    private function carrier(array $item, ?Project $project): array
    {
        $out = ['nome' => $item['nome'] ?? '', 'tipo' => $item['tipo'] ?? 'campanha', 'criada' => (bool) $project,
                'url' => null, 'status' => null, 'conferencia' => null];
        if (!$project) {
            return $out;
        }
        $out['nome']   = $project->title;
        $out['status'] = Project::$statuses[$project->status]['label'] ?? $project->status;
        $out['url']    = $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project, 'aba' => 'acompanhamento'])
            : route('projects.showDirect', [$project, 'aba' => 'acompanhamento']);
        $c = ProjectCheckin::withoutGlobalScopes()->where('project_id', $project->id)->orderByDesc('checked_on')->orderByDesc('created_at')->first();
        if ($c) {
            $principal = collect($c->metas ?? [])->first();
            $out['conferencia'] = [
                'data'      => $c->checked_on->format('d/m'),
                'situacao'  => $c->situacao,
                'avaliacao' => $principal['avaliacao'] ?? null,
                'atual'     => $principal['atual'] ?? null,
                'proximo'   => $c->proximo_passo,
                'fechada'   => $c->is_closing,
            ];
        }

        return $out;
    }

    /**
     * Agenda das conferências das metas: a cada 30 dias desde a última (ou do começo do ciclo),
     * a revisão do meio (se ainda não houve conferência nela ou depois) e o fechamento.
     * A mais próxima vence; nunca depois do fechamento.
     *
     * @return array{next: ?Carbon, moment: ?string, meio: ?Carbon, closing: ?Carbon}
     */
    public static function schedule(MacroPlan $plan, array $content, $last, bool $closed): array
    {
        $d = fn ($v) => filled($v) ? Carbon::parse($v)->startOfDay() : null;
        $meio    = $d($content['revisao_meio'] ?? null);
        $closing = $d($content['fechamento'] ?? null) ?? $plan->period_end?->copy()->startOfDay();
        if ($closed) {
            return ['next' => null, 'moment' => null, 'meio' => $meio, 'closing' => $closing];
        }
        $base = $last?->checked_on?->copy() ?? $plan->period_start?->copy()->startOfDay();
        $cands = [];
        if ($base) {
            $cands['mensal'] = $base->copy()->addDays(MacroPlanCheckin::DAYS);
        }
        if ($meio && (!$last || $last->checked_on->lt($meio))) {
            $cands['meio'] = $meio;
        }
        if ($closing) {
            $cands['fechamento'] = $closing;
        }
        if (!$cands) {
            return ['next' => null, 'moment' => null, 'meio' => $meio, 'closing' => $closing];
        }
        // A mais cedo; empate fica com o momento mais importante (fechamento > meio > mensal).
        $moment = null;
        $next   = null;
        foreach (['fechamento', 'meio', 'mensal'] as $m) {
            if (isset($cands[$m]) && (!$next || $cands[$m]->lt($next))) {
                [$next, $moment] = [$cands[$m], $m];
            }
        }

        return ['next' => $next, 'moment' => $moment, 'meio' => $meio, 'closing' => $closing];
    }

    private static function elapsed(?Carbon $from, ?Carbon $to): ?int
    {
        if (!$from || !$to || $to->lte($from)) {
            return null;
        }
        $pct = $from->diffInDays(now()->startOfDay(), false) / $from->diffInDays($to);

        return (int) round(max(0, min(1, $pct)) * 100);
    }
}
