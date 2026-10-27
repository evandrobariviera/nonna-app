<?php

namespace App\Services\Projects;

use App\Models\MacroPlan;
use App\Models\MacroPlanActivity;
use App\Models\Meeting;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Linha do tempo do planejamento (2026-10-26): as 4 etapas do ciclo + uma linha por
 * campanha/projeto (fases e marcos de cada um, via ProjectTimeline).
 *
 * Etapas são AUTOMÁTICAS (decisão do Evandro) — saem de acontecimentos que o App já registra:
 *  - Briefing   = dia da reunião de Macro/Kick-off do ciclo
 *  - Elaboração = do dia seguinte à reunião até o planejamento entrar em execução
 *  - Execução   = do início do período até 30 dias antes do fim
 *  - Renovação  = últimos 30 dias (mesma janela que gera "Agendar Reunião de Macroplanejamento")
 *
 * Dado real é bagunçado (só metade das reuniões ligadas ao plano; ata_recorded_at às vezes
 * antes da reunião), então a reunião é procurada também pelo cliente perto do início do ciclo
 * e a ATA não é usada como data.
 *
 * Espera $plan->projects com phases e tasks carregados.
 */
class MacroPlanTimeline
{
    public const RENEWAL_DAYS = 30;
    private const MEETING_TYPES = ['macroplanejamento', 'kickoff_estrategico'];
    // Reunião do ciclo: até 45 dias antes do início do período e até 20 depois (45, não mais,
    // pra não pegar o Briefing do ciclo anterior em ciclos curtos de 60 dias).
    private const MEETING_BEFORE = 45;
    private const MEETING_AFTER  = 20;
    private const PAD_DAYS = 3;

    public static array $stageMeta = [
        'briefing'   => ['label' => 'Briefing',   'hint' => 'reunião de Macro/Kick-off'],
        'elaboracao' => ['label' => 'Elaboração', 'hint' => 'montar e aprovar o planejamento'],
        'execucao'   => ['label' => 'Execução',   'hint' => 'campanhas e projetos rodando'],
        'renovacao'  => ['label' => 'Renovação',  'hint' => 'últimos 30 dias: reunião do próximo ciclo'],
    ];

    public static function for(MacroPlan $plan): array
    {
        $today   = now()->startOfDay();
        $start   = $plan->period_start->copy()->startOfDay();
        $end     = $plan->period_end->copy()->startOfDay();
        $meeting = self::cycleMeeting($plan);
        $nextMeeting = self::nextCycleMeeting($plan);
        // Próximo planejamento já cadastrado (liberado na Renovação deste).
        $nextPlan = MacroPlan::where('client_id', $plan->client_id)->where('id', '!=', $plan->id)
            ->where('period_start', '>', $plan->period_start)->orderBy('period_start')->first();

        // Renovação nunca começa antes do início (ciclo curto).
        $renewalStart = $end->copy()->subDays(self::RENEWAL_DAYS - 1)->max($start->copy()->addDay());
        $execStart    = self::executionStart($plan) ?? $start;

        $briefingDay = $meeting?->scheduled_at?->copy()->startOfDay();
        $elabStart   = $briefingDay?->copy()->addDay();
        $elabEnd     = $execStart->copy()->subDay();

        // Planejamento ainda não aprovado (rascunho): a Elaboração vai até hoje, mesmo que o
        // período já tenha começado — a Execução só começa depois. Sem reunião achada, a
        // Elaboração conta da criação do planejamento.
        $draft = in_array($plan->status, ['em_planejamento', 'revisao_interna', 'aprovacao'], true);
        if ($draft && !($briefingDay && $briefingDay->gt($today))) {
            $elabStart ??= ($plan->created_at?->copy()->startOfDay() ?? $today);
            $elabEnd     = $elabEnd->max($today);
            $execStart   = $execStart->copy()->max($elabEnd->copy()->addDay());
        }

        $stages = collect([
            'briefing'   => $briefingDay ? ['start' => $briefingDay, 'end' => $briefingDay] : null,
            'elaboracao' => $elabStart ? ['start' => $elabStart, 'end' => $elabEnd] : null,
            'execucao'   => ['start' => $execStart->copy()->max($start), 'end' => $renewalStart->copy()->subDay()],
            'renovacao'  => ['start' => $renewalStart->copy()->max($execStart), 'end' => $end],
        ])->filter(fn ($s) => $s && $s['end']->gte($s['start']))->map(function ($s, $key) use ($today, $plan, $draft) {
            $s['key']   = $key;
            $s['label'] = self::$stageMeta[$key]['label'];
            $s['hint']  = self::$stageMeta[$key]['hint'];
            $s['state'] = match (true) {
                $plan->status === 'concluido'                              => 'feita',
                $draft && in_array($key, ['execucao', 'renovacao'], true)  => 'futura',
                $s['end']->lt($today)                                      => 'feita',
                $s['start']->gt($today)                                    => 'futura',
                default                                                    => 'atual',
            };
            return $s;
        });

        $current = $stages->firstWhere('state', 'atual')['key'] ?? null;

        // Campanhas/projetos: reaproveita a linha do tempo de cada um.
        $projects = $plan->projects
            ->reject(fn (Project $p) => $p->status === 'cancelado')
            ->map(fn (Project $p) => ['project' => $p, 'timeline' => ProjectTimeline::for($p)])
            ->values();

        // Período = etapas + tudo que os projetos citam.
        $dates = $stages->flatMap(fn ($s) => [$s['start'], $s['end']])
            ->merge($projects->flatMap(fn ($r) => $r['timeline']['rows']->flatMap(fn ($x) => [$x['start'], $x['end']])
                ->merge($r['timeline']['fixed']->pluck('date'))))
            ->merge([$nextMeeting?->scheduled_at?->copy()->startOfDay()])
            ->filter();

        $from = $dates->min()->copy()->subDays(self::PAD_DAYS);
        $to   = $dates->max()->copy()->addDays(self::PAD_DAYS);
        $span = max(1, $from->diffInDays($to));
        $pct  = fn (Carbon $d) => round($from->diffInDays($d, false) / $span * 100, 2);
        $bar  = fn (Carbon $a, Carbon $b) => ['left' => $pct($a), 'width' => max(round(100 / $span, 2), $pct($b->copy()->addDay()) - $pct($a))];

        $stages = $stages->map(fn ($s) => $s + $bar($s['start'], $s['end']))->values();

        $projects = $projects->map(function ($r) use ($pct, $bar) {
            $tl = $r['timeline'];
            $segments = $tl['rows']->filter(fn ($x) => $x['start'] && !$x['phase']->is_milestone)
                ->map(fn ($x) => ['name' => $x['phase']->name, 'status' => $x['status'], 'start' => $x['start'], 'end' => $x['end']] + $bar($x['start'], $x['end']))
                ->values();
            $milestones = $tl['rows']->filter(fn ($x) => $x['phase']->is_milestone && $x['start'])
                ->map(fn ($x) => ['label' => $x['phase']->name, 'date' => $x['start'], 'status' => $x['status']])
                ->merge($tl['fixed']->map(fn ($f) => ['label' => $f['label'], 'date' => $f['date'], 'status' => null]))
                ->map(fn ($m) => $m + ['left' => $pct($m['date'])])
                ->sortBy('date')->values();

            // Barra de fundo do item: do primeiro ao último dia citado por ele.
            $itemDates = $segments->flatMap(fn ($s) => [$s['start'], $s['end']])->merge($milestones->pluck('date'));
            $span = $itemDates->isNotEmpty() ? $bar($itemDates->min(), $itemDates->max()) : null;

            return $r + [
                'segments'   => $segments,
                'milestones' => $milestones,
                'span'       => $span,
                'current'    => $tl['current'],
                'late'       => $tl['late'],
            ];
        });

        return [
            'stages'      => $stages,
            'current'     => $current,
            'meeting'     => $meeting,
            'nextMeeting' => $nextMeeting,
            'nextPlan'    => $nextPlan,
            'needsNext'   => $current === 'renovacao' && !$nextMeeting && !$nextPlan,
            'canCreateNext' => $current === 'renovacao' && !$nextPlan,
            'projects'    => $projects->filter(fn ($r) => $r['span'])->values(),
            'undated'     => $projects->reject(fn ($r) => $r['span'])->pluck('project')->values(),
            'range'       => [
                'from'     => $from,
                'to'       => $to,
                'todayPct' => $today->between($from, $to) ? $pct($today) : null,
                'ticks'    => ProjectTimeline::ticks($from, $to, $pct),
                'nextPct'  => $nextMeeting ? $pct($nextMeeting->scheduled_at->copy()->startOfDay()) : null,
            ],
        ];
    }

