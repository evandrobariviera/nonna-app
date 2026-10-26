<?php

namespace App\Services\Leads;

use App\Models\ServiceConversation;
use App\Support\PhoneKey;
use Carbon\Carbon;

/**
 * Liga a conversa do Atendimento Assistido (uazapi) à pessoa da Central de
 * Leads — até aqui eram duas representações soltas da mesma pessoa.
 *
 * Duas situações diferentes:
 *  - ENTRADA (a pessoa chegou): abre/reaproveita uma oportunidade na Central,
 *    com o anúncio de origem quando houver. É entrada quando a conversa nasce
 *    de uma mensagem da pessoa, quando ela clica num anúncio de WhatsApp
 *    (mesmo numa conversa antiga) ou quando volta a falar depois de muito tempo.
 *  - SÓ A PESSOA: a conversa foi puxada pelo cliente e a pessoa respondeu —
 *    ela existe e conversou, mas não "chegou" por conta própria; liga a
 *    conversa ao lead sem abrir cartão.
 * Conversa em que a pessoa nunca escreveu não vira lead.
 */
class WhatsappLeadLinker
{
    // Pessoa que volta a escrever depois desse silêncio conta como nova entrada.
    public const REENTRY_SILENCE_DAYS = 30;

    public function __construct(private LeadCaptureService $capture)
    {
    }

    public function link(ServiceConversation $conversation, bool $isEntry, Carbon $at, ?array $adReply = null): void
    {
        if ($conversation->is_group || !$conversation->client_id || !PhoneKey::from($conversation->contact_phone)) {
            return;
        }

        $client = $conversation->client;
        if (!$client) {
            return;
        }

        $payload = [
            'name'  => $conversation->contact_name,
            'phone' => $conversation->contact_phone,
        ];

        if ($isEntry) {
            $result = $this->capture->captureForClient($client, 'whatsapp', $payload + [
                'received_at'             => $at->toIso8601String(),
                'ad_id'                   => $adReply['sourceID'] ?? null,
                'ctwa_clid'               => $adReply['ctwaClid'] ?? null,
                'form_name'               => $adReply ? ($adReply['title'] ?? 'Anúncio WhatsApp') : null,
                'service_conversation_id' => $conversation->id,
                // O cliente já está vendo a conversa chegar no WhatsApp dele.
                'notify'                  => false,
            ]);
            $leadId = $result['client_lead_id'];
        } else {
            $leadId = $this->capture->findOrCreateLead($client, 'whatsapp', $payload, $at)->id;
        }

        if ($conversation->client_lead_id !== $leadId) {
            $conversation->forceFill(['client_lead_id' => $leadId])->saveQuietly();
        }
    }
}
