<?php

namespace App\Services\Leads;

use App\Models\ClientLead;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientLeadSource;
use App\Models\LeadChannel;
use App\Models\Organization;
use App\Services\NotificationDispatchService;
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

            $client = $source?->client;
            $receivedAt = isset($payload['received_at']) ? Carbon::parse($payload['received_at']) : now();

            $lead = $this->resolveLead($client?->id, $channel?->id, $payload, $receivedAt);
            [$opportunity, $isNew] = $this->resolveOpportunity($lead, $source, $channel, $payload, $receivedAt);

            if ($isNew && $client) {
                $this->notifications->send('lead_capturado', $client, [
                    'lead_nome'     => $lead->name ?? '(sem nome)',
                    'lead_telefone' => $lead->phone ?? '—',
                    'canal'         => $channel?->kindLabel() ?? $payload['source_channel'],
                ]);
            }

            return [
                'client_lead_id'      => $lead->id,
                'client_id'           => $client?->id,
                'opportunity_id'      => $opportunity->id,
                'opportunity_is_new'  => $isNew,
                'stage'               => $opportunity->stage,
            ];
        });
    }

    private function resolveLead(?string $clientId, ?string $channelId, array $payload, Carbon $receivedAt): ClientLead
    {
        $phone = $payload['phone'] ?? null;
        $email = $payload['email'] ?? null;

        // Dedup só faz sentido quando já sabemos de qual Cliente é o lead —
        // sem isso (triagem manual) cada conversão vira uma pessoa nova,
        // já que não há como saber se duas conversões "sem cliente" são a
        // mesma pessoa em negócios diferentes.
        $existing = null;
        if ($clientId) {
            $existing = ClientLead::where('client_id', $clientId)
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();
        }

        if ($existing) {
            $existing->fill([
                'name'              => $existing->name ?: ($payload['name'] ?? null),
                'email'             => $existing->email ?: $email,
                'phone'             => $existing->phone ?: $phone,
                'city'              => $existing->city ?: ($payload['city'] ?? null),
                'state'             => $existing->state ?: ($payload['state'] ?? null),
                'last_contacted_at' => $receivedAt,
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
        $reopenable = ClientLeadOpportunity::where('client_lead_id', $lead->id)
            ->whereNotIn('stage', ['ganho', 'perdido'])
            ->where('created_at', '>=', now()->subHours(self::REOPEN_WINDOW_HOURS))
            ->orderByDesc('created_at')
            ->first();

        if ($reopenable) {
            $reopenable->fill([
                'utm_source'       => $reopenable->utm_source ?: ($payload['utm_source'] ?? null),
                'utm_medium'       => $reopenable->utm_medium ?: ($payload['utm_medium'] ?? null),
                'utm_campaign'     => $reopenable->utm_campaign ?: ($payload['utm_campaign'] ?? null),
                'utm_content'      => $reopenable->utm_content ?: ($payload['utm_content'] ?? null),
                'utm_term'         => $reopenable->utm_term ?: ($payload['utm_term'] ?? null),
                'fbclid'           => $reopenable->fbclid ?: ($payload['fbclid'] ?? null),
                'gclid'            => $reopenable->gclid ?: ($payload['gclid'] ?? null),
                'ctwa_clid'        => $reopenable->ctwa_clid ?: ($payload['ctwa_clid'] ?? null),
                'event_id'         => $payload['event_id'] ?? $reopenable->event_id,
                'received_at'      => $receivedAt,
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
            'event_id'               => $payload['event_id'] ?? null,
            'form_name'              => $payload['form_name'] ?? null,
            'received_at'            => $receivedAt,
            'raw_payload'            => $payload['raw_payload'] ?? null,
        ]);

        return [$opportunity, true];
    }
}
