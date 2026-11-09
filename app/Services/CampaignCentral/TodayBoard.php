<?php

namespace App\Services\CampaignCentral;

use App\Models\AdCampaign;
use App\Models\CampaignDailyCheck;
use App\Models\CampaignInsight;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A aba "Hoje" da Central de Campanhas: o que o gestor de tráfego precisa
 * resolver, otimizar e subir. Tudo calculado com consultas agregadas (sem
 * uma consulta por campanha) — a tela abre todo dia, para todo o time.
 */
class TodayBoard
{
    /** Quantos itens cada bloco mostra antes do "ver todos". */
    private const LIMIT = 8;

    /** Artes aprovadas nesta janela aparecem como "esperando subir". */
    private const ART_WINDOW_DAYS = 14;

    /** Campanha planejada que começa até daqui a N dias e ainda não existe. */
    private const PLANNED_WINDOW_DAYS = 15;

    /** Saldo pré-pago que acaba em até N dias vira "resolver agora". */
    private const BALANCE_ALERT_DAYS = 3;

    private Collection $accountIds;
    private Collection $clientIds;

    /**
     * @param string|null $clientId  cliente escolhido no filtro
     * @param int|null    $mineUserId só as contas em que este usuário é o responsável
     */
    public function __construct(private ?string $clientId = null, private ?int $mineUserId = null)
    {
        $accounts = ClientAdAccount::query()
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($mineUserId, fn ($q) => $q->where('responsible_user_id', $mineUserId))
            ->get(['id', 'client_id']);

        $this->accountIds = $accounts->pluck('id');
        $this->clientIds = $accounts->pluck('client_id')->unique()->values();

        // Cliente escolhido sem conta de anúncio ainda pode ter arte/planejamento.
        if ($clientId && $this->clientIds->isEmpty()) {
            $this->clientIds = collect([$clientId]);
        }
    }

    public function build(int $userId): array
    {
        $yesterday = now()->subDay()->toDateString();

        $stale = $this->staleAccounts();
        $stopped = $this->stoppedCampaigns($yesterday, $stale->pluck('id'));
        $problemAds = $this->problemAds();
        $balance = $this->balanceIssues();
        $overdue = $this->overdueOptimizations();

        return [
            'stale'       => $stale,
            'stopped'     => $stopped,
            'problemAds'  => $problemAds,
            'balance'     => $balance,
            'alerts'      => $this->alerts(),
            'overdue'     => $overdue,
            'arts'        => $this->approvedArts(),
            'planned'     => $this->plannedWithoutCampaign(),
            'billing'     => \App\Services\Billing\AccountStatement::pendingForToday($this->accountIds),
            'kpis'        => $this->kpis($yesterday) + [
                'urgent' => $stale->count() + $stopped->count() + $problemAds->count() + $balance->count(),
            ],
            'check'       => CampaignDailyCheck::forToday($userId),
        ];
    }

    /**
     * Conta que anuncia mas não recebeu dados na sincronização de hoje. Sem
     * isso, as campanhas dela pareceriam "paradas" quando o problema é acesso.
     */
    private function staleAccounts(): Collection
    {
        if ($this->accountIds->isEmpty()) {
            return collect();
        }

        $rows = DB::connection('pgsql')->table('ad_campaigns as c')
            ->join('client_ad_accounts as a', 'a.id', '=', 'c.client_ad_account_id')
            ->whereIn('c.client_ad_account_id', $this->accountIds)
            ->where('c.status', 'active')
            ->groupBy('c.client_ad_account_id', 'a.client_id', 'a.platform', 'a.account_id')
            ->havingRaw('MAX(c.last_synced_at) < ?', [today()->startOfDay()])
            ->selectRaw('c.client_ad_account_id AS id, a.client_id, a.platform, a.account_id, MAX(c.last_synced_at) AS last_synced_at')
            ->get();

        return $this->attachClients($rows);
    }

    /**
     * Ativa, gastou nos 7 dias anteriores e não gastou ontem. Campanha com
     * data de fim já passada terminou — não está parada (a Meta mantém o
     * status "ativa" depois do fim).
     */
    private function stoppedCampaigns(string $yesterday, Collection $staleAccountIds): Collection
    {
        if ($this->accountIds->isEmpty()) {
            return collect();
        }

        $accountIds = $this->accountIds->diff($staleAccountIds)->values();
        if ($accountIds->isEmpty()) {
            return collect();
        }

        $spendExpr = fn (string $from, string $to) => "EXISTS (SELECT 1 FROM ad_daily_snapshots s
            WHERE s.client_ad_account_id = ad_campaigns.client_ad_account_id AND s.entity_level = 'campaign'
              AND s.entity_id = ad_campaigns.external_id AND s.snapshot_date BETWEEN {$from} AND {$to} AND s.spend > 0)";

