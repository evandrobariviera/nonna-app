<?php

namespace App\Services\Projects;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cronograma da agência (2026-10-26) — a tela de controle dos planejamentos: uma linha por
 * cliente com o ciclo atual e o próximo (etapas automáticas, MacroPlanTimeline), os marcos das
 * campanhas/projetos e os alertas (Renovação sem próximo ciclo, fases atrasadas, sem data).
 * Abrindo o cliente aparecem as campanhas/projetos com as fases.
 *
 * Consultas fixas (não cresce por cliente): planejamentos, reuniões, e os planejamentos
 * escolhidos com projetos/fases/tarefas.
 */
class AgencyTimeline
{
    public const WINDOWS = [
        'mes'       => ['label' => '2 meses', 'before' => 15, 'after' => 45],
        'trimestre' => ['label' => '4 meses', 'before' => 30, 'after' => 90],
        'semestre'  => ['label' => '8 meses', 'before' => 60, 'after' => 180],
    ];

    public const STAGES = [
        'sem_ciclo'  => ['label' => 'Sem ciclo',  'color' => 'red',    'hint' => 'nenhum planejamento aberto'],
        'briefing'   => ['label' => 'Briefing',   'color' => 'blue',   'hint' => 'reunião de Macro/Kick-off'],
        'elaboracao' => ['label' => 'Elaboração', 'color' => 'purple', 'hint' => 'montando/aprovando o plano'],
        'execucao'   => ['label' => 'Execução',   'color' => 'green',  'hint' => 'ciclo rodando'],
        'renovacao'  => ['label' => 'Renovação',  'color' => 'orange', 'hint' => 'últimos 30 dias'],
    ];

    // Cliente que concluiu o último ciclo há até tantos dias (e não abriu outro) aparece como "Sem ciclo".
    private const LAPSED_DAYS = 90;
    private const UPCOMING_DAYS = 14;

