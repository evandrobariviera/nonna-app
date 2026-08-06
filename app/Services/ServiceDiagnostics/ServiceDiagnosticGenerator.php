<?php

namespace App\Services\ServiceDiagnostics;

use App\Models\AdAd;
use App\Models\AiAgent;
use App\Models\ClientIntegration;
use App\Models\ServiceConversation;
use App\Models\ServiceDiagnostic;
use App\Models\ServiceDiagnosticRecommendation;
use App\Services\AiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceDiagnosticGenerator
{
    // Orçamento de caracteres pra transcrição (não pro prompt inteiro) por chamada de IA —
    // conservador o bastante pra deixar folga pro resto do prompt (instruções, JSON de
    // resposta) e pro completion (max_tokens do agente) dentro do teto de 128k tokens dos
    // modelos disponíveis hoje (gpt-4o/gpt-4o-mini). ~4 caracteres por token em português é
    // uma estimativa realista; 300k caracteres ≈ 75k tokens de transcrição, sobra bastante.
    private const MAX_TRANSCRIPT_CHARS = 300000;

    public function __construct(private AiService $aiService)
    {
    }

    public function generate(ClientIntegration $integration, string $generatedBy = 'manual', ?AiAgent $agent = null): ServiceDiagnostic
    {
        $agent ??= $this->resolveAgent($integration);
        if (!$agent) {
            throw new \RuntimeException('Nenhum agente de IA configurado para este número de atendimento (aba Atendimento do cliente).');
        }

        [$periodStart, $periodEnd] = $this->resolvePeriod($integration);

        $conversations = ServiceConversation::where('client_integration_id', $integration->id)
            ->where('is_group', false)
            ->whereHas('messages', fn ($q) => $q->whereBetween('sent_at', [$periodStart, $periodEnd]))
            ->with(['messages' => fn ($q) => $q->whereBetween('sent_at', [$periodStart, $periodEnd])->orderBy('sent_at')])
            ->get();

        if ($conversations->isEmpty()) {
            throw new \RuntimeException("Nenhuma conversa encontrada entre {$periodStart->format('d/m/Y')} e {$periodEnd->format('d/m/Y')}.");
        }

        // Período grande demais (muita conversa acumulada desde o último diagnóstico) estoura
        // o limite de contexto do modelo se mandar tudo numa chamada só. Em vez de falhar,
        // quebra em pedaços cronologicamente contíguos e gera uma versão por pedaço — cada
        // diagnóstico continua completo e coerente (não é um resumo truncado de nada), só o
        // período coberto por cada versão fica menor. resolvePeriod() do próximo diagnóstico
        // sempre parte do fim do ÚLTIMO pedaço, então nada fica sem cobertura.
        $batches = $this->splitIntoBatches($conversations);

        $diagnostic = null;
        $lastCovered = $periodStart;

        foreach ($batches as $index => $batchConversations) {
            $isLastBatch = $index === array_key_last($batches);
            $batchEnd = $isLastBatch ? $periodEnd : $batchConversations->max('last_message_at');

            $diagnostic = $this->generateForBatch(
                $integration, $agent, $generatedBy, $batchConversations, $lastCovered, $batchEnd
            );

            $lastCovered = $batchEnd;
        }

        return $diagnostic;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServiceConversation>  $conversations
     */
    private function generateForBatch(
        ClientIntegration $integration,
        AiAgent $agent,
        string $generatedBy,
        $conversations,
        Carbon $periodStart,
        Carbon $periodEnd
    ): ServiceDiagnostic {
        $avgFirstResponseMinutes = $this->computeAvgFirstResponseMinutes($conversations);
        // Consultado de novo a cada lote (não só 1x fora do loop) de propósito: quando o
        // período é dividido em vários lotes, o lote 2 precisa enxergar as recomendações
        // pendentes que o lote 1 acabou de criar, não as de antes de rodar generate().
        $previousRecommendations = $this->previousPendingRecommendations($integration);

        $userMessage = $this->buildPrompt($conversations, $periodStart, $periodEnd, $previousRecommendations);

        $aiResult = $this->aiService->runStructured(
            $agent,
            $userMessage,
            context: [],
            clientId: $integration->client_id,
            trigger: 'service_diagnostic'
        );

        return DB::connection('pgsql')->transaction(function () use (
            $integration, $periodStart, $periodEnd, $generatedBy, $agent,
            $conversations, $avgFirstResponseMinutes, $aiResult, $previousRecommendations
        ) {
            $version = (int) (ServiceDiagnostic::where('client_integration_id', $integration->id)->max('version') ?? 0) + 1;

            // conversation_outcomes (classificação por conversa) é a fonte de verdade quando a
            // IA devolve ela — dá pra cruzar com atribuição de anúncio por conversa. Sem isso
            // (falha pontual da IA nesse campo específico), cai pro agregado antigo do período
            // inteiro, que não permite quebrar por campanha mas não derruba o diagnóstico.
            $outcomesByPhone = collect($aiResult['conversation_outcomes'] ?? [])
                ->filter(fn ($o) => !empty($o['contact_phone']))
                ->keyBy(fn ($o) => $o['contact_phone']);

            if ($outcomesByPhone->isNotEmpty()) {
                $salesConfirmed = $outcomesByPhone->where('outcome', 'confirmado')->count();
                $salesInNegotiation = $outcomesByPhone->where('outcome', 'negociacao')->count();
            } else {
                $salesConfirmed = max(0, (int) ($aiResult['sales_confirmed'] ?? 0));
                $salesInNegotiation = max(0, (int) ($aiResult['sales_in_negotiation'] ?? 0));
            }

            $totalConversations = $conversations->count();
            $conversionRate = $totalConversations > 0 ? round($salesConfirmed / $totalConversations * 100, 2) : 0;
            $adAttribution = $this->computeAdAttribution($conversations, $outcomesByPhone);

            $breakdown = [
                'velocidade'   => $this->normalizeResponseTimeScore($avgFirstResponseMinutes),
                'conversao'    => $this->normalizeConversionScore($conversionRate),
                'consistencia' => max(0, min(100, (int) ($aiResult['consistencia_score'] ?? 50))),
                'sentimento'   => max(0, min(100, (int) ($aiResult['sentimento_score'] ?? 50))),
            ];
            // Fórmula confirmada junto com o usuário (2026-07-22): 30% velocidade + 30%
            // conversão + 20% consistência + 20% sentimento - mesma usada nos exemplos.
            $serviceScore = (int) round(
                $breakdown['velocidade'] * 0.3
                + $breakdown['conversao'] * 0.3
                + $breakdown['consistencia'] * 0.2
                + $breakdown['sentimento'] * 0.2
            );

            $gaps = $aiResult['gaps'] ?? [];
            $avgTicket = $integration->avgTicketValue();
            $estimatedLostRevenue = $avgTicket !== null
                ? collect($gaps)->sum(fn ($g) => (int) ($g['estimated_leads_lost'] ?? 0)) * $avgTicket
                : null;

            $diagnostic = ServiceDiagnostic::create([
                'client_id'                  => $integration->client_id,
                'client_integration_id'      => $integration->id,
                'version'                    => $version,
                'period_start'               => $periodStart,
                'period_end'                 => $periodEnd,
                'status'                     => 'draft',
                'generated_by'               => $generatedBy,
                'ai_agent_id'                => $agent->id,
                'total_conversations'        => $totalConversations,
                'sales_confirmed'            => $salesConfirmed,
                'sales_in_negotiation'       => $salesInNegotiation,
                'conversion_rate'            => $conversionRate,
                'avg_first_response_minutes' => $avgFirstResponseMinutes,
                'service_score'              => $serviceScore,
                'service_score_breakdown'    => $breakdown,
                'avg_sentiment_score'        => $breakdown['sentimento'],
                'estimated_lost_revenue'     => $estimatedLostRevenue,
                'funnel_summary'             => $aiResult['funnel_summary'] ?? null,
                'gaps'                       => $gaps,
                'strengths'                  => $aiResult['strengths'] ?? null,
                'campaign_insights'          => $aiResult['campaign_insights'] ?? null,
                'ad_attribution'             => $adAttribution,
            ]);

            foreach (($aiResult['personas'] ?? []) as $i => $p) {
                $diagnostic->personas()->create([
                    'position' => $i + 1,
                    'tag'      => $p['tag'] ?? ('Persona ' . chr(65 + $i)),
                    'name'     => $p['name'] ?? 'Persona',
                    'profile'  => $p['profile'] ?? null,
                    'behavior' => $p['behavior'] ?? null,
                    'evidence' => $p['evidence'] ?? null,
                ]);
            }

            foreach (($aiResult['recommendations'] ?? []) as $i => $r) {
                $status = ($r['status'] ?? 'pendente') === 'implementada' ? 'implementada' : 'pendente';
                $previous = null;
                if (!empty($r['carries_forward_title'])) {
                    $previous = $previousRecommendations->get(Str::lower(trim($r['carries_forward_title'])));
                }

                $diagnostic->recommendations()->create([
                    'category'                   => ($r['category'] ?? 'estrutural') === 'quick_win' ? 'quick_win' : 'estrutural',
                    'title'                      => $r['title'] ?? 'Recomendação',
                    'description'                => $r['description'] ?? null,
                    'status'                     => $status,
                    'position'                   => $i + 1,
                    'previous_recommendation_id' => $previous?->id,
                    'resolved_at'                => $status === 'implementada' ? $periodEnd : null,
                ]);
            }

            return $diagnostic;
        });
    }

    private function resolveAgent(ClientIntegration $integration): ?AiAgent
    {
        $agentId = $integration->settings['ai_agent_id'] ?? null;
        if (!$agentId) {
            return null;
        }

        return AiAgent::where('id', $agentId)->where('is_active', true)->first();
    }

    /**
     * Público pra permitir uma prévia ("prontidão pro próximo diagnóstico") antes
     * de rodar a geração de verdade - ver ServiceDiagnosticController::integration().
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolvePeriod(ClientIntegration $integration): array
    {
        $last = ServiceDiagnostic::where('client_integration_id', $integration->id)
            ->orderByDesc('version')
            ->first();

        if ($last) {
            $start = $last->period_end->copy()->addDay()->startOfDay();
        } else {
            $firstMessage = ServiceConversation::where('client_integration_id', $integration->id)
                ->orderBy('started_at')
                ->value('started_at');
            $start = $firstMessage ? Carbon::parse($firstMessage)->startOfDay() : $integration->created_at->copy()->startOfDay();
        }

        $end = now();

        return [$start, $end];
    }

    /**
     * Quebra as conversas em pedaços cronologicamente contíguos (ordenados por started_at)
     * cujo texto total fica dentro de MAX_TRANSCRIPT_CHARS — cada pedaço vira uma chamada de
     * IA e um ServiceDiagnostic próprio. Uma conversa sozinha maior que o orçamento (rara)
     * ainda assim forma um pedaço próprio, em vez de travar o processo inteiro.
     *
     * @param  \Illuminate\Support\Collection<int, ServiceConversation>  $conversations
     * @return array<int, \Illuminate\Support\Collection<int, ServiceConversation>>
     */
    private function splitIntoBatches($conversations): array
    {
        $sorted = $conversations->sortBy('started_at')->values();

        $batches = [];
        $current = collect();
        $currentChars = 0;

        foreach ($sorted as $conversation) {
            $conversationChars = $this->estimateConversationChars($conversation);

            if ($currentChars > 0 && ($currentChars + $conversationChars) > self::MAX_TRANSCRIPT_CHARS) {
                $batches[] = $current;
                $current = collect();
                $currentChars = 0;
            }

            $current->push($conversation);
            $currentChars += $conversationChars;
        }

        if ($current->isNotEmpty()) {
            $batches[] = $current;
        }

        return $batches;
    }

    private function estimateConversationChars(ServiceConversation $conversation): int
    {
        // +40 de margem por mensagem (cabeçalho "[dd/mm HH:mm] Nome: ") — aproximado, não
        // precisa ser exato, só o suficiente pra não estourar o orçamento por sub-medir.
        return $conversation->messages->sum(fn ($m) => strlen($m->body ?? '') + 40) + 60;
    }

    private function computeAvgFirstResponseMinutes($conversations): ?int
    {
        $deltas = [];

        foreach ($conversations as $conversation) {
            $firstIn = $conversation->messages->firstWhere('direction', 'in');
            if (!$firstIn) {
                continue;
            }

            $firstOutAfter = $conversation->messages
                ->where('direction', 'out')
                ->where('sent_at', '>', $firstIn->sent_at)
                ->sortBy('sent_at')
                ->first();

            if ($firstOutAfter) {
                $deltas[] = $firstIn->sent_at->diffInMinutes($firstOutAfter->sent_at);
            }
        }

        return count($deltas) > 0 ? (int) round(array_sum($deltas) / count($deltas)) : null;
    }

    /**
     * Quantas conversas (e quantas venderam) cada campanha/anúncio da Meta gerou no período —
     * cruza service_conversations.ad_source_id (capturado na 1ª mensagem via
     * UazapiMessageIngestor) com AdAd.external_id, já sincronizado pelo AdSync pra dashboards
     * de Campanhas. Conversa sem anúncio (ou com anúncio que não bate com nada sincronizado)
     * cai em bucket próprio, pra dar contraste pago vs. orgânico no relatório.
     *
     * @param  \Illuminate\Support\Collection<int, ServiceConversation>  $conversations
     */
    private function computeAdAttribution($conversations, \Illuminate\Support\Collection $outcomesByPhone): array
    {
        $sourceIds = $conversations->pluck('ad_source_id')->filter()->unique()->values();

        $adsByExternalId = $sourceIds->isEmpty()
            ? collect()
            : AdAd::whereIn('external_id', $sourceIds)->with('adset.campaign')->get()->keyBy('external_id');

        $buckets = [];

        foreach ($conversations as $conversation) {
            $bucketKey = 'organico';
            $campaignId = null;
            $campaignName = 'Orgânico / Direto';

            if ($conversation->ad_source_id) {
                $campaign = $adsByExternalId->get($conversation->ad_source_id)?->adset?->campaign;
                if ($campaign) {
                    $bucketKey = $campaign->id;
                    $campaignId = $campaign->id;
                    $campaignName = $campaign->name;
                } else {
                    $bucketKey = 'anuncio_sem_sync';
                    $campaignName = 'Anúncio (não sincronizado)';
                }
            }

            $buckets[$bucketKey] ??= [
                'campaign_id'          => $campaignId,
                'campaign_name'        => $campaignName,
                'total_conversations'  => 0,
                'sales_confirmed'      => 0,
                'sales_in_negotiation' => 0,
            ];
            $buckets[$bucketKey]['total_conversations']++;

            $outcome = $outcomesByPhone->get($conversation->contact_phone)['outcome'] ?? null;
            if ($outcome === 'confirmado') {
                $buckets[$bucketKey]['sales_confirmed']++;
            } elseif ($outcome === 'negociacao') {
                $buckets[$bucketKey]['sales_in_negotiation']++;
            }
        }

        return collect($buckets)
            ->map(function (array $b) {
                $b['conversion_rate'] = $b['total_conversations'] > 0
                    ? round($b['sales_confirmed'] / $b['total_conversations'] * 100, 1)
                    : 0.0;
                return $b;
            })
            ->sortByDesc('total_conversations')
            ->values()
            ->all();
    }

    // Recomendações ainda pendentes do diagnóstico mais recente, indexadas por título
    // (minúsculo) - usado pra encadear a mesma recomendação entre versões.
    private function previousPendingRecommendations(ClientIntegration $integration)
    {
        $last = ServiceDiagnostic::where('client_integration_id', $integration->id)
            ->orderByDesc('version')
            ->first();

        if (!$last) {
            return collect();
        }

        return ServiceDiagnosticRecommendation::where('diagnostic_id', $last->id)
            ->where('status', 'pendente')
            ->get()
            ->keyBy(fn ($r) => Str::lower(trim($r->title)));
    }

    private function buildPrompt($conversations, Carbon $periodStart, Carbon $periodEnd, $previousRecommendations): string
    {
        $transcript = $conversations->map(function (ServiceConversation $c) {
            $header = "### Conversa com {$c->contact_name} ({$c->contact_phone})";
            $lines = $c->messages->map(function ($m) {
                $who = $m->direction === 'in' ? ($m->sender_name ?: 'Cliente') : 'Atendente';
                return "[{$m->sent_at->format('d/m H:i')}] {$who}: {$m->body}";
            })->implode("\n");

            return "{$header}\n{$lines}";
        })->implode("\n\n");

        $previousRecsText = $previousRecommendations->isEmpty()
            ? 'Nenhuma.'
            : $previousRecommendations->values()->map(fn ($r) => "- \"{$r->title}\": {$r->description}")->implode("\n");

        return <<<PROMPT
Você vai analisar conversas de atendimento via WhatsApp de um período específico e produzir um diagnóstico estratégico de atendimento comercial.

PERÍODO ANALISADO: {$periodStart->format('d/m/Y')} a {$periodEnd->format('d/m/Y')}
TOTAL DE CONVERSAS: {$conversations->count()}

RECOMENDAÇÕES AINDA PENDENTES DO DIAGNÓSTICO ANTERIOR (avalie se os dados deste período mostram evidência de que foram implementadas):
{$previousRecsText}

CONVERSAS:
{$transcript}

Responda SOMENTE com um objeto JSON com exatamente esta estrutura:
{
  "conversation_outcomes": [{"contact_phone": "<telefone EXATO como aparece no cabeçalho \"### Conversa com Nome (telefone)\" de CADA conversa acima, sem exceção, mesmo as sem venda>", "outcome": "confirmado"|"negociacao"|"nao_convertido"}],
  "sales_confirmed": <int - quantas conversas terminaram em venda/fechamento confirmado (deve bater com a contagem de "confirmado" em conversation_outcomes)>,
  "sales_in_negotiation": <int - quantas ainda estão em negociação ativa (deve bater com a contagem de "negociacao" em conversation_outcomes)>,
  "consistencia_score": <int 0-100 - quão consistente é o processo/script de atendimento entre as conversas>,
  "sentimento_score": <int 0-100 - sentimento médio dos clientes ao longo das conversas>,
  "funnel_summary": {
    "achado_central": "<string - o insight mais importante do período>",
    "como_entra": "<string - como os leads chegam / padrão de entrada>",
    "estagios": [{"estagio": "<string>", "observado": "<string>", "onde_perde": "<string>"}]
  },
  "gaps": [{"titulo": "<string>", "descricao": "<string>", "estimated_leads_lost": <int - estimativa de quantos leads foram perdidos por esse gap especificamente neste período>}],
  "strengths": [{"titulo": "<string>", "descricao": "<string>"}],
  "campaign_insights": [{"titulo": "<string>", "descricao": "<string>"}],
  "personas": [{"tag": "<string curto, no máximo 20 caracteres, formato 'Persona A', 'Persona B' etc - NUNCA uma frase descritiva>", "name": "<string - aqui sim pode ser descritivo, ex: 'Cliente em busca de fitness'>", "profile": "<string>", "behavior": "<string>", "evidence": "<string - trecho real de alguma conversa>"}],
  "recommendations": [{"category": "quick_win"|"estrutural", "title": "<string>", "description": "<string>", "status": "pendente"|"implementada", "carries_forward_title": "<título EXATO de uma recomendação pendente acima, se esta for continuação dela, senão null>"}]
}
PROMPT;
    }

    private function normalizeResponseTimeScore(?int $minutes): int
    {
        if ($minutes === null) {
            return 50;
        }

        // Heurística inicial - ajustar quando houver benchmark real da carteira (service_benchmarks)
        if ($minutes <= 5) return 100;
        if ($minutes >= 120) return 0;

        return (int) round(100 - (($minutes - 5) / (120 - 5)) * 100);
    }

    private function normalizeConversionScore(float $rate): int
    {
        // Heurística inicial - 25%+ de conversão já é topo de escala
        return (int) max(0, min(100, round(($rate / 25) * 100)));
    }
}
