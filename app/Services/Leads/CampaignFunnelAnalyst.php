<?php

namespace App\Services\Leads;

use App\Models\AdCampaign;
use App\Models\AiAgent;
use App\Models\CampaignInsight;
use App\Models\Client;
use App\Services\AiService;
use Illuminate\Support\Facades\DB;

/**
 * Agente analista de campanhas: no momento de otimizar (mesmo gatilho dos
 * alertas do CampaignInsightService), lê o funil anúncio → lead → lead bom →
 * venda da campanha (LeadFunnel::forCampaign) e grava um insight "lead_funnel"
 * com diagnóstico/recomendações para a equipe e resumo para o Portal.
 */
class CampaignFunnelAnalyst
{
    // Ciclo de otimização é de 1 a 5 dias — pouco volume para julgar lead.
    // A análise olha pelo menos 14 dias, comparando com os 14 anteriores.
    public const MIN_WINDOW_DAYS = 14;
    // Abaixo disso não há o que analisar (campanha sem leads ligados).
    public const MIN_LEADS = 5;
    // Uma análise por campanha por semana, mesmo com ciclo diário.
    private const MIN_DAYS_BETWEEN = 7;
    private const MAX_EXAMPLES = 12;

    public function __construct(private AiService $ai)
    {
    }

    public static function agent(): ?AiAgent
    {
        return AiAgent::bySlug(AiAgent::SLUG_CAMPAIGN_FUNNEL_ANALYST);
    }

    /** Analisa se a campanha tem leads suficientes e não foi analisada há pouco. */
    public function analyzeIfDue(Client $client, AdCampaign $campaign): ?CampaignInsight
    {
        $recent = CampaignInsight::where('ad_campaign_id', $campaign->id)
            ->where('kind', 'lead_funnel')
            ->where('generated_at', '>=', now()->subDays(self::MIN_DAYS_BETWEEN))
            ->exists();

        return $recent ? null : $this->analyze($client, $campaign);
    }

    public function analyze(Client $client, AdCampaign $campaign, ?AiAgent $agent = null): ?CampaignInsight
    {
        $agent ??= self::agent();
        if (!$agent) {
            return null;
        }

        [$from, $to, $prevFrom, $prevTo] = self::windows($campaign);
        $current = LeadFunnel::forCampaign($campaign, $from, $to);
        if ($current['campaign']['leads'] < self::MIN_LEADS) {
            return null;
        }
        $previous = LeadFunnel::forCampaign($campaign, $prevFrom, $prevTo);

        $answer = $this->ai->runStructured(
            $agent,
            json_encode($this->input($client, $campaign, $current, $previous), JSON_UNESCAPED_UNICODE),
            [],
            null,
            $client->id,
            'campaign_funnel_analysis',
        );

        $diagnosis = trim((string) ($answer['diagnostico'] ?? ''));
        if ($diagnosis === '') {
            return null;
        }

        $recommendations = collect($answer['recomendacoes'] ?? [])->filter(fn ($r) => is_string($r) && trim($r) !== '')->take(4);
        $summary = $diagnosis . ($recommendations->isNotEmpty()
            ? "\n\nO que fazer:\n" . $recommendations->map(fn ($r) => '- ' . trim($r))->implode("\n")
            : '');

        return CampaignInsight::create([
            'organization_id'      => $client->organization_id,
            'client_id'            => $client->id,
            'client_ad_account_id' => $campaign->client_ad_account_id,
            'ad_campaign_id'       => $campaign->id,
            'kind'                 => 'lead_funnel',
            'severity'             => array_key_exists($answer['severidade'] ?? '', CampaignInsight::$severities) ? $answer['severidade'] : 'info',
            'status'               => 'novo',
            'title'                => mb_substr(trim((string) ($answer['titulo'] ?? '')) ?: "Do anúncio à venda: {$campaign->name}", 0, 255),
            'summary'              => $summary,
            'client_summary'       => filled($answer['resumo_cliente'] ?? null) ? trim($answer['resumo_cliente']) : null,
            'metrics_snapshot'     => ['atual' => $current['campaign'], 'anterior' => $previous['campaign'], 'anuncios' => $current['ads']],
            'agent_id'             => $agent->id,
            'generated_at'         => now(),
        ]);
    }

