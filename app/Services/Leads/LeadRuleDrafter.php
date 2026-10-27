<?php

namespace App\Services\Leads;

use App\Models\AiAgent;
use App\Models\BrandDossier;
use App\Models\Client;
use App\Models\ClientLeadOpportunity;
use App\Models\ServiceDiagnostic;
use App\Services\AiService;
use Illuminate\Support\Str;

/**
 * Rascunha a régua de lead qualificado do cliente (agente lead-rule-drafter) a
 * partir do que a equipe já sabe dele — briefing, dossiê, diagnóstico de
 * atendimento — e de leads reais. Só rascunho: vale depois que alguém revisa e salva.
 */
class LeadRuleDrafter
{
    private const MAX_BRIEFING_CHARS = 8000;
    private const SAMPLE_LEADS = 15;

    public function __construct(private AiService $ai)
    {
    }

    public function draft(Client $client, ?int $userId = null): string
    {
        $agent = AiAgent::bySlug(AiAgent::SLUG_LEAD_RULE_DRAFTER);
        if (!$agent) {
            throw new \RuntimeException('Agente "Central de Leads — Rascunho da Régua" não está ativo (Agentes & IA).');
        }

        $answer = $this->ai->runStructured(
            $agent,
            json_encode($this->input($client), JSON_UNESCAPED_UNICODE),
            [],
            $userId,
            $client->id,
            'lead_rule_draft',
        );

        $rule = trim((string) ($answer['regua'] ?? ''));
        if ($rule === '') {
            throw new \RuntimeException('A IA não devolveu a régua. Tente de novo.');
        }

        return $rule;
    }

    private function input(Client $client): array
    {
        $dossier = BrandDossier::where('client_id', $client->id)->with('personas')->latest('version')->first();
        $diagnostic = ServiceDiagnostic::where('client_id', $client->id)->with('personas')->latest('period_end')->first();

        $leads = ClientLeadOpportunity::whereHas('lead', fn ($q) => $q->where('client_id', $client->id))
            ->latest('received_at')
            ->limit(self::SAMPLE_LEADS)
            ->get(['id', 'form_answers', 'raw_payload', 'lead_channel_id'])
            ->map(fn ($o) => collect($o->answers())->map(fn ($a) => "{$a['label']}: " . Str::limit($a['value'], 300))->implode(' | '))
            ->filter()
            ->values();

        return [
            'cliente'      => $client->displayName(),
            'ticket_medio' => $client->average_ticket !== null ? (float) $client->average_ticket : null,
            'briefing'     => $client->briefing ? Str::limit(trim(html_entity_decode(strip_tags($client->briefing))), self::MAX_BRIEFING_CHARS) : null,
            'dossie'       => $dossier ? array_filter([
                'como_compra'       => $dossier->bloco01_como_compra,
                'gera_confianca'    => $dossier->bloco01_gera_confianca,
                'contexto_mercado'  => $dossier->bloco01_contexto_mercado,
                'personas'          => $dossier->personas->map(fn ($p) => array_filter($p->only([
                    'tipo', 'idade_genero', 'cargo_setor', 'renda_contexto', 'o_que_valida_compra', 'dores_principais', 'o_que_motiva',
                ])))->all(),
            ]) : null,
            'diagnostico_atendimento' => $diagnostic ? [
                'personas' => $diagnostic->personas->map(fn ($p) => array_filter($p->only(['name', 'profile', 'behavior'])))->all(),
                'gargalos' => $diagnostic->gaps,
            ] : null,
            'exemplos_de_leads' => $leads->all(),
        ];
    }
}