        $campaigns = AdCampaign::with('adAccount.client')
            ->whereIn('client_ad_account_id', $accountIds)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $yesterday))
            ->whereRaw($spendExpr("?::date - 7", "?::date - 1"), [$yesterday, $yesterday])
            ->whereRaw('NOT ' . $spendExpr('?::date', '?::date'), [$yesterday, $yesterday])
            ->orderBy('name')
            ->get();

        return $campaigns->map(function (AdCampaign $c) {
            $c->stop_reason = match ($c->raw_data['effective_status'] ?? null) {
                'WITH_ISSUES'          => 'a plataforma aponta um problema na campanha',
                'PENDING_BILLING_INFO' => 'falta forma de pagamento na conta',
                'IN_PROCESS'           => 'em análise na plataforma',
                default                => 'não gastou nada ontem',
            };

            return $c;
        });
    }

    /** Anúncio reprovado ou com problema, em campanha que está no ar. */
    private function problemAds(): Collection
    {
        if ($this->accountIds->isEmpty()) {
            return collect();
        }

        $rows = DB::connection('pgsql')->table('ad_ads as ad')
            ->join('ad_adsets as st', 'st.id', '=', 'ad.ad_adset_id')
            ->join('ad_campaigns as c', 'c.id', '=', 'st.ad_campaign_id')
            ->join('client_ad_accounts as a', 'a.id', '=', 'c.client_ad_account_id')
            ->whereIn('c.client_ad_account_id', $this->accountIds)
            ->where('c.status', 'active')
            ->whereRaw("ad.raw_data->>'effective_status' IN ('DISAPPROVED', 'WITH_ISSUES')")
            ->orderBy('c.name')
            ->limit(30)
            ->get([
                'ad.id', 'ad.name', DB::raw("ad.raw_data->>'effective_status' AS effective_status"),
                'c.id AS campaign_id', 'c.name AS campaign_name', 'a.client_id',
            ]);

        return $this->attachClients($rows);
    }

    /**
     * Saldo pré-pago acabando, ou conta marcada em Orçamentos como precisando
     * de recarga/pagamento. Média de gasto em uma consulta só para todas.
     */
    private function balanceIssues(): Collection
    {
        $accounts = ClientAdAccount::with('client')
            ->whereIn('id', $this->accountIds)
            ->whereIn('platform', ClientAdAccount::billingPlatforms())
            ->where('budget_status', '!=', 'standby')
            ->get();

        if ($accounts->isEmpty()) {
            return collect();
        }

        $avgSpend = DB::connection('pgsql')->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $accounts->pluck('id'))
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', now()->subDays(7)->toDateString())
            ->groupBy('client_ad_account_id')
            ->selectRaw('client_ad_account_id, SUM(spend) / 7.0 AS daily')
            ->pluck('daily', 'client_ad_account_id');

        return $accounts->map(function (ClientAdAccount $a) use ($avgSpend) {
            $daily = (float) ($avgSpend[$a->id] ?? 0);
            $a->daily_spend = $daily;
            $a->days_left = $a->balance !== null && $daily > 0 ? (int) floor((float) $a->balance / $daily) : null;

            return $a;
        })->filter(fn (ClientAdAccount $a) => in_array($a->budget_status, ['aguardando_pagto', 'adicao_necessaria'], true)
            || ($a->days_left !== null && $a->days_left <= self::BALANCE_ALERT_DAYS))
          ->sortBy(fn (ClientAdAccount $a) => $a->days_left ?? 999)
          ->values();
    }

    /** Alertas abertos das análises automáticas (os mesmos de Insights). */
    private function alerts(): array
    {
        $query = CampaignInsight::with(['client', 'campaign'])
            ->whereIn('status', ['novo', 'lido'])
            ->whereIn('client_id', $this->clientIds);

        return [
            'items' => (clone $query)
                ->orderByRaw("CASE severity WHEN 'critico' THEN 0 WHEN 'atencao' THEN 1 ELSE 2 END")
                ->orderByDesc('generated_at')
                ->limit(self::LIMIT)
                ->get(),
            'total' => $query->count(),
        ];
    }

    /**
     * Campanha que está gastando e passou do prazo de otimização do seu ritmo
     * (normal 5 dias, alta 3, diária 1). Quem nunca foi otimizada vem antes.
     */
    private function overdueOptimizations(): array
    {
        if ($this->accountIds->isEmpty()) {
            return ['items' => collect(), 'total' => 0, 'active' => 0];
        }

        $active = AdCampaign::with('adAccount.client')
            ->whereIn('client_ad_account_id', $this->accountIds)
            ->where('status', 'active')
            ->whereRaw("EXISTS (SELECT 1 FROM ad_daily_snapshots s
                WHERE s.client_ad_account_id = ad_campaigns.client_ad_account_id AND s.entity_level = 'campaign'
                  AND s.entity_id = ad_campaigns.external_id AND s.snapshot_date >= ? AND s.spend > 0)", [now()->subDays(7)->toDateString()])
            ->orderByRaw('last_optimized_at ASC NULLS FIRST')
            ->get();

        $overdue = $active->filter(fn (AdCampaign $c) => $c->isOptimizationOverdue())->values();

        return [
            'items'  => $overdue->take(self::LIMIT),
            'total'  => $overdue->count(),
            'active' => $active->count(),
        ];
    }

    /**
     * Arte de campanha patrocinada aprovada pelo cliente nos últimos dias. Por
     * enquanto o App não sabe se ela já virou anúncio (isso chega quando o
     * anúncio puder ser criado daqui), então é uma lista de conferência.
     */
    private function approvedArts(): array
    {
        $since = now()->subDays(self::ART_WINDOW_DAYS);

        $query = Task::with(['client', 'latestApprovalRound'])
            ->where('destination', 'campanhas_patrocinadas')
            ->whereIn('client_id', $this->clientIds)
            ->whereHas('latestApprovalRound', fn ($q) => $q->where('status', 'approved')
                ->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'aviso'))
                ->whereRaw('COALESCE(resolved_at, updated_at) >= ?', [$since]));

        return [
            'items' => (clone $query)->orderByDesc('updated_at')->limit(self::LIMIT)->get(),
            'total' => $query->count(),
        ];
    }

    /** Campanha do planejamento que vai começar e ainda não existe na plataforma. */
    private function plannedWithoutCampaign(): Collection
    {
        return Project::with(['client', 'macroPlan.client'])
            ->where('type', 'campanha')
            ->whereIn('status', ['em_planejamento', 'aprovacao', 'em_execucao'])
            ->whereNotNull('start_date')
            ->where('start_date', '<=', now()->addDays(self::PLANNED_WINDOW_DAYS)->toDateString())
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ad_campaigns')->whereColumn('ad_campaigns.project_id', 'projects.id'))
            ->where(fn ($q) => $q->whereIn('client_id', $this->clientIds)
                ->orWhereHas('macroPlan', fn ($m) => $m->whereIn('client_id', $this->clientIds)))
            ->orderBy('start_date')
            ->limit(self::LIMIT)
            ->get();
    }

    private function kpis(string $yesterday): array
    {
        $spend = $this->accountIds->isEmpty() ? 0 : (float) DB::connection('pgsql')->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $this->accountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', $yesterday)
            ->sum('spend');

        $leads = DB::connection('pgsql')->table('client_lead_opportunities as o')
            ->join('client_leads as l', 'l.id', '=', 'o.client_lead_id')
            ->whereIn('l.client_id', $this->clientIds)
            ->whereRaw('COALESCE(o.received_at, o.created_at)::date = ?', [$yesterday])
            ->selectRaw("COUNT(*) AS total, COUNT(*) FILTER (WHERE COALESCE(o.quality, o.ai_quality) = 'bom') AS good")
            ->first();

        return [
            'spend_yesterday' => $spend,
            'leads_yesterday' => (int) ($leads->total ?? 0),
            'good_yesterday'  => (int) ($leads->good ?? 0),
        ];
    }

    /** Pendura o Client nas linhas cruas (uma consulta só). */
    private function attachClients(Collection $rows): Collection
    {
        $clients = Client::whereIn('id', $rows->pluck('client_id')->unique())->get()->keyBy('id');

        return $rows->map(function ($row) use ($clients) {
            $row->client = $clients->get($row->client_id);

            return $row;
        });
    }
}
