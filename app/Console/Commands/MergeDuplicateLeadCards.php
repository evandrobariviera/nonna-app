<?php

namespace App\Console\Commands;

use App\Models\ClientLeadOpportunity;
use App\Models\ClientLeadOpportunityNote;
use App\Models\NotificationDispatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Junta os cartões repetidos da Central de Leads: até 27/10/2026 a mesma pessoa
 * ganhava cartão novo quando voltava depois de 72h. Agora a entrada volta pro
 * cartão em andamento (LeadCaptureService) — este comando arruma o passado.
 *
 * Por pessoa com mais de um cartão em andamento: fica o mais avançado no funil
 * (empate → o mais antigo); os outros têm notas, avisos e respostas levados pra
 * ele e são apagados. Cartões Ganho/Perdido não são tocados.
 */
class MergeDuplicateLeadCards extends Command
{
    protected $signature = 'leads:merge-duplicate-cards {--dry : Só mostra, não grava nada}';

    protected $description = 'Junta cartões em andamento repetidos da mesma pessoa na Central de Leads';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $stageOrder = array_flip(array_keys(ClientLeadOpportunity::$stages));

        $groups = ClientLeadOpportunity::with('lead.client:id,nickname,company_name')
            ->whereNotIn('stage', ClientLeadOpportunity::CLOSED_STAGES)
            ->whereIn('client_lead_id', ClientLeadOpportunity::select('client_lead_id')
                ->whereNotIn('stage', ClientLeadOpportunity::CLOSED_STAGES)
                ->groupBy('client_lead_id')->havingRaw('COUNT(*) > 1'))
            ->get()
            ->groupBy('client_lead_id');

        $rows = [];

        foreach ($groups as $cards) {
            $sorted = $cards->sortBy([
                fn ($a, $b) => $stageOrder[$b->stage] <=> $stageOrder[$a->stage],
                fn ($a, $b) => $a->created_at <=> $b->created_at,
            ])->values();
            $keep = $sorted->first();
            $others = $sorted->slice(1);

            $rows[] = [
                $keep->lead->client?->displayName() ?? '—',
                $keep->lead->name ?: ($keep->lead->phone ?: $keep->lead->email),
                $cards->count(),
                $keep->stageLabel() . ' (' . $keep->created_at->format('d/m/Y') . ')',
            ];

            if ($dry) {
                continue;
            }

            DB::connection('pgsql')->transaction(function () use ($keep, $others) {
                $history = $keep->previous_submissions ?? [];

                foreach ($others as $other) {
                    if ($answers = $other->answers()) {
                        $history[] = [
                            'received_at' => ($other->received_at ?? $other->created_at)?->toIso8601String(),
                            'form_name'   => $other->form_name,
                            'answers'     => $answers,
                        ];
                    }
                    foreach ($other->previous_submissions ?? [] as $old) {
                        $history[] = $old;
                    }

                    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page_url',
                                 'fbclid', 'gclid', 'ctwa_clid', 'ad_external_id', 'campaign_external_id', 'ad_ad_id',
                                 'ad_campaign_id', 'service_conversation_id', 'assigned_to', 'assigned_contact_id',
                                 'assigned_at', 'deal_value', 'client_lead_source_id', 'lead_channel_id'] as $field) {
                        if ($keep->$field === null && $other->$field !== null) {
                            $keep->$field = $other->$field;
                        }
                    }
                    if ($other->received_at && (!$keep->received_at || $other->received_at->gt($keep->received_at))) {
                        $keep->received_at = $other->received_at;
                    }

                    ClientLeadOpportunityNote::where('client_lead_opportunity_id', $other->id)->update(['client_lead_opportunity_id' => $keep->id]);
                    NotificationDispatch::where('related_type', $other->getMorphClass())
                        ->where('related_id', $other->id)
                        ->update(['related_id' => $keep->id]);

                    $keep->notes()->create([
                        'body' => 'Juntou o cartão repetido de ' . ($other->received_at ?? $other->created_at)->format('d/m/Y') . ' (mesma pessoa).',
                    ]);

                    $other->delete();
                }

                usort($history, fn ($a, $b) => strcmp($a['received_at'] ?? '', $b['received_at'] ?? ''));
                $keep->previous_submissions = $history ?: null;
                $keep->save();
            });
        }

        $this->table(['Cliente', 'Pessoa', 'Cartões', 'Fica o cartão'], $rows);
        $this->info(count($rows) . ' pessoa(s) com cartão repetido. ' . ($dry ? 'Simulação — nada foi gravado.' : 'Juntados.'));

        return self::SUCCESS;
    }
}
