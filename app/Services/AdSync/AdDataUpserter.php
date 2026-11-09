<?php

namespace App\Services\AdSync;

use App\Models\AdAd;
use App\Models\AdAdset;
use App\Models\AdCampaign;
use App\Models\AdDailySnapshot;
use App\Models\ClientAdAccount;
use Illuminate\Support\Facades\DB;

/**
 * Grava campanhas/adsets/ads e snapshots diários vindos de qualquer origem
 * (fetchers internos do App, ou POST externo via /api/sync/*). Extraído de
 * SyncController para ser reaproveitado pelos dois caminhos.
 */
class AdDataUpserter
{
    public function upsertCampaigns(string $organizationId, string $clientAdAccountId, string $platform, array $campaigns): array
    {
        $counts = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0];

        DB::connection('pgsql')->transaction(function () use ($organizationId, $clientAdAccountId, $platform, $campaigns, &$counts) {
            foreach ($campaigns as $campaignData) {
                $status = $campaignData['status'] ?? 'active';

                // Meta/Google só reportam `status` como "desligada manualmente" — uma
                // campanha que expira sozinha por end_date (stop_time) continua chegando
                // como "active" pra sempre, porque a API não atualiza esse campo nesse
                // caso (só o effective_status, que não sincronizamos). Corrige aqui, toda
                // sincronização, senão volta a ficar errado no próximo ciclo.
                if ($status === 'active' && !empty($campaignData['end_date']) && $campaignData['end_date'] < now()->toDateString()) {
                    $status = 'paused';
                }

                $campaign = AdCampaign::updateOrCreate(
                    [
                        'client_ad_account_id' => $clientAdAccountId,
                        'external_id'          => $campaignData['external_id'],
                    ],
                    [
                        'organization_id' => $organizationId,
                        'platform'        => $platform,
                        'name'            => $campaignData['name'],
                        'status'          => $status,
                        'objective'       => $campaignData['objective'] ?? null,
                        'start_date'      => $campaignData['start_date'] ?? null,
                        'end_date'        => $campaignData['end_date'] ?? null,
                        'raw_data'        => $campaignData['raw_data'] ?? null,
                        'last_synced_at'  => now(),
                    ]
                );
                $counts['campaigns']++;

                foreach ($campaignData['adsets'] ?? [] as $adsetData) {
                    $adset = AdAdset::updateOrCreate(
                        [
                            'ad_campaign_id' => $campaign->id,
                            'external_id'    => $adsetData['external_id'],
                        ],
                        [
                            'organization_id'      => $organizationId,
                            'platform'             => $platform,
                            'campaign_external_id' => $campaign->external_id,
                            'name'                 => $adsetData['name'],
                            'status'               => $adsetData['status'] ?? 'active',
                            'daily_budget'         => $adsetData['daily_budget'] ?? null,
                            'lifetime_budget'      => $adsetData['lifetime_budget'] ?? null,
                            'targeting'            => $adsetData['targeting'] ?? null,
                            'raw_data'             => $adsetData['raw_data'] ?? null,
                            'last_synced_at'       => now(),
                        ]
                    );
                    $counts['adsets']++;

                    foreach ($adsetData['ads'] ?? [] as $adData) {
                        AdAd::updateOrCreate(
                            [
                                'ad_adset_id' => $adset->id,
                                'external_id' => $adData['external_id'],
                            ],
                            [
                                'organization_id'    => $organizationId,
                                'platform'           => $platform,
                                'adset_external_id'  => $adset->external_id,
                                'name'               => $adData['name'],
                                'status'             => $adData['status'] ?? 'active',
                                'creative_type'      => $adData['creative_type'] ?? null,
                                'creative_url'       => $adData['creative_url'] ?? null,
                                'raw_data'           => $adData['raw_data'] ?? null,
                                'last_synced_at'     => now(),
                            ]
                        );
                        $counts['ads']++;
                    }
                }
            }
        });

        return $counts;
    }

    public function upsertSnapshots(string $organizationId, string $clientAdAccountId, string $platform, string $snapshotDate, array $snapshots): int
    {
        $upserted = 0;

        DB::connection('pgsql')->transaction(function () use ($organizationId, $clientAdAccountId, $platform, $snapshotDate, $snapshots, &$upserted) {
            foreach ($snapshots as $snap) {
                $row = AdDailySnapshot::withCalculatedMetrics([
                    'organization_id'       => $organizationId,
                    'client_ad_account_id'  => $clientAdAccountId,
                    'platform'              => $platform,
                    'snapshot_date'         => $snapshotDate,
                    'entity_level'          => $snap['entity_level'],
                    'entity_id'             => $snap['entity_id'],
                    'entity_name'           => $snap['entity_name'],
                    'parent_entity_id'      => $snap['parent_entity_id'] ?? null,
                    'spend'                 => $snap['spend'] ?? 0,
                    'revenue'               => $snap['revenue'] ?? 0,
                    'impressions'           => $snap['impressions'] ?? 0,
                    'clicks'                => $snap['clicks'] ?? 0,
                    'conversions'           => $snap['conversions'] ?? 0,
                    'reach'                 => $snap['reach'] ?? 0,
                    'raw_data'              => $snap['raw_data'] ?? null,
                ]);

                AdDailySnapshot::updateOrCreate(
                    [
                        'client_ad_account_id' => $clientAdAccountId,
                        'entity_level'         => $snap['entity_level'],
                        'entity_id'            => $snap['entity_id'],
                        'snapshot_date'        => $snapshotDate,
                    ],
                    $row
                );
                $upserted++;
            }
        });

        return $upserted;
    }

    /**
     * Saldo de verdade da conta Meta (crédito que ainda sobra para gastar).
     *
     * O campo `balance` da API NÃO é isso: é o valor ainda não cobrado (fatura
     * em aberto). Em conta pré-paga ele fica em centavos perto de 1 dia de
     * gasto, o que fazia toda conta parecer "acabando" (corrigido 09/11/2026).
     *
     * - Pré-paga (boleto/PIX): "Saldo disponível (R$614,31 BRL)" vem em
     *   funding_source_details.display_string; sem ele, spend_cap - amount_spent.
     * - Pós-paga (cartão): não existe saldo para acabar → fica vazio (null).
     */
    public function upsertAccountBalance(ClientAdAccount $account, array $data): void
    {
        if (!array_key_exists('balance', $data) && !array_key_exists('is_prepay_account', $data)) {
            return;
        }

        $account->update([
            'balance'           => self::metaAvailableBalance($data),
            'balance_source'    => 'api',
            'balance_synced_at' => now(),
        ]);
    }

    /** @return float|null em reais; null quando a conta não é pré-paga ou não dá para saber. */
    public static function metaAvailableBalance(array $data): ?float
    {
        if (($data['is_prepay_account'] ?? null) === false) {
            return null;
        }

        $display = $data['funding_source_details']['display_string'] ?? '';
        if (preg_match('/(?:Saldo dispon[ií]vel|Available balance)\s*\(\s*R?\$\s*([\d.,]+)/iu', $display, $m)) {
            // Formato brasileiro: 1.234,56
            return (float) str_replace(',', '.', str_replace('.', '', $m[1]));
        }

        $cap = (float) ($data['spend_cap'] ?? 0);
        if ($cap > 0 && isset($data['amount_spent'])) {
            return max(0, $cap - (float) $data['amount_spent']) / 100;
        }

        return null;
    }
}
