<?php

namespace App\Services\Commerce;

use App\Models\CommercePlace;

/**
 * Conteúdo do raio-x público (/rx/{token}) — etapa 5 da Central de Comércio.
 *
 * Tudo sai do que já está guardado (google_data, site_signals, score_parts): abrir a página
 * não consulta o Google. Os pontos fracos saem de REGRAS FIXAS sobre os pontos da Nota Nonna
 * (nada de IA lendo dado do Google — ver .claude/docs/central-comercio.md).
 *
 * A nota aparece em escala de 100: enquanto a parte "nas IAs" (etapa 4) não existe, a nota
 * interna vai até 80 e é convertida (46/80 → 58/100). Concorrentes aparecem só com a nota,
 * nunca com o nome.
 */
class ProspectReport
{
    /** Pontos máximos de cada item da régua (PlaceScorer). */
    private const MAX = [
        'phone' => 6, 'website' => 4, 'hours' => 8, 'photos' => 8, 'category' => 4,
        'rating' => 15, 'volume' => 15,
        'site_up' => 5, 'https' => 3, 'speed' => 6, 'whatsapp' => 3, 'tracking' => 3,
    ];

    public static function build(CommercePlace $place): array
    {
        $g = $place->google_data ?? [];
        $site = $place->site_signals ?? [];
        $points = $place->score_parts['points'] ?? [];
        $scale = fn (?int $s, ?int $max) => $s === null ? null : (int) round($s / max(1, $max ?: 100) * 100);

        $peers = CommercePlace::withoutGlobalScopes()
            ->where('organization_id', $place->organization_id)
            ->where('city_id', $place->city_id)
            ->when($place->segment_id, fn ($q, $v) => $q->where('segment_id', $v), fn ($q) => $q->whereNull('segment_id'))
            ->whereNotNull('score')->where('status', '!=', 'discarded')
            ->orderByDesc('score')->get(['id', 'score', 'score_parts', 'google_data']);

        $score = $scale($place->score, $place->scoreMax());
        $peerScores = $peers->map(fn ($p) => ['id' => $p->id, 'score' => $scale($p->score, $p->scoreMax()),
            'reviews' => (int) ($p->google_data['reviews'] ?? 0), 'rating' => $p->google_data['rating'] ?? null]);
        $position = $peerScores->search(fn ($p) => $p['id'] === $place->id);
        // "Onde você está": os 3 primeiros da cidade (a própria empresa entra se estiver entre eles),
        // cada um com a posição de verdade. $top (os outros) alimenta as faixas de comparação.
        $ranking = $peerScores->take(3)->map(fn ($p, $i) => $p + ['pos' => $i + 1, 'me' => $p['id'] === $place->id])->values();
        $top = $peerScores->reject(fn ($p) => $p['id'] === $place->id)->take(3)->values();

        $type = mb_strtolower(trim((string) ($g['type'] ?? '')));
        $city = $place->city?->name;

        return [
            'name'      => $g['name'] ?? 'Sua empresa',
            'type'      => $type ?: null,
            'city'      => $city,
            'score'     => $score,
            'position'  => $position === false ? null : $position + 1,
            'total'     => $peerScores->count(),
            'top'       => $top,
            'ranking'   => $ranking,
            'inTop'     => $ranking->contains('me', true),
            'topRange'  => $top->isNotEmpty() ? [$top->min('score'), $top->max('score')] : null,
            'rating'    => isset($g['rating']) ? (float) $g['rating'] : null,
            'reviews'   => (int) ($g['reviews'] ?? 0),
            'topReviews'=> $top->isNotEmpty() ? [$top->min('reviews'), $top->max('reviews')] : null,
            'topRating' => $top->pluck('rating')->filter()->avg(),
            'weak'      => array_slice(self::weakPoints($points, $g, $site, $type, $top), 0, 3),
            'strong'    => array_slice(self::strongPoints($points, $g, $site), 0, 3),
            'mapsUrl'   => $g['maps_url'] ?? null,
        ];
    }

