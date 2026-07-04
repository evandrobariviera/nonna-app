<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContextResolver
{
    public static function for(Model $entity): array
    {
        return match (true) {
            $entity instanceof Task       => self::forTask($entity),
            $entity instanceof Project    => self::forProject($entity),
            $entity instanceof AdCampaign => self::forCampaign($entity),
            default                       => [],
        };
    }

    public static function forTask(Task $task): array
    {
        $task->loadMissing(['project', 'client', 'executor']);

        $context = [
            'task_id'          => $task->id,
            'task_title'       => $task->title ?? '',
            'task_description' => $task->description ?? '',
            'task_type'        => $task->typeLabel(),
            'task_status'      => $task->statusLabel(),
            'task_situation'   => $task->situationLabel(),
            'task_destination' => $task->destinationLabel(),
            'task_due_date'    => $task->due_date?->format('d/m/Y') ?? '',
        ];

        if ($task->client) {
            $context['client_name']    = $task->client->name ?? '';
            $context['client_segment'] = $task->client->segment ?? '';
        }

        if ($task->project) {
            $context['project_name']  = $task->project->name ?? '';
            $context['project_brief'] = $task->project->description ?? '';
        }

        if ($task->executor) {
            $context['executor_name'] = $task->executor->name ?? '';
        }

        return $context;
    }

    public static function forProject(Project $project): array
    {
        $project->loadMissing(['client', 'macroPlan']);

        $context = [
            'project_id'          => $project->id,
            'project_name'        => $project->name ?? '',
            'project_description' => $project->description ?? '',
        ];

        if ($project->client) {
            $context['client_name']    = $project->client->name ?? '';
            $context['client_segment'] = $project->client->segment ?? '';
        }

        if ($project->macroPlan) {
            $context['macro_plan_name'] = $project->macroPlan->name ?? '';
        }

        return $context;
    }

    public static function forCampaign(AdCampaign $campaign): array
    {
        $campaign->loadMissing(['adAccount.client']);
        $client = $campaign->adAccount?->client;

        $context = [
            'campaign_id'        => $campaign->id,
            'campaign_name'      => $campaign->name ?? '',
            'campaign_status'    => $campaign->status ?? '',
            'campaign_objective' => $campaign->objective ?? '',
            'client_name'        => $client?->company_name ?? '',
        ];

        $last7d = self::campaignMetrics($campaign, 7, 0);
        $prev7d = self::campaignMetrics($campaign, 14, 7);

        return array_merge($context, [
            'spend_last_7_days'  => number_format($last7d['spend'], 2, ',', '.'),
            'cpa_last_7_days'    => $last7d['cpa'] !== null ? number_format($last7d['cpa'], 2, ',', '.') : '—',
            'ctr_last_7_days'    => $last7d['ctr'] !== null ? number_format($last7d['ctr'], 2, ',', '.') . '%' : '—',
            'roas_last_7_days'   => $last7d['roas'] !== null ? number_format($last7d['roas'], 2, ',', '.') . 'x' : '—',
            'spend_previous_7_days' => number_format($prev7d['spend'], 2, ',', '.'),
            'cpa_previous_7_days'   => $prev7d['cpa'] !== null ? number_format($prev7d['cpa'], 2, ',', '.') : '—',
        ]);
    }

    /**
     * Agrega métricas de ad_daily_snapshots para uma campanha numa janela de dias
     * terminando `$daysAgoEnd` dias atrás (ex: janela 7..0 = últimos 7 dias).
     */
    public static function campaignMetrics(AdCampaign $campaign, int $daysAgoStart, int $daysAgoEnd): array
    {
        $start = now()->subDays($daysAgoStart)->toDateString();
        $end = now()->subDays($daysAgoEnd)->toDateString();

        $agg = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->where('client_ad_account_id', $campaign->client_ad_account_id)
            ->where('entity_level', 'campaign')
            ->where('entity_id', $campaign->external_id)
            ->whereBetween('snapshot_date', [$start, $end])
            ->selectRaw('
                COALESCE(SUM(spend), 0) AS spend,
                COALESCE(SUM(revenue), 0) AS revenue,
                COALESCE(SUM(clicks), 0) AS clicks,
                COALESCE(SUM(impressions), 0) AS impressions,
                COALESCE(SUM(conversions), 0) AS conversions
            ')
            ->first();

        $spend = (float) ($agg->spend ?? 0);
        $clicks = (int) ($agg->clicks ?? 0);
        $impressions = (int) ($agg->impressions ?? 0);
        $conversions = (int) ($agg->conversions ?? 0);
        $revenue = (float) ($agg->revenue ?? 0);

        return [
            'spend' => $spend,
            'cpa'   => $conversions > 0 ? $spend / $conversions : null,
            'ctr'   => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'roas'  => $spend > 0 ? round($revenue / $spend, 2) : null,
        ];
    }
}
