<?php

namespace App\Services\Dashboard;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Modo Planejamento da Dashboard — em que pé está o macroplanejamento de cada cliente.
 * Decisões do usuário (2026-10-06):
 *  - Entram na visão os clientes (não inativos) que já tiveram ao menos um Macroplanejamento
 *    ou uma reunião de Kick-off/Macro. Os demais só aparecem numa lista recolhida —
 *    contracted_services está preenchido em pouquíssimos clientes, não serve de critério.
 *  - Alerta "entrando no planejamento" = 30 dias antes do fim do ciclo (mesma janela da
 *    automação que gera "Agendar Reunião de Macroplanejamento").
 *  - Escopo: a agência inteira (não só os planejamentos de quem está logado).
 *
 * A ETAPA de cada cliente é deduzida (não existe campo pra isso), nesta prioridade:
 *  1. montando     — há um próximo ciclo em elaboração (Em Planejamento / Revisão Interna / Aprovação)
 *  2. pos_reuniao  — a reunião de macro já aconteceu e está em Pós-Reunião / Revisão Interna / Despacho
 *  3. agendada     — reunião de macro agendada
 *  4. sem_ciclo    — o ciclo atual já venceu (ou nunca houve ciclo) e nada acima está andando
 *  5. entrando     — faltam ≤ 30 dias pro fim do ciclo (inclui reunião "Para Agendar")
 *  6. execucao     — ciclo rodando com folga
 */
class PlanningCockpit
{
    public const ALERT_DAYS = 30;

    public const STAGES = [
        'execucao'    => ['label' => 'Em execução',              'color' => 'green',  'hint' => 'ciclo rodando com folga'],
        'entrando'    => ['label' => 'Entrando no planejamento', 'color' => 'orange', 'hint' => '≤ 30 dias pro fim — agendar reunião'],
        'agendada'    => ['label' => 'Reunião agendada',         'color' => 'blue',   'hint' => 'reunião de macro marcada'],
        'pos_reuniao' => ['label' => 'Pós-reunião / ATA',        'color' => 'purple', 'hint' => 'ATA, revisão interna, despacho'],
        'montando'    => ['label' => 'Montando o plano',         'color' => 'purple', 'hint' => 'próximo ciclo em elaboração'],
        'sem_ciclo'   => ['label' => 'Sem ciclo ativo',          'color' => 'red',    'hint' => 'ciclo vencido ou nunca começou'],
    ];

    private const MEETING_TYPES = ['macroplanejamento', 'kickoff_estrategico'];
    private const DRAFT_PLAN    = ['em_planejamento', 'revisao_interna', 'aprovacao'];

    public function build(): array
    {
        $today = today();

        $plans = MacroPlan::with('responsible:id,name')->orderBy('period_end')->get()->groupBy('client_id');
        $meetings = Meeting::whereIn('type', self::MEETING_TYPES)
            ->where('status', '!=', 'cancelada')
            ->with(['client', 'organizer:id,name'])
            ->orderBy('scheduled_at')
            ->get();
        $meetingsByClient = $meetings->groupBy('client_id');

        $universeIds = $plans->keys()->merge($meetingsByClient->keys())->filter()->unique();
        $clients = Client::whereIn('id', $universeIds)->where('status', '!=', 'inactive')
            ->get(['id', 'nickname', 'company_name']);

        $rows = $clients->map(fn (Client $c) => $this->row(
            $c,
            $plans->get($c->id, collect()),
            $meetingsByClient->get($c->id, collect()),
            $today
        ))->sortBy('sort')->values();

        // Janela da linha do tempo: 3 meses pra trás, 3 pra frente, alargando se algum
        // ciclo relevante passar disso (limite de 6 meses pra cada lado).
        $from = $today->copy()->subMonths(3)->startOfMonth();
        $to   = $today->copy()->addMonths(3)->endOfMonth();
        foreach ($rows as $r) {
            foreach (array_filter([$r['current'], $r['next']]) as $p) {
                $from = $from->min(max($p->period_start, $today->copy()->subMonths(6)))->copy();
                $to   = $to->max(min($p->period_end, $today->copy()->addMonths(6)))->copy();
            }
        }

        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $months[] = $m->copy();
        }

        $others = Client::where('status', 'active')->whereNotIn('id', $universeIds)
            ->orderBy('company_name')->get(['id', 'nickname', 'company_name']);

        return [
            'rows'         => $rows,
            'stageCounts'  => collect(self::STAGES)->map(fn ($s, $k) => $rows->where('stage', $k)->count()),
            'agenda'       => $meetings->whereIn('status', ['para_agendar', 'agendada', 'pos_reuniao', 'revisao_ata', 'despacho'])
                                ->filter(fn ($m) => ! $m->client || $m->client->status !== 'inactive')
                                ->groupBy('status'),
            'window'       => ['from' => $from, 'to' => $to, 'days' => max(1, $from->diffInDays($to))],
            'months'       => $months,
            'today'        => $today,
            'otherClients' => $others,
        ];
    }

    private function row(Client $client, Collection $plans, Collection $meetings, Carbon $today): array
    {
        // Ciclo atual: em execução cobrindo hoje; senão o em execução mais recente; senão o
        // último concluído (pra mostrar há quanto tempo venceu).
        $running = $plans->where('status', 'em_execucao');
        $current = $running->first(fn ($p) => $p->period_start->lte($today) && $p->period_end->gte($today))
            ?? $running->sortByDesc('period_end')->first()
            ?? $plans->where('status', 'concluido')->sortByDesc('period_end')->first();

        $next = $plans->whereIn('status', self::DRAFT_PLAN)->sortByDesc('period_start')->first();

        // Reunião "viva": a mais recente que ainda não foi finalizada.
        $meeting = $meetings->whereNotIn('status', ['realizada', 'cancelada'])->sortByDesc('scheduled_at')->first();

        $daysLeft = $current ? (int) $today->diffInDays($current->period_end, false) : null;
        $elapsed  = $current
            ? max(0, min(100, (int) round($current->period_start->diffInDays($today, false) / max(1, $current->period_start->diffInDays($current->period_end)) * 100)))
            : null;

        $stage = match (true) {
            $next !== null                                                              => 'montando',
            $meeting && in_array($meeting->status, ['pos_reuniao', 'revisao_ata', 'despacho'], true) => 'pos_reuniao',
            $meeting && $meeting->status === 'agendada'                                => 'agendada',
            $current === null || $daysLeft < 0 || $current->status === 'concluido'     => 'sem_ciclo',
            $daysLeft <= self::ALERT_DAYS                                              => 'entrando',
            default                                                                    => 'execucao',
        };

        // Urgência pra ordenar: sem ciclo e entrando primeiro, depois quem vence antes.
        $stageWeight = ['sem_ciclo' => 0, 'entrando' => 1, 'agendada' => 2, 'pos_reuniao' => 3, 'montando' => 4, 'execucao' => 5][$stage];

        return [
            'client'   => $client,
            'stage'    => $stage,
            'current'  => $current,
            'next'     => $next,
            'meeting'  => $meeting,
            'daysLeft' => $daysLeft,
            'elapsed'  => $elapsed,
            'needsScheduling' => $stage === 'entrando' && (! $meeting || $meeting->status === 'para_agendar'),
            'sort'     => sprintf('%d-%06d', $stageWeight, 500000 + ($daysLeft ?? -99999)),
        ];
    }
}
