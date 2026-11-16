<?php

namespace App\Http\Middleware;

use App\Models\ClientTracking;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conexão do site (servidor → App). Cada envio vem assinado com a senha do
 * site, que nunca trafega:
 *
 *   X-Nonna-Site:      chave do site (24 caracteres)
 *   X-Nonna-Timestamp: segundos Unix do envio (aceita até 5 min de diferença)
 *   X-Nonna-Signature: hex(HMAC-SHA256("{timestamp}.{corpo cru}", senha))
 *
 * Quem interceptar um envio não descobre a senha nem consegue reaproveitá-lo
 * depois de 5 min; dentro da janela, o event_id repetido é descartado.
 * Contrato completo: .claude/docs/site-connection-api.md
 */
class VerifySiteSignature
{
    public const TOLERANCE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-Nonna-Site');
        $ts = (string) $request->header('X-Nonna-Timestamp');
        $sig = strtolower((string) $request->header('X-Nonna-Signature'));

        if (!preg_match('/^[a-z0-9]{24}$/', $key) || !ctype_digit($ts) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
            return $this->deny('Faltam os cabeçalhos da conexão (X-Nonna-Site, X-Nonna-Timestamp, X-Nonna-Signature).');
        }
        if (abs(time() - (int) $ts) > self::TOLERANCE) {
            return $this->deny('O relógio do servidor do site está fora da hora (mais de 5 minutos de diferença).', 'clock_skew', ['server_time' => time()]);
        }

        $tracking = $this->findTracking($key);
        $secret = $tracking?->site_secret;
        if (!$tracking || !$tracking->client || !$secret
            || !hash_equals(hash_hmac('sha256', $ts . '.' . $request->getContent(), $secret), $sig)) {
            return $this->deny('Código de conexão inválido. Copie o código atual na aba Rastreamento do Nonna App.');
        }

        // Por site, além do limite por IP da rota: um site invadido não inunda a Central.
        if (RateLimiter::tooManyAttempts("site-conn:{$key}", 60)) {
            return response()->json(['status' => 'rate_limited', 'message' => 'Muitos envios em pouco tempo. Tente de novo em 1 minuto.'], 429, ['Retry-After' => 60]);
        }
        RateLimiter::hit("site-conn:{$key}", 60);

        $request->attributes->set('site_tracking', $tracking);

        return $next($request);
    }

    protected function findTracking(string $siteKey): ?ClientTracking
    {
        return ClientTracking::withoutGlobalScopes()->with('client')->where('site_key', $siteKey)->first();
    }

    private function deny(string $message, string $status = 'unauthorized', array $extra = []): Response
    {
        return response()->json(['status' => $status, 'message' => $message] + $extra, 401);
    }
}
