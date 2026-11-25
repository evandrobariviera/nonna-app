<?php

namespace App\Services\Commerce;

use App\Models\CommercePlace;

/**
 * Coleta de uma empresa da prospecção: dados ao vivo do Google (não guardados) + checagem
 * do site → Nota Nonna. Guarda só o que é da Nonna: pontos, sinais do site e a situação.
 */
class PlaceCollector
{
    public function __construct(private ?GooglePlaces $places = null, private ?SiteInspector $inspector = null) {}

    public function collect(CommercePlace $place, bool $withPagespeed = true, bool $allowPaid = false): CommercePlace
    {
        $google = $this->places ?? GooglePlaces::forOrganization($place->organization_id)->allowPaid($allowPaid);
        $inspector = $this->inspector ?? new SiteInspector($google->apiKey());

        try {
            $g = $google->collect($place->place_id);
            if ($g === null) {
                $place->update(['status' => 'discarded', 'collect_error' => 'A empresa não está mais no Google Maps.', 'collected_at' => now()]);

                return $place;
            }

            $site = $inspector->inspect($g['website'] ?? null, $withPagespeed);
            $score = PlaceScorer::score($g, $site, $place->score_parts['parts']['ai'] ?? null);

            $place->update([
                'score'         => $score['total'],
                'score_parts'   => $score,
                'site_signals'  => $site,
                'google_data'    => $g, // guardado: consulta uma vez só (decisão 25/11/2026)
                'google_data_at' => now(),
                'status'        => in_array($place->status, ['mapped', 'scored'], true) ? ($g['closed'] ? 'discarded' : 'scored') : $place->status,
                'collected_at'  => now(),
                'collect_error' => null,
            ]);
        } catch (FreeTierExhausted $e) {
            // Trava de custo: não marca como coletada (collected_at fica), dá pra pedir de novo.
            $place->update(['collect_error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $place->update(['collect_error' => mb_substr($e->getMessage(), 0, 500), 'collected_at' => now()]);
        }

        return $place->fresh();
    }
}