    // Reunião que deu origem ao ciclo: a ligada ao plano; senão a do cliente perto do início —
    // inclusive a que foi marcada ligada ao planejamento ANTERIOR, na Renovação dele (o Briefing
    // do novo ciclo acontece enquanto o anterior ainda está rodando).
    private static function cycleMeeting(MacroPlan $plan): ?Meeting
    {
        $base = Meeting::whereIn('type', self::MEETING_TYPES)->where('status', '!=', 'cancelada');

        return (clone $base)->where('macro_plan_id', $plan->id)->orderBy('scheduled_at')->first()
            ?? (clone $base)->where('client_id', $plan->client_id)
                ->where(fn ($q) => $q->whereNull('macro_plan_id')->orWhere('macro_plan_id', '!=', $plan->id))
                ->whereBetween('scheduled_at', [
                    $plan->period_start->copy()->subDays(self::MEETING_BEFORE)->startOfDay(),
                    $plan->period_start->copy()->addDays(self::MEETING_AFTER)->endOfDay(),
                ])
                ->orderByDesc('scheduled_at')->first();
    }

    // Reunião do PRÓXIMO ciclo: do mesmo cliente, marcada dentro da Renovação ou depois do fim
    // (até 45 dias), que não seja a deste ciclo.
    private static function nextCycleMeeting(MacroPlan $plan): ?Meeting
    {
        return Meeting::whereIn('type', self::MEETING_TYPES)
            ->where('status', '!=', 'cancelada')
            ->where('client_id', $plan->client_id)
            ->where(fn ($q) => $q->whereNull('macro_plan_id')->orWhere('macro_plan_id', '!=', $plan->id))
            ->whereBetween('scheduled_at', [
                $plan->period_end->copy()->subDays(self::RENEWAL_DAYS + 15)->startOfDay(),
                $plan->period_end->copy()->addDays(45)->endOfDay(),
            ])
            ->orderBy('scheduled_at')
            ->first();
    }

    // Quando o plano virou "Em Execução" (histórico de status), se depois do início do período.
    private static function executionStart(MacroPlan $plan): ?Carbon
    {
        $at = $plan->relationLoaded('activities')
            ? $plan->activities->where('action', 'status_changed')->where('to_label', \App\Models\MacroPlan::$statuses['em_execucao']['label'])->sortBy('created_at')->first()?->created_at
            : MacroPlanActivity::where('macro_plan_id', $plan->id)->where('action', 'status_changed')
                ->where('to_label', \App\Models\MacroPlan::$statuses['em_execucao']['label'])->orderBy('created_at')->value('created_at');

        if (!$at) {
            return null;
        }
        $at = Carbon::parse($at)->startOfDay();

        return $at->gt($plan->period_start) && $at->lt($plan->period_end) ? $at : null;
    }
}
