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
 *  - VEIO DE ANÚNCIO (clique num anúncio de WhatsApp, mesmo numa conversa
 *    antiga): abre/reaproveita uma oportunidade na Central com o anúncio de
 *    origem — é mídia paga, entra mesmo que seja curioso (a qualificação diz
 *    depois se era bom ou fraco).
 *  - QUALQUER OUTRA CONVERSA: só liga a conversa à pessoa, sem cartão. O
 *    WhatsApp do negócio tem aluno, fornecedor, curioso — cartão para esses só
 *    quando a IA identificar interesse real (etapa de qualificação).
 * Conversa em que a pessoa nunca escreveu não vira lead.
 */
class WhatsappLeadLinker
{
    public function __construct(private LeadCaptureService $capture)
    {
    }

    public function link(ServiceConversation $conversation, Carbon $at, ?array $adReply = null): void
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

        if (self::isFromAd($adReply)) {
            $result = $this->capture->captureForClient($client, 'whatsapp', $payload + [
                'received_at'             => $at->toIso8601String(),
                'ad_id'                   => $adReply['sourceID'],
                'ctwa_clid'               => $adReply['ctwaClid'] ?? null,
                'form_name'               => $adReply['title'] ?? 'Anúncio WhatsApp',
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

    public static function isFromAd(?array $adReply): bool
    {
        return filled($adReply['sourceID'] ?? null);
    }
}
