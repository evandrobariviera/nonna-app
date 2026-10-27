<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectPhase;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Monta a linha do tempo de uma campanha/projeto pra view (2026-10-26): uma linha por fase/
 * marco na ordem, barras posicionadas em % do período, linha do "hoje", marcos fixos que saem
 * das datas do próprio projeto e as tarefas que ainda não têm fase.
 *
 * Espera $project->phases e $project->tasks já carregados — não faz consulta (a página do
 * projeto já tem tudo em memória).
 */
class ProjectTimeline
{
    // Folga nas pontas, pra barra não colar na borda.
    private const PAD_DAYS = 2;

    public static function for(Project $project): array
    {
        $today     = now()->startOfDay();
        $tasks     = $project->tasks->where('status', '!=', 'cancelado');
        $byPhase   = $tasks->groupBy(fn ($t) => $t->project_phase_id ?? '');
        $phases    = $project->phases;

        $rows = $phases->map(function (ProjectPhase $phase) use ($byPhase) {
            $phaseTasks = $byPhase->get($phase->id, collect())->values();
            $status     = $phase->statusFrom($phaseTasks);

            return [
                'phase'  => $phase,
                'status' => $status,
                'tasks'  => $phaseTasks->sortBy(fn ($t) => [$t->status === 'concluido' ? 1 : 0, $t->due_date?->timestamp ?? PHP_INT_MAX])->values(),
                'total'  => $phaseTasks->count(),
                'done'   => $phaseTasks->where('status', 'concluido')->count(),
                'start'  => $phase->is_milestone ? $phase->end_date : ($phase->start_date ?? $phase->end_date),
                'end'    => $phase->end_date ?? $phase->start_date,
            ];
        })->values();

        $isCampaign = $project->type === 'campanha';
        $fixed = collect([
            $isCampaign && $project->pieces_due_date ? ['label' => 'Entrega das peças', 'date' => $project->pieces_due_date] : null,
            $project->start_date ? ['label' => $isCampaign ? 'Vai ao ar' : 'Início', 'date' => $project->start_date] : null,
            $project->end_date ? ['label' => 'Término', 'date' => $project->end_date] : null,
        ])->filter()->values();

        // Período = do primeiro ao último dia citado (fases, marcos, datas do projeto).
        $dates = $rows->flatMap(fn ($r) => [$r['start'], $r['end']])
            ->merge($fixed->pluck('date'))
            ->filter();

        $range = null;
        if ($dates->isNotEmpty()) {
            $from = $dates->min()->copy()->subDays(self::PAD_DAYS);
            $to   = $dates->max()->copy()->addDays(self::PAD_DAYS);
            $span = max(1, $from->diffInDays($to));
            $pct  = fn (Carbon $d) => round($from->diffInDays($d, false) / $span * 100, 2);

            $rows = $rows->map(function ($r) use ($pct, $span) {
                if ($r['start']) {
                    $r['left']  = $pct($r['start']);
                    // Fase de um dia só ainda ocupa um dia inteiro na régua.
                    $r['width'] = max(round(100 / $span, 2), $pct($r['end']->copy()->addDay()) - $r['left']);
                }
                return $r;
            });
            $fixed = $fixed->map(fn ($f) => $f + ['left' => $pct($f['date'])]);

            $range = [
                'from'     => $from,
                'to'       => $to,
                'todayPct' => $today->between($from, $to) ? $pct($today) : null,
                'ticks'    => self::ticks($from, $to, $pct),
            ];
        }

        $phaseRows = $rows->where('phase.is_milestone', false);

        return [
            'rows'     => $rows,
            'fixed'    => $fixed,
            'range'    => $range,
            'unphased' => $byPhase->get('', collect())->values(),
            // Fase atual = a primeira (na ordem) que ainda não se concluiu.
            'current'  => $phaseRows->first(fn ($r) => $r['status'] !== 'concluida'),
            'late'     => $phaseRows->where('status', 'atrasada')->count(),
            'done'     => $phaseRows->where('status', 'concluida')->count(),
            'phases'   => $phaseRows->count(),
        ];
    }

    // Marcações da régua: semanais (segundas) em períodos curtos, mensais nos longos.
    // Também usada pela linha do tempo do planejamento (MacroPlanTimeline).
    public static function ticks(Carbon $from, Carbon $to, \Closure $pct): array
    {
        $ticks  = [];
        $weekly = $from->diffInDays($to) <= 60;
        $cursor = $weekly
            ? $from->copy()->next(Carbon::MONDAY)
            : $from->copy()->startOfMonth()->addMonth();

        while ($cursor->lte($to)) {
            $ticks[] = [
                'left'  => $pct($cursor),
                'label' => $weekly ? $cursor->format('d/m') : ucfirst($cursor->translatedFormat('M/y')),
            ];
            $weekly ? $cursor->addWeek() : $cursor->addMonth();
        }

        return $ticks;
    }
}