    /**
     * Pontos que mais tiraram nota, do maior para o menor peso.
     *
     * @return array<int, array{title: string, text: string, lost: int}>
     */
    private static function weakPoints(array $pt, array $g, array $site, string $type, $top): array
    {
        $lost = fn (string $k) => self::MAX[$k] - (int) ($pt[$k] ?? 0);
        $out = [];
        $add = function (int $lost, string $title, string $text) use (&$out) {
            if ($lost >= 2) {
                $out[] = compact('title', 'text', 'lost');
            }
        };

        $add($lost('hours'), 'Horário não cadastrado no Google',
            'Quem procura "' . ($type ?: 'empresa') . ' aberto agora" não encontra você.');
        $photos = (int) ($g['photos'] ?? 0);
        $add($lost('photos'), $photos ? 'Poucas fotos no perfil do Google' : 'Nenhuma foto no perfil do Google',
            'Perfis com mais fotos recebem mais cliques, ligações e pedidos de rota.');
        $add($lost('phone'), 'Sem telefone no perfil do Google', 'Quem encontra você no Maps não consegue ligar direto.');
        $add($lost('category'), 'Perfil sem endereço completo ou sem categoria', 'O Google mostra menos a sua empresa quando faltam essas informações.');

        $reviews = (int) ($g['reviews'] ?? 0);
        if ($reviews === 0) {
            $add($lost('rating') + $lost('volume'), 'Nenhuma avaliação no Google', 'Avaliação é a primeira coisa que as pessoas olham antes de escolher.');
        } else {
            $range = $top->isNotEmpty() ? ' Os mais bem colocados da cidade têm entre ' . $top->min('reviews') . ' e ' . $top->max('reviews') . '.' : '';
            $add($lost('volume'), 'Poucas avaliações no Google', "Você tem {$reviews}." . $range);
            $add($lost('rating'), 'Nota ' . number_format((float) $g['rating'], 1, ',', '') . ' no Google',
                'Uma nota mais alta faz diferença na hora em que a pessoa compara opções.');
        }

        if (empty($g['website'])) {
            $add($lost('website') + 20, 'Sem site', 'Quem quer saber mais sobre você não tem para onde ir além do Maps.');
        } elseif (!empty($site['checked']) && empty($site['reachable'])) {
            $add(20, 'O site não abre', 'Quem clica no link do seu perfil encontra uma página com erro.');
        } elseif (!empty($site['reachable'])) {
            $add($lost('speed'), 'Site lento no celular', 'Ele demora para abrir, e boa parte das pessoas desiste antes de ver o conteúdo.');
            $add($lost('https') + 1, 'Site sem cadeado de segurança', 'O navegador avisa "não seguro", o que afasta quem chega.');
            $add($lost('whatsapp') + 1, 'Site sem botão de WhatsApp', 'Quem quer falar com você precisa procurar o número.');
            $add($lost('tracking') + 1, 'Site sem medição de visitas', 'Não dá para saber de onde vêm os clientes, nem se um anúncio está funcionando.');
        }

        usort($out, fn ($a, $b) => $b['lost'] <=> $a['lost']);

        return $out;
    }

    /** O que já está bom (para a página não ser só crítica). */
    private static function strongPoints(array $pt, array $g, array $site): array
    {
        $out = [];
        if (($pt['hours'] ?? 0) >= 8) $out[] = 'Horário de funcionamento cadastrado no Google';
        if (isset($g['rating']) && (float) $g['rating'] >= 4.6 && (int) ($g['reviews'] ?? 0) >= 20) $out[] = 'Nota ' . number_format((float) $g['rating'], 1, ',', '') . ' no Google';
        if (($pt['photos'] ?? 0) >= 8) $out[] = 'Perfil do Google com bastante fotos';
        if (!empty($site['reachable']) && ($pt['speed'] ?? 0) >= 5) $out[] = 'Site rápido no celular';
        if (!empty($site['whatsapp'])) $out[] = 'Site com botão de WhatsApp';
        if (($pt['phone'] ?? 0) >= 6 && ($pt['category'] ?? 0) >= 4) $out[] = 'Perfil com telefone, endereço e categoria';

        return $out;
    }
}
