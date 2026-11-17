<?php

namespace App\Services\Commerce;

use App\Models\CommercePlace;

/**
 * Sinal de oportunidade (decidido 30/10/2026): fica FORA da nota; serve para filtrar e
 * para a mensagem de abordagem. Junta o que a checagem do site achou (Pixel, tag do
 * Google Ads) com a conferência manual de anúncio (Biblioteca da Meta / Transparência do Google).
 */
class OpportunitySignal
{
    public static array $labels = [
        'ads_no_tracking'   => ['label' => 'Anuncia sem medir',              'color' => 'red',    'hint' => 'Roda anúncio, mas o site não tem como medir quantos clientes vêm dele. Bom gancho para tráfego.'],
        'competitor_ads'    => ['label' => 'Não anuncia, concorrente sim',   'color' => 'orange', 'hint' => 'Concorrentes do mesmo segmento na cidade anunciam; ela não.'],
        'ready_no_ads'      => ['label' => 'Pixel pronto, sem anúncio',      'color' => 'purple', 'hint' => 'O site já mede, mas ela não anuncia. Começar é rápido.'],
        'to_check'          => ['label' => 'Anúncio a conferir',             'color' => 'muted',  'hint' => 'Falta conferir se anuncia na Meta e no Google.'],
        'ads_and_tracking'  => ['label' => 'Anuncia e mede',                 'color' => 'green',  'hint' => 'Já anuncia e mede. Abordagem pela qualidade, não pelo básico.'],
        'no_ads'            => ['label' => 'Não anuncia',                    'color' => 'muted',  'hint' => 'Não anuncia na Meta nem no Google.'],
    ];

    /** @param bool $competitorAds alguém do mesmo segmento e cidade anuncia */
    public static function for(CommercePlace $p, bool $competitorAds = false): string
    {
        $site = $p->site_signals ?? [];
        $meta = $p->ads_meta;
        $google = $p->ads_google;

        if ($meta === null && $google === null) {
            return 'to_check';
        }
        $advertises = $meta === 'yes' || $google === 'yes';
        if ($advertises) {
            $measuresMeta = $meta !== 'yes' || !empty($site['pixel']);
            $measuresGoogle = $google !== 'yes' || !empty($site['ads_tag']) || !empty($site['ga4']);

            return $measuresMeta && $measuresGoogle ? 'ads_and_tracking' : 'ads_no_tracking';
        }
        if (!empty($site['pixel']) || !empty($site['ads_tag'])) {
            return 'ready_no_ads';
        }

        return $competitorAds ? 'competitor_ads' : 'no_ads';
    }
}
