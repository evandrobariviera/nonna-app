<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\CampaignInsight;
use App\Models\Client;
use App\Models\ClientAdAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public static array $campaignStatuses = [
        'active'   => 'Ativa',
        'paused'   => 'Pausada',
        'deleted'  => 'Excluída',
        'archived' => 'Arquivada',
    ];

    public function index(Request $request)
    {
        $adAccountIds = ClientAdAccount::whereIn('client_id', Client::pluck('id'))->pluck('id');

        $campaigns = collect();
        if ($adAccountIds->isNotEmpty()) {
            $campaigns = AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
                ->with('adAccount.client')
                ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->platform))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('client_id'), fn ($q) => $q->whereHas(
                    'adAccount',
                    fn ($q2) => $q2->where('client_id', $request->client_id)
                ))
                ->orderBy('name')
                ->get();

            $since = now()->subDays(7)->toDateString();
            $perCampaignStats = DB::connection('pgsql')
                ->table('ad_daily_snapshots')
                ->whereIn('client_ad_account_id', $adAccountIds)
                ->where('entity_level', 'campaign')
                ->where('snapshot_date', '>=', $since)
                ->selectRaw('
                    client_ad_account_id, entity_id,
                    COALESCE(SUM(spend), 0) AS spend,
                    COALESCE(SUM(revenue), 0) AS revenue,
                    COALESCE(SUM(clicks), 0) AS clicks,
                    COALESCE(SUM(impressions), 0) AS impressions,
                    COALESCE(SUM(conversions), 0) AS conversions
                ')
                ->groupBy('client_ad_account_id', 'entity_id')
                ->get()
                ->keyBy(fn ($row) => $row->client_ad_account_id . ':' . $row->entity_id);

            foreach ($campaigns as $campaign) {
                $row = $perCampaignStats->get($campaign->client_ad_account_id . ':' . $campaign->external_id);
                $spend = (float) ($row->spend ?? 0);
                $clicks = (int) ($row->clicks ?? 0);
                $impressions = (int) ($row->impressions ?? 0);
                $conversions = (int) ($row->conversions ?? 0);
                $revenue = (float) ($row->revenue ?? 0);

                $campaign->stats = (object) [
                    'spend' => $spend,
                    'cpa'   => $conversions > 0 ? $spend / $conversions : null,
                    'ctr'   => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
                    'roas'  => $spend > 0 ? round($revenue / $spend, 2) : null,
                ];
            }
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthSpend = $adAccountIds->isEmpty() ? 0.0 : (float) DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $monthStart)
            ->sum('spend');

        $totalBudget = 0.0;
        $overBudgetCount = 0;
        foreach (Client::all() as $client) {
            $budget = $client->currentAdBudget();
            if (!$budget) {
                continue;
            }
            $totalBudget += (float) $budget->monthly_budget;
            if ($client->currentMonthAdSpend() > (float) $budget->monthly_budget) {
                $overBudgetCount++;
            }
        }

        $stats = [
            'month_spend'       => $monthSpend,
            'total_budget'      => $totalBudget,
            'over_budget_count' => $overBudgetCount,
            'active_campaigns'  => $adAccountIds->isEmpty() ? 0 : AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
                ->where('status', 'active')->count(),
        ];

        $openInsights = CampaignInsight::whereIn('status', ['novo', 'lido'])
            ->with(['client', 'campaign'])
            ->orderByDesc('generated_at')
            ->get();

        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('campaigns.index', compact('campaigns', 'stats', 'openInsights', 'clients'));
    }
}
