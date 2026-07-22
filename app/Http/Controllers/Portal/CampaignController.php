<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public static array $periods = [
        '7d'  => 'Últimos 7 dias',
        '15d' => 'Últimos 15 dias',
        '30d' => 'Últimos 30 dias',
    ];

    public static array $statuses = [
        'active'   => 'Ativa',
        'paused'   => 'Pausada',
        'deleted'  => 'Removida',
        'archived' => 'Arquivada',
    ];

    public function index(Request $request): View
    {
        $client = app('currentPortalClient');
        $adAccountIds = $client->adAccounts()->pluck('id');

        $period = $request->filled('period') && array_key_exists($request->get('period'), self::$periods)
            ? $request->get('period')
            : '30d';

        // Sem parâmetro (primeiro load) → "Ativa" por padrão, igual ao app interno.
        // status= vazio explícito ("Todos os status") → mostra tudo.
        $statusFilter = $request->has('status') ? $request->get('status') : 'active';

        [$periodStart, $periodEnd, $periodLabel] = $this->periodRange($period);
        [$previousStart, $previousEnd] = $this->previousPeriodRange($periodStart, $periodEnd);

        if ($adAccountIds->isEmpty()) {
            return view('portal.campaigns.index', [
                'client' => $client, 'stats' => null, 'deltas' => null, 'campaigns' => collect(),
                'dailySpend' => collect(), 'period' => $period, 'statusFilter' => $statusFilter, 'periodLabel' => $periodLabel,
            ]);
        }

        $aggregate = function (string $start, string $end) use ($adAccountIds) {
            return DB::connection('pgsql')
                ->table('ad_daily_snapshots')
                ->whereIn('client_ad_account_id', $adAccountIds)
                ->where('entity_level', 'campaign')
                ->where('snapshot_date', '>=', $start)
                ->where('snapshot_date', '<=', $end)
                ->selectRaw('
                    COALESCE(SUM(spend), 0)       AS total_spend,
                    COALESCE(SUM(revenue), 0)     AS total_revenue,
                    COALESCE(SUM(impressions), 0) AS total_impressions,
                    COALESCE(SUM(clicks), 0)      AS total_clicks,
                    COALESCE(SUM(conversions), 0) AS total_conversions
                ')
                ->first();
        };

        $stats = $this->buildStats($aggregate($periodStart, $periodEnd));
        $previousStats = $this->buildStats($aggregate($previousStart, $previousEnd));
        $deltas = $this->buildDeltas($stats, $previousStats);

        // Campanhas com stats individuais do período selecionado
        $campaigns = AdCampaign::whereIn('client_ad_account_id', $adAccountIds)
            ->with('adAccount')
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->get();

        $perCampaign = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->whereBetween('snapshot_date', [$periodStart, $periodEnd])
            ->selectRaw('
                entity_id,
                COALESCE(SUM(spend), 0)       AS total_spend,
                COALESCE(SUM(revenue), 0)     AS total_revenue,
                COALESCE(SUM(impressions), 0) AS total_impressions,
                COALESCE(SUM(clicks), 0)      AS total_clicks,
                COALESCE(SUM(conversions), 0) AS total_conversions
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
            $im = (int) ($s->total_impressions ?? 0);

            $campaign->period_stats = (object) [
                'spend'       => $sp,
                'revenue'     => $re,
                'impressions' => $im,
                'clicks'      => $cl,
                'conversions' => $co,
                'roas'        => $sp > 0 ? round($re / $sp, 2) : 0,
                'cpc'         => $cl > 0 ? round($sp / $cl, 2) : 0,
                'cpa'         => $co > 0 ? round($sp / $co, 2) : 0,
            ];
            $campaign->had_activity_in_period = $sp > 0 || $im > 0;
        });

        // Por padrão só mostra quem teve veiculação de verdade no período - reduz
        // a visão. Filtro de status continua funcionando por cima disso normalmente.
        $campaigns = $campaigns->filter->had_activity_in_period->values();

        // Gasto diário dos últimos 14 dias pro mini-gráfico (janela fixa, independente
        // do período selecionado acima - é só contexto visual de tendência recente).
        $fourteenDaysAgo = now()->subDays(13)->toDateString();
        $dailySpend = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $fourteenDaysAgo)
            ->selectRaw('snapshot_date, SUM(spend) AS total_spend')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        return view('portal.campaigns.index', compact(
            'client', 'stats', 'deltas', 'campaigns', 'dailySpend', 'period', 'statusFilter', 'periodLabel'
        ));
    }

    private function buildStats(?object $agg): array
    {
        $spend = (float) ($agg->total_spend ?? 0);
        $revenue = (float) ($agg->total_revenue ?? 0);
        $clicks = (int) ($agg->total_clicks ?? 0);
        $conversions = (int) ($agg->total_conversions ?? 0);

        return [
            'spend'       => $spend,
            'revenue'     => $revenue,
            'impressions' => (int) ($agg->total_impressions ?? 0),
            'clicks'      => $clicks,
            'conversions' => $conversions,
            'roas'        => $spend > 0 ? round($revenue / $spend, 2) : 0,
            'cpc'         => $clicks > 0 ? round($spend / $clicks, 2) : 0,
            'cpa'         => $conversions > 0 ? round($spend / $conversions, 2) : 0,
        ];
    }

    // Comparativo com o período anterior, como as plataformas de anúncio mostram -
    // % de variação, sem julgar se subir é "bom" ou "ruim" pra cada métrica.
    private function buildDeltas(array $current, array $previous): array
    {
        $deltas = [];
        foreach (['spend', 'roas', 'conversions', 'cpc'] as $key) {
            $prev = $previous[$key] ?? 0;
            $deltas[$key] = $prev > 0 ? round((($current[$key] - $prev) / $prev) * 100, 1) : null;
        }

        return $deltas;
    }

    /**
     * @return array{0: string, 1: string, 2: string} início, fim (Y-m-d) e label do período.
     */
    private function periodRange(string $period): array
    {
        return match ($period) {
            '15d' => [now()->subDays(15)->toDateString(), now()->toDateString(), 'Últimos 15 dias'],
            '30d' => [now()->subDays(30)->toDateString(), now()->toDateString(), 'Últimos 30 dias'],
            default => [now()->subDays(7)->toDateString(), now()->toDateString(), 'Últimos 7 dias'],
        };
    }

    /**
     * @return array{0: string, 1: string} início, fim (Y-m-d) do período imediatamente anterior.
     */
    private function previousPeriodRange(string $periodStart, string $periodEnd): array
    {
        $start = Carbon::parse($periodStart);
        $end   = Carbon::parse($periodEnd);
        $days  = $start->diffInDays($end) + 1;

        $previousEnd   = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [$previousStart->toDateString(), $previousEnd->toDateString()];
    }
}
