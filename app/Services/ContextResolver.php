<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use App\Models\AdCampaign;
use App\Models\AdAdset;
use App\Models\AdAd;
use App\Models\CampaignLog;
use App\Models\Opportunity;
use App\Models\Meeting;
use App\Models\MacroPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContextResolver
{
    public static function for(Model $entity): array
    {
        return match (true) {
            $entity instanceof Task        => self::forTask($entity),
            $entity instanceof Project     => self::forProject($entity),
            $entity instanceof AdCampaign  => self::forCampaign($entity),
            $entity instanceof AdAdset     => self::forAdset($entity),
            $entity instanceof AdAd        => self::forAd($entity),
            $entity instanceof Opportunity => self::forOpportunity($entity),
            $entity instanceof Meeting     => self::forMeeting($entity),
            $entity instanceof MacroPlan   => self::forMacroPlan($entity),
            default                        => [],
        };
    }

    public static function forMacroPlan(MacroPlan $macroPlan): array
    {
        $macroPlan->loadMissing(['client', 'responsible']);

        return [
            'macro_plan_id'        => $macroPlan->id,
            'macro_plan_title'     => $macroPlan->title ?? '',
            'macro_plan_status'    => $macroPlan->statusLabel(),
            'client_name'          => $macroPlan->client?->displayName() ?? '',
            'responsible_name'     => $macroPlan->responsible?->name ?? '',
            'period_end'           => $macroPlan->period_end?->format('d/m/Y') ?? '',
        ];
    }

    public static function forMeeting(Meeting $meeting): array
    {
        $meeting->loadMissing(['client', 'organizer']);

        return [
            'meeting_id'    => $meeting->id,
            'meeting_title' => $meeting->title ?? '',
            'meeting_type'  => $meeting->typeLabel(),
            'meeting_date'  => $meeting->scheduled_at?->format('d/m/Y H:i') ?? '',
            'client_name'   => $meeting->client?->displayName() ?? '',
            'organizer_name'=> $meeting->organizer?->name ?? '',
            'ata'           => $meeting->ata ?? '',
        ];
    }

    public static function forOpportunity(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(['client', 'contact', 'assignedTo']);

        return [
            'opportunity_id'        => $opportunity->id,
            'opportunity_title'     => $opportunity->title ?? '',
            'opportunity_stage'     => $opportunity->stageLabel(),
            'opportunity_type'      => $opportunity->typeLabel(),
            'opportunity_type_key'  => $opportunity->type ?? '',
            'client_name'           => $opportunity->client?->displayName() ?? $opportunity->contact?->name ?? '',
            'contact_name'          => $opportunity->contact?->name ?? '',
            'contact_whatsapp'      => $opportunity->contact?->whatsapp ?? '',
            'assigned_to_name'      => $opportunity->assignedTo?->name ?? '',
            'proposed_fee'          => $opportunity->proposed_fee !== null ? number_format((float) $opportunity->proposed_fee, 2, ',', '.') : '',
            'proposed_ad_budget'    => $opportunity->proposed_ad_budget !== null ? number_format((float) $opportunity->proposed_ad_budget, 2, ',', '.') : '',
            'contract_months'       => $opportunity->contract_months ?? '',
            'proposal_url'          => $opportunity->proposal_url ?? '',
            'services_interest'     => collect($opportunity->services_interest ?? [])
                ->map(fn ($s) => \App\Models\Client::$services[$s] ?? $s)->implode(', '),
        ];
    }

    public static function forTask(Task $task): array
    {
        $task->loadMissing(['project', 'macroPlan', 'meeting', 'client', 'executor']);

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
        } elseif ($task->macroPlan) {
            // Vinculada direto ao Planejamento, sem Projeto — ver Task::macroPlan().
            $context['macro_plan_name'] = $task->macroPlan->title ?? '';
        }

        if ($task->meeting) {
            // Nasceu de uma Reunião (ver Task::meeting()) — origem permanente.
            $context['source_meeting_title'] = $task->meeting->title ?? '';
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
            'project_id'         => $project->id,
            'project_title'      => $project->title ?? '',
            'project_objective'  => $project->objective ?? '',
            'project_type'       => $project->typeLabel(),
            'project_status'     => $project->statusLabel(),
            'project_disciplines'=> implode(', ', $project->disciplineLabels()),
            'project_start_date' => $project->start_date?->format('d/m/Y') ?? '',
            'project_end_date'   => $project->end_date?->format('d/m/Y') ?? '',
        ];

        if ($project->client) {
            $context['client_name']    = $project->client->displayName() ?? '';
            $context['client_segment'] = $project->client->segment ?? '';
        }

        if ($project->macroPlan) {
            $context['macro_plan_title']  = $project->macroPlan->title ?? '';
            $context['macro_plan_status'] = $project->macroPlan->statusLabel();
            $context['macro_plan_period'] = ($project->macroPlan->period_start && $project->macroPlan->period_end)
                ? $project->macroPlan->period_start->format('d/m/Y') . ' a ' . $project->macroPlan->period_end->format('d/m/Y')
                : '';
        }

        foreach ($project->disciplines ?? [] as $discipline) {
            $field = 'briefing_' . $discipline;
            if (!empty($project->{$field})) {
                $context[$field] = \Illuminate\Support\Str::limit($project->{$field}, 500);
            }
        }

        $context['existing_tasks_summary']  = self::existingTasksSummary($project);
        $context['playbooks_catalog']       = self::playbooksCatalog();
        $context['functional_roles_catalog']= self::functionalRolesCatalog();

        return $context;
    }

    /**
     * Papéis Funcionais da organização, formatado como "chave: Nome" — é
     * assim que a IA referencia um responsável no JSON de rascunho
     * (functional_role_key), já que ela não tem acesso direto à tabela
     * functional_roles.
     */
    private static function functionalRolesCatalog(): string
    {
        $roles = \App\Models\FunctionalRole::orderBy('name')->get(['key', 'name']);
        if ($roles->isEmpty()) {
            return 'Nenhum papel funcional cadastrado.';
        }

        return $roles->map(fn ($r) => "{$r->key}: {$r->name}")->implode('; ');
    }

    /**
     * Contagem por status + até 20 títulos de tarefas abertas do Projeto —
     * evita que a IA sugira uma tarefa que já existe. Cortado em 20 pra não
     * inflar o prompt em projetos com muitas tarefas (ver risco #5 do plano).
     */
    private static function existingTasksSummary(Project $project): string
    {
        $tasks = $project->tasks()->where('status', '!=', 'cancelado')->get(['status', 'title']);
        if ($tasks->isEmpty()) {
            return 'Nenhuma tarefa criada ainda neste projeto.';
        }

        $byStatus = $tasks->groupBy('status')->map->count();
        $counts = $byStatus->map(fn ($count, $status) => (Task::$statuses[$status]['label'] ?? $status) . ": {$count}")->implode(', ');

        $titles = $tasks->take(20)->pluck('title')->implode('; ');

        return "Contagem por status — {$counts}. Títulos (até 20): {$titles}";
    }

    /**
     * Catálogo de Playbooks ativos da organização, formatado pra a IA saber
     * que existem e poder orientar o usuário a aplicá-los em vez de gerar um
     * rascunho equivalente na mão (ver ProjectPlaybook, regra #3 do prompt do
     * Assistente de Lançamento de Tarefas).
     */
    private static function playbooksCatalog(): string
    {
        $playbooks = \App\Models\ProjectPlaybook::where('is_active', true)->withCount('tasks')->orderBy('name')->get();
        if ($playbooks->isEmpty()) {
            return 'Nenhum playbook cadastrado ainda.';
        }

        return $playbooks->map(function ($p) {
            $desc = $p->description ? " — {$p->description}" : '';
            return "\"{$p->name}\" ({$p->tasks_count} tarefa(s)){$desc}";
        })->implode('; ');
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
            'historico_otimizacoes' => self::optimizationHistory($campaign),
        ]);
    }

    /**
     * Resumo em texto dos últimos 14 dias de CampaignLog (otimizações e
     * comentários já registrados pelo gestor) — sem isso a IA pode sugerir
     * algo que já foi feito, porque hoje ela só vê números, não o histórico
     * humano. Limitado às 8 entradas mais recentes pra não inflar o prompt.
     */
    private static function optimizationHistory(AdCampaign $campaign): string
    {
        $logs = $campaign->logs()
            ->with('user')
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        if ($logs->isEmpty()) {
            // Deixa explícito que ausência de registro não é uma decisão
            // deliberada da equipe — sem essa ressalva, a IA já interpretou
            // silêncio como "a equipe avaliou e decidiu manter assim", o que
            // é enganoso (ninguém necessariamente revisou ainda).
            return 'Nenhuma otimização ou comentário registrado nos últimos 14 dias — '
                . 'isso não significa que a campanha foi revisada e aprovada, apenas que não há '
                . 'registro de nenhuma ação recente da equipe.';
        }

        return $logs->map(function (CampaignLog $log) {
            $label = CampaignLog::$types[$log->type]['label'] ?? $log->type;
            $author = $log->user?->name ?? 'equipe';
            $date = $log->created_at->format('d/m');

            return "{$date} ({$author}) — {$label}: {$log->description}";
        })->implode("\n");
    }

    public static function forAdset(AdAdset $adset): array
    {
        $adset->loadMissing(['campaign.adAccount.client']);
        $campaign = $adset->campaign;
        $client = $campaign?->adAccount?->client;

        $context = [
            'campaign_name'   => $campaign?->name ?? '',
            'adset_name'      => $adset->name ?? '',
            'adset_status'    => $adset->status ?? '',
            'client_name'     => $client?->company_name ?? '',
        ];

        return array_merge($context, self::metricsForContext(
            $campaign?->client_ad_account_id,
            'adset',
            $adset->external_id
        ));
    }

    public static function forAd(AdAd $ad): array
    {
        $ad->loadMissing(['adset.campaign.adAccount.client']);
        $adset = $ad->adset;
        $campaign = $adset?->campaign;
        $client = $campaign?->adAccount?->client;

        $context = [
            'campaign_name'  => $campaign?->name ?? '',
            'adset_name'     => $adset?->name ?? '',
            'ad_name'        => $ad->name ?? '',
            'creative_type'  => $ad->creative_type ?? '',
            'client_name'    => $client?->company_name ?? '',
        ];

        return array_merge($context, self::metricsForContext(
            $campaign?->client_ad_account_id,
            'ad',
            $ad->external_id
        ));
    }

    private static function metricsForContext(?string $clientAdAccountId, string $entityLevel, string $entityId): array
    {
        $last7d = self::entityMetrics($clientAdAccountId, $entityLevel, $entityId, 7, 0);
        $prev7d = self::entityMetrics($clientAdAccountId, $entityLevel, $entityId, 14, 7);

        return [
            'spend_last_7_days'     => number_format($last7d['spend'], 2, ',', '.'),
            'cpa_last_7_days'       => $last7d['cpa'] !== null ? number_format($last7d['cpa'], 2, ',', '.') : '—',
            'ctr_last_7_days'       => $last7d['ctr'] !== null ? number_format($last7d['ctr'], 2, ',', '.') . '%' : '—',
            'roas_last_7_days'      => $last7d['roas'] !== null ? number_format($last7d['roas'], 2, ',', '.') . 'x' : '—',
            'spend_previous_7_days' => number_format($prev7d['spend'], 2, ',', '.'),
            'cpa_previous_7_days'   => $prev7d['cpa'] !== null ? number_format($prev7d['cpa'], 2, ',', '.') : '—',
        ];
    }

    /**
     * Agrega métricas de ad_daily_snapshots para uma campanha numa janela de dias
     * terminando `$daysAgoEnd` dias atrás (ex: janela 7..0 = últimos 7 dias).
     */
    public static function campaignMetrics(AdCampaign $campaign, int $daysAgoStart, int $daysAgoEnd): array
    {
        return self::entityMetrics($campaign->client_ad_account_id, 'campaign', $campaign->external_id, $daysAgoStart, $daysAgoEnd);
    }

    /**
     * Versão genérica de campaignMetrics, parametrizada por nível de entidade
     * (campaign|adset|ad) — ad_daily_snapshots já grava os 3 níveis com o
     * mesmo formato de colunas.
     */
    public static function entityMetrics(?string $clientAdAccountId, string $entityLevel, string $entityId, int $daysAgoStart, int $daysAgoEnd): array
    {
        $start = now()->subDays($daysAgoStart)->toDateString();
        $end = now()->subDays($daysAgoEnd)->toDateString();

        $agg = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->where('client_ad_account_id', $clientAdAccountId)
            ->where('entity_level', $entityLevel)
            ->where('entity_id', $entityId)
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
            'spend'       => $spend,
            'impressions' => $impressions,
            'cpa'         => $conversions > 0 ? $spend / $conversions : null,
            'ctr'         => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'roas'        => $spend > 0 ? round($revenue / $spend, 2) : null,
        ];
    }
}
