<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\MacroPlanActivity;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\ClientItemNoticeService;
use Illuminate\Support\Facades\Auth;

class MacroPlanObserver
{
    public function created(MacroPlan $macroPlan): void
    {
        MacroPlanActivity::log($macroPlan, 'created', null, null, Auth::id() ?? $macroPlan->created_by);

        if ($macroPlan->status === 'em_execucao') {
            $this->closePreviousCycles($macroPlan);
        }
    }

    public function updated(MacroPlan $macroPlan): void
    {
        $this->logActivity($macroPlan);

        if ($macroPlan->wasChanged('status') && $macroPlan->status === 'em_execucao') {
            $this->closePreviousCycles($macroPlan);
        }

        if ($macroPlan->wasChanged('status')) {
            $this->noticeClient($macroPlan);

            AutomationEngine::evaluate('status_changed', $macroPlan, [
                'field' => 'status',
                'from'  => $macroPlan->getOriginal('status'),
                'to'    => $macroPlan->status,
            ]);
        }
    }

    private function noticeClient(MacroPlan $macroPlan): void
    {
        $notices = app(ClientItemNoticeService::class);

        if ($macroPlan->status === 'em_execucao') {
            $notices->schedule($macroPlan);
        } elseif ($macroPlan->getOriginal('status') === 'em_execucao') {
            $notices->cancel($macroPlan);
        }
    }

    // Um planejamento ativo por cliente: o novo entrou em execução → os outros em execução do
    // mesmo cliente (o ciclo que estava em Renovação) são concluídos, com histórico normal.
    private function closePreviousCycles(MacroPlan $macroPlan): void
    {
        MacroPlan::where('client_id', $macroPlan->client_id)
            ->where('id', '!=', $macroPlan->id)
            ->where('status', 'em_execucao')
            ->get()
            ->each(fn (MacroPlan $old) => $old->update(['status' => 'concluido']));
    }

    // Histórico (MacroPlanActivity) — só AÇÃO: bloco de conteúdo vira
    // "Bloco editado — 01 Visão Geral e Metas", sem o texto.
    private function logActivity(MacroPlan $macroPlan): void
    {
        if ($macroPlan->wasChanged('title')) {
            MacroPlanActivity::log($macroPlan, 'title_changed', $macroPlan->getOriginal('title'), $macroPlan->title);
        }
        if ($macroPlan->wasChanged('version')) {
            MacroPlanActivity::log($macroPlan, 'version_changed', $macroPlan->getOriginal('version') ?: '—', $macroPlan->version ?: '—');
        }
        if ($macroPlan->wasChanged('status')) {
            MacroPlanActivity::log($macroPlan, 'status_changed',
                MacroPlan::$statuses[$macroPlan->getOriginal('status')]['label'] ?? $macroPlan->getOriginal('status'),
                $macroPlan->statusLabel());
        }
        if ($macroPlan->wasChanged('responsible_id')) {
            MacroPlanActivity::log($macroPlan, 'responsible_changed',
                User::find($macroPlan->getOriginal('responsible_id'))?->name ?? '—',
                User::find($macroPlan->responsible_id)?->name ?? '—');
        }
        if ($macroPlan->wasChanged('client_id')) {
            MacroPlanActivity::log($macroPlan, 'client_changed',
                Client::find($macroPlan->getOriginal('client_id'))?->displayName(),
                Client::find($macroPlan->client_id)?->displayName());
        }
        if ($macroPlan->wasChanged('period_start') || $macroPlan->wasChanged('period_end')) {
            $fmt = fn ($start, $end) => ($start?->format('d/m/Y') ?? '—') . ' a ' . ($end?->format('d/m/Y') ?? '—');
            MacroPlanActivity::log($macroPlan, 'period_changed',
                $fmt($macroPlan->getOriginal('period_start'), $macroPlan->getOriginal('period_end')),
                $fmt($macroPlan->period_start, $macroPlan->period_end));
        }
        if ($macroPlan->wasChanged('disciplines')) {
            $labels = fn ($list) => collect($list ?? [])->map(fn ($d) => MacroPlan::$disciplineOptions[$d] ?? $d)->implode(', ') ?: '—';
            MacroPlanActivity::log($macroPlan, 'disciplines_changed', $labels($macroPlan->getOriginal('disciplines')), $labels($macroPlan->disciplines));
        }
        foreach (['bloco1', 'bloco2', 'bloco4', 'bloco5'] as $block) {
            if ($macroPlan->wasChanged($block)) {
                $meta = MacroPlan::$blocks[$block];
                MacroPlanActivity::log($macroPlan, 'block_updated', null, "{$meta['num']} {$meta['label']}");
            }
        }
    }
}
