<?php

namespace App\Services\Leads;

use App\Models\AdAd;
use App\Models\AdCampaign;

/**
 * Descobre de qual anúncio/campanha (já sincronizados da Meta/Google) veio uma
 * entrada de lead. Ordem de confiança:
 *   1. ID explícito (ad_id/campaign_id — n8n repassa do Lead Ads; WhatsApp
 *      "Clique para conversar" traz o sourceID do anúncio);
 *   2. UTM no padrão Nonna — utm_content = ID do anúncio, utm_campaign = ID
 *      da campanha (só quando o valor é numérico; nome de campanha não liga).
 * Busca sempre restrita às contas de anúncio do próprio cliente.
 */
class AdAttribution
{
    /**
     * @return array{ad_external_id: ?string, campaign_external_id: ?string, ad_ad_id: ?string, ad_campaign_id: ?string}
     */
    public static function resolve(?string $clientId, array $payload): array
    {
        $adExternalId = self::numericId($payload['ad_id'] ?? null) ?? self::numericId($payload['utm_content'] ?? null);
        $campaignExternalId = self::numericId($payload['campaign_id'] ?? null) ?? self::numericId($payload['utm_campaign'] ?? null);

        $result = [
            'ad_external_id'       => $adExternalId,
            'campaign_external_id' => $campaignExternalId,
            'ad_ad_id'             => null,
            'ad_campaign_id'       => null,
        ];

        if (!$clientId) {
            return $result;
        }

        if ($adExternalId) {
            $ad = AdAd::where('external_id', $adExternalId)
                ->whereHas('adset.campaign.adAccount', fn ($q) => $q->where('client_id', $clientId))
                ->with('adset.campaign:id,external_id')
                ->first();

            if ($ad) {
                $result['ad_ad_id'] = $ad->id;
                $result['ad_campaign_id'] = $ad->adset->campaign->id;
                $result['campaign_external_id'] = $ad->adset->campaign->external_id;

                return $result;
            }
        }

        if ($campaignExternalId) {
            $result['ad_campaign_id'] = AdCampaign::where('external_id', $campaignExternalId)
                ->whereHas('adAccount', fn ($q) => $q->where('client_id', $clientId))
                ->value('id');
        }

        return $result;
    }

    // IDs de anúncio/campanha da Meta e do Google são sempre numéricos e
    // longos; qualquer outra coisa (nome, "{{ad.id}}" não substituído) é ignorada.
    private static function numericId(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{6,30}$/', $value) ? $value : null;
    }
}
