<?php

namespace App\Services\Tracking;

use App\Models\ClientLeadOpportunity;
use App\Models\ClientTracking;
use App\Models\ConversionEvent;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\GoogleAdsApi;
use App\Services\AdSync\MetaAdsFetcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Manda as conversões do App para as plataformas pelo servidor (substitui o
 * GTM server): Meta (API de Conversões), Google Ads (conversão importada) e
 * GA4 (Measurement Protocol).
 *
 * Regras:
 * - um envio por lead + plataforma + evento (conversion_events é único);
 * - o event_id é o mesmo do navegador (plugin/Nonna Tag), para a Meta não
 *   contar duas vezes o que o Pixel já contou;
 * - dados pessoais vão só em hash SHA-256, como as plataformas pedem;
 * - modo teste da Meta (meta_test_event_code): os eventos aparecem só em
 *   "Eventos de teste" e não contam para os anúncios;
 * - sem send_enabled e sem modo teste, nada sai.
 * Lead de formulário da própria Meta (Lead Ads) não volta como Lead: a Meta
 * já tem. WhatsApp entra quando a permissão de mensagens for liberada.
 */
class ConversionSender
{
    /** A Meta só aceita eventos de até 7 dias. */
    private const META_MAX_AGE_DAYS = 7;

    public bool $validateOnly = false; // Google: validateOnly; GA4: endpoint de validação

    public function __construct(private GoogleAdsApi $google) {}

    /**
     * Envia o evento do lead para tudo que estiver configurado.
     *
     * @return array<string, ConversionEvent> por plataforma
     */
    public function send(ClientLeadOpportunity $opp, string $eventName = 'Lead', ?float $value = null): array
    {
        $opp->loadMissing('lead', 'channel');
        $client = $opp->lead?->client;
        $tracking = $client ? ClientTracking::where('client_id', $client->id)->first() : null;
        if (!$tracking || (!$tracking->send_enabled && !$tracking->isTestMode() && !$this->validateOnly)) {
            return [];
        }

        $out = [];
        if ($tracking->meta_pixel_id) {
            $out['meta'] = $this->once($opp, $tracking, 'meta', $eventName, $value, fn () => $this->meta($opp, $tracking, $eventName, $value));
        }
        if ($tracking->google_lead_action && $tracking->google_ads_account_id && (!$tracking->isTestMode() || $this->validateOnly)) {
            $out['google'] = $this->once($opp, $tracking, 'google', $eventName, $value, fn () => $this->googleAds($opp, $tracking, $eventName, $value));
        }
        if ($tracking->ga4_measurement_id && $tracking->ga4_api_secret && (!$tracking->isTestMode() || $this->validateOnly)) {
            $out['ga4'] = $this->once($opp, $tracking, 'ga4', $eventName, $value, fn () => $this->ga4($opp, $tracking, $eventName, $value));
        }

        return $out;
    }

    /** Executa o envio uma vez só e registra o resultado. */
    private function once(ClientLeadOpportunity $opp, ClientTracking $t, string $platform, string $event, ?float $value, \Closure $fn): ConversionEvent
    {
        $existing = ConversionEvent::where('client_lead_opportunity_id', $opp->id)->where('platform', $platform)->where('event_name', $event)->first();
        if ($existing && $existing->status === 'enviado') {
            return $existing;
        }

        [$status, $detail] = $fn();
        $row = $existing ?? new ConversionEvent([
            'organization_id' => $t->organization_id, 'client_id' => $t->client_id, 'client_lead_opportunity_id' => $opp->id,
            'platform' => $platform, 'event_name' => $event,
        ]);
        $row->fill(['event_id' => $this->eventId($opp, $event), 'status' => $status, 'detail' => mb_substr((string) $detail, 0, 1000), 'value' => $value, 'event_time' => $this->eventTime($opp, $event)]);
        if (!$this->validateOnly) {
            $row->save();
        }

        return $row;
    }

