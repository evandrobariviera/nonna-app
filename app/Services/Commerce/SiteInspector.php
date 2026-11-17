<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\Http;

/**
 * Checa o site de uma empresa da prospecção (dado público do site, não do Google Maps):
 * abre, cadeado, botão de WhatsApp, Pixel da Meta, GA4, tag do Google Ads e GTM —
 * inclusive o que está dentro do container público do GTM — e a velocidade no celular
 * pelo PageSpeed Insights.
 *
 * O resultado (sinais, sem conteúdo) é da Nonna e pode ser guardado.
 */
class SiteInspector
{
    public const PAGESPEED = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(private ?string $pagespeedKey = null) {}

    /**
     * @return array{checked: bool, reachable: bool, https: bool, whatsapp: bool, pixel: bool, ga4: bool, ads_tag: bool,
     *               gtm: bool, pagespeed: ?int, sources: array<string, string>, error: ?string}
     */
    public function inspect(?string $url, bool $withPagespeed = true): array
    {
        $out = ['checked' => true, 'reachable' => false, 'https' => false, 'whatsapp' => false, 'pixel' => false, 'ga4' => false,
                'ads_tag' => false, 'gtm' => false, 'pagespeed' => null, 'sources' => [], 'error' => null];

        if (!$url || !preg_match('#^https?://#i', $url)) {
            return ['checked' => false] + $out;
        }

        try {
            $res = Http::timeout(12)->connectTimeout(6)->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NonnaRaioX/1.0; +https://nonnaagenciadigital.com.br)', 'Accept-Language' => 'pt-BR,pt;q=0.9'])
                ->get($url);
        } catch (\Throwable $e) {
            return ['error' => 'Site não abriu: ' . mb_substr($e->getMessage(), 0, 160)] + $out;
        }

        $final = $this->finalUrl($res, $url);
        $out['https'] = str_starts_with(strtolower($final), 'https://');
        if (!$res->successful()) {
            return ['error' => 'Site respondeu HTTP ' . $res->status()] + $out;
        }
        $out['reachable'] = true;

        $html = mb_substr((string) $res->body(), 0, 2_000_000);
        $this->scan($html, 'site', $out);

        // O que está dentro do GTM (container público): Pixel e Google Ads às vezes só aparecem ali.
        preg_match_all('/GTM-[A-Z0-9]{4,10}/', $html, $m);
        foreach (array_slice(array_unique($m[0]), 0, 3) as $gtm) {
            try {
                $js = Http::timeout(8)->get('https://www.googletagmanager.com/gtm.js', ['id' => $gtm]);
                if ($js->successful()) {
                    $this->scan(mb_substr($js->body(), 0, 3_000_000), 'gtm', $out);
                }
            } catch (\Throwable) {
                // container inacessível: fica só o que estava no HTML
            }
        }

        if ($withPagespeed) {
            $out['pagespeed'] = $this->pagespeed($final);
        }

        return $out;
    }

    /** Liga os sinais encontrados num texto (HTML do site ou JS do GTM). */
    private function scan(string $text, string $source, array &$out): void
    {
        $found = [
            'whatsapp' => (bool) preg_match('#wa\.me/|api\.whatsapp\.com/send|whatsapp://send|web\.whatsapp\.com/send#i', $text),
            'pixel'    => (bool) preg_match('#connect\.facebook\.net/[a-z_A-Z]+/fbevents\.js|fbq\(\s*[\'"]init#', $text),
            'ga4'      => (bool) preg_match('#googletagmanager\.com/gtag/js\?id=G-[A-Z0-9]{4,}|[\'"]G-[A-Z0-9]{6,12}[\'"]#', $text),
            'ads_tag'  => (bool) preg_match('#AW-\d{6,12}#', $text),
            'gtm'      => $source === 'site' && (bool) preg_match('#googletagmanager\.com/gtm\.js|GTM-[A-Z0-9]{4,10}#', $text),
        ];
        foreach ($found as $key => $hit) {
            if ($hit && !$out[$key]) {
                $out[$key] = true;
                $out['sources'][$key] = $source;
            }
        }
    }

    /** Nota de desempenho no celular (0-100) do PageSpeed Insights. Null = não deu para medir. */
    private function pagespeed(string $url): ?int
    {
        try {
            $res = Http::timeout(60)->get(self::PAGESPEED, array_filter([
                'url' => $url, 'strategy' => 'mobile', 'category' => 'performance', 'key' => $this->pagespeedKey,
            ]));
            $score = $res->successful() ? $res->json('lighthouseResult.categories.performance.score') : null;

            return $score !== null ? (int) round(((float) $score) * 100) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function finalUrl(\Illuminate\Http\Client\Response $res, string $url): string
    {
        $history = $res->header('X-Guzzle-Redirect-History');
        $list = $history ? array_map('trim', explode(',', $history)) : [];

        return $list ? (string) end($list) : $url;
    }
}
