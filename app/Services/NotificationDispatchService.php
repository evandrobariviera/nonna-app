<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contact;
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
     * ninguém assinado, template sem texto pro canal).
     */
    public function send(string $type, Client $client, array $variables = [], ?string $attachmentUrl = null): void
    {
        $recipients = ClientContact::where('client_id', $client->id)
            ->whereHas('subscriptions', fn ($q) => $q->where('type', $type))
            ->with(['contact', 'subscriptions' => fn ($q) => $q->where('type', $type)])
            ->get();

        foreach ($recipients as $clientContact) {
            $contact = $clientContact->contact;
            $channels = $clientContact->subscriptions->first()->channels ?? [];

            foreach ($channels as $channel) {
                $this->dispatch($type, $channel, $client, $contact, $variables, $attachmentUrl);
            }
        }
    }

    /**
     * Dispara pra um (contato, canal) já conhecido de antemão — usado quando
     * quem chama já tem a lista exata de destinatários (ex: TaskApprovalService,
     * que snapshota os canais no TaskApprovalToken no momento da submissão,
     * em vez de reconsultar a assinatura atual).
     */
    public function dispatch(string $type, string $channel, Client $client, Contact $contact, array $variables = [], ?string $attachmentUrl = null): void
    {
        $webhookUrl = config('services.n8n.notification_webhook_url');
        if (!$webhookUrl) {
            return;
        }

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
                'subject'        => $template->subject ? $this->render($template->subject, $vars) : null,
                'body'           => $this->render($template->body, $vars),
                'attachment_url' => $attachmentUrl,
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