    public function build(string $window = 'trimestre'): array
    {
        $today = now()->startOfDay();
        $w     = self::WINDOWS[$window] ?? self::WINDOWS['trimestre'];
        $from  = $today->copy()->subDays($w['before']);
        $to    = $today->copy()->addDays($w['after']);
        $span  = max(1, $from->diffInDays($to));
        $pct   = fn (Carbon $d) => round($from->diffInDays($d, false) / $span * 100, 2);
        // Barra presa à janela; null se cair toda fora.
        $bar = function (?Carbon $a, ?Carbon $b) use ($from, $to, $pct, $span) {
            if (!$a || !$b || $b->lt($from) || $a->gt($to)) {
                return null;
            }
            $l = $pct($a->copy()->max($from));
            $r = $pct($b->copy()->min($to)->addDay());
            return ['left' => $l, 'width' => max(round(100 / $span, 2), $r - $l)];
        };
        $point = fn (?Carbon $d) => $d && $d->between($from, $to) ? $pct($d) : null;

        // 1) Todos os planejamentos (leve) → escolhe atual e próximo de cada cliente.
        $allPlans = MacroPlan::with('responsible:id,name')->orderBy('period_start')->get()->groupBy('client_id');
        $picked = [];
        foreach ($allPlans as $clientId => $plans) {
            $current = $plans->where('status', 'em_execucao')->sortByDesc('period_start')->first();
            $next    = $plans->whereIn('status', MacroPlan::DRAFT_STATUSES)->sortByDesc('period_start')->first();
            $lapsed  = !$current && !$next
                ? $plans->where('status', 'concluido')->sortByDesc('period_end')->first()
                : null;
            if ($lapsed && $lapsed->period_end->lt($today->copy()->subDays(self::LAPSED_DAYS))) {
                continue; // cliente parado há muito tempo: fora do cronograma
            }
            $picked[$clientId] = ['current' => $current ?? $lapsed, 'next' => $next];
        }

        $clients = Client::whereIn('id', array_keys($picked))->where('status', '!=', 'inactive')
            ->get(['id', 'nickname', 'company_name'])->keyBy('id');
        $picked = array_intersect_key($picked, $clients->all());

        // 2) Planejamentos escolhidos com tudo que a linha do tempo precisa.
        $planIds = collect($picked)->flatMap(fn ($p) => [$p['current']?->id, $p['next']?->id])->filter()->values();
        $full = MacroPlan::whereIn('id', $planIds)->with([
            'responsible:id,name',
            'projects' => fn ($q) => $q->where('status', '!=', 'cancelado'),
            'projects.phases',
            'projects.tasks:id,project_id,project_phase_id,status,due_date',
            'activities' => fn ($q) => $q->where('action', 'status_changed'),
        ])->get()->keyBy('id');

        $meetings = Meeting::whereIn('type', MacroPlanTimeline::MEETING_TYPES)->where('status', '!=', 'cancelada')
            ->whereIn('client_id', array_keys($picked))->orderBy('scheduled_at')->get()->groupBy('client_id');

        // 3) Linhas.
        $rows = collect();
        $upcoming = collect();
        foreach ($picked as $clientId => $p) {
            $client = $clients[$clientId];
            $cm     = $meetings->get($clientId, collect());
            $cp     = $allPlans->get($clientId, collect());
            $lanes  = [];
            $tls    = [];

            foreach (['current' => 'atual', 'next' => 'proximo'] as $slot => $kind) {
                $plan = $p[$slot] ? $full->get($p[$slot]->id) : null;
                if (!$plan) {
                    continue;
                }
                $tl = MacroPlanTimeline::for($plan, $cm, $cp);
                $tls[$slot] = ['plan' => $plan, 'tl' => $tl];
                $lanes[] = [
                    'kind'   => $kind,
                    'plan'   => $plan,
                    'stages' => $tl['stages']->map(fn ($s) => $s + ['bar' => $bar($s['start'], $s['end'])])
                        ->filter(fn ($s) => $s['bar'])->values(),
                ];
            }

            $currentTl = $tls['current']['tl'] ?? null;
            $nextTl    = $tls['next']['tl'] ?? null;
            $currentPlan = $tls['current']['plan'] ?? null;

            $stage = match (true) {
                $currentPlan && $currentPlan->status === 'em_execucao' => $currentTl['current'] ?? 'execucao',
                $nextTl !== null                                       => $nextTl['current'] ?? 'elaboracao',
                default                                                => 'sem_ciclo',
            };

            // Campanhas/projetos dos dois planejamentos.
            $projects = collect($tls)->flatMap(fn ($x) => $x['tl']['projects']->map(fn ($r) => $r + ['plan' => $x['plan']]))
                ->map(function ($r) use ($bar, $point) {
                    $r['segBars'] = $r['segments']->map(fn ($s) => $s + ['bar' => $bar($s['start'], $s['end'])])->filter(fn ($s) => $s['bar'])->values();
                    $r['msPoints'] = $r['milestones']->map(fn ($m) => $m + ['pos' => $point($m['date'])])->filter(fn ($m) => $m['pos'] !== null)->values();
                    $dates = $r['segments']->flatMap(fn ($s) => [$s['start'], $s['end']])->merge($r['milestones']->pluck('date'));
                    $r['spanBar'] = $dates->isNotEmpty() ? $bar($dates->min(), $dates->max()) : null;
                    return $r;
                })->values();

            $milestones = $projects->flatMap(fn ($r) => $r['msPoints']->map(fn ($m) => $m + ['project' => $r['project']]))->values();

            foreach ($projects as $r) {
                foreach ($r['milestones'] as $m) {
                    if ($m['date']->between($today, $today->copy()->addDays(self::UPCOMING_DAYS))) {
                        $upcoming->push(['date' => $m['date'], 'label' => $m['label'], 'client' => $client, 'project' => $r['project'], 'plan' => $r['plan']]);
                    }
                }
            }
            $nextMeeting = $currentTl['nextMeeting'] ?? null;
            if ($nextMeeting && $nextMeeting->scheduled_at->between($today, $today->copy()->addDays(self::UPCOMING_DAYS)->endOfDay())) {
                $upcoming->push(['date' => $nextMeeting->scheduled_at->copy()->startOfDay(), 'label' => 'Reunião de Macro', 'client' => $client, 'meeting' => $nextMeeting]);
            }

            $mainPlan = $currentPlan ?? ($tls['next']['plan'] ?? null);
            $needsNext = ($currentTl['current'] ?? null) === 'renovacao' && !$nextTl && !$nextMeeting;
            $late = $projects->sum('late');
            $undated = collect($tls)->sum(fn ($x) => $x['tl']['undated']->count());
            $daysLeft = $currentPlan && $currentPlan->status === 'em_execucao' ? (int) $today->diffInDays($currentPlan->period_end, false) : null;

            $rows->push([
                'client'      => $client,
                'stage'       => $stage,
                'responsible' => $mainPlan?->responsible,
                'currentPlan' => $currentPlan,
                'nextPlan'    => $tls['next']['plan'] ?? null,
                'mainPlan'    => $mainPlan,
                'lanes'       => $lanes,
                'milestones'  => $milestones,
                'projects'    => $projects,
                'undatedList' => collect($tls)->flatMap(fn ($x) => $x['tl']['undated'])->values(),
                'nextMeeting' => $nextMeeting,
                'needsNext'   => $needsNext,
                'canCreateNext' => in_array($stage, ['renovacao', 'sem_ciclo'], true) && !$nextTl,
                'late'        => $late,
                'undated'     => $undated,
                'daysLeft'    => $daysLeft,
                // Urgência: sem ciclo, renovação sem próximo, renovação, elaboração/briefing, execução (quem vence antes).
                'sort'        => sprintf('%d-%06d',
                    match (true) { $stage === 'sem_ciclo' => 0, $needsNext => 1, $stage === 'renovacao' => 2, in_array($stage, ['briefing', 'elaboracao'], true) => 3, default => 4 },
                    500000 + ($daysLeft ?? 0)),
            ]);
        }

        $rows = $rows->sortBy('sort')->values();

        return [
            'rows'     => $rows,
            'window'   => $window,
            'range'    => [
                'from'     => $from,
                'to'       => $to,
                'todayPct' => $pct($today),
                'ticks'    => ProjectTimeline::ticks($from, $to, $pct),
            ],
            'counts'   => collect(self::STAGES)->map(fn ($s, $k) => $rows->where('stage', $k)->count()),
            'alerts'   => [
                'needsNext' => $rows->where('needsNext', true)->count(),
                'late'      => $rows->sum('late'),
                'undated'   => $rows->sum('undated'),
            ],
            'upcoming' => $upcoming->sortBy('date')->values(),
            'responsibles' => $rows->pluck('responsible')->filter()->unique('id')->sortBy('name')->values(),
        ];
    }
}