    /** Período atual (até ontem) e o anterior de mesmo tamanho. */
    public static function windows(AdCampaign $campaign): array
    {
        $days = max(self::MIN_WINDOW_DAYS, (int) (AdCampaign::$optimizationTiers[$campaign->optimization_tier]['days'] ?? 5));
        $to = now()->subDay()->endOfDay();
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        return [$from, $to, $from->copy()->subDays($days), $from->copy()->subDay()->endOfDay()];
    }

    public function input(Client $client, AdCampaign $campaign, array $current, array $previous): array
    {
        $notes = [];
        $c = $current['campaign'];
        if (!$current['has_rule']) {
            $notes[] = 'O cliente não tem régua de lead qualificado: nenhum lead foi avaliado, então não há leads bons/fracos para comparar.';
        } elseif ($c['evaluated'] < $c['leads']) {
            $notes[] = ($c['leads'] - $c['evaluated']) . ' de ' . $c['leads'] . ' leads ainda sem avaliação.';
        }
        if ($current['ticket'] === null) {
            $notes[] = 'Sem ticket médio: não há retorno estimado.';
        }
        if ($c['sold'] === 0) {
            $notes[] = 'Nenhuma venda marcada no período (pode ser que a equipe do cliente ainda não esteja marcando).';
        }

        return [
            'cliente'      => $client->displayName(),
            'regua'        => $client->lead_rule,
            'ticket_medio' => $current['ticket'],
            'taxa_compra'  => $current['purchase_rate'] !== null ? round($current['purchase_rate'], 2) : null,
            'campanha'     => [
                'nome'       => $campaign->name,
                'plataforma' => $campaign->platform,
                'objetivo'   => $campaign->objective,
                'periodo'    => $current['from']->format('d/m/Y') . ' a ' . $current['to']->format('d/m/Y'),
            ],
            'atual'        => self::funnelRow($current['campaign']),
            'anterior'     => self::funnelRow($previous['campaign']),
            'anuncios'     => array_map(fn ($ad) => ['anuncio' => $ad['name'], 'conjunto' => $ad['adset'] ?? null] + self::funnelRow($ad), $current['ads']),
            'exemplos'     => $this->examples($campaign, $current['from'], $current['to']),
            'observacoes'  => $notes,
        ];
    }

    private static function funnelRow(array $r): array
    {
        return [
            'investido'             => $r['spend'],
            'resultados_plataforma' => $r['platform_results'],
            'leads'                 => $r['leads'],
            'avaliados'             => $r['evaluated'],
            'bons'                  => $r['good'],
            'fracos'                => $r['weak'],
            'sem_perfil'            => $r['no_profile'],
            'vendas'                => $r['sold'],
            'valor_vendas'          => $r['sold_value'],
            'custo_lead'            => $r['cost_per_lead'],
            'custo_lead_bom'        => $r['cost_per_good'],
            'retorno'               => $r['return'],
            'retorno_estimado'      => $r['return_estimated'],
        ];
    }

    /** Resumos da IA de leads reais da campanha, misturando as qualidades. */
    private function examples(AdCampaign $campaign, $from, $to): array
    {
        return DB::connection('pgsql')->table('client_lead_opportunities as o')
            ->leftJoin('ad_ads as ad', 'ad.id', '=', 'o.ad_ad_id')
            ->where('o.ad_campaign_id', $campaign->id)
            ->whereNotNull('o.ai_summary')
            ->whereRaw('COALESCE(o.received_at, o.created_at) BETWEEN ? AND ?', [$from, $to])
            ->selectRaw("COALESCE(o.quality, o.ai_quality) AS qualidade, ad.name AS anuncio, o.ai_summary AS resumo, o.stage,
                ROW_NUMBER() OVER (PARTITION BY COALESCE(o.quality, o.ai_quality) ORDER BY COALESCE(o.received_at, o.created_at) DESC) AS n")
            ->get()
            ->sortBy('n')
            ->take(self::MAX_EXAMPLES)
            ->map(fn ($r) => [
                'qualidade' => $r->qualidade,
                'anuncio'   => $r->anuncio,
                'comprou'   => $r->stage === 'ganho',
                'resumo'    => $r->resumo,
            ])
            ->values()
            ->all();
    }
}
