<?php

namespace App\Services\Leads;

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\ClientLeadOpportunity;
use App\Models\ServiceMessage;
use App\Services\AiService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Avalia cada lead pela régua do cliente (agente lead-qualifier): qualidade
 * (bom/fraco/sem perfil), desfecho sugerido e um resumo. É só sugestão — quem
 * atende confirma com um toque na ficha (ClientLeadOpportunity::confirmQuality).
 */
class LeadQualifier
{
    // Conversa longa vai cortada no começo — o fim é o que diz em que pé está.
    private const MAX_TRANSCRIPT_CHARS = 12000;

    // Não avalia no meio do papo: espera a conversa assentar.
    public const QUIET_HOURS = 2;

    public function __construct(private AiService $ai)
    {
    }

    public static function agent(): ?AiAgent
    {
        return AiAgent::bySlug(AiAgent::SLUG_LEAD_QUALIFIER);
    }

    /**
     * Cartões que precisam de (nova) avaliação: cliente com régua revisada, nunca
     * avaliado ou com mensagem nova desde a última avaliação, conversa parada há
     * QUIET_HOURS. Fechados só entram se nunca foram avaliados.
     */
    public static function pending(): Builder
    {
        return ClientLeadOpportunity::query()
            ->select('client_lead_opportunities.*')
            ->join('client_leads', 'client_leads.id', '=', 'client_lead_opportunities.client_lead_id')
            ->join('clients', 'clients.id', '=', 'client_leads.client_id')
            ->leftJoin('service_conversations', 'service_conversations.id', '=', 'client_lead_opportunities.service_conversation_id')
            ->whereNotNull('clients.lead_rule')
            ->whereNotNull('clients.lead_rule_reviewed_at')
            ->where(fn ($q) => $q->whereNull('client_lead_opportunities.ai_evaluated_at')
                ->orWhere(fn ($q) => $q->whereColumn('service_conversations.last_message_at', '>', 'client_lead_opportunities.ai_evaluated_at')
                    ->whereNotIn('client_lead_opportunities.stage', ClientLeadOpportunity::CLOSED_STAGES)))
            ->where(fn ($q) => $q->whereNull('service_conversations.id')
                ->orWhere('service_conversations.last_message_at', '<=', now()->subHours(self::QUIET_HOURS)))
            ->orderByDesc('client_lead_opportunities.received_at');
    }

    public function evaluate(ClientLeadOpportunity $opportunity, ?AiAgent $agent = null): void
    {
        $agent ??= self::agent();
        if (!$agent) {
            throw new \RuntimeException('Agente "Central de Leads — Avaliação de Lead" não está ativo (Agentes & IA).');
        }

        $opportunity->loadMissing(['lead.client', 'channel', 'ad:id,name', 'campaign:id,name']);
        $client = $opportunity->lead->client;
        if (!$client?->hasReviewedLeadRule()) {
            return;
        }

        $answer = $this->ai->runStructured(
            $agent,
            json_encode($this->input($opportunity, $client), JSON_UNESCAPED_UNICODE),
            [],
            null,
            $client->id,
            'lead_qualification',
        );

        $quality = $answer['qualidade'] ?? null;
        $outcome = $answer['desfecho'] ?? null;
        $value = $answer['valor_venda'] ?? null;

        $opportunity->update([
            'ai_quality'       => array_key_exists((string) $quality, ClientLeadOpportunity::$qualities) ? $quality : null,
            'ai_outcome'       => array_key_exists((string) $outcome, ClientLeadOpportunity::$outcomes) ? $outcome : null,
            'ai_outcome_value' => is_numeric($value) && $value > 0 ? round((float) $value, 2) : null,
            'ai_summary'       => filled($answer['resumo'] ?? null) ? mb_substr(trim($answer['resumo']), 0, 1000) : null,
            'ai_reason'        => filled($answer['motivo'] ?? null) ? mb_substr(trim($answer['motivo']), 0, 1000) : null,
            'ai_evaluated_at'  => now(),
        ]);
    }

    public function input(ClientLeadOpportunity $opportunity, Client $client): array
    {
        $conversation = $this->transcript($opportunity);
        $lastAt = $opportunity->conversation?->last_message_at ?? $opportunity->received_at ?? $opportunity->created_at;

        return [
            'cliente'      => $client->displayName(),
            'regua'        => $client->lead_rule,
            'ticket_medio' => $client->average_ticket !== null ? (float) $client->average_ticket : null,
            'lead'         => [
                'nome'       => $opportunity->lead->name,
                'canal'      => $opportunity->channel?->kindLabel(),
                'anuncio'    => $opportunity->ad?->name,
                'campanha'   => $opportunity->campaign?->name,
                'chegou_em'  => ($opportunity->received_at ?? $opportunity->created_at)->format('d/m/Y H:i'),
                'respostas'  => $opportunity->answers(),
            ],
            'conversa'          => $conversation['messages'],
            'conversa_cortada'  => $conversation['truncated'],
            'dias_sem_mensagem' => (int) $lastAt->diffInDays(now()),
            'estagio_atual'     => $opportunity->stageLabel(),
            'valor_anotado'     => $opportunity->deal_value !== null ? (float) $opportunity->deal_value : null,
        ];
    }

    /** @return array{messages: array<int, array{quando: string, quem: string, texto: string}>, truncated: bool} */
    private function transcript(ClientLeadOpportunity $opportunity): array
    {
        if (!$opportunity->service_conversation_id) {
            return ['messages' => [], 'truncated' => false];
        }

        // Do fim pro começo até o orçamento de caracteres; sem raw_payload (jsonb pesado).
        $messages = [];
        $chars = 0;
        $truncated = false;
        $rows = ServiceMessage::where('conversation_id', $opportunity->service_conversation_id)
            ->orderByDesc('sent_at')
            ->limit(400)
            ->get(['direction', 'body', 'media_url', 'sent_at']);

        foreach ($rows as $row) {
            $text = trim((string) $row->body) ?: ($row->media_url ? '[mídia]' : '');
            if ($text === '') {
                continue;
            }
            $chars += mb_strlen($text);
            if ($chars > self::MAX_TRANSCRIPT_CHARS) {
                $truncated = true;
                break;
            }
            $messages[] = [
                'quando' => $row->sent_at->setTimezone(config('app.timezone'))->format('d/m H:i'),
                'quem'   => $row->direction === 'in' ? 'lead' : 'empresa',
                'texto'  => $text,
            ];
        }

        return ['messages' => array_reverse($messages), 'truncated' => $truncated || $rows->count() === 400];
    }
}
