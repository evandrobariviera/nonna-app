<?php

namespace App\Services\Leads;

use App\Models\Client;
use App\Models\ClientLead;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientLeadSource;
use App\Models\LeadChannel;
use App\Models\Organization;
use App\Services\NotificationDispatchService;
use App\Support\PhoneKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadCaptureService
{
    // Janela de dedup por Contato: uma conversão nova (mesma pessoa) só reabre
    // uma Oportunidade já aberta se ela tiver menos de 72h; passado isso, vira
    // cartão novo no Kanban, sempre preso à mesma client_leads (decisão 19/08).
    private const REOPEN_WINDOW_HOURS = 72;

    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    /**
     * Recebe o payload já normalizado pelo n8n (mesmo formato não importa a
     * fonte original) e resolve: canal -> fonte -> Cliente -> pessoa (dedup) ->
     * Oportunidade (nova ou reaberta). Nunca derruba um lead — se não achar o
     * Cliente pelo (channel, source_identifier), grava mesmo assim com
     * client_id null pra triagem manual.
     */
    public function capture(Organization $organization, array $payload): array
    {
        return DB::connection('pgsql')->transaction(function () use ($organization, $payload) {
            $channel = LeadChannel::where('organization_id', $organization->id)
                ->where('kind', $payload['source_channel'])
                ->first();

            $source = null;
            if ($channel) {
                $source = ClientLeadSource::where('lead_channel_id', $channel->id)
                    ->where('external_id', $payload['source_identifier'])
                    ->where('is_active', true)
                    ->whereHas('client', fn ($q) => $q->where('organization_id', $organization->id))
                    ->first();
            }

            return $this->store($source?->client, $source, $channel, $payload);
        });
    }

    /**
     * Entrada de lead cujo Cliente já é conhecido de antemão — hoje, a conversa
     * de WhatsApp que chega pelo número de atendimento do próprio cliente
     * (WhatsappLeadLinker). Pula a resolução por client_lead_sources.
     */
    public function captureForClient(Client $client, string $channelKind, array $payload): array
    {
        return DB::connection('pgsql')->transaction(function () use ($client, $channelKind, $payload) {
            $channel = LeadChannel::where('organization_id', $client->organization_id)
                ->where('kind', $channelKind)
                ->first();

            return $this->store($client, null, $channel, $payload);
        });
    }

    /**
     * Só a pessoa, sem abrir entrada nova — pra conversa que o próprio cliente
     * puxou e a pessoa respondeu: ela existe e conversou, mas não "chegou".
     */
    public function findOrCreateLead(Client $client, string $channelKind, array $payload, Carbon $at): ClientLead
    {
        $channelId = LeadChannel::where('organization_id', $client->organization_id)
            ->where('kind', $channelKind)
            ->value('id');

        return $this->resolveLead($client->id, $channelId, $payload, $at);
    }

    private function store(?Client $client, ?ClientLeadSource $source, ?LeadChannel $channel, array $payload): array
    {
        $receivedAt = isset($payload['received_at']) ? Carbon::parse($payload['received_at']) : now();

        $lead = $this->resolveLead($client?->id, $channel?->id, $payload, $receivedAt);
        [$opportunity, $isNew] = $this->resolveOpportunity($lead, $source, $channel, $payload, $receivedAt);

        // notify=false: entrada que o cliente já está vendo acontecer (a conversa
        // de WhatsApp no número dele) ou carga retroativa — avisar seria ruído.
        if ($isNew && $client && ($payload['notify'] ?? true)) {
            $this->notifications->send('lead_capturado', $client, [
                'lead_nome'          => $lead->name ?? '(sem nome)',
                'lead_telefone'      => $lead->phone ?? '—',
                'canal'              => $channel?->kindLabel() ?? $payload['source_channel'],
                'link_central_leads' => route('portal.leads.index'),
            ], related: $opportunity);
        }

        return [
            'client_lead_id'      => $lead->id,
            'client_id'           => $client?->id,
            'opportunity_id'      => $opportunity->id,
            'opportunity_is_new'  => $isNew,
            'stage'               => $opportunity->stage,
        ];
    }

    private function resolveLead(?string $clientId, ?string $channelId, array $payload, Carbon $receivedAt): ClientLead
    {
        $phone = $payload['phone'] ?? null;
        $email = $payload['email'] ?? null;
        $phoneKey = PhoneKey::from($phone);

        // Dedup só faz sentido quando já sabemos de qual Cliente é o lead —
        // sem isso (triagem manual) cada conversão vira uma pessoa nova,
        // já que não há como saber se duas conversões "sem cliente" são a
        // mesma pessoa em negócios diferentes. Telefone compara pela chave
        // DDD + 8 dígitos (PhoneKey), não pelo texto cru.
        $existing = null;
        if ($clientId && ($phoneKey || $email)) {
            $existing = ClientLead::where('client_id', $clientId)
                ->where(function ($q) use ($phoneKey, $email) {
                    if ($phoneKey) {
                        $q->orWhere('phone_key', $phoneKey);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();
        }

        if ($existing) {
            // Carga retroativa chega fora de ordem — first/last só alargam o intervalo.
            $existing->fill([
                'name'               => $existing->name ?: ($payload['name'] ?? null),
                'email'              => $existing->email ?: $email,
                'phone'              => $existing->phone ?: $phone,
                'city'               => $existing->city ?: ($payload['city'] ?? null),
                'state'              => $existing->state ?: ($payload['state'] ?? null),
                'first_contacted_at' => $existing->first_contacted_at?->lt($receivedAt) ? $existing->first_contacted_at : $receivedAt,
                'last_contacted_at'  => $existing->last_contacted_at?->gt($receivedAt) ? $existing->last_contacted_at : $receivedAt,
            ]);
            $existing->save();

            return $existing;
        }

        return ClientLead::create([
            'client_id'             => $clientId,
            'first_seen_channel_id' => $channelId,
            'name'                  => $payload['name'] ?? null,
            'email'                 => $email,
            'phone'                 => $phone,
            'city'                  => $payload['city'] ?? null,
            'state'                 => $payload['state'] ?? null,
            'first_contacted_at'    => $receivedAt,
            'last_contacted_at'     => $receivedAt,
        ]);
    }

    private function resolveOpportunity(ClientLead $lead, ?ClientLeadSource $source, ?LeadChannel $channel, array $payload, Carbon $receivedAt): array
    {
        // Janela medida pela data em que a entrada aconteceu (não por "agora") —
        // assim a carga retroativa de conversas antigas agrupa igual ao tempo real.
        $reopenable = ClientLeadOpportunity::where('client_lead_id', $lead->id)
            ->whereNotIn('stage', ['ganho', 'perdido'])
            ->whereRaw('COALESCE(received_at, created_at) BETWEEN ? AND ?', [
                $receivedAt->copy()->subHours(self::REOPEN_WINDOW_HOURS),
                $receivedAt->copy()->addHours(self::REOPEN_WINDOW_HOURS),
            ])
            ->orderByDesc('created_at')
            ->first();

        $answers = FormAnswers::normalize($payload['form_answers'] ?? null)
            ?: FormAnswers::fromRawPayload($payload['raw_payload'] ?? null);

        $ad = AdAttribution::resolve($lead->client_id, $payload);

        if ($reopenable) {
            // O novo envio vira o conteúdo principal; o anterior vai pro histórico
            // em vez de ser sobrescrito (ex: a pessoa manda uma 2ª mensagem diferente).
            $previousAnswers = $reopenable->answers();
            if ($answers && $previousAnswers) {
                $history = $reopenable->previous_submissions ?? [];
                $history[] = [
                    'received_at' => ($reopenable->received_at ?? $reopenable->created_at)?->toIso8601String(),
                    'form_name'   => $reopenable->form_name,
                    'answers'     => $previousAnswers,
                ];
                $reopenable->previous_submissions = $history;
            }

            $reopenable->fill([
                'form_answers'     => $answers ?: $reopenable->form_answers,
                'form_name'        => $payload['form_name'] ?? $reopenable->form_name,
                'utm_source'       => $reopenable->utm_source ?: ($payload['utm_source'] ?? null),
                'utm_medium'       => $reopenable->utm_medium ?: ($payload['utm_medium'] ?? null),
                'utm_campaign'     => $reopenable->utm_campaign ?: ($payload['utm_campaign'] ?? null),
                'utm_content'      => $reopenable->utm_content ?: ($payload['utm_content'] ?? null),
                'utm_term'         => $reopenable->utm_term ?: ($payload['utm_term'] ?? null),
                'fbclid'           => $reopenable->fbclid ?: ($payload['fbclid'] ?? null),
                'gclid'            => $reopenable->gclid ?: ($payload['gclid'] ?? null),
                'ctwa_clid'        => $reopenable->ctwa_clid ?: ($payload['ctwa_clid'] ?? null),
                // Primeiro anúncio da janela fica com o crédito; os outros toques
                // continuam rastreáveis pela conversa/raw_payload.
                'ad_external_id'          => $reopenable->ad_external_id ?: $ad['ad_external_id'],
                'campaign_external_id'    => $reopenable->campaign_external_id ?: $ad['campaign_external_id'],
                'ad_ad_id'                => $reopenable->ad_ad_id ?: $ad['ad_ad_id'],
                'ad_campaign_id'          => $reopenable->ad_campaign_id ?: $ad['ad_campaign_id'],
                'service_conversation_id' => $reopenable->service_conversation_id ?: ($payload['service_conversation_id'] ?? null),
                'event_id'         => $payload['event_id'] ?? $reopenable->event_id,
                'received_at'      => $reopenable->received_at?->gt($receivedAt) ? $reopenable->received_at : $receivedAt,
                'raw_payload'      => $payload['raw_payload'] ?? $reopenable->raw_payload,
            ]);
            $reopenable->save();

            return [$reopenable, false];
        }

        $opportunity = ClientLeadOpportunity::create([
            'client_lead_id'         => $lead->id,
            'client_lead_source_id'  => $source?->id,
            'lead_channel_id'        => $channel?->id,
            'stage'                  => 'novo',
            'utm_source'             => $payload['utm_source'] ?? null,
            'utm_medium'             => $payload['utm_medium'] ?? null,
            'utm_campaign'           => $payload['utm_campaign'] ?? null,
            'utm_content'            => $payload['utm_content'] ?? null,
            'utm_term'               => $payload['utm_term'] ?? null,
            'landing_page_url'       => $payload['landing_page_url'] ?? null,
            'fbclid'                 => $payload['fbclid'] ?? null,
            'gclid'                  => $payload['gclid'] ?? null,
            'ctwa_clid'              => $payload['ctwa_clid'] ?? null,
            ...$ad,
            'service_conversation_id' => $payload['service_conversation_id'] ?? null,
            'event_id'               => $payload['event_id'] ?? null,
            'form_name'              => $payload['form_name'] ?? null,
            'received_at'            => $receivedAt,
            'raw_payload'            => $payload['raw_payload'] ?? null,
            'form_answers'           => $answers ?: null,
        ]);

        return [$opportunity, true];
    }
}
