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
        '15d'        => 'Últimos 15 dias',
        '30d'        => 'Últimos 30 dias',
        'month'      => 'Mês atual',
        'last_month' => 'Mês anterior',
    ];

    // Períodos da página de detalhe da campanha — conjunto próprio (diferente
    // de $periods, que é o filtro da listagem) porque o pedido aqui foi
    // especificamente ontem/7/15/30 dias.
    public static array $campaignPeriods = [
        'yesterday' => 'Ontem',
        '7d'        => 'Últimos 7 dias',
        '15d'       => 'Últimos 15 dias',
        '30d'       => 'Últimos 30 dias',
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
                // Quem nunca foi otimizada (ou está há mais tempo sem otimização)
                // aparece primeiro, pra ficar fácil ver o que precisa de atenção.
                ->orderByRaw('last_optimized_at ASC NULLS FIRST')
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
                $campaign->had_activity_in_period = $spend > 0 || $impressions > 0;
            }

            // Por padrão só mostra quem teve veiculação de verdade no período - reduz
            // a visão pra o que importa. Filtro de status continua funcionando por
            // cima disso normalmente (são independentes, não um substitui o outro).
            $campaigns = $campaigns->filter->had_activity_in_period->values();
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

        // Comparativo com o período anterior de mesma duração (ex: 7d → compara com
        // os 7 dias imediatamente antes) - mesmo escopo de cliente/campanha/plataforma.
        [$previousPeriodStart, $previousPeriodEnd] = $this->previousPeriodRange($periodStart, $periodEnd);
        $previousPeriodSpend = 0.0;
        if ($adAccountIds->isNotEmpty()) {
            $previousSpendQuery = DB::connection('pgsql')
                ->table('ad_daily_snapshots')
                ->whereIn('client_ad_account_id', $adAccountIds)
                ->where('entity_level', 'campaign')
                ->whereBetween('snapshot_date', [$previousPeriodStart, $previousPeriodEnd])
                ->when($platform, fn ($q) => $q->where('platform', $platform));

            if ($selectedCampaign) {
                $previousSpendQuery->where('client_ad_account_id', $selectedCampaign->client_ad_account_id)
                    ->where('entity_id', $selectedCampaign->external_id);
            }

            $previousPeriodSpend = (float) $previousSpendQuery->sum('spend');
        }
        $periodSpendDelta = $previousPeriodSpend > 0
            ? round((($periodSpend - $previousPeriodSpend) / $previousPeriodSpend) * 100, 1)
            : null;

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
            'period_spend'       => $periodSpend,
            'period_spend_delta' => $periodSpendDelta,
            'total_budget'       => $totalBudget,
            'over_budget_count'  => $overBudgetCount,
            'active_campaigns'   => $activeCampaigns,
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

        // Agrupamento opcional (mesmo padrão de Filas/Sprint) — mantém a ordenação
        // por otimização (mais atrasada primeiro) dentro de cada grupo, já que
        // group By não reordena, só particiona a coleção já ordenada.
        $groupBy = $request->get('group_by', '');
        $campaignsGrouped = $groupBy
            ? AdCampaign::groupCollection($campaigns, $groupBy)->sortByDesc(fn ($g) => $g->filter->isOptimizationOverdue()->count())
            : null;

        return view('campaigns.index', compact(
            'campaigns', 'campaignsGrouped', 'groupBy', 'stats', 'openInsights', 'clients', 'campaignOptions',
            'periodLabel', 'period', 'statusFilter', 'clientId', 'campaignId', 'platform'
        ));
    }

    public function show(Request $request, AdCampaign $campaign)
    {
        $campaign->load('adAccount.client');
        $logs = $campaign->logs()->with('user')->orderByDesc('created_at')->get();

        $period = $request->filled('period') && array_key_exists($request->get('period'), self::$campaignPeriods)
            ? $request->get('period')
            : '7d';

        [$periodStart, $periodEnd, $periodLabel] = $this->periodRange($period);

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
                COALESCE(SUM(conversions), 0) AS conversions,
                COALESCE(SUM(reach), 0) AS reach
            ')
            ->first();

        $spend = (float) ($row->spend ?? 0);
        $clicks = (int) ($row->clicks ?? 0);
        $impressions = (int) ($row->impressions ?? 0);
        $conversions = (int) ($row->conversions ?? 0);
        $revenue = (float) ($row->revenue ?? 0);
        $reach = (int) ($row->reach ?? 0);

        // Recalculadas em cima da soma dos 7 dias — não dá pra somar as médias
        // diárias já gravadas em ad_daily_snapshots (cpc/ctr/cpa/roas são por dia).
        $stats = (object) [
            'spend'        => $spend,
            'impressions'  => $impressions,
            'clicks'       => $clicks,
            'reach'        => $reach,
            'conversions'  => $conversions,
            'cpc'          => $clicks > 0 ? $spend / $clicks : null,
            'cpa'          => $conversions > 0 ? $spend / $conversions : null,
            'ctr'          => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'roas'         => $spend > 0 ? round($revenue / $spend, 2) : null,
        ];

        return view('campaigns.show', compact('campaign', 'logs', 'stats', 'period', 'periodLabel'));
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
            'comment' => 'required|string|max:2000',
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
                'description'          => $data['comment'],
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
            'yesterday'  => [now()->subDay()->toDateString(), now()->subDay()->toDateString(), 'Ontem'],
            '15d'        => [now()->subDays(15)->toDateString(), now()->toDateString(), 'Últimos 15 dias'],
            '30d'        => [now()->subDays(30)->toDateString(), now()->toDateString(), 'Últimos 30 dias'],
            'month'      => [now()->startOfMonth()->toDateString(), now()->toDateString(), 'mês atual'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString(), 'mês anterior'],
            default      => [now()->subDays(7)->toDateString(), now()->toDateString(), 'Últimos 7 dias'],
        };
    }

    /**
     * Período imediatamente anterior, com a mesma duração em dias do período
     * selecionado - base do comparativo "subiu/desceu vs período anterior".
     * @return array{0: string, 1: string} início, fim (Y-m-d)
     */
    private function previousPeriodRange(string $periodStart, string $periodEnd): array
    {
        $start = \Illuminate\Support\Carbon::parse($periodStart);
        $end   = \Illuminate\Support\Carbon::parse($periodEnd);
        $days  = $start->diffInDays($end) + 1;

        $previousEnd   = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [$previousStart->toDateString(), $previousEnd->toDateString()];
    }
}
