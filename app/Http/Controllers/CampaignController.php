<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\CampaignInsight;
use App\Models\CampaignLog;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientAdBudget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public static array $campaignStatuses = [
        'active'   => 'Ativa',
        'paused'   => 'Pausada',
        'deleted'  => 'Excluída',
        'archived' => 'Arquivada',
    ];

    public static array $periods = [
        '7d'         => 'Últimos 7 dias',
        '30d'        => 'Últimos 30 dias',
        'month'      => 'Mês atual',
        'last_month' => 'Mês anterior',
    ];

    public function index(Request $request)
    {
        $clientId   = $request->get('client_id') ?: null;
        $campaignId = $request->get('ad_campaign_id') ?: null;
        $platform   = $request->get('platform') ?: null;
        $period     = $request->filled('period') && array_key_exists($request->get('period'), self::$periods)
            ? $request->get('period')
            : '7d';

        // Sem parâmetro na URL (primeiro load) → "Ativa" por padrão.
        // status= vazio explícito (usuário escolheu "Todos os status") → mostra tudo.
        $statusFilter = $request->has('status') ? $request->get('status') : 'active';

        [$periodStart, $periodEnd, $periodLabel] = $this->periodRange($period);

        // Se só a campanha foi selecionada (sem cliente), o cliente dela vira o
        // escopo efetivo — assim os cards de orçamento também se moldam.
        $selectedCampaign = $campaignId ? AdCampaign::with('adAccount')->find($campaignId) : null;
        $effectiveClientId = $clientId ?: $selectedCampaign?->adAccount?->client_id;

        $accountsQuery = ClientAdAccount::whereIn('client_id', Client::pluck('id'));
        if ($effectiveClientId) {
            $accountsQuery->where('client_id', $effectiveClientId);
        }
        $adAccountIds = $accountsQuery->pluck('id');

        // Opções do filtro de campanha — restritas ao cliente/plataforma/status
        // selecionados, pra o próprio dropdown de campanha já vir dinâmico.
        $campaignOptions = $adAccountIds->isEmpty() ? collect() : AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
            ->with('adAccount.client:id,company_name')
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->orderBy('name')
            ->get(['id', 'name', 'client_ad_account_id']);

        $campaigns = collect();
        if ($adAccountIds->isNotEmpty()) {
            $campaigns = AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
                ->with('adAccount.client')
                ->when($platform, fn ($q) => $q->where('platform', $platform))
                ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
                ->when($campaignId, fn ($q) => $q->where('id', $campaignId))
                ->orderBy('name')
                ->get();

            $perCampaignStats = DB::connection('pgsql')
                ->table('ad_daily_snapshots')
                ->whereIn('client_ad_account_id', $adAccountIds)
                ->where('entity_level', 'campaign')
                ->whereBetween('snapshot_date', [$periodStart, $periodEnd])
                ->when($platform, fn ($q) => $q->where('platform', $platform))
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

        // "Gasto no período" — mesmo escopo de cliente/campanha/plataforma/período da tabela.
        $periodSpend = 0.0;
        if ($adAccountIds->isNotEmpty()) {
            $spendQuery = DB::connection('pgsql')
                ->table('ad_daily_snapshots')
                ->whereIn('client_ad_account_id', $adAccountIds)
                ->where('entity_level', 'campaign')
                ->whereBetween('snapshot_date', [$periodStart, $periodEnd])
                ->when($platform, fn ($q) => $q->where('platform', $platform));

            if ($selectedCampaign) {
                $spendQuery->where('client_ad_account_id', $selectedCampaign->client_ad_account_id)
                    ->where('entity_id', $selectedCampaign->external_id);
            }

            $periodSpend = (float) $spendQuery->sum('spend');
        }

        // "Campanhas ativas" — sempre conta status=active de verdade, independente do
        // filtro de Status escolhido (que só restringe as linhas da tabela).
        $activeCampaigns = $adAccountIds->isEmpty() ? 0 : AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($campaignId, fn ($q) => $q->where('id', $campaignId))
            ->where('status', 'active')
            ->count();

        // Orçamento é mensal por natureza — sempre mês corrente, só respeita o cliente.
        // Duas queries agregadas no lugar de N+1 (uma por cliente) — antes isso rodava
        // 2 a 4 consultas *por cliente* em loop, ficando bem lento com muitos clientes.
        $budgetClientIds = $effectiveClientId ? collect([$effectiveClientId]) : Client::pluck('id');

        $currentBudgetByClient = ClientAdBudget::whereIn('client_id', $budgetClientIds)
            ->where('start_date', '<=', now())
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('client_id')
            ->map(fn ($group) => $group->first());

        $monthSpendByClient = DB::connection('pgsql')
            ->table('ad_daily_snapshots as s')
            ->join('client_ad_accounts as a', 'a.id', '=', 's.client_ad_account_id')
            ->whereIn('a.client_id', $budgetClientIds)
            ->where('s.entity_level', 'campaign')
            ->where('s.snapshot_date', '>=', now()->startOfMonth()->toDateString())
            ->groupBy('a.client_id')
            ->selectRaw('a.client_id, COALESCE(SUM(s.spend), 0) as total_spend')
            ->pluck('total_spend', 'client_id');

        $totalBudget = 0.0;
        $overBudgetCount = 0;
        foreach ($currentBudgetByClient as $clientIdForBudget => $budget) {
            $totalBudget += (float) $budget->monthly_budget;
            $spend = (float) ($monthSpendByClient[$clientIdForBudget] ?? 0);
            if ($spend > (float) $budget->monthly_budget) {
                $overBudgetCount++;
            }
        }

        $stats = [
            'period_spend'      => $periodSpend,
            'total_budget'      => $totalBudget,
            'over_budget_count' => $overBudgetCount,
            'active_campaigns'  => $activeCampaigns,
        ];

        // Insights: client_id sempre existe (mesmo os de orçamento, que não têm campanha/conta
        // associada), então o filtro certo é por client_id — não por client_ad_account_id.
        $openInsights = CampaignInsight::whereIn('status', ['novo', 'lido'])
            ->when($effectiveClientId, fn ($q) => $q->where('client_id', $effectiveClientId))
            ->when($campaignId, fn ($q) => $q->where('ad_campaign_id', $campaignId))
            ->whereBetween('generated_at', ["{$periodStart} 00:00:00", "{$periodEnd} 23:59:59"])
            ->with(['client', 'campaign'])
            ->orderByDesc('generated_at')
            ->get();

        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('campaigns.index', compact(
            'campaigns', 'stats', 'openInsights', 'clients', 'campaignOptions',
            'periodLabel', 'period', 'statusFilter', 'clientId', 'campaignId', 'platform'
        ));
    }

    public function show(AdCampaign $campaign)
    {
        $campaign->load('adAccount.client');
        $logs = $campaign->logs()->with('user')->orderByDesc('created_at')->get();

        [$periodStart, $periodEnd] = $this->periodRange('7d');

        $row = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->where('client_ad_account_id', $campaign->client_ad_account_id)
            ->where('entity_level', 'campaign')
            ->where('entity_id', $campaign->external_id)
            ->whereBetween('snapshot_date', [$periodStart, $periodEnd])
            ->selectRaw('
                COALESCE(SUM(spend), 0) AS spend,
                COALESCE(SUM(revenue), 0) AS revenue,
                COALESCE(SUM(clicks), 0) AS clicks,
                COALESCE(SUM(impressions), 0) AS impressions,
                COALESCE(SUM(conversions), 0) AS conversions
            ')
            ->first();

        $spend = (float) ($row->spend ?? 0);
        $clicks = (int) ($row->clicks ?? 0);
        $impressions = (int) ($row->impressions ?? 0);
        $conversions = (int) ($row->conversions ?? 0);
        $revenue = (float) ($row->revenue ?? 0);

        $stats = (object) [
            'spend' => $spend,
            'cpa'   => $conversions > 0 ? $spend / $conversions : null,
            'ctr'   => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'roas'  => $spend > 0 ? round($revenue / $spend, 2) : null,
        ];

        return view('campaigns.show', compact('campaign', 'logs', 'stats'));
    }

    public function updateManagementStatus(Request $request, AdCampaign $campaign)
    {
        $data = $request->validate([
            'management_status' => 'required|string|in:' . implode(',', array_keys(AdCampaign::$managementStatuses)),
        ]);

        $campaign->update($data);

        return back()->with('success', 'Status atualizado.');
    }

    public function updateManagementSituation(Request $request, AdCampaign $campaign)
    {
        $data = $request->validate([
            'management_situation' => 'nullable|string|in:' . implode(',', array_keys(AdCampaign::$managementSituations)),
        ]);

        $campaign->update($data);

        return back()->with('success', 'Situação atualizada.');
    }

    public function updateOptimizationTier(Request $request, AdCampaign $campaign)
    {
        $data = $request->validate([
            'optimization_tier' => 'required|string|in:' . implode(',', array_keys(AdCampaign::$optimizationTiers)),
        ]);

        $campaign->update($data);

        return back()->with('success', 'Frequência de otimização atualizada.');
    }

    public function markOptimized(Request $request, AdCampaign $campaign)
    {
        $data = $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        DB::connection('pgsql')->transaction(function () use ($campaign, $data) {
            CampaignLog::create([
                'organization_id'      => $campaign->organization_id,
                'client_ad_account_id' => $campaign->client_ad_account_id,
                'entity_level'         => 'campaign',
                'entity_id'            => $campaign->external_id,
                'entity_name'          => $campaign->name,
                'platform'             => $campaign->platform,
                'logged_by'            => Auth::id(),
                'type'                 => 'otimizacao',
                'description'          => $data['comment'] ?: 'Otimização realizada',
            ]);

            $campaign->update(['last_optimized_at' => now()]);
        });

        return back()->with('success', 'Otimização registrada.')->withFragment('otimizacao');
    }

    /**
     * @return array{0: string, 1: string, 2: string} início, fim (Y-m-d) e label do período.
     */
    private function periodRange(string $period): array
    {
        return match ($period) {
            '30d'        => [now()->subDays(30)->toDateString(), now()->toDateString(), 'Últimos 30 dias'],
            'month'      => [now()->startOfMonth()->toDateString(), now()->toDateString(), 'mês atual'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString(), 'mês anterior'],
            default      => [now()->subDays(7)->toDateString(), now()->toDateString(), 'Últimos 7 dias'],
        };
    }
}
