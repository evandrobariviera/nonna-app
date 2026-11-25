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
                'ads_tag' => false, 'gtm' => false, 'pagespeed' => null, 'sources' => [], 'error' => null, 'contacts' => [], 'site_url' => null];

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

        // Contatos publicados no PRÓPRIO site (não são dados do Google: podem ser guardados).
        // Olha a página inicial e, se houver, a página de contato do mesmo site.
        $out['site_url'] = $final;
        $contacts = $this->contacts($html);
        if ($contactPage = $this->contactPageUrl($html, $final)) {
            try {
                $page = Http::timeout(8)->connectTimeout(5)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NonnaRaioX/1.0; +https://nonnaagenciadigital.com.br)'])
                    ->get($contactPage);
                if ($page->successful()) {
                    $contacts = array_merge_recursive($contacts, $this->contacts(mb_substr((string) $page->body(), 0, 1_000_000)));
                }
            } catch (\Throwable) {
                // página de contato fora do ar: fica o que achou na inicial
            }
        }
        $out['contacts'] = array_map(fn ($v) => array_values(array_slice(array_unique($v), 0, 5)), $contacts);

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

    /**
     * WhatsApp, telefone, e-mail e redes sociais que o site publica.
     *
     * @return array{whatsapp: string[], phones: string[], emails: string[], instagram: string[], facebook: string[],
     *               linkedin: string[], tiktok: string[], youtube: string[]}
     */
    public function contacts(string $html): array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $c = ['whatsapp' => [], 'phones' => [], 'emails' => [], 'instagram' => [], 'facebook' => [], 'linkedin' => [], 'tiktok' => [], 'youtube' => []];

        preg_match_all('#(?:wa\.me/|api\.whatsapp\.com/send/?\?(?:[^"\'\s>]*?&)?phone=|whatsapp://send\?phone=|web\.whatsapp\.com/send\?phone=)\+?(\d{10,13})#i', $html, $m);
        $c['whatsapp'] = array_map(fn ($n) => self::brPhone($n), $m[1]);

        preg_match_all('#href=["\']tel:([+\d\s().\-]{8,25})["\']#i', $html, $m);
        $c['phones'] = array_values(array_filter(array_map(fn ($n) => self::brPhone(preg_replace('/\D/', '', $n)), $m[1])));

        preg_match_all('#mailto:([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})#i', $html, $m1);
        preg_match_all('#\b([A-Z0-9._%+\-]+@[A-Z0-9\-]+(?:\.[A-Z0-9\-]+)*\.(?:com|br|net|org|adv|med|eng|arq|info|biz|io|co)(?:\.br)?)\b#i', strip_tags($html), $m2);
        $c['emails'] = array_values(array_filter(array_map('strtolower', array_merge($m1[1], $m2[1])),
            fn ($e) => !preg_match('#(\.(png|jpe?g|gif|webp|svg)$|sentry|wixpress|example\.|seudominio|email@|@domain|@dominio)#', $e)));

        $social = [
            'instagram' => ['#instagram\.com/([A-Za-z0-9_.]{2,30})#i', ['p', 'reel', 'reels', 'explore', 'accounts', 'stories', 'tv', 'share']],
            'facebook'  => ['#(?:facebook|fb)\.com/([A-Za-z0-9.\-]{2,60})#i', ['sharer', 'sharer.php', 'tr', 'plugins', 'dialog', 'share', 'login', 'groups', 'events', 'watch', 'profile.php']],
            'linkedin'  => ['#linkedin\.com/((?:company|in)/[A-Za-z0-9_\-%.]{2,80})#i', []],
            'tiktok'    => ['#tiktok\.com/@([A-Za-z0-9_.]{2,30})#i', []],
            'youtube'   => ['#youtube\.com/(@[A-Za-z0-9_.\-]{2,40}|channel/[A-Za-z0-9_\-]{10,40}|c/[A-Za-z0-9_.\-]{2,40})#i', []],
        ];
        foreach ($social as $key => [$re, $skip]) {
            preg_match_all($re, $html, $m);
            foreach ($m[1] as $handle) {
                $handle = rtrim($handle, '.');
                if (!in_array(strtolower($handle), $skip, true)) {
                    $c[$key][] = match ($key) {
                        'instagram' => 'https://instagram.com/' . $handle,
                        'facebook'  => 'https://facebook.com/' . $handle,
                        'linkedin'  => 'https://linkedin.com/' . $handle,
                        'tiktok'    => 'https://tiktok.com/@' . $handle,
                        'youtube'   => 'https://youtube.com/' . $handle,
                    };
                }
            }
        }

        return array_map(fn ($v) => array_values(array_unique($v)), $c);
    }

    /** "5549999998888" / "49999998888" → "(49) 99999-8888". Fora do padrão BR, devolve os dígitos. */
    public static function brPhone(string $digits): string
    {
        $d = preg_replace('/\D/', '', $digits);
        if (strlen($d) >= 12 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 11) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
        }
        if (strlen($d) === 10) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
        }

        return $d;
    }

    /** Link para a página de contato do mesmo site ("contato", "fale conosco"...), se houver. */
    private function contactPageUrl(string $html, string $base): ?string
    {
        if (!preg_match('#href=["\']([^"\'\#]*(?:contato|contact|fale-conosco|faleconosco|atendimento)[^"\'\#]*)["\']#i', $html, $m)) {
            return null;
        }
        $href = $m[1];
        $host = parse_url($base, PHP_URL_HOST);
        if (preg_match('#^https?://#i', $href)) {
            return parse_url($href, PHP_URL_HOST) === $host ? $href : null;
        }
        if (str_starts_with($href, '//') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return null;
        }
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

        return $scheme . '://' . $host . '/' . ltrim($href, '/');
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
