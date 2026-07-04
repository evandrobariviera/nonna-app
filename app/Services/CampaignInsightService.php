<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AiAgent;
use App\Models\CampaignInsight;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

class CampaignInsightService
{
    private const DUPLICATE_WINDOW_DAYS = 3;
    private const BUDGET_IDLE_MIN_DAY = 10;
    private const CPA_SPIKE_ATENCAO_PCT = 30;
    private const CPA_SPIKE_CRITICO_PCT = 60;

    public function __construct(private AiService $aiService)
    {
    }

    /**
     * Roda toda a detecção para um cliente: orçamento e campanhas ativas.
     * Retorna os insights criados nesta chamada (novos disparos apenas).
     */
    public function generateForClient(Client $client): array
    {
        $created = [];

        if ($budgetInsight = $this->checkBudget($client)) {
            $created[] = $budgetInsight;
        }

        $adAccountIds = $client->adAccounts()->pluck('id');
        if ($adAccountIds->isNotEmpty()) {
            $campaigns = AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
                ->where('status', 'active')
                ->get();

            foreach ($campaigns as $campaign) {
                $created = array_merge($created, $this->checkCampaign($client, $campaign));
            }
        }

        return $created;
    }

    private function checkBudget(Client $client): ?CampaignInsight
    {
        $budget = $client->currentAdBudget();
        if (!$budget || (float) $budget->monthly_budget <= 0) {
            return null;
        }

        $spend = $client->currentMonthAdSpend();
        $pct = ($spend / (float) $budget->monthly_budget) * 100;
        $dayOfMonth = now()->day;

        if ($pct >= 90) {
            $kind = 'budget_overrun';
            $severity = $pct >= 100 ? 'critico' : 'atencao';
            $title = "Orçamento de anúncios em " . round($pct) . "% do combinado";
        } elseif ($spend <= 0.0 && $dayOfMonth >= self::BUDGET_IDLE_MIN_DAY) {
            $kind = 'budget_idle';
            $severity = 'atencao';
            $title = 'Verba de anúncios do mês ainda não foi utilizada';
        } else {
            return null;
        }

        if ($this->hasRecentOpenInsight($client, $kind, null)) {
            return null;
        }

        $metrics = [
            'monthly_budget' => (float) $budget->monthly_budget,
            'month_spend'    => $spend,
            'pct_used'       => round($pct, 1),
            'day_of_month'   => $dayOfMonth,
        ];

        return $this->createInsight($client, null, null, $kind, $severity, $title, $metrics);
    }

    private function checkCampaign(Client $client, AdCampaign $campaign): array
    {
        $insights = [];

        $last7d = ContextResolver::campaignMetrics($campaign, 7, 0);
        $prev7d = ContextResolver::campaignMetrics($campaign, 14, 7);

        // CPA em alta
        if ($last7d['cpa'] !== null && $prev7d['cpa'] !== null && $prev7d['cpa'] > 0) {
            $deltaPct = (($last7d['cpa'] - $prev7d['cpa']) / $prev7d['cpa']) * 100;

            if ($deltaPct >= self::CPA_SPIKE_ATENCAO_PCT && !$this->hasRecentOpenInsight($client, 'cpa_spike', $campaign->id)) {
                $severity = $deltaPct >= self::CPA_SPIKE_CRITICO_PCT ? 'critico' : 'atencao';
                $metrics = [
                    'cpa_last_7_days'     => round($last7d['cpa'], 2),
                    'cpa_previous_7_days' => round($prev7d['cpa'], 2),
                    'delta_pct'           => round($deltaPct, 1),
                ];
                $insights[] = $this->createInsight(
                    $client, $campaign->client_ad_account_id, $campaign->id, 'cpa_spike', $severity,
                    "CPA da campanha \"{$campaign->name}\" subiu " . round($deltaPct) . '%',
                    $metrics, $campaign
                );
            }
        }

        // ROAS abaixo do ponto de equilíbrio
        if ($last7d['roas'] !== null && $last7d['roas'] < 1.0 && $last7d['spend'] > 0
            && !$this->hasRecentOpenInsight($client, 'roas_drop', $campaign->id)) {
            $metrics = [
                'roas_last_7_days'  => $last7d['roas'],
                'spend_last_7_days' => round($last7d['spend'], 2),
            ];
            $insights[] = $this->createInsight(
                $client, $campaign->client_ad_account_id, $campaign->id, 'roas_drop', 'atencao',
                "ROAS da campanha \"{$campaign->name}\" abaixo de 1x",
                $metrics, $campaign
            );
        }

        return $insights;
    }

    /**
     * Evita duplicidade: não gera um novo insight do mesmo tipo/escopo se já
     * existe um em aberto (novo/lido) criado nos últimos dias.
     */
    private function hasRecentOpenInsight(Client $client, string $kind, ?string $campaignId): bool
    {
        return $client->insights()
            ->where('kind', $kind)
            ->where('ad_campaign_id', $campaignId)
            ->whereIn('status', ['novo', 'lido'])
            ->where('generated_at', '>=', now()->subDays(self::DUPLICATE_WINDOW_DAYS))
            ->exists();
    }

    private function createInsight(
        Client $client,
        ?string $adAccountId,
        ?string $campaignId,
        string $kind,
        string $severity,
        string $title,
        array $metrics,
        ?AdCampaign $campaign = null
    ): CampaignInsight {
        $narrative = $this->generateNarrative($client, $kind, $metrics, $campaign);

        return CampaignInsight::create([
            'client_id'            => $client->id,
            'client_ad_account_id' => $adAccountId,
            'ad_campaign_id'       => $campaignId,
            'kind'                 => $kind,
            'severity'             => $severity,
            'status'               => 'novo',
            'title'                => $title,
            'summary'              => $narrative['summary'] ?? null,
            'metrics_snapshot'     => $metrics,
            'agent_id'             => $narrative['agent_id'] ?? null,
            'generated_at'         => now(),
        ]);
    }

    /**
     * Gera a narrativa via IA se houver um agente configurado para insights de
     * campanha (Organization->settings.campaign_insights.agent_id). Degrada
     * graciosamente (retorna null) se não houver agente ou a chamada falhar —
     * o insight ainda é criado, só sem texto explicativo.
     */
    private function generateNarrative(Client $client, string $kind, array $metrics, ?AdCampaign $campaign): ?array
    {
        $agent = $this->resolveAgent();
        if (!$agent) {
            return null;
        }

        $context = array_merge(
            [
                'client_name'   => $client->company_name,
                'insight_kind'  => CampaignInsight::$kinds[$kind] ?? $kind,
                'metrics_json'  => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            ],
            $campaign ? ContextResolver::forCampaign($campaign) : []
        );

        $message = 'Com base nos dados acima, escreva um diagnóstico curto (2 a 3 frases) e uma recomendação '
            . 'prática em português para a equipe de tráfego pago.';

        try {
            $summary = $this->aiService->run($agent, $message, $context, null, $client->id, 'campaign_insight');
            return ['summary' => $summary, 'agent_id' => $agent->id];
        } catch (\Throwable $e) {
            Log::warning('CampaignInsightService: falha ao gerar narrativa de IA', [
                'error'     => $e->getMessage(),
                'kind'      => $kind,
                'client_id' => $client->id,
            ]);
            return null;
        }
    }

    private function resolveAgent(): ?AiAgent
    {
        if (!app()->has('currentOrganization')) {
            return null;
        }

        $agentId = data_get(app('currentOrganization')->settings, 'campaign_insights.agent_id');
        if (!$agentId) {
            return null;
        }

        return AiAgent::where('id', $agentId)->where('is_active', true)->first();
    }
}
