<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationDispatchService
{
    private const SENSITIVE_VARIABLES = ['senha', 'link_definir_senha'];

    /**
     * Dispara um evento de notificação padrão pro n8n, um POST por contato
     * assinado no tipo (client_contact_subscriptions) e por canal marcado
     * naquela assinatura — com o texto do template já resolvido (variáveis
     * trocadas pelo valor real).
     *
     * Não dispara se faltar peça (webhook não configurado, ninguém assinado,
     * template sem texto pro canal) — mas toda tentativa fica registrada no
     * Histórico de Envios (notification_dispatches), com o motivo.
     */
    public function send(string $type, Client $client, array $variables = [], ?string $attachmentUrl = null): void
    {
        $recipients = ClientContact::where('client_id', $client->id)
            ->whereHas('subscriptions', fn ($q) => $q->where('type', $type))
            ->with(['contact', 'subscriptions' => fn ($q) => $q->where('type', $type)])
            ->get();

        if ($recipients->isEmpty()) {
            $this->record($type, '—', $client, null, 'skipped', 'Nenhum contato do cliente está assinado nesse tipo de mensagem.');
            return;
        }

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
        // URL do webhook é configurada por organização (Configurações > Integrações,
        // provider "n8n"), não mais via variável de ambiente — permite cada organização
        // apontar pro seu próprio n8n.
        $webhookUrl = $client->organization?->integration('n8n')?->credential('webhook_url');
        if (!$webhookUrl) {
            $this->record($type, $channel, $client, $contact, 'skipped', 'Integração n8n sem URL de webhook (Configurações > Integrações).');
            return;
        }

        $template = NotificationTemplate::where('organization_id', $client->organization_id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        if (!$template || (blank($template->subject) && blank($template->body))) {
            $this->record($type, $channel, $client, $contact, 'skipped', 'Mensagem Padrão sem texto pra esse canal.');
            return;
        }

        // Sem número/e-mail o n8n não tem pra onde mandar. Disparo de teste
        // (contato fictício, não salvo) passa direto — serve pra conferir no n8n.
        $address = match ($channel) {
            'whatsapp' => $contact->whatsapp,
            'email'    => $contact->email,
            default    => 'n/a',
        };
        if ($contact->exists && blank($address)) {
            $label = NotificationTemplate::$channels[$channel] ?? $channel;
            $this->record($type, $channel, $client, $contact, 'skipped', "Contato sem {$label} cadastrado.");
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

        // Texto que fica no Histórico — sem senha nem link de criar senha
        // (qualquer admin que abrisse o histórico tomaria a conta do cliente).
        $masked = array_intersect_key($vars, array_flip(self::SENSITIVE_VARIABLES));
        $body = $this->render($template->body, [...$vars, ...array_map(fn () => '••••••', $masked)]);

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning("NotificationDispatchService: falha ao disparar [{$type}/{$channel}] pra contato {$contact->id}: {$e->getMessage()}");
            // URL do webhook sai do texto — não é pra ficar exposta no histórico.
            $error = trim(preg_replace(['~\s*\(see https?://\S+\)~', '~\s*for https?://\S+~', '~https?://\S+~'], ['', '', '[webhook]'], $e->getMessage()));
            $this->record($type, $channel, $client, $contact, 'failed', 'n8n não respondeu: ' . Str::limit($error, 300), null, $body);
            return;
        }

        // Http:: não lança exceção em 4xx/5xx — antes disso um fluxo quebrado
        // no n8n passava como enviado.
        if ($response->successful()) {
            $this->record($type, $channel, $client, $contact, 'sent', null, $response->status(), $body);
            return;
        }

        $detail = Str::limit(trim(strip_tags((string) $response->body())), 300);
        $this->record(
            $type, $channel, $client, $contact, 'failed',
            "n8n respondeu com erro {$response->status()}" . ($detail !== '' ? ": {$detail}" : '.'),
            $response->status(), $body
        );
    }

    /**
     * Grava a tentativa no Histórico de Envios. Uma falha aqui nunca pode
     * derrubar o fluxo que pediu o disparo (aprovação, chamado, lead...).
     */
    private function record(string $type, string $channel, Client $client, ?Contact $contact, string $status, ?string $reason = null, ?int $httpStatus = null, ?string $body = null): void
    {
        // transaction() próprio = savepoint quando quem chamou já está numa
        // transação: um erro aqui volta só o savepoint, não envenena a
        // transação de fora (no Postgres, qualquer erro aborta a transação toda).
        try {
            DB::connection('pgsql')->transaction(fn () => NotificationDispatch::create([
                'organization_id' => $client->organization_id,
                'type'            => $type,
                'channel'         => $channel,
                'client_id'       => $client->exists ? $client->id : null,
                'contact_id'      => $contact?->exists ? $contact->id : null,
                'client_name'     => $client->company_name,
                'contact_name'    => $contact?->name,
                'recipient'       => match ($channel) {
                    'whatsapp' => $contact?->whatsapp,
                    'email'    => $contact?->email,
                    default    => null,
                },
                'status'          => $status,
                'reason'          => $reason,
                'http_status'     => $httpStatus,
                'message_body'    => $body,
                'is_test'         => !$client->exists,
            ]));
        } catch (\Throwable $e) {
            Log::warning("NotificationDispatchService: não consegui gravar o histórico [{$type}/{$channel}]: {$e->getMessage()}");
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
