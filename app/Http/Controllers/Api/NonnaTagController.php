<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientTracking;
use App\Services\AdSync\GoogleAdsApi;
use App\Services\Leads\LeadCaptureService;
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
    public const VERSION = '1.0.0';

    public function script(string $siteKey): Response
    {
        $tracking = ClientTracking::withoutGlobalScopes()->with('client')->where('site_key', Str::before($siteKey, '.js'))->first();
        if (!$tracking || !$tracking->client || $tracking->client->status === 'inactive') {
            return response('/* Nonna Tag: chave não encontrada */', 404, ['Content-Type' => 'application/javascript; charset=utf-8']);
        }

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

    public function lead(Request $request, string $siteKey, LeadCaptureService $capture): Response
    {
        $tracking = ClientTracking::withoutGlobalScopes()->with('client')->where('site_key', $siteKey)->first();
        $body = json_decode($request->getContent(), true);
        if (!$tracking || !$tracking->client || !is_array($body) || !$this->fromClientSite($request, $tracking)) {
            return response()->noContent();
        }

        $email = filter_var(trim((string) ($body['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null;
        $phone = preg_replace('/[^\d+]/', '', (string) ($body['phone'] ?? '')) ?: null;
        $eventId = Str::limit(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($body['event_id'] ?? '')), 120, '') ?: null;
        if (!$email && !$phone) {
            return response()->noContent();
        }
        // mesmo lead mandado 2x (clique duplo, reenvio do navegador)
        if ($eventId && ClientLeadOpportunity::where('event_id', $eventId)->exists()) {
            return response()->noContent();
        }

        $attr = is_array($body['attribution'] ?? null) ? $body['attribution'] : [];
        $last = array_filter(is_array($attr['last'] ?? null) ? $attr['last'] : []);
        $first = array_filter(is_array($attr['first'] ?? null) ? $attr['first'] : []);
        $touch = $last ?: $first;
        $text = fn ($v, $n = 190) => is_scalar($v) && trim((string) $v) !== '' ? Str::limit(trim(strip_tags((string) $v)), $n, '') : null;

        $capture->captureForClient($tracking->client, 'site', [
            'name'             => $text($body['name'] ?? null, 120),
            'email'            => $email,
            'phone'            => $phone,
            'city'             => $text($body['city'] ?? null, 80),
            'state'            => $text($body['state'] ?? null, 40),
            'form_name'        => $text($body['form_name'] ?? null, 120),
            'event_id'         => $eventId,
            'utm_source'       => $text($touch['utm_source'] ?? null),
            'utm_medium'       => $text($touch['utm_medium'] ?? null),
            'utm_campaign'     => $text($touch['utm_campaign'] ?? null),
            'utm_content'      => $text($touch['utm_content'] ?? null),
            'utm_term'         => $text($touch['utm_term'] ?? null),
            'gclid'            => $text($touch['gclid'] ?? null),
            'fbclid'           => $text($touch['fbclid'] ?? null),
            'landing_page_url' => $text($touch['landing_page'] ?? ($body['page_url'] ?? null), 500),
            'raw_payload'      => [
                'plugin'      => 'nonna-tag/' . self::VERSION,
                'site'        => $this->origin($request),
                'page_url'    => $text($body['page_url'] ?? null, 500),
                'page_title'  => $text($body['page_title'] ?? null, 200),
                'fields'      => collect(is_array($body['fields'] ?? null) ? $body['fields'] : [])->take(40)->map(fn ($v) => $text($v, 300))->filter()->all(),
                'fbp'         => $text($body['fbp'] ?? null, 120),
                'fbc'         => $text($body['fbc'] ?? null, 300),
                'consent'     => (bool) ($body['consent'] ?? false),
                'first_touch' => array_keys($first),
                'user_agent'  => Str::limit((string) $request->userAgent(), 400, ''),
                'ip'          => $request->ip(),
            ],
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

        return Cache::remember("nonna_tag_aw:{$t->google_ads_account_id}", 86400, function () use ($t) {
            $account = ClientAdAccount::withoutGlobalScopes()->find($t->google_ads_account_id);
            try {
                $id = app(GoogleAdsApi::class)->search(GoogleAdsApi::cid($account), 'SELECT customer.conversion_tracking_setting.conversion_tracking_id FROM customer')[0]['customer']['conversionTrackingSetting']['conversionTrackingId'] ?? null;
            } catch (\Throwable) {
                $id = null;
            }

            return $id ? "AW-{$id}" : null;
        });
    }
}
