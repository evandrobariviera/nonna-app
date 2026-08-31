<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dispara um evento pro n8n da organização — POST único no webhook_url
 * configurado em Configurações > Integrações (provider "n8n", status
 * "connected"). O n8n ramifica pelo campo `event`.
 *
 * Mesmo webhook_url que o NotificationDispatchService já usa; a diferença é
 * que aqui o payload é um evento de fluxo (onboarding), não uma mensagem
 * padrão resolvida por template. Silencioso se a integração não estiver
 * conectada (mesma regra do NotificationDispatchService).
 */
class N8nEventService
{
    public function dispatch(string $event, ?Organization $organization, array $payload): bool
    {
        $webhookUrl = $organization?->integration('n8n')?->credential('webhook_url');
        if (!$webhookUrl) {
            Log::info("N8nEventService: evento [{$event}] ignorado — integração n8n não conectada.");
            return false;
        }

        $body = array_merge(['event' => $event, 'fired_at' => now()->toISOString()], $payload);

        try {
            // Timeout curto — parte do fluxo roda no request do formulário
            // público de cadastro; n8n fora do ar não pode travar o cliente.
            Http::timeout(8)->post($webhookUrl, $body);
            return true;
        } catch (\Throwable $e) {
            Log::warning("N8nEventService: falha ao disparar [{$event}]: {$e->getMessage()}");
            return false;
        }
    }
}
