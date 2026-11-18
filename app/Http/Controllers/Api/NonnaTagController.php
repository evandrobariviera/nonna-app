<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientTracking;
use App\Services\AdSync\GoogleAdsApi;
use App\Services\Tracking\SiteLeadIntake;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Nonna Tag (substitui o GTM): entrega o script do site com a configuração
 * do cliente e recebe os leads dos formulários dos sites sem plugin.
 * Público de propósito: a chave do site (site_key) só identifica o cliente,
 * não dá acesso a nada; o lead passa por limite de envios e checagem do site.
 */
class NonnaTagController extends Controller
{
    public const VERSION = '1.1.0';

    public function script(string $siteKey): Response
    {
        $tracking = ClientTracking::withoutGlobalScopes()->with('client')->where('site_key', Str::before($siteKey, '.js'))->first();
        if (!$tracking || !$tracking->client || $tracking->client->status === 'inactive') {
            return response('/* Nonna Tag: chave não encontrada */', 404, ['Content-Type' => 'application/javascript; charset=utf-8']);
        }

        // "A tag está no site?": anota onde e quando foi vista (no máximo a cada 10 min).
        SiteLeadIntake::markSeen($tracking, 'tag', ['host' => self::host((string) request()->headers->get('Referer'))]);

        $config = [
            'version'  => self::VERSION,
            'pixel'    => $tracking->meta_pixel_id,
            'ga4'      => $tracking->ga4_measurement_id,
            'ads'      => $this->googleTagId($tracking),
            'clarity'  => $tracking->clarity_id,
            'endpoint' => url("/api/t/{$tracking->site_key}/lead"),
            'capture'  => true,
            'consent'  => 'granted',
            'prefix'   => Str::limit(preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($tracking->client->nickname ?: $tracking->client->company_name))), 10, ''),
        ];

        $js = '/* Nonna Tag ' . self::VERSION . " — configuração em Central de Campanhas → Rastreamento */\n"
            . 'window.NONNA_TAG_CONFIG=' . json_encode($config, JSON_UNESCAPED_SLASHES) . ";\n"
            . file_get_contents(resource_path('tag/nonna-tag.js'));

        return response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function lead(Request $request, string $siteKey, SiteLeadIntake $intake): Response
    {
        $tracking = ClientTracking::withoutGlobalScopes()->with('client')->where('site_key', $siteKey)->first();
        $body = json_decode($request->getContent(), true);
        if (!$tracking || !$tracking->client || !is_array($body) || !$this->fromClientSite($request, $tracking)) {
            return response()->noContent();
        }

        // Sempre 204, aceito ou não: não dá pista a quem tenta abusar.
        $intake->receive($tracking, $body, [
            'via'        => 'tag',
            'platform'   => 'nonna-tag/' . self::VERSION,
            'site'       => $this->origin($request),
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->noContent();
    }

    /**
     * O lead tem que vir de um site do cliente: o da ficha ou um de onde os
     * leads do site dele já chegaram (em 10/11/2026 só 4 de 81 clientes tinham
     * o site na ficha). Cliente sem nenhum dos dois (site novo) é aceito.
     */
    private function fromClientSite(Request $request, ClientTracking $tracking): bool
    {
        $allowed = self::clientHosts($tracking->client);
        if (!$allowed) {
            return true;
        }
        $from = self::host((string) $this->origin($request));

        return $from !== '' && collect($allowed)->contains(fn ($h) => $from === $h || Str::endsWith($from, '.' . $h));
    }

    /** @return array<int, string> domínios do cliente (sem www.) */
    public static function clientHosts(\App\Models\Client $client): array
    {
        return Cache::remember("nonna_tag_hosts:{$client->id}", 3600, function () use ($client) {
            $hosts = collect([$client->website ? self::host(Str::start($client->website, 'https://')) : null]);
            ClientLeadOpportunity::whereHas('lead', fn ($q) => $q->where('client_id', $client->id))
                ->whereHas('channel', fn ($q) => $q->where('kind', 'site'))->latest('received_at')->limit(200)->get(['raw_payload', 'landing_page_url'])
                ->each(function ($o) use ($hosts) {
                    $raw = is_array($o->raw_payload) ? $o->raw_payload : (json_decode((string) $o->raw_payload, true) ?: []);
                    $hosts->push(self::host((string) ($raw['site'] ?? '')), self::host((string) $o->landing_page_url));
                });

            return $hosts->filter()->unique()->values()->all();
        });
    }

    /** @return ?array{at: string, host: string} última vez que a tag foi carregada num site */
    public static function lastSeen(ClientTracking $t): ?array
    {
        return SiteLeadIntake::lastSeen($t, 'tag') ?? Cache::get("nonna_tag_seen:{$t->id}");
    }

    private static function host(string $url): string
    {
        return Str::of(parse_url($url, PHP_URL_HOST) ?: '')->lower()->replace('www.', '')->toString();
    }

    private function origin(Request $request): ?string
    {
        return $request->headers->get('Origin') ?: $request->headers->get('Referer');
    }

    /** "AW-123…" da conta Google do cliente (para remarketing/atribuição no navegador). */
    private function googleTagId(ClientTracking $t): ?string
    {
        if (!$t->google_ads_account_id) {
            return null;
        }

        // Esta rota roda em TODA página dos sites dos clientes. O Cache::remember
        // não guarda null, então "sem ID" ou falha no Google virava uma chamada
        // ao Google Ads (até 30 s + renovação do token) a cada pageview e
        // travava os workers do App. Agora "sem ID" também fica guardado ('').
        $key = "nonna_tag_aw:{$t->google_ads_account_id}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached !== '' ? $cached : null;
        }

        // Só uma requisição por vez consulta o Google; as outras seguem sem o ID.
        if (!Cache::add("{$key}:busy", 1, 120)) {
            return null;
        }

        $failed = false;
        try {
            $account = ClientAdAccount::withoutGlobalScopes()->find($t->google_ads_account_id);
            $id = $account ? (app(GoogleAdsApi::class)->search(GoogleAdsApi::cid($account), 'SELECT customer.conversion_tracking_setting.conversion_tracking_id FROM customer')[0]['customer']['conversionTrackingSetting']['conversionTrackingId'] ?? null) : null;
        } catch (\Throwable) {
            $id = null;
            $failed = true;
        }

        $value = $id ? "AW-{$id}" : '';
        // Falha do Google: tenta de novo em 1 h. Resposta "sem ID": 1 dia.
        Cache::put($key, $value, $failed ? 3600 : 86400);
        Cache::forget("{$key}:busy");

        return $value !== '' ? $value : null;
    }
}
