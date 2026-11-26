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
 *
 * "Se você resolver": soma os pontos que cada um dos 3 pontos fracos devolveria (teto da régua,
 * por isso a página fala em "até") e recalcula a posição contra as notas atuais da cidade.
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
        $parts = $place->score_parts['parts'] ?? [];
        $max = $place->scoreMax();
        $scale = fn (?int $s, ?int $m) => $s === null ? null : (int) round($s / max(1, $m ?: 100) * 100);

        $peers = CommercePlace::withoutGlobalScopes()
            ->where('organization_id', $place->organization_id)
            ->where('city_id', $place->city_id)
            ->when($place->segment_id, fn ($q, $v) => $q->where('segment_id', $v), fn ($q) => $q->whereNull('segment_id'))
            ->whereNotNull('score')->where('status', '!=', 'discarded')
            ->orderByDesc('score')->get(['id', 'score', 'score_parts', 'google_data', 'ads_meta', 'ads_google']);

        $score = $scale($place->score, $max);
        $peerScores = $peers->map(fn ($p) => ['id' => $p->id, 'score' => $scale($p->score, $p->scoreMax()),
            'reviews' => (int) ($p->google_data['reviews'] ?? 0), 'rating' => $p->google_data['rating'] ?? null]);
        $position = $peerScores->search(fn ($p) => $p['id'] === $place->id);
        // "Onde você está": os 3 primeiros da cidade (a própria empresa entra se estiver entre eles),
        // cada um com a posição de verdade. $top (os outros) alimenta as faixas de comparação.
        $ranking = $peerScores->take(3)->map(fn ($p, $i) => $p + ['pos' => $i + 1, 'me' => $p['id'] === $place->id])->values();
        $top = $peerScores->reject(fn ($p) => $p['id'] === $place->id)->take(3)->values();
        $others = $peerScores->reject(fn ($p) => $p['id'] === $place->id);

        $type = mb_strtolower(trim((string) ($g['type'] ?? '')));
        $weakAll = self::weakPoints($points, $parts, $g, $site, $type, $top);
        if ($ai = self::aiPoint($place, $parts, $type)) {
            $weakAll[] = $ai;
            usort($weakAll, fn ($a, $b) => $b['weight'] <=> $a['weight']);
        }
        $weak = array_slice($weakAll, 0, 3);

        // Simulação: nota e posição se os 3 pontos fossem resolvidos (teto).
        $potential = null;
        if ($place->score !== null && $weak) {
            $raw = min($max, $place->score + array_sum(array_column($weak, 'gain')));
            $pScore = $scale($raw, $max);
            if ($pScore > $score) {
                $potential = ['score' => $pScore, 'position' => 1 + $others->where('score', '>', $pScore)->count()];
            }
        }

        // As áreas da nota ("Nas IAs" só depois de uma rodada de IA do segmento e cidade).
        $areas = [];
        foreach (['profile' => ['Perfil no Google', 30], 'reputation' => ['Reputação', 30], 'site' => ['Site', 20], 'ai' => ['Nas IAs (ChatGPT)', 20]] as $k => [$label, $of]) {
            if (isset($parts[$k])) {
                $areas[] = ['label' => $label, 'pct' => (int) round($parts[$k] / $of * 100)];
            }
        }

        return [
            'name'      => $g['name'] ?? 'Sua empresa',
            'type'      => $type ?: null,
            'city'      => $place->city?->name,
            'score'     => $score,
            'position'  => $position === false ? null : $position + 1,
            'total'     => $peerScores->count(),
            // Ranking só com amostra mínima (e o "Gerar raio-x" exige 80% do segmento com nota).
            'compare'   => $peerScores->count() >= 5,
            'top'       => $top,
            'ranking'   => $ranking,
            'inTop'     => $ranking->contains('me', true),
            'topRange'  => $top->isNotEmpty() ? [$top->min('score'), $top->max('score')] : null,
            'rating'    => isset($g['rating']) ? (float) $g['rating'] : null,
            'reviews'   => (int) ($g['reviews'] ?? 0),
            'topReviews'=> $top->isNotEmpty() ? [$top->min('reviews'), $top->max('reviews')] : null,
            'weak'      => $weak,
            'strong'    => array_slice(array_merge(self::aiStrong($place), self::strongPoints($points, $g, $site)), 0, 3),
            'areas'     => $areas,
            'potential' => $potential,
            'ads'       => self::ads($place, $site, $peers),
            'mapsUrl'   => $g['maps_url'] ?? null,
            'date'      => ($place->collected_at ?? $place->report_at ?? now())->format('d/m/Y'),
        ];
    }

    /**
     * Pontos que mais tiraram nota, do maior para o menor peso.
     * weight = ordem na página; gain = pontos reais que voltam se resolver (para a simulação);
     * who = 'voce' (o dono resolve sozinho, dica de graça) ou 'nos' (a agência resolve).
     *
     * @return array<int, array{title: string, text: string, fix: string, who: string, weight: int, gain: int}>
     */
    private static function weakPoints(array $pt, array $parts, array $g, array $site, string $type, $top): array
    {
        $lost = fn (string $k) => self::MAX[$k] - (int) ($pt[$k] ?? 0);
        $out = [];
        $add = function (int $weight, int $gain, string $title, string $text, string $fix, string $who) use (&$out) {
            if ($weight >= 2) {
                $out[] = compact('title', 'text', 'fix', 'who', 'weight', 'gain');
            }
        };

        $add($lost('hours'), $lost('hours'), 'Horário não cadastrado no Google',
            'Quem procura "' . ($type ?: 'empresa') . ' aberto agora" não encontra você.',
            'No seu Perfil da Empresa no Google, abra Editar perfil → Horário e preencha os dias e horários. Leva 5 minutos.', 'voce');
        $photos = (int) ($g['photos'] ?? 0);
        $add($lost('photos'), $lost('photos'), $photos ? 'Poucas fotos no perfil do Google' : 'Nenhuma foto no perfil do Google',
            'Perfis com mais fotos recebem mais cliques, ligações e pedidos de rota.',
            'Suba pelo menos 10 fotos: fachada, ambiente, equipe e o seu trabalho. Dá para fazer pelo próprio app do Google Maps.', 'voce');
        $add($lost('phone'), $lost('phone'), 'Sem telefone no perfil do Google', 'Quem encontra você no Maps não consegue ligar direto.',
            'Em Editar perfil → Contato, coloque o telefone ou o WhatsApp da empresa.', 'voce');
        $add($lost('category'), $lost('category'), 'Perfil sem endereço completo ou sem categoria', 'O Google mostra menos a sua empresa quando faltam essas informações.',
            'Em Editar perfil, confira o endereço completo e escolha a categoria principal certa.', 'voce');

        // Metas realistas para a simulação: até 30 avaliações a mais (sem passar do nível do 3º colocado,
        // mínimo 20) com nota 4,7; site novo "bom" (no ar, cadeado, celular ~80, WhatsApp, medição).
        $reviews = (int) ($g['reviews'] ?? 0);
        $targetReviews = max($reviews, min($reviews + 30, max(20, (int) ($top->min('reviews') ?? 0))));
        $volGain = max(0, PlaceScorer::volumePoints($targetReviews) - (int) ($pt['volume'] ?? 0));
        $rateGain = max(0, PlaceScorer::ratingPoints(max(4.7, (float) ($g['rating'] ?? 0)), $targetReviews) - (int) ($pt['rating'] ?? 0));
        $goodSite = 5 + 3 + 5 + 3 + 3;
        if ($reviews === 0) {
            $add($lost('rating') + $lost('volume'), $volGain + $rateGain, 'Nenhuma avaliação no Google',
                'Avaliação é a primeira coisa que as pessoas olham antes de escolher.',
                'Peça para os clientes satisfeitos avaliarem, mandando o link direto do seu perfil. A gente pode automatizar esse pedido depois de cada atendimento.', 'nos');
        } else {
            $range = $top->isNotEmpty() ? ' Os mais bem colocados da cidade têm entre ' . $top->min('reviews') . ' e ' . $top->max('reviews') . '.' : '';
            $add($lost('volume'), $volGain, 'Poucas avaliações no Google', "Você tem {$reviews}." . $range,
                'Peça avaliação para quem já foi atendido, com o link direto do perfil. A gente pode automatizar esse pedido.', 'nos');
            $add($lost('rating'), $rateGain, 'Nota ' . number_format((float) $g['rating'], 1, ',', '') . ' no Google',
                'Uma nota mais alta faz diferença na hora em que a pessoa compara opções.',
                'Responda todas as avaliações, inclusive as negativas, e peça avaliação para os clientes satisfeitos.', 'voce');
        }

        $siteNow = (int) ($parts['site'] ?? 0);
        if (empty($g['website'])) {
            $add($lost('website') + 20, $lost('website') + $goodSite, 'Sem site', 'Quem quer saber mais sobre você não tem para onde ir além do Maps.',
                'Um site simples, rápido no celular e com botão de WhatsApp já resolve. A gente faz.', 'nos');
        } elseif (!empty($site['checked']) && empty($site['reachable'])) {
            $add(20, max(0, $goodSite - $siteNow), 'O site não abre', 'Quem clica no link do seu perfil encontra uma página com erro.',
                'Precisa ver a hospedagem e o domínio. A gente confere e coloca no ar.', 'nos');
        } elseif (!empty($site['reachable'])) {
            $add($lost('speed'), $lost('speed'), 'Site lento no celular', 'Ele demora para abrir, e boa parte das pessoas desiste antes de ver o conteúdo.',
                'Otimizar imagens e a hospedagem costuma resolver. A gente faz.', 'nos');
            $add($lost('https') + 1, $lost('https'), 'Site sem cadeado de segurança', 'O navegador avisa "não seguro", o que afasta quem chega.',
                'Ativar o certificado de segurança (https) na hospedagem. A gente faz.', 'nos');
            $add($lost('whatsapp') + 1, $lost('whatsapp'), 'Site sem botão de WhatsApp', 'Quem quer falar com você precisa procurar o número.',
                'Um botão fixo de WhatsApp no site. A gente coloca.', 'nos');
            $add($lost('tracking') + 1, $lost('tracking'), 'Site sem medição de visitas', 'Não dá para saber de onde vêm os clientes, nem se um anúncio está funcionando.',
                'Instalar Google Analytics e Pixel da Meta. A gente instala e configura.', 'nos');
        }

        usort($out, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return $out;
    }

    /**
     * Bloco "Anúncios" (carro-chefe da agência). Fica FORA da nota, como o sinal de oportunidade.
     * Só afirma algo sobre anúncio quando a equipe conferiu (Biblioteca da Meta / Transparência do
     * Google); sem conferência, mostra só se o site está pronto para anunciar. Concorrentes: só a
     * contagem, entre as que foram conferidas.
     *
     * @return array{case: string, meta: ?string, google: ?string, ready: array, rivals: int, rivalsChecked: int}
     */
    private static function ads(CommercePlace $place, array $site, $peers): array
    {
        $meta = $place->ads_meta;
        $google = $place->ads_google;
        $others = $peers->reject(fn ($p) => $p->id === $place->id);
        $checked = $others->filter(fn ($p) => in_array($p->ads_meta, ['yes', 'no'], true) || in_array($p->ads_google, ['yes', 'no'], true));
        $rivals = $checked->filter(fn ($p) => $p->ads_meta === 'yes' || $p->ads_google === 'yes')->count();

        $ready = [
            ['label' => 'Pixel da Meta (mede Instagram e Facebook)', 'ok' => !empty($site['pixel'])],
            ['label' => 'Tag do Google Ads (mede anúncios no Google)', 'ok' => !empty($site['ads_tag'])],
            ['label' => 'Google Analytics (mede as visitas do site)', 'ok' => !empty($site['ga4']) || !empty($site['gtm'])],
        ];
        if (empty($site['reachable'])) {
            $ready = []; // sem site no ar não há o que medir; o bloco fala disso no texto
        }

        $advertises = $meta === 'yes' || $google === 'yes';
        $case = match (true) {
            $meta === null && $google === null => 'unchecked',
            $advertises && (($meta === 'yes' && empty($site['pixel'])) || ($google === 'yes' && empty($site['ads_tag']) && empty($site['ga4']))) => 'no_tracking',
            $advertises => 'measured',
            default => 'none',
        };

        return ['case' => $case, 'meta' => $meta, 'google' => $google, 'ready' => $ready,
            'rivals' => $rivals, 'rivalsChecked' => $checked->count(), 'hasSite' => !empty($site['reachable'])];
    }

    /**
     * "Quase não aparece nas IAs" (etapa 4): só quando há rodada de IA do segmento e cidade.
     * Conta quantas vezes o ChatGPT citou a empresa e quantas citou a mais lembrada (sem nome).
     */
    private static function aiPoint(CommercePlace $place, array $parts, string $type): ?array
    {
        $d = $place->score_parts['ai_detail'] ?? null;
        $pts = $parts['ai'] ?? null;
        if (!$d || $pts === null || $pts >= 14) {
            return null;
        }
        $best = (int) \Illuminate\Support\Facades\DB::connection('pgsql')->table('commerce_ai_round_place')->where('round_id', $d['round_id'])->max('mentions');
        $n = (int) $d['mentions'];
        $who = $type ?: 'empresa';
        $city = $place->city?->name;
        $text = "Perguntamos {$d['answers']} vezes ao ChatGPT qual {$who} procurar" . ($city ? " em {$city}" : '') . '. '
            . ($n === 0 ? 'Você não foi citado nenhuma vez.' : 'Você foi citado ' . ($n === 1 ? '1 vez' : "{$n} vezes") . '.')
            . ($best > $n ? " A empresa mais lembrada apareceu {$best} vezes." : '');

        return [
            'title'  => $n === 0 ? 'Não aparece nas IAs' : 'Quase não aparece nas IAs',
            'text'   => $text,
            'fix'    => 'As IAs indicam quem tem perfil completo no Google, boas avaliações e um site que explica bem o que você faz. Cada vez mais gente pergunta ao ChatGPT antes de escolher. A gente cuida disso.',
            'who'    => 'nos',
            'weight' => 20 - (int) $pts,
            'gain'   => (int) min(20 - (int) $pts, 10), // meta realista: metade do que falta
        ];
    }

    /** "Aparece bem nas IAs" — entra primeiro no "O que já está bom". */
    private static function aiStrong(CommercePlace $place): array
    {
        $d = $place->score_parts['ai_detail'] ?? null;

        return $d && ($place->score_parts['parts']['ai'] ?? 0) >= 14
            ? ["Aparece bem nas IAs: citada em {$d['mentions']} de {$d['answers']} respostas do ChatGPT"]
            : [];
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
