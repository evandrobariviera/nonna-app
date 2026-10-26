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

        // Primeira mensagem da pessoa por conversa — sem
        // tocar em raw_payload (pesado, ver project-service-diagnostic-ad-attribution).
        $firsts = DB::connection('pgsql')->table('service_messages')
            ->selectRaw("conversation_id, MIN(sent_at) FILTER (WHERE direction = 'in') AS first_inbound_at")
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
                $linker->link($conversation, $at, $adReply);
            } catch (\Throwable $e) {
                $this->warn("Conversa {$conversation->id}: {$e->getMessage()}");
                DB::purge('pgsql');
            }
        }

        $this->table(
            ['Cliente', 'Conversas c/ resposta', 'Cartões (vieram de anúncio)', 'Pessoas novas', 'Já estavam na Central'],
            collect($stats)->map(fn ($r, $name) => [$name, ...array_values($r)])->values()->all()
        );
        $this->info($dry ? 'Simulação — nada foi gravado.' : 'Concluído.');

        return self::SUCCESS;
    }
}