    // ── Meta ──────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string} status, detalhe */
    private function meta(ClientLeadOpportunity $opp, ClientTracking $t, string $event, ?float $value): array
    {
        $kind = $opp->channel?->kind;
        if ($event === 'Lead' && $kind === 'facebook_lead_ad') {
            return ['ignorado', 'Lead de formulário da própria Meta: ela já tem.'];
        }
        if ($kind === 'whatsapp') {
            return ['ignorado', 'WhatsApp: aguardando a permissão de mensagens da Meta.'];
        }
        $time = $this->eventTime($opp, $event);
        if ($time->lt(now()->subDays(self::META_MAX_AGE_DAYS))) {
            return ['ignorado', 'Mais de 7 dias: a Meta não aceita evento tão antigo.'];
        }

        $raw = $this->raw($opp);
        $lead = $opp->lead;
        [$fn, $ln] = $this->splitName($lead?->name);
        $userData = array_filter([
            'em'                => $this->hashList($lead?->email ? mb_strtolower(trim($lead->email)) : null),
            'ph'                => $this->hashList($this->phone($lead?->phone)),
            'fn'                => $this->hashList($fn),
            'ln'                => $this->hashList($ln),
            'ct'                => $this->hashList($lead?->city ? preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($lead->city))) : null),
            'st'                => $this->hashList($lead?->state && strlen(trim($lead->state)) === 2 ? Str::lower(trim($lead->state)) : null),
            'country'           => $this->hashList('br'),
            'external_id'       => $this->hashList($lead?->id),
            'fbc'               => $raw['fbc'] ?? ($opp->fbclid ? 'fb.1.' . $opp->received_at->getTimestampMs() . '.' . $opp->fbclid : null),
            'fbp'               => $raw['fbp'] ?? null,
            'client_ip_address' => $raw['ip'] ?? $raw['client_ip'] ?? null,
            'client_user_agent' => $raw['user_agent'] ?? null,
            // Lead de formulário da Meta: o id do lead dela liga o andamento ao lead (integração com CRM).
            'lead_id'           => $kind === 'facebook_lead_ad' && ctype_digit((string) $opp->event_id) ? (int) $opp->event_id : null,
        ]);
        $crm = $kind === 'facebook_lead_ad' ? ['event_source' => 'crm', 'lead_event_source' => 'Nonna App'] : [];

        $data = [[
            'event_name'       => $event,
            'event_time'       => $time->timestamp,
            'event_id'         => $this->eventId($opp, $event),
            'action_source'    => $event === 'Lead' && $kind === 'site' ? 'website' : 'system_generated',
            'event_source_url' => $opp->landing_page_url ?: ($raw['site'] ?? null),
            'user_data'        => $userData,
            'custom_data'      => array_filter($crm + [
                'currency'     => $value !== null ? 'BRL' : null,
                'value'        => $value,
                'lead_channel' => $kind,
                'form_name'    => $opp->form_name,
            ], fn ($v) => $v !== null),
        ]];

        $res = Http::timeout(30)->asForm()->post('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . "/{$t->meta_pixel_id}/events", array_filter([
            'data'            => json_encode($data),
            'test_event_code' => $t->meta_test_event_code,
            'access_token'    => $this->metaToken(),
        ]))->json() ?? [];

        if (isset($res['error'])) {
            return ['erro', (string) ($res['error']['error_user_msg'] ?? $res['error']['message'] ?? 'erro')];
        }

        return [$t->isTestMode() ? 'teste' : 'enviado', 'Meta recebeu ' . ($res['events_received'] ?? 0) . ' evento(s)' . (!empty($res['messages']) ? ': ' . implode(' ', (array) $res['messages']) : '.')];
    }

    // ── Google Ads ────────────────────────────────────────────────────

    private function googleAds(ClientLeadOpportunity $opp, ClientTracking $t, string $event, ?float $value): array
    {
        $action = $event === 'Purchase' ? ($t->google_purchase_action ?: null) : ($event === 'Lead' ? $t->google_lead_action : null);
        if (!$action) {
            return ['ignorado', "Sem conversão do Google configurada para {$event}."];
        }
        if (!$opp->gclid) {
            return ['ignorado', 'O lead não veio de clique no Google (sem gclid).'];
        }

        $account = $t->googleAccount;
        $cid = GoogleAdsApi::cid($account);
        $lead = $opp->lead;
        $ids = array_values(array_filter([
            $lead?->email ? ['hashedEmail' => hash('sha256', mb_strtolower(trim($lead->email)))] : null,
            ($p = $this->phone($lead?->phone)) ? ['hashedPhoneNumber' => hash('sha256', '+' . $p)] : null,
        ]));

        $res = $this->google->uploadClickConversions($cid, [[
            'gclid'              => $opp->gclid,
            'conversionAction'   => $action,
            'conversionDateTime' => $this->eventTime($opp, $event)->copy()->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:sP'),
            'conversionValue'    => $value ?? 0,
            'currencyCode'       => 'BRL',
            'orderId'            => $this->eventId($opp, $event),
            'userIdentifiers'    => $ids ?: null,
        ]], $this->validateOnly);

        if (!$res['ok']) {
            return ['erro', $res['error']];
        }

        return [$this->validateOnly ? 'teste' : 'enviado', 'Google Ads aceitou a conversão.'];
    }

    // ── GA4 ───────────────────────────────────────────────────────────

    private function ga4(ClientLeadOpportunity $opp, ClientTracking $t, string $event, ?float $value): array
    {
        $raw = $this->raw($opp);
        $name = match ($event) { 'Lead' => 'generate_lead', 'QualifiedLead' => 'qualify_lead', 'Purchase' => 'close_convert_lead', default => Str::snake($event) };
        $body = [
            // Sem o cookie _ga do visitante, o App usa um id estável do lead.
            'client_id' => $raw['ga_client_id'] ?? (crc32($opp->client_lead_id) . '.' . $opp->received_at->timestamp),
            'timestamp_micros' => $this->eventTime($opp, $event)->getTimestampMs() * 1000,
            'events' => [['name' => $name, 'params' => array_filter([
                'currency' => $value !== null ? 'BRL' : null, 'value' => $value,
                'lead_source' => $opp->channel?->kind, 'campaign' => $opp->utm_campaign, 'source' => $opp->utm_source, 'medium' => $opp->utm_medium,
                'event_id' => $this->eventId($opp, $event),
            ], fn ($v) => $v !== null && $v !== '')]],
        ];

        $url = 'https://www.google-analytics.com/' . ($this->validateOnly ? 'debug/mp/collect' : 'mp/collect') . '?' . http_build_query(['measurement_id' => $t->ga4_measurement_id, 'api_secret' => $t->ga4_api_secret]);
        $res = Http::timeout(20)->post($url, $body);
        if ($res->failed()) {
            return ['erro', 'GA4 respondeu ' . $res->status()];
        }
        if ($this->validateOnly && ($msgs = $res->json('validationMessages'))) {
            return ['erro', collect($msgs)->pluck('description')->implode(' ')];
        }

        return [$this->validateOnly ? 'teste' : 'enviado', 'GA4 recebeu o evento ' . $name . '.'];
    }

    // ── apoio ─────────────────────────────────────────────────────────

    /** Mesmo id do navegador quando houver (dedup); senão, um id estável do App. */
    public function eventId(ClientLeadOpportunity $opp, string $event): string
    {
        return $event === 'Lead' && $opp->event_id ? $opp->event_id : Str::lower($event) . '_' . $opp->id;
    }

    private function eventTime(ClientLeadOpportunity $opp, string $event): Carbon
    {
        return match ($event) {
            'Purchase'      => $opp->won_at,
            'QualifiedLead' => $opp->quality_confirmed_at,
            default         => null,
        } ?? $opp->received_at ?? now();
    }

    private function raw(ClientLeadOpportunity $opp): array
    {
        $raw = $opp->raw_payload;

        return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
    }

    /** Telefone só com dígitos e o 55 do Brasil. */
    private function phone(?string $phone): ?string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if (strlen($d) < 10) {
            return null;
        }

        return strlen($d) <= 11 ? '55' . $d : $d;
    }

    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', mb_strtolower(trim((string) $name))) ?: [];
        $parts = array_values(array_filter($parts));

        return [$parts[0] ?? null, count($parts) > 1 ? end($parts) : null];
    }

    private function hashList(?string $v): ?array
    {
        return $v === null || $v === '' ? null : [hash('sha256', $v)];
    }

    private function metaToken(): string
    {
        return OrganizationIntegration::where('provider', 'meta')->where('status', 'connected')->first()?->credentials['access_token']
            ?? throw new \RuntimeException('Integração com a Meta não está conectada.');
    }
}
