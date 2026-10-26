<?php

namespace App\Services\Whatsapp;

use App\Models\ClientIntegration;
use App\Models\ServiceConversation;
use App\Models\ServiceMessage;
use App\Services\AiService;
use App\Services\Leads\WhatsappLeadLinker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UazapiMessageIngestor
{
    // mediaType da uazapi que representam mensagem de voz (a uazapi descriptografa
    // no servidor dela - não precisamos lidar com a criptografia E2E do WhatsApp)
    private const AUDIO_MEDIA_TYPES = ['ptt', 'audio'];

    public function __construct(private AiService $aiService, private WhatsappLeadLinker $leadLinker)
    {
    }

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

        $isNewConversation = !$conversation->exists;
        $adReply = $message['content']['contextInfo']['externalAdReply'] ?? null;

        if ($isNewConversation) {
            $conversation->client_id  = $integration->client_id;
            $conversation->started_at = $sentAt;

            // Atribuição de anúncio (Meta "Clique para o WhatsApp") — só existe na primeira
            // mensagem da conversa, a uazapi replica o contextInfo original do WhatsApp ali.
            // Capturado só na criação de propósito: uma 2ª mensagem na mesma thread nunca
            // carrega esse bloco (ou carrega o de uma mensagem RESPONDIDA, que não é a
            // origem real da conversa) — sobrescrever depois estragaria a atribuição.
            if ($adReply) {
                $conversation->ad_source_id = $adReply['sourceID'] ?? null;
                $conversation->ad_title     = $adReply['title'] ?? null;
                $conversation->ad_ctwa_clid = $adReply['ctwaClid'] ?? null;
            }
        }

        // Nome salvo na agenda do cliente vence; sem ele (quem chega pela 1ª vez
        // quase nunca está salvo), o nome do perfil de WhatsApp da pessoa.
        $conversation->contact_name  = $chat['wa_contactName'] ?? $chat['name'] ?? $conversation->contact_name
            ?? (($message['fromMe'] ?? false) ? null : WhatsappLeadLinker::profileName($chat['wa_name'] ?? $message['senderName'] ?? null));
        $conversation->contact_phone = $chat['phone'] ?? $conversation->contact_phone;
        $conversation->is_group      = (bool) ($chat['wa_isGroup'] ?? false);

        if (!$conversation->last_message_at || $sentAt->greaterThan($conversation->last_message_at)) {
            $conversation->last_message_at = $sentAt;
        }

        $conversation->save();
        // increment() mais abaixo zera o wasChanged — guarda agora.
        $nameChanged = $conversation->wasChanged('contact_name');

        $externalMessageId = $message['messageid'] ?? $message['id'] ?? null;

        $alreadyIngested = $externalMessageId && ServiceMessage::where('conversation_id', $conversation->id)
            ->where('external_message_id', $externalMessageId)
            ->exists();

        if ($alreadyIngested) {
            return; // retry do webhook - já processada
        }

        [$body, $mediaUrl] = $this->resolveContent($integration, $message);

        ServiceMessage::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => $externalMessageId,
            'direction'           => ($message['fromMe'] ?? false) ? 'out' : 'in',
            'sent_via_api'        => (bool) ($message['wasSentByApi'] ?? false),
            'sender_name'         => $message['senderName'] ?? null,
            'body'                => $body,
            'media_url'           => $mediaUrl,
            'sent_at'             => $sentAt,
            'raw_payload'         => $payload,
        ]);

        $conversation->increment('message_count');
        $integration->update(['last_synced_at' => now(), 'status' => 'connected']);

        $this->linkLead($conversation, $sentAt, $adReply, (bool) ($message['fromMe'] ?? false), $nameChanged, $body);
    }

    /**
     * Mensagem da pessoa → liga a conversa à Central de Leads (ver WhatsappLeadLinker).
     * Só faz consulta quando há algo a decidir: clique em anúncio, conversa ainda sem
     * lead ou nome que acabou de aparecer (vale também em mensagem do cliente).
     * Nunca derruba o webhook — a mensagem já foi gravada.
     */
    private function linkLead(ServiceConversation $conversation, Carbon $sentAt, ?array $adReply, bool $fromMe, bool $nameChanged, ?string $body): void
    {
        try {
            if ($fromMe || (!WhatsappLeadLinker::isFromAd($adReply) && $conversation->client_lead_id)) {
                if ($nameChanged) {
                    $this->leadLinker->fillMissingName($conversation);
                }

                return;
            }

            $this->leadLinker->link($conversation, $sentAt, $adReply, $body);
        } catch (\Throwable $e) {
            Log::warning('UazapiMessageIngestor: falha ao ligar conversa à Central de Leads', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve o texto/mídia da mensagem. Pra texto puro, é só o campo `text`/`content`
     * (que aqui são sempre string). Pra mídia, `content` vira um objeto com os dados
     * de descriptografia (URL, mediaKey, mimetype...) - baixamos via /message/download
     * (a uazapi já entrega descriptografado) e, se for áudio, transcrevemos via Whisper
     * pra virar texto analisável no diagnóstico.
     *
     * @return array{0: ?string, 1: ?string} [body, media_url]
     */
    private function resolveContent(ClientIntegration $integration, array $message): array
    {
        $mediaType = $message['mediaType'] ?? null;

        if (empty($mediaType)) {
            return [$message['text'] ?? $message['content'] ?? null, null];
        }

        $messageId = $message['messageid'] ?? $message['id'] ?? null;
        $mediaUrl = $messageId ? $this->downloadMedia($integration, $messageId) : null;

        $body = null;
        if ($mediaUrl && in_array($mediaType, self::AUDIO_MEDIA_TYPES, true)) {
            $transcript = $this->aiService->transcribeAudio($mediaUrl);
            $body = $transcript ? "[áudio transcrito] {$transcript}" : null;
        }

        return [$body ?? "[mídia: {$mediaType}]", $mediaUrl];
    }

    // Pede pra uazapi descriptografar e servir a mídia (ela já lida com a criptografia
    // E2E do WhatsApp do lado dela - nunca lidamos com mediaKey/AES diretamente).
    private function downloadMedia(ClientIntegration $integration, string $messageId): ?string
    {
        $baseUrl = $integration->baseUrl();
        if (!$baseUrl) {
            return null;
        }

        $response = Http::withHeaders(['Token' => $integration->external_id])
            ->timeout(30)
            ->post(rtrim($baseUrl, '/') . '/message/download', ['id' => $messageId]);

        return $response->successful() ? $response->json('fileURL') : null;
    }
}
