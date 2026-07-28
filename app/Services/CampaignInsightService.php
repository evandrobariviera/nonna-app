<?php

namespace App\Services;

use App\Models\AdAd;
use App\Models\AdAdset;
use App\Models\AdCampaign;
use App\Models\AiAgent;
use App\Models\CampaignInsight;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CampaignInsightService
{
    private const DUPLICATE_WINDOW_DAYS = 3;
    private const BUDGET_IDLE_MIN_DAY = 10;
    private const CPA_SPIKE_ATENCAO_PCT = 30;
    private const CPA_SPIKE_CRITICO_PCT = 60;
    private const ADSET_BUDGET_SHARE_ATENCAO_PCT = 40;
    private const CREATIVE_FATIGUE_CTR_WORSE_PCT = 50;
    private const CREATIVE_FATIGUE_MIN_IMPRESSIONS = 1000;

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
                $created = array_merge($created, $this->checkAdsetsAndAds($client, $campaign));
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
     * Detecta insights de fadiga de criativo e concentração de verba por
     * conjunto — mesmo princípio de checkCampaign, mas olhando os filhos
     * (adsets/ads) da campanha em vez da campanha como um todo.
     */
    private function checkAdsetsAndAds(Client $client, AdCampaign $campaign): array
    {
        $insights = [];
        $campaignLast7d = ContextResolver::campaignMetrics($campaign, 7, 0);

        $adsets = $campaign->adsets()->where('status', 'active')->get();

        foreach ($adsets as $adset) {
            $adsetLast7d = ContextResolver::entityMetrics($campaign->client_ad_account_id, 'adset', $adset->external_id, 7, 0);

            if ($campaignLast7d['spend'] > 0 && $adsetLast7d['spend'] > 0) {
                $share = ($adsetLast7d['spend'] / $campaignLast7d['spend']) * 100;

                if ($share >= self::ADSET_BUDGET_SHARE_ATENCAO_PCT
                    && $adsetLast7d['roas'] !== null && $adsetLast7d['roas'] < 1.0
                    && !$this->hasRecentOpenInsight($client, 'adset_budget_concentration', $campaign->id, $adset->id)) {
                    $metrics = [
                        'spend_share_pct'   => round($share, 1),
                        'spend_last_7_days' => round($adsetLast7d['spend'], 2),
                        'roas_last_7_days'  => $adsetLast7d['roas'],
                    ];
                    $insights[] = $this->createInsight(
                        $client, $campaign->client_ad_account_id, $campaign->id, 'adset_budget_concentration', 'atencao',
                        "Conjunto \"{$adset->name}\" concentra " . round($share) . "% da verba da campanha \"{$campaign->name}\" com ROAS abaixo de 1x",
                        $metrics, $adset, $adset->id
                    );
                }
            }

            $insights = array_merge($insights, $this->checkCreativeFatigue($client, $campaign, $adset));
        }

        return $insights;
    }

    /**
     * Compara o CTR de cada anúncio ativo de um conjunto com a média dos seus
     * irmãos (mesmo conjunto) — precisa de pelo menos 2 anúncios com volume
     * mínimo de impressões pra não dar falso positivo em anúncio novo/com
     * pouco alcance.
     */
    private function checkCreativeFatigue(Client $client, AdCampaign $campaign, AdAdset $adset): array
    {
        $insights = [];

        $ads = $adset->ads()->where('status', 'active')->get();
        if ($ads->count() < 2) {
            return $insights;
        }

        $adMetrics = $ads->map(function (AdAd $ad) use ($campaign) {
            $metrics = ContextResolver::entityMetrics($campaign->client_ad_account_id, 'ad', $ad->external_id, 7, 0);
            $metrics['ad'] = $ad;
            return $metrics;
        })->filter(fn ($m) => $m['ctr'] !== null && $m['impressions'] >= self::CREATIVE_FATIGUE_MIN_IMPRESSIONS)
            ->values();

        if ($adMetrics->count() < 2) {
            return $insights;
        }

        foreach ($adMetrics as $m) {
            $siblings = $adMetrics->reject(fn ($other) => $other['ad']->id === $m['ad']->id);
            $siblingAvgCtr = $siblings->avg('ctr');

            if (!$siblingAvgCtr || $siblingAvgCtr <= 0) {
                continue;
            }

            $deltaPct = (($siblingAvgCtr - $m['ctr']) / $siblingAvgCtr) * 100;

            if ($deltaPct >= self::CREATIVE_FATIGUE_CTR_WORSE_PCT
                && !$this->hasRecentOpenInsight($client, 'creative_fatigue', $campaign->id, $adset->id, $m['ad']->id)) {
                $metrics = [
                    'ctr_last_7_days'        => $m['ctr'],
                    'sibling_avg_ctr_7_days' => round($siblingAvgCtr, 2),
                    'delta_pct'              => round($deltaPct, 1),
                ];
                $insights[] = $this->createInsight(
                    $client, $campaign->client_ad_account_id, $campaign->id, 'creative_fatigue', 'atencao',
                    "Criativo \"{$m['ad']->name}\" com CTR " . round($deltaPct) . "% pior que os outros do conjunto \"{$adset->name}\"",
                    $metrics, $m['ad'], $adset->id, $m['ad']->id
                );
            }
        }

        return $insights;
    }

    /**
     * Evita duplicidade: não gera um novo insight do mesmo tipo/escopo se já
     * existe um em aberto (novo/lido) criado nos últimos dias. Escopo inclui
     * adset/ad pra um insight granular não colidir com um de campanha do
     * mesmo kind.
     */
    private function hasRecentOpenInsight(Client $client, string $kind, ?string $campaignId, ?string $adsetId = null, ?string $adId = null): bool
    {
        return $client->insights()
            ->where('kind', $kind)
            ->where('ad_campaign_id', $campaignId)
            ->where('ad_adset_id', $adsetId)
            ->where('ad_ad_id', $adId)
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
        ?Model $entity = null,
        ?string $adsetId = null,
        ?string $adId = null
    ): CampaignInsight {
        $narrative = $this->generateNarrative($client, $kind, $metrics, $entity);

        return CampaignInsight::create([
            'client_id'            => $client->id,
            'client_ad_account_id' => $adAccountId,
            'ad_campaign_id'       => $campaignId,
            'ad_adset_id'          => $adsetId,
            'ad_ad_id'             => $adId,
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
    private function generateNarrative(Client $client, string $kind, array $metrics, ?Model $entity): ?array
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
            $entity ? ContextResolver::for($entity) : []
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
