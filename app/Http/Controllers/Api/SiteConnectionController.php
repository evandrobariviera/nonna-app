<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientTracking;
use App\Services\Tracking\SiteLeadIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Conexão do site, lado servidor: o site (plugin do WordPress, Nonna CMS ou
 * qualquer plataforma) entrega os leads ao App sem passar pelo n8n.
 * Assinatura conferida em VerifySiteSignature. Contrato: .claude/docs/site-connection-api.md
 *
 * Diferente da entrada da Nonna Tag (sempre 204), aqui a resposta diz o que
 * aconteceu, porque quem chama é o servidor do site e precisa saber se tenta de novo.
 */
class SiteConnectionController extends Controller
{
    public const VERSION = '1';

    /** "Testar conexão" no site: confere o código e devolve o que o site precisa para se configurar. */
    public function ping(Request $request): JsonResponse
    {
        $t = $this->tracking($request);
        $this->seen($request, $t);

        return response()->json([
            'status'   => 'ok',
            'protocol' => self::VERSION,
            'client'   => ['name' => $t->client->company_name],
            'tag'      => [
                'url'     => url("/api/t/{$t->site_key}.js"),
                'pixel'   => (bool) $t->meta_pixel_id,
                'ga4'     => (bool) $t->ga4_measurement_id,
                'ads'     => (bool) $t->google_ads_account_id,
                'clarity' => (bool) $t->clarity_id,
            ],
            'conversions_enabled' => (bool) $t->send_enabled,
            'server_time'         => time(),
        ]);
    }

    public function lead(Request $request, SiteLeadIntake $intake): JsonResponse
    {
        $t = $this->tracking($request);
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return response()->json(['status' => 'invalid', 'message' => 'O corpo precisa ser JSON.'], 422);
        }
        $this->seen($request, $t);

        $result = $intake->receive($t, $body, [
            'via'        => 'server',
            'platform'   => $this->platform($request, $body),
            'site'       => is_string($body['site_url'] ?? null) ? $body['site_url'] : null,
            // Assinado: aqui o IP e o navegador são os do visitante, informados pelo site.
            'ip'         => $body['ip'] ?? null,
            'user_agent' => $body['user_agent'] ?? null,
        ]);

        $code = match ($result['status']) {
            'created' => 201,
            'ignored' => 422,
            default   => 200,
        };

        return response()->json($result, $code);
    }

    private function tracking(Request $request): ClientTracking
    {
        return $request->attributes->get('site_tracking');
    }

    private function platform(Request $request, array $body = []): string
    {
        $p = (string) ($body['platform'] ?? $request->header('X-Nonna-Platform', ''));

        return Str::limit(preg_replace('/[^A-Za-z0-9._\/\- ]/', '', $p), 60, '') ?: 'desconhecida';
    }

    private function seen(Request $request, ClientTracking $t): void
    {
        $site = (string) $request->header('X-Nonna-Site-Url', '');
        SiteLeadIntake::markSeen($t, 'server', [
            'platform' => $this->platform($request),
            'host'     => Str::of(parse_url($site, PHP_URL_HOST) ?: '')->lower()->replace('www.', '')->toString(),
        ]);
    }
}
