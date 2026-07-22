<?php

namespace App\Services\Whatsapp;

use App\Models\ClientIntegration;
use App\Models\ServiceConversation;
use App\Models\ServiceMessage;
use Illuminate\Support\Carbon;

class UazapiMessageIngestor
{
    /**
     * Normaliza um evento de webhook da uazapiGO em service_conversations/service_messages.
     * Formato de referência (evento "messages", texto simples):
     * { EventType, instanceName, owner, token, chat: {...}, message: {...} }
     */
    public function ingest(array $payload): void
    {
        if (($payload['EventType'] ?? null) !== 'messages') {
            return; // outros eventos (conexão, presença, etc.) ignorados por enquanto
        }

        $token = $payload['token'] ?? null;
        $integration = ClientIntegration::where('provider', 'uazapi')
            ->where('external_id', $token)
            ->first();

        if (!$integration) {
            throw new \RuntimeException("Nenhuma integração uazapi encontrada para o token informado.");
        }

        $chat    = $payload['chat'] ?? [];
        $message = $payload['message'] ?? [];

        $threadId = $chat['wa_chatid'] ?? $message['chatid'] ?? null;
        if (!$threadId) {
            return;
        }

        // Precisa converter pro fuso do app (America/Sao_Paulo) já na criação - sem o
        // 2º argumento, Carbon guarda o instante em UTC e o valor gravado no banco
        // (coluna timestamp sem timezone) fica ~3h "no futuro" quando lido de volta,
        // porque a leitura assume que o texto salvo já está no fuso do app.
        $sentAt = isset($message['messageTimestamp'])
            ? Carbon::createFromTimestampMs((int) $message['messageTimestamp'], config('app.timezone'))
            : now();

        $conversation = ServiceConversation::firstOrNew([
            'client_integration_id' => $integration->id,
            'external_thread_id'    => $threadId,
        ]);

        if (!$conversation->exists) {
            $conversation->client_id  = $integration->client_id;
            $conversation->started_at = $sentAt;
        }

        $conversation->contact_name  = $chat['wa_contactName'] ?? $chat['name'] ?? $conversation->contact_name;
        $conversation->contact_phone = $chat['phone'] ?? $conversation->contact_phone;
        $conversation->is_group      = (bool) ($chat['wa_isGroup'] ?? false);

        if (!$conversation->last_message_at || $sentAt->greaterThan($conversation->last_message_at)) {
            $conversation->last_message_at = $sentAt;
        }

        $conversation->save();

        $externalMessageId = $message['messageid'] ?? $message['id'] ?? null;

        $alreadyIngested = $externalMessageId && ServiceMessage::where('conversation_id', $conversation->id)
            ->where('external_message_id', $externalMessageId)
            ->exists();

        if ($alreadyIngested) {
            return; // retry do webhook - já processada
        }

        ServiceMessage::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => $externalMessageId,
            'direction'           => ($message['fromMe'] ?? false) ? 'out' : 'in',
            'sent_via_api'        => (bool) ($message['wasSentByApi'] ?? false),
            'sender_name'         => $message['senderName'] ?? null,
            'body'                => $message['text'] ?? $message['content'] ?? null,
            // uazapi ainda não teve um evento de mídia observado - campo fica pronto,
            // mapeamento do nome exato do campo de URL fica pendente até termos um exemplo real
            'media_url'           => $message['mediaUrl'] ?? null,
            'sent_at'             => $sentAt,
            'raw_payload'         => $payload,
        ]);

        $conversation->increment('message_count');
        $integration->update(['last_synced_at' => now(), 'status' => 'connected']);
    }
}
