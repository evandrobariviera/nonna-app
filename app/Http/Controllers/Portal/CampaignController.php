<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $client = auth()->user()->client;
        $adAccountIds = $client->adAccounts()->pluck('id');

        if ($adAccountIds->isEmpty()) {
            return view('portal.campaigns.index', [
                'client'     => $client,
                'stats'      => null,
                'campaigns'  => collect(),
                'dailySpend' => collect(),
            ]);
        }

        $thirtyDaysAgo  = now()->subDays(30)->toDateString();
        $fourteenDaysAgo = now()->subDays(13)->toDateString();

        // Métricas agregadas dos últimos 30 dias
        $agg = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $thirtyDaysAgo)
            ->selectRaw('
                COALESCE(SUM(spend), 0)       AS total_spend,
                COALESCE(SUM(revenue), 0)     AS total_revenue,
                COALESCE(SUM(impressions), 0) AS total_impressions,
                COALESCE(SUM(clicks), 0)      AS total_clicks,
                COALESCE(SUM(conversions), 0) AS total_conversions
            ')
            ->first();

        $totalSpend = (float) ($agg->total_spend ?? 0);
        $totalClicks = (int) ($agg->total_clicks ?? 0);
        $totalConversions = (int) ($agg->total_conversions ?? 0);
        $totalRevenue = (float) ($agg->total_revenue ?? 0);

        $stats = [
            'spend'       => $totalSpend,
            'revenue'     => $totalRevenue,
            'impressions' => (int) ($agg->total_impressions ?? 0),
            'clicks'      => $totalClicks,
            'conversions' => $totalConversions,
            'roas'        => $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0,
            'cpc'         => $totalClicks > 0 ? round($totalSpend / $totalClicks, 2) : 0,
            'cpa'         => $totalConversions > 0 ? round($totalSpend / $totalConversions, 2) : 0,
        ];

        // Campanhas com stats individuais dos últimos 30 dias
        $campaigns = AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
            ->with('adAccount')
            ->get();

        $perCampaign = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $thirtyDaysAgo)
            ->selectRaw('
                entity_id,
                COALESCE(SUM(spend), 0)       AS total_spend,
                COALESCE(SUM(revenue), 0)      AS total_revenue,
                COALESCE(SUM(impressions), 0)  AS total_impressions,
                COALESCE(SUM(clicks), 0)        AS total_clicks,
                COALESCE(SUM(conversions), 0)   AS total_conversions
            ')
            ->groupBy('entity_id')
            ->get()
            ->keyBy('entity_id');

        $campaigns->each(function ($campaign) use ($perCampaign) {
            $s = $perCampaign->get($campaign->external_id);
            $sp = (float) ($s->total_spend ?? 0);
            $re = (float) ($s->total_revenue ?? 0);
            $cl = (int) ($s->total_clicks ?? 0);
            $co = (int) ($s->total_conversions ?? 0);

            $campaign->period_stats = (object) [
                'spend'       => $sp,
                'revenue'     => $re,
                'impressions' => (int) ($s->total_impressions ?? 0),
                'clicks'      => $cl,
                'conversions' => $co,
                'roas'        => $sp > 0 ? round($re / $sp, 2) : 0,
                'cpc'         => $cl > 0 ? round($sp / $cl, 2) : 0,
                'cpa'         => $co > 0 ? round($sp / $co, 2) : 0,
            ];
        });

        // Gasto diário dos últimos 14 dias para o mini-gráfico
        $dailySpend = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $fourteenDaysAgo)
            ->selectRaw('snapshot_date, SUM(spend) AS total_spend')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        return view('portal.campaigns.index', compact('client', 'stats', 'campaigns', 'dailySpend'));
    }
}
