<?php

namespace App\Services\Tracking;

use App\Models\Client;
use App\Models\ClientTracking;
use App\Models\Organization;

/**
 * Qual fonte de números do site usar para um cliente: o Google de verdade
 * (GoogleSiteReports) ou, na organização de demonstração, os números fictícios
 * (DemoSiteReports). Também diz se o cliente tem algo ligado para mostrar.
 */
class SiteReports
{
    /** Canais do GA4 (sessionDefaultChannelGroup) em linguagem do dia a dia. */
    public const CHANNEL_LABELS = [
        'Organic Search' => 'Busca no Google', 'Paid Search' => 'Anúncios no Google', 'Direct' => 'Acesso direto',
        'Organic Social' => 'Redes sociais', 'Paid Social' => 'Anúncios nas redes sociais', 'Referral' => 'Outros sites',
        'Cross-network' => 'Anúncios do Google (várias redes)', 'Paid Other' => 'Outros anúncios', 'Display' => 'Banners (Google)',
        'Email' => 'E-mail', 'Unassigned' => 'Sem origem identificada', 'Organic Video' => 'Vídeos (YouTube)', 'Paid Video' => 'Anúncios em vídeo',
        'Organic Shopping' => 'Google Shopping', 'Paid Shopping' => 'Anúncios de produto', 'SMS' => 'SMS', 'Mobile Push Notifications' => 'Notificações',
    ];

    public static function for(Client $client): GoogleSiteReports|DemoSiteReports
    {
        $isDemo = $client->organization_id
            && Organization::whereKey($client->organization_id)->value('status') === 'demo';

        return $isDemo ? new DemoSiteReports() : app(GoogleSiteReports::class);
    }

    /** Analytics e/ou Search Console vinculados na aba Rastreamento. */
    public static function tracking(Client $client): ?ClientTracking
    {
        $tracking = ClientTracking::where('client_id', $client->id)->first();

        return $tracking && ($tracking->ga4_property_id || $tracking->search_console_site) ? $tracking : null;
    }
}
