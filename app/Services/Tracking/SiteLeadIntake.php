<?php

namespace App\Services\Tracking;

use App\Models\ClientLeadOpportunity;
use App\Models\ClientTracking;
use App\Services\Leads\LeadCaptureService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Entrada única dos leads de site, qualquer que seja a plataforma: a Nonna Tag
 * no navegador (site sem servidor próprio) ou o servidor do site — plugin do
 * WordPress, Nonna CMS, outro sistema — pela conexão assinada.
 * Contrato: .claude/docs/site-connection-api.md
 */
class SiteLeadIntake
{
    public function __construct(private LeadCaptureService $capture) {}

    /**
     * @param array $body     corpo do envio (formato do contrato)
     * @param array $context  ['via' => 'tag'|'server', 'platform' => 'wordpress/2.0.0', 'site' => origem, 'ip' => ..., 'user_agent' => ...]
     * @return array{status: string, message?: string, client_lead_id?: string, opportunity_id?: string}
     *   status: created (oportunidade nova) | existing (voltou para a que estava aberta) | duplicate (event_id já recebido) | ignored (sem contato)
     */
    public function receive(ClientTracking $tracking, array $body, array $context): array
    {
        $email = filter_var(trim((string) ($body['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null;
        $phone = preg_replace('/[^\d+]/', '', (string) ($body['phone'] ?? '')) ?: null;
        $eventId = Str::limit(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($body['event_id'] ?? '')), 120, '') ?: null;
        if (!$email && !$phone) {
            return ['status' => 'ignored', 'message' => 'Lead sem e-mail e sem telefone.'];
        }
        // mesmo lead mandado 2x (clique duplo, reenvio da fila depois de resposta perdida)
        if ($eventId && ($dup = ClientLeadOpportunity::where('event_id', $eventId)->first(['id', 'client_lead_id']))) {
            return ['status' => 'duplicate', 'client_lead_id' => $dup->client_lead_id, 'opportunity_id' => $dup->id];
        }

        $attr = is_array($body['attribution'] ?? null) ? $body['attribution'] : [];
        $last = array_filter(is_array($attr['last'] ?? null) ? $attr['last'] : [], 'is_scalar');
        $first = array_filter(is_array($attr['first'] ?? null) ? $attr['first'] : [], 'is_scalar');
        $touch = $last ?: $first;
        $text = fn ($v, $n = 190) => is_scalar($v) && trim((string) $v) !== '' ? Str::limit(trim(strip_tags((string) $v)), $n, '') : null;
        $fields = collect(is_array($body['fields'] ?? null) ? $body['fields'] : [])
            ->take(40)->mapWithKeys(fn ($v, $k) => [Str::limit((string) $k, 60, '') => $text(is_array($v) ? implode(', ', array_filter($v, 'is_scalar')) : $v, 300)])->filter()->all();

        $result = $this->capture->captureForClient($tracking->client, 'site', [
            'name'             => $text($body['name'] ?? null, 120),
            'email'            => $email,
            'phone'            => $phone,
            'city'             => $text($body['city'] ?? null, 80),
            'state'            => $text($body['state'] ?? null, 40),
            'form_name'        => $text($body['form_name'] ?? null, 120),
            'event_id'         => $eventId,
            'received_at'      => $this->receivedAt($body['received_at'] ?? null),
            'utm_source'       => $text($touch['utm_source'] ?? null),
            'utm_medium'       => $text($touch['utm_medium'] ?? null),
            'utm_campaign'     => $text($touch['utm_campaign'] ?? null),
            'utm_content'      => $text($touch['utm_content'] ?? null),
            'utm_term'         => $text($touch['utm_term'] ?? null),
            'gclid'            => $text($touch['gclid'] ?? ($first['gclid'] ?? null)),
            'fbclid'           => $text($touch['fbclid'] ?? ($first['fbclid'] ?? null)),
            'landing_page_url' => $text($touch['landing_page'] ?? ($body['page_url'] ?? null), 500),
            'raw_payload'      => array_filter([
                'via'         => $context['via'],
                'plugin'      => $text($context['platform'] ?? null, 60),
                'site'        => $text($context['site'] ?? null, 300),
                'page_url'    => $text($body['page_url'] ?? null, 500),
                'page_title'  => $text($body['page_title'] ?? null, 200),
                'fields'      => $fields,
                'fbp'         => $text($body['fbp'] ?? null, 120),
                'fbc'         => $text($body['fbc'] ?? null, 300),
                'ga_client_id' => preg_match('/^\d{5,12}\.\d{5,12}$/', (string) ($body['ga_client_id'] ?? '')) ? $body['ga_client_id'] : null,
                'gbraid'      => $text($touch['gbraid'] ?? null),
                'wbraid'      => $text($touch['wbraid'] ?? null),
                'referrer'    => $text($touch['referrer'] ?? null, 300),
                'consent'     => (bool) ($body['consent'] ?? false),
                'consent_text' => $text($body['consent_text'] ?? null, 600),
                'first_touch' => $first,
                'user_agent'  => $text($context['user_agent'] ?? null, 400),
                'ip'          => filter_var($context['ip'] ?? null, FILTER_VALIDATE_IP) ?: null,
            ], fn ($v) => $v !== null && $v !== []),
        ]);

        return [
            'status'         => $result['opportunity_is_new'] ? 'created' : 'existing',
            'client_lead_id' => $result['client_lead_id'],
            'opportunity_id' => $result['opportunity_id'],
        ];
    }

    /** Hora em que a pessoa enviou (a fila do site pode entregar horas depois). Nunca no futuro nem antes de 30 dias. */
    private function receivedAt(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            $at = \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        // No fuso do App: a coluna não guarda fuso, e "22:00+00:00" viraria 22:00 de Brasília.
        return $at->isFuture() || $at->lt(now()->subDays(30)) ? null : $at->setTimezone(config('app.timezone'))->toIso8601String();
    }

    /** Onde o site foi visto por último (tag carregada ou servidor conectado), para a aba Rastreamento. */
    public static function markSeen(ClientTracking $tracking, string $kind, array $info): void
    {
        if (Cache::add("nonna_site_seen_lock:{$kind}:{$tracking->id}", 1, 600)) {
            Cache::put("nonna_site_seen:{$kind}:{$tracking->id}", $info + ['at' => now()->toIso8601String()], now()->addDays(30));
        }
    }

    /** @return ?array{at: string, host?: string, platform?: string} */
    public static function lastSeen(ClientTracking $tracking, string $kind): ?array
    {
        return Cache::get("nonna_site_seen:{$kind}:{$tracking->id}");
    }
}
