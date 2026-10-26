<?php

namespace App\Console\Commands;

use App\Models\ClientLead;
use App\Models\ServiceConversation;
use App\Services\Leads\WhatsappLeadLinker;
use App\Support\PhoneKey;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga retroativa: liga as conversas do Atendimento Assistido que já existiam
 * à Central de Leads, com a mesma regra do tempo real (WhatsappLeadLinker):
 * cartão só para conversa que veio de anúncio; as demais (em que a pessoa
 * escreveu) só ligam a pessoa, sem cartão.
 * Nunca dispara aviso de "Lead Capturado".
 */
class LinkWhatsappLeads extends Command
{
    protected $signature = 'leads:link-whatsapp {--dry : Só conta, não grava nada} {--client= : Só um cliente (UUID)}';

    protected $description = 'Liga conversas antigas do WhatsApp (Atendimento Assistido) à Central de Leads';

    public function handle(WhatsappLeadLinker $linker): int
    {
        $dry = (bool) $this->option('dry');

        // Leads que chegaram pelo código antigo, antes de phone_key existir.
        if (!$dry) {
            ClientLead::whereNull('phone_key')->whereNotNull('phone')->each(fn (ClientLead $lead) => $lead->save());
        }

        // Conversa sem nome na agenda do cliente → nome do perfil de WhatsApp da
        // pessoa (última mensagem dela com alguma letra; "." e emoji não contam).
        $nameSql = "FROM (SELECT DISTINCT ON (m.conversation_id) m.conversation_id, m.sender_name
                FROM service_messages m JOIN service_conversations c ON c.id = m.conversation_id
                WHERE m.direction = 'in' AND m.sender_name ~ '[[:alpha:]]' AND NOT c.is_group
                  AND COALESCE(c.contact_name, '') = '' AND (?::uuid IS NULL OR c.client_id = ?::uuid)
                ORDER BY m.conversation_id, m.sent_at DESC) AS p";
        $clientFilter = [$this->option('client'), $this->option('client')];
        $namesFound = $dry
            ? DB::connection('pgsql')->selectOne("SELECT COUNT(*) AS n {$nameSql}", $clientFilter)->n
            : DB::connection('pgsql')->update("UPDATE service_conversations SET contact_name = p.sender_name {$nameSql} WHERE service_conversations.id = p.conversation_id", $clientFilter);
        $this->info("Conversas sem nome que ganham o nome do perfil de WhatsApp: {$namesFound}");

        // Primeira mensagem da pessoa por conversa (data e texto) — sem
        // tocar em raw_payload (pesado, ver project-service-diagnostic-ad-attribution).
        $firsts = DB::connection('pgsql')->table('service_messages')
            ->selectRaw("conversation_id, MIN(sent_at) FILTER (WHERE direction = 'in') AS first_inbound_at, (array_agg(body ORDER BY sent_at) FILTER (WHERE direction = 'in' AND COALESCE(body, '') <> ''))[1] AS first_inbound_body")
            ->groupBy('conversation_id')
            ->get()
            ->keyBy('conversation_id');

        $conversations = ServiceConversation::query()
            ->with('client:id,organization_id,nickname,company_name')
            ->where('is_group', false)
            ->whereNull('client_lead_id')
            ->whereNotNull('client_id')
            ->when($this->option('client'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->orderBy('started_at')
            ->get();

        $existingKeys = ClientLead::whereNotNull('phone_key')->get(['client_id', 'phone_key'])
            ->groupBy('client_id')->map(fn ($leads) => $leads->pluck('phone_key')->flip());

        $stats = [];
        $seenKeys = [];

        foreach ($conversations as $conversation) {
            $first = $firsts->get($conversation->id);
            $phoneKey = PhoneKey::from($conversation->contact_phone);

            if (!$first?->first_inbound_at || !$phoneKey) {
                continue; // a pessoa nunca escreveu (ou sem telefone utilizável)
            }

            $clientName = $conversation->client->nickname ?: $conversation->client->company_name;
            $row = $stats[$clientName] ??= ['conversas' => 0, 'cartoes' => 0, 'pessoas_novas' => 0, 'ja_na_central' => 0];

            $fromAd = (bool) $conversation->ad_source_id;
            $row['conversas']++;
            $row['cartoes'] += $fromAd ? 1 : 0;

            $seenKey = $conversation->client_id . '|' . $phoneKey;
            if (isset($existingKeys[$conversation->client_id][$phoneKey])) {
                $row['ja_na_central'] += isset($seenKeys[$seenKey]) ? 0 : 1;
            } elseif (!isset($seenKeys[$seenKey])) {
                $row['pessoas_novas']++;
            }
            $seenKeys[$seenKey] = true;
            $stats[$clientName] = $row;

            if ($dry) {
                continue;
            }

            $at = Carbon::parse($fromAd ? $conversation->started_at : $first->first_inbound_at);
            $adReply = $fromAd ? [
                'sourceID' => $conversation->ad_source_id,
                'ctwaClid' => $conversation->ad_ctwa_clid,
                'title'    => $conversation->ad_title,
            ] : null;

            try {
                $linker->link($conversation, $at, $adReply, $first->first_inbound_body);
            } catch (\Throwable $e) {
                $this->warn("Conversa {$conversation->id}: {$e->getMessage()}");
                DB::purge('pgsql');
            }
        }

        // Pessoa já ligada (inclusive antes desta carga) sem nome → nome da conversa.
        if (!$dry) {
            $filled = DB::connection('pgsql')->update("UPDATE client_leads SET name = c.contact_name, updated_at = NOW()
                FROM service_conversations c
                WHERE c.client_lead_id = client_leads.id AND COALESCE(client_leads.name, '') = '' AND COALESCE(c.contact_name, '') <> ''");
            $this->info("Pessoas da Central que ganharam nome: {$filled}");
        }

        $this->table(
            ['Cliente', 'Conversas c/ resposta', 'Cartões (vieram de anúncio)', 'Pessoas novas', 'Já estavam na Central'],
            collect($stats)->map(fn ($r, $name) => [$name, ...array_values($r)])->values()->all()
        );
        $this->info($dry ? 'Simulação — nada foi gravado.' : 'Concluído.');

        return self::SUCCESS;
    }
}
