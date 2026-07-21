<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationDispatchService
{
    /**
     * Dispara um evento de notificação padrão pro n8n, um POST por contato
     * assinado no tipo (client_contact_subscriptions) e por canal marcado
     * naquela assinatura — com o texto do template já resolvido (variáveis
     * trocadas pelo valor real).
     *
     * Silenciosamente não faz nada se faltar peça (webhook não configurado,
     * ninguém assinado, template sem texto pro canal) — mesmo padrão do
     * TaskApprovalService::dispatchWebhook().
     */
    public function send(string $type, Client $client, array $variables = []): void
    {
        $webhookUrl = config('services.n8n.notification_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        $recipients = ClientContact::where('client_id', $client->id)
            ->whereHas('subscriptions', fn ($q) => $q->where('type', $type))
            ->with(['contact', 'subscriptions' => fn ($q) => $q->where('type', $type)])
            ->get();

        foreach ($recipients as $clientContact) {
            $contact = $clientContact->contact;
            $channels = $clientContact->subscriptions->first()->channels ?? [];

            foreach ($channels as $channel) {
                $this->sendOne($webhookUrl, $type, $channel, $client, $contact, $variables);
            }
        }
    }

    private function sendOne(string $webhookUrl, string $type, string $channel, Client $client, $contact, array $variables): void
    {
        $template = NotificationTemplate::where('organization_id', $client->organization_id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        if (!$template || (blank($template->subject) && blank($template->body))) {
            return;
        }

        $vars = [
            'cliente' => $client->company_name,
            'contato' => $contact->name,
            ...$variables,
        ];

        $payload = [
            'event' => $type,
            'channel' => $channel,
            'client' => [
                'id'           => $client->id,
                'company_name' => $client->company_name,
            ],
            'contact' => [
                'id'       => $contact->id,
                'name'     => $contact->name,
                'email'    => $contact->email,
                'whatsapp' => $contact->whatsapp,
            ],
            'message' => [
                'subject' => $template->subject ? $this->render($template->subject, $vars) : null,
                'body'    => $this->render($template->body, $vars),
            ],
            'fired_at' => now()->toISOString(),
        ];

        try {
            Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning("NotificationDispatchService: falha ao disparar [{$type}/{$channel}] pra contato {$contact->id}: {$e->getMessage()}");
        }
    }

    private function render(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }

        return $text;
    }
}
