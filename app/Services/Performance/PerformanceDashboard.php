<?php

namespace App\Services\Performance;

use App\Models\CampaignInsight;
use App\Models\Client;
use App\Models\ServiceDiagnostic;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números do dashboard central da Central de Performance que não estão no
 * funil (LeadFunnel): evolução semana a semana, conversas de WhatsApp,
 * insights em aberto e o último diagnóstico de atendimento. Sem cliente,
 * soma todos.
 */
class PerformanceDashboard
{
    /**
     * Últimas $weeks semanas (segunda a domingo, como o date_trunc do Postgres —
     * o App começa a semana no domingo), a atual incompleta:
     * investido, leads, bons e vendas por semana.
     */
    public static function weekly(?Client $client, int $weeks = 8): array
    {
        $db = DB::connection('pgsql');
        $start = now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks($weeks - 1)->toDateString();

        $spend = $db->table('ad_daily_snapshots as s')
            ->join('client_ad_accounts as a', 'a.id', '=', 's.client_ad_account_id')
            ->when($client, fn ($q) => $q->where('a.client_id', $client->id))
            ->where('s.entity_level', 'campaign')
            ->where('s.snapshot_date', '>=', $start)
            ->groupByRaw("date_trunc('week', s.snapshot_date)")
            ->selectRaw("date_trunc('week', s.snapshot_date)::date AS week, SUM(s.spend) AS spend")
            ->pluck('spend', 'week');

        $leads = $db->table('client_lead_opportunities as o')
            ->join('client_leads as l', 'l.id', '=', 'o.client_lead_id')
            ->when($client, fn ($q) => $q->where('l.client_id', $client->id), fn ($q) => $q->whereNotNull('l.client_id'))
            ->whereRaw('COALESCE(o.received_at, o.created_at) >= ?', [$start])
            ->groupByRaw("date_trunc('week', COALESCE(o.received_at, o.created_at))")
            ->selectRaw("date_trunc('week', COALESCE(o.received_at, o.created_at))::date AS week,
                COUNT(*) AS leads,
                COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'bom') AS good,
                COUNT(*) FILTER (WHERE o.stage = 'ganho') AS sold")
            ->get()
            ->keyBy('week');

        $rows = [];
        for ($i = 0; $i < $weeks; $i++) {
            $week = now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks($weeks - 1 - $i);
            $key = $week->toDateString();
            $l = $leads->get($key);
            $rows[] = [
                'week'    => $week,
                'current' => $i === $weeks - 1,
                'spend'   => round((float) ($spend[$key] ?? 0), 2),
                'leads'   => (int) ($l->leads ?? 0),
                'good'    => (int) ($l->good ?? 0),
                'sold'    => (int) ($l->sold ?? 0),
            ];
        }

        return $rows;
    }

    /** Conversas de WhatsApp que começaram no período (e quantas vieram de anúncio). */
    public static function conversations(?Client $client, CarbonInterface $from, CarbonInterface $to): array
    {
        $r = DB::connection('pgsql')->table('service_conversations')
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->where('is_group', false)
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('COUNT(*) AS total, COUNT(ad_source_id) AS from_ad')
            ->first();

        return ['total' => (int) ($r->total ?? 0), 'from_ad' => (int) ($r->from_ad ?? 0)];
    }

    /** Insights (alertas e análises da IA) em aberto, mais graves primeiro. */
    public static function openInsights(?Client $client, int $limit = 5): Collection
    {
        return CampaignInsight::whereIn('status', ['novo', 'lido'])
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->with(['client:id,nickname,company_name', 'campaign:id,name'])
            ->orderByRaw("CASE severity WHEN 'critico' THEN 0 WHEN 'atencao' THEN 1 ELSE 2 END")
            ->orderByDesc('generated_at')
            ->limit($limit)
            ->get();
    }

    public static function openInsightsCount(?Client $client): int
    {
        return CampaignInsight::whereIn('status', ['novo', 'lido'])
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->count();
    }

    public static function latestServiceDiagnostic(Client $client): ?ServiceDiagnostic
    {
        return ServiceDiagnostic::where('client_id', $client->id)
            ->orderByDesc('period_end')
            ->first(['id', 'client_id', 'client_integration_id', 'period_start', 'period_end', 'service_score', 'status', 'total_conversations', 'conversion_rate', 'avg_first_response_minutes']);
    }
}
