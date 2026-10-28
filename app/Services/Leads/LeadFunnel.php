<?php

namespace App\Services\Leads;

use App\Models\AdCampaign;
use App\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Funil do anúncio até a venda, por campanha: gasto (ad_daily_snapshots) →
 * leads que chegaram na Central → bons → vendas → retorno. Fonte única da aba
 * "Resultados" da Central de Leads e do agente analista de campanhas.
 *
 * Retorno = vendas ÷ gasto ("cada R$ 1 investido voltou R$ X"). O estimado
 * soma às vendas confirmadas os leads bons ainda abertos × taxa de compra do
 * cliente × ticket médio — sempre rotulado como estimativa.
 */
class LeadFunnel
{
    // Taxa de compra só vale com algum histórico; antes disso não estima.
    private const MIN_SALES_FOR_RATE = 3;
    private const RATE_HISTORY_DAYS = 180;

    // Contagens por grupo de cartões (alias "o" = client_lead_opportunities).
    private const LEAD_COUNTS = "COUNT(*) AS leads,
        COUNT(COALESCE(o.quality, o.ai_quality)) AS evaluated,
        COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'bom') AS good,
        COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'fraco') AS weak,
        COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'sem_perfil') AS no_profile,
        COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'bom' AND o.stage NOT IN ('ganho', 'perdido')) AS good_open,
        COUNT(*) FILTER (WHERE o.stage = 'ganho') AS sold,
        COALESCE(SUM(o.deal_value) FILTER (WHERE o.stage = 'ganho'), 0) AS sold_value";

    /**
     * Uma campanha, anúncio por anúncio — o que o agente analista lê. O total
     * usa o gasto da campanha (o de anúncio pode vir incompleto do sync).
     */
    public static function forCampaign(AdCampaign $campaign, CarbonInterface $from, CarbonInterface $to): array
    {
        $db = DB::connection('pgsql');
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $client = $campaign->adAccount->client;

        $campaignSpend = $db->table('ad_daily_snapshots')
            ->where('client_ad_account_id', $campaign->client_ad_account_id)
            ->where('entity_level', 'campaign')
            ->where('entity_id', $campaign->external_id)
            ->whereBetween('snapshot_date', [$fromDate, $toDate])
            ->selectRaw('COALESCE(SUM(spend), 0) AS spend, COALESCE(SUM(conversions), 0) AS platform_results')
            ->first();

        $spend = $db->table('ad_daily_snapshots as s')
            ->join('ad_ads as ad', 'ad.external_id', '=', 's.entity_id')
            ->join('ad_adsets as st', 'st.id', '=', 'ad.ad_adset_id')
            ->where('st.ad_campaign_id', $campaign->id)
            ->where('s.client_ad_account_id', $campaign->client_ad_account_id)
            ->where('s.entity_level', 'ad')
            ->whereBetween('s.snapshot_date', [$fromDate, $toDate])
            ->groupBy('ad.id', 'ad.name', 'ad.status', 'st.name')
            ->havingRaw('SUM(s.spend) > 0')
            ->selectRaw('ad.id, ad.name, ad.status, st.name AS adset, SUM(s.spend) AS spend, SUM(s.conversions) AS platform_results')
            ->get()
            ->keyBy('id');

        $leads = $db->table('client_lead_opportunities as o')
            ->leftJoin('ad_ads as ad', 'ad.id', '=', 'o.ad_ad_id')
            ->leftJoin('ad_adsets as st', 'st.id', '=', 'ad.ad_adset_id')
            ->where('o.ad_campaign_id', $campaign->id)
            ->whereRaw('COALESCE(o.received_at, o.created_at)::date BETWEEN ? AND ?', [$fromDate, $toDate])
            ->groupBy('o.ad_ad_id', 'ad.name', 'ad.status', 'st.name')
            ->selectRaw('o.ad_ad_id AS id, ad.name, ad.status, st.name AS adset, ' . self::LEAD_COUNTS)
            ->get()
            ->keyBy(fn ($r) => $r->id ?? '');

        $rate = self::purchaseRate($client);
        $ticket = $client->average_ticket !== null ? (float) $client->average_ticket : null;

        $ads = [];
        foreach ($spend->keys()->merge($leads->keys())->unique() as $id) {
            $s = $spend->get($id);
            $l = $leads->get($id);
            $row = self::row($id ?: null, $id ? ($s->name ?? $l->name) : 'Anúncio não identificado', null, $s->status ?? $l->status ?? null, $s, $l, $rate, $ticket);
            $row['adset'] = $s->adset ?? $l->adset ?? null;
            $ads[] = $row;
        }
        usort($ads, fn ($a, $b) => [$b['spend'], $b['leads']] <=> [$a['spend'], $a['leads']]);

        $total = self::row($campaign->id, $campaign->name, $campaign->platform, $campaign->status, $campaignSpend,
            (object) collect(['leads', 'evaluated', 'good', 'weak', 'no_profile', 'good_open', 'sold', 'sold_value'])
                ->mapWithKeys(fn ($k) => [$k => array_sum(array_column($ads, $k))])->all(),
            $rate, $ticket);

        return [
            'from'          => $from,
            'to'            => $to,
            'campaign'      => $total,
            'ads'           => $ads,
            'purchase_rate' => $rate,
            'ticket'        => $ticket,
            'has_rule'      => $client->hasReviewedLeadRule(),
        ];
    }

    public static function forClient(Client $client, CarbonInterface $from, CarbonInterface $to): array
    {
        $db = DB::connection('pgsql');
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $spend = $db->table('ad_daily_snapshots as s')
            ->join('client_ad_accounts as a', 'a.id', '=', 's.client_ad_account_id')
            ->join('ad_campaigns as c', function ($j) {
                $j->on('c.client_ad_account_id', '=', 's.client_ad_account_id')->on('c.external_id', '=', 's.entity_id');
            })
            ->where('a.client_id', $client->id)
            ->where('s.entity_level', 'campaign')
            ->whereBetween('s.snapshot_date', [$fromDate, $toDate])
            ->groupBy('c.id', 'c.name', 'c.platform', 'c.status')
            ->selectRaw('c.id, c.name, c.platform, c.status, SUM(s.spend) AS spend, SUM(s.conversions) AS platform_results')
            ->havingRaw('SUM(s.spend) > 0')
            ->get()
            ->keyBy('id');

        $leads = $db->table('client_lead_opportunities as o')
            ->join('client_leads as l', 'l.id', '=', 'o.client_lead_id')
            ->leftJoin('ad_campaigns as c', 'c.id', '=', 'o.ad_campaign_id')
            ->where('l.client_id', $client->id)
            ->whereRaw('COALESCE(o.received_at, o.created_at)::date BETWEEN ? AND ?', [$fromDate, $toDate])
            ->groupBy('o.ad_campaign_id', 'c.name', 'c.platform', 'c.status')
            ->selectRaw('o.ad_campaign_id AS id, c.name, c.platform, c.status, ' . self::LEAD_COUNTS)
            ->get()
            ->keyBy(fn ($r) => $r->id ?? '');

        $rate = self::purchaseRate($client);
        $ticket = $client->average_ticket !== null ? (float) $client->average_ticket : null;

        $rows = [];
        foreach ($spend->keys()->merge($leads->keys()->filter())->unique() as $id) {
            $s = $spend->get($id);
            $l = $leads->get($id);
            $rows[] = self::row($id, $s->name ?? $l->name, $s->platform ?? $l->platform, $s->status ?? $l->status, $s, $l, $rate, $ticket);
        }
        usort($rows, fn ($a, $b) => [$b['spend'], $b['leads']] <=> [$a['spend'], $a['leads']]);

        $unattributed = $leads->get('') ? self::row(null, 'Sem campanha identificada', null, null, null, $leads->get(''), $rate, $ticket) : null;

        $all = array_merge($rows, $unattributed ? [$unattributed] : []);
        $total = self::row(null, 'Total', null, null,
            (object) ['spend' => array_sum(array_column($rows, 'spend')), 'platform_results' => array_sum(array_column($rows, 'platform_results'))],
            (object) collect(['leads', 'evaluated', 'good', 'weak', 'no_profile', 'good_open', 'sold', 'sold_value'])
                ->mapWithKeys(fn ($k) => [$k => array_sum(array_column($all, $k))])->all(),
            $rate, $ticket);

        return [
            'from'          => $from,
            'to'            => $to,
            'rows'          => $rows,
            'unattributed'  => $unattributed,
            'total'         => $total,
            'purchase_rate' => $rate,
            'ticket'        => $ticket,
            'has_rule'      => $client->hasReviewedLeadRule(),
        ];
    }

    /**
     * Uma linha por cliente (com gasto ou lead no período), para a visão geral
     * da aba Resultados quando nenhum cliente está escolhido.
     */
    public static function overview(CarbonInterface $from, CarbonInterface $to): array
    {
        $db = DB::connection('pgsql');
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $spend = $db->table('ad_daily_snapshots as s')
            ->join('client_ad_accounts as a', 'a.id', '=', 's.client_ad_account_id')
            ->where('s.entity_level', 'campaign')
            ->whereBetween('s.snapshot_date', [$fromDate, $toDate])
            ->groupBy('a.client_id')
            ->havingRaw('SUM(s.spend) > 0')
            ->selectRaw('a.client_id, SUM(s.spend) AS spend')
            ->pluck('spend', 'client_id');

        $leads = $db->table('client_lead_opportunities as o')
            ->join('client_leads as l', 'l.id', '=', 'o.client_lead_id')
            ->whereNotNull('l.client_id')
            ->whereRaw('COALESCE(o.received_at, o.created_at)::date BETWEEN ? AND ?', [$fromDate, $toDate])
            ->groupBy('l.client_id')
            ->selectRaw("l.client_id,
                COUNT(*) AS leads,
                COUNT(o.ad_campaign_id) AS attributed,
                COUNT(COALESCE(o.quality, o.ai_quality)) AS evaluated,
                COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'bom') AS good,
                COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'fraco') AS weak,
                COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'sem_perfil') AS no_profile,
                COUNT(*) FILTER (WHERE o.stage = 'ganho') AS sold,
                COALESCE(SUM(o.deal_value) FILTER (WHERE o.stage = 'ganho'), 0) AS sold_value")
            ->get()
            ->keyBy('client_id');

        $clients = Client::whereIn('id', $spend->keys()->merge($leads->keys())->unique())
            ->get(['id', 'nickname', 'company_name', 'lead_rule', 'lead_rule_reviewed_at'])
            ->keyBy('id');

        return $clients->map(function (Client $client) use ($spend, $leads) {
            $s = (float) ($spend[$client->id] ?? 0);
            $l = $leads->get($client->id);
            $good = (int) ($l->good ?? 0);
            $soldValue = (float) ($l->sold_value ?? 0);

            return [
                'client'        => $client,
                'spend'         => round($s, 2),
                'leads'         => (int) ($l->leads ?? 0),
                'attributed'    => (int) ($l->attributed ?? 0),
                'evaluated'     => (int) ($l->evaluated ?? 0),
                'good'          => $good,
                'weak'          => (int) ($l->weak ?? 0),
                'no_profile'    => (int) ($l->no_profile ?? 0),
                'sold'          => (int) ($l->sold ?? 0),
                'sold_value'    => round($soldValue, 2),
                'has_rule'      => $client->hasReviewedLeadRule(),
                'cost_per_good' => $s > 0 && $good > 0 ? round($s / $good, 2) : null,
                'return'        => $s > 0 && $soldValue > 0 ? round($soldValue / $s, 2) : null,
            ];
        })->sortByDesc('spend')->values()->all();
    }

    /**
     * Soma da visão geral para o gráfico de funil. Só entra o investimento de
     * quem tem lead na Central — cliente que investe mas não usa a Central
     * inflaria o custo por lead.
     */
    public static function overviewTotals(array $overview): array
    {
        $withLeads = array_filter($overview, fn ($r) => $r['leads'] > 0);

        return collect(['spend', 'leads', 'evaluated', 'good', 'weak', 'no_profile', 'sold', 'sold_value'])
            ->mapWithKeys(fn ($k) => [$k => array_sum(array_column($withLeads, $k))])
            ->all();
    }

    /**
     * Das pessoas marcadas como lead bom nos últimos 180 dias e já resolvidas
     * (ganho ou perdido), quantas compraram. Null sem histórico suficiente.
     */
    public static function purchaseRate(Client $client): ?float
    {
        $r = DB::connection('pgsql')->table('client_lead_opportunities as o')
            ->join('client_leads as l', 'l.id', '=', 'o.client_lead_id')
            ->where('l.client_id', $client->id)
            ->whereRaw("COALESCE(o.quality, o.ai_quality) = 'bom'")
            ->whereIn('o.stage', ['ganho', 'perdido'])
            ->where('o.created_at', '>=', now()->subDays(self::RATE_HISTORY_DAYS))
            ->selectRaw("COUNT(*) AS closed, COUNT(*) FILTER (WHERE o.stage = 'ganho') AS sold")
            ->first();

        return $r && $r->sold >= self::MIN_SALES_FOR_RATE ? $r->sold / $r->closed : null;
    }

    private static function row(?string $id, ?string $name, ?string $platform, ?string $status, ?object $s, ?object $l, ?float $rate, ?float $ticket): array
    {
        $spend = (float) ($s->spend ?? 0);
        $leads = (int) ($l->leads ?? 0);
        $good = (int) ($l->good ?? 0);
        $goodOpen = (int) ($l->good_open ?? 0);
        $soldValue = (float) ($l->sold_value ?? 0);

        $estimatedValue = $rate !== null && $ticket !== null ? $soldValue + $goodOpen * $rate * $ticket : null;

        return [
            'id'                 => $id,
            'name'               => $name ?? 'Campanha',
            'platform'           => $platform,
            'status'             => $status,
            'spend'              => round($spend, 2),
            'platform_results'   => (int) ($s->platform_results ?? 0),
            'leads'              => $leads,
            'evaluated'          => (int) ($l->evaluated ?? 0),
            'good'               => $good,
            'weak'               => (int) ($l->weak ?? 0),
            'no_profile'         => (int) ($l->no_profile ?? 0),
            'good_open'          => $goodOpen,
            'sold'               => (int) ($l->sold ?? 0),
            'sold_value'         => round($soldValue, 2),
            'good_pct'           => ($l->evaluated ?? 0) > 0 ? round($good / $l->evaluated * 100) : null,
            'cost_per_lead'      => $spend > 0 && $leads > 0 ? round($spend / $leads, 2) : null,
            'cost_per_good'      => $spend > 0 && $good > 0 ? round($spend / $good, 2) : null,
            'return'             => $spend > 0 && $soldValue > 0 ? round($soldValue / $spend, 2) : null,
            'return_estimated'   => $spend > 0 && $estimatedValue ? round($estimatedValue / $spend, 2) : null,
        ];
    }
}
