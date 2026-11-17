<?php

namespace App\Services\Commerce;

/**
 * Nota Nonna (0-100) de presença digital — régua única para a lista, a página da empresa
 * e o raio-x. Versão em score_parts.version: mudou a régua, sobe a versão.
 *
 *   Perfil no Google  30  telefone 6 · site no perfil 4 · horário 8 · fotos 8 · categoria e endereço 4
 *   Reputação         30  nota 15 (3,5 ou menos = 0 → 4,9 ou mais = 15) · volume 15 (200+ avaliações = 15)
 *   Site              20  abre 5 · cadeado 3 · velocidade no celular 6 · botão de WhatsApp 3 · medição 3
 *   Nas IAs           20  etapa 4 (rodadas de IA); até lá fica pendente e a nota vai até 80
 *
 * Fotos: a Places API devolve no máximo 10, então a régua vai até "10 ou mais".
 * Guarda-se só os pontos (da Nonna); nota, avaliações e fotos do Google são lidos ao vivo.
 */
class PlaceScorer
{
    public const VERSION = 1;

    /**
     * @param array $g     GooglePlaces::collect()
     * @param array $site  SiteInspector::inspect()
     * @return array{total: int, max: int, parts: array, points: array, version: int}
     */
    public static function score(array $g, array $site, ?int $aiPoints = null): array
    {
        $photos = (int) ($g['photos'] ?? 0);
        $points = [
            'phone'      => !empty($g['phone']) ? 6 : 0,
            'website'    => !empty($g['website']) ? 4 : 0,
            'hours'      => !empty($g['has_hours']) ? 8 : 0,
            'photos'     => $photos >= 10 ? 8 : ($photos >= 5 ? 6 : ($photos >= 1 ? 3 : 0)),
            'category'   => !empty($g['primary_type']) && !empty($g['address']) ? 4 : 0,
            'rating'     => self::ratingPoints($g['rating'] ?? null, (int) ($g['reviews'] ?? 0)),
            'volume'     => self::volumePoints((int) ($g['reviews'] ?? 0)),
            'site_up'    => !empty($site['reachable']) ? 5 : 0,
            'https'      => !empty($site['reachable']) && !empty($site['https']) ? 3 : 0,
            'speed'      => !empty($site['reachable']) && isset($site['pagespeed']) ? (int) round($site['pagespeed'] / 100 * 6) : 0,
            'whatsapp'   => !empty($site['whatsapp']) ? 3 : 0,
            'tracking'   => !empty($site['pixel']) || !empty($site['ga4']) || !empty($site['gtm']) || !empty($site['ads_tag']) ? 3 : 0,
        ];

        $parts = [
            'profile'    => $points['phone'] + $points['website'] + $points['hours'] + $points['photos'] + $points['category'],
            'reputation' => $points['rating'] + $points['volume'],
            'site'       => $points['site_up'] + $points['https'] + $points['speed'] + $points['whatsapp'] + $points['tracking'],
            'ai'         => $aiPoints,
        ];

        return [
            'total'   => array_sum(array_filter($parts, 'is_int')),
            'max'     => $aiPoints === null ? 80 : 100,
            'parts'   => $parts,
            'points'  => $points,
            'version' => self::VERSION,
        ];
    }

    private static function ratingPoints(?float $rating, int $reviews): int
    {
        if ($rating === null || $reviews === 0) {
            return 0;
        }

        return (int) round(max(0, min(1, ($rating - 3.5) / 1.4)) * 15);
    }

    private static function volumePoints(int $reviews): int
    {
        return (int) min(15, round(15 * log10($reviews + 1) / log10(201)));
    }

    /**
     * "O que pesou na nota", em linguagem do dia a dia. Precisa dos dados ao vivo do Google.
     *
     * @return array<int, array{icon: string, text: string}>  icon: ok | bad | warn
     */
    public static function reasons(?array $g, array $site): array
    {
        $out = [];
        if ($g) {
            $out[] = !empty($g['phone']) && !empty($g['address']) ? ['icon' => 'ok', 'text' => 'Perfil com telefone, endereço e categoria'] : ['icon' => 'bad', 'text' => 'Perfil sem telefone ou sem endereço completo'];
            $out[] = !empty($g['has_hours']) ? ['icon' => 'ok', 'text' => 'Horário de funcionamento cadastrado'] : ['icon' => 'bad', 'text' => 'Sem horário de funcionamento cadastrado'];
            $p = (int) ($g['photos'] ?? 0);
            $out[] = $p >= 10 ? ['icon' => 'ok', 'text' => '10 fotos ou mais no perfil'] : ['icon' => $p ? 'warn' : 'bad', 'text' => $p ? "Só {$p} foto(s) no perfil" : 'Nenhuma foto no perfil'];
            $r = $g['rating'] ?? null;
            $n = (int) ($g['reviews'] ?? 0);
            $out[] = $n === 0 ? ['icon' => 'bad', 'text' => 'Nenhuma avaliação no Google']
                : ['icon' => ($r >= 4.6 && $n >= 50) ? 'ok' : 'warn', 'text' => 'Nota ' . number_format((float) $r, 1, ',', '') . " com {$n} avaliação(ões)"];
            if (empty($g['website'])) {
                $out[] = ['icon' => 'bad', 'text' => 'Sem site no perfil do Google'];
            }
        }
        if (!empty($site['checked'])) {
            if (empty($site['reachable'])) {
                $out[] = ['icon' => 'bad', 'text' => 'O site não abriu' . (!empty($site['error']) ? ' (' . $site['error'] . ')' : '')];
            } else {
                $out[] = !empty($site['https']) ? ['icon' => 'ok', 'text' => 'Site com cadeado (https)'] : ['icon' => 'bad', 'text' => 'Site sem cadeado (https)'];
                if (isset($site['pagespeed'])) {
                    $ps = (int) $site['pagespeed'];
                    $out[] = ['icon' => $ps >= 70 ? 'ok' : ($ps >= 40 ? 'warn' : 'bad'), 'text' => ($ps >= 70 ? 'Site rápido no celular' : 'Site lento no celular') . " (PageSpeed {$ps})"];
                } else {
                    $out[] = ['icon' => 'warn', 'text' => 'Velocidade no celular não pôde ser medida'];
                }
                $out[] = !empty($site['whatsapp']) ? ['icon' => 'ok', 'text' => 'Site com botão de WhatsApp'] : ['icon' => 'bad', 'text' => 'Site sem botão de WhatsApp'];
                $track = !empty($site['pixel']) || !empty($site['ga4']) || !empty($site['gtm']) || !empty($site['ads_tag']);
                $out[] = $track ? ['icon' => 'ok', 'text' => 'Site mede as visitas (Pixel, GA4 ou GTM)'] : ['icon' => 'bad', 'text' => 'Site sem Pixel, GA4 ou GTM: não mede de onde vêm os clientes'];
            }
        }

        return $out;
    }
}
