<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_ad_account_id',
        'platform', 'external_id', 'name', 'description', 'status',
        'objective', 'start_date', 'end_date',
        'raw_data', 'last_synced_at',
        'management_status', 'management_situation', 'optimization_tier', 'last_optimized_at',
        'last_insight_checked_at', 'target_cost_per_result', 'target_roas', 'optimization_locked_reason',
    ];

    protected $casts = [
        'raw_data'         => 'array',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'last_synced_at'   => 'datetime',
        'last_optimized_at' => 'datetime',
        'last_insight_checked_at' => 'datetime',
        'target_cost_per_result' => 'decimal:2',
        'target_roas'            => 'decimal:2',
    ];

    // ── Status de gestão interna (não confundir com `status`, que é o espelho
    // cru active/paused/deleted/archived vindo direto da API do Meta/Google) ──
    public static array $managementStatuses = [
        'em_veiculacao'    => ['label' => 'Em Veiculação',       'color' => 'blue'],
        'em_otimizacao'    => ['label' => 'Em Otimização',       'color' => 'purple'],
        'pendente_cliente' => ['label' => 'Pendente do Cliente', 'color' => 'orange'],
        'pausada'          => ['label' => 'Pausada',             'color' => 'muted'],
        'encerrada'        => ['label' => 'Encerrada',           'color' => 'green'],
    ];

    public static array $managementSituations = [
        ''                              => '—',
        'otimizacao_em_dia'             => 'Otimização em dia',
        'otimizacao_atrasada'           => 'Otimização atrasada',
        'aguardando_orcamento_cliente'  => 'Aguardando orçamento do cliente',
        'aguardando_aprovacao_criativo' => 'Aguardando aprovação de criativo',
        'aguardando_ativacao'          => 'Aguardando ativação',
        'pausada_baixo_desempenho'     => 'Pausada por baixo desempenho',
        'pausada_pedido_cliente'       => 'Pausada a pedido do cliente',
        'encerrada_no_prazo'           => 'Encerrada no prazo',
    ];

    public static array $managementSituationColors = [
        'otimizacao_em_dia'             => 'green',
        'otimizacao_atrasada'           => 'red',
        'aguardando_orcamento_cliente'  => 'orange',
        'aguardando_aprovacao_criativo' => 'orange',
        'aguardando_ativacao'          => 'blue',
        'pausada_baixo_desempenho'     => 'red',
        'pausada_pedido_cliente'       => 'muted',
        'encerrada_no_prazo'           => 'green',
    ];

    // "days" é o intervalo padrão de cada tier — hoje só usado pra calcular a
    // próxima data de otimização; no futuro a IA pode promover/rebaixar o tier
    // de uma campanha sozinha (ex: detectou queda de performance -> "alta").
    public static array $optimizationTiers = [
        'normal' => ['label' => 'Normal',  'days' => 5, 'color' => 'blue'],
        'alta'   => ['label' => 'Alta',    'days' => 3, 'color' => 'orange'],
        'diaria' => ['label' => 'Diária',  'days' => 1, 'color' => 'red'],
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    // Deep link pro gerenciador da própria plataforma, já aberto na campanha
    // específica. Só implementado pro Meta por enquanto — o formato de deep
    // link do Google Ads varia conforme a conta ser MCC ou não (precisa de um
    // login-customer-id que não temos guardado), então não dá pra garantir
    // que abre certo sem confirmar com quem usa todo dia.
    public function managerUrl(): ?string
    {
        $accountId = $this->adAccount?->account_id;
        if (!$accountId) {
            return null;
        }

        if (str_starts_with($this->platform, 'meta')) {
            return "https://adsmanager.facebook.com/adsmanager/manage/campaigns?act={$accountId}&selected_campaign_ids={$this->external_id}";
        }

        return null;
    }

    public function adsets(): HasMany
    {
        return $this->hasMany(AdAdset::class);
    }

    /**
     * Benchmark em cascata: override da campanha, senão o padrão da conta
     * (client_ad_accounts). Sem valor em nenhum nível = sem benchmark
     * configurado (não existe padrão global sensato de custo por resultado
     * — varia demais por objetivo/nicho).
     */
    public function resolveTargetCostPerResult(): ?float
    {
        $value = $this->target_cost_per_result ?? $this->adAccount?->target_cost_per_result;

        return $value !== null ? (float) $value : null;
    }

    public function resolveTargetRoas(): ?float
    {
        $value = $this->target_roas ?? $this->adAccount?->target_roas;

        return $value !== null ? (float) $value : null;
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AdDailySnapshot::class, 'entity_id', 'external_id')
            ->where('entity_level', 'campaign');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class, 'entity_id', 'external_id')
            ->where('entity_level', 'campaign');
    }

    /**
     * Agrupamento da listagem de campanhas — mesmo padrão de Task::groupCollection().
     *
     * @param  \Illuminate\Support\Collection<int, AdCampaign>  $campaigns
     */
    public static function groupCollection(\Illuminate\Support\Collection $campaigns, string $groupBy): \Illuminate\Support\Collection
    {
        return match ($groupBy) {
            'situacao' => $campaigns->groupBy(fn ($c) => $c->management_situation ?: '__sem_situacao__'),
            default    => $campaigns->groupBy(fn ($c) => $c->adAccount?->client_id ?? '__sem_cliente__'),
        };
    }

    public function managementStatusLabel(): string
    {
        return self::$managementStatuses[$this->management_status]['label'] ?? $this->management_status;
    }

    public function managementStatusColor(): string
    {
        return self::$managementStatuses[$this->management_status]['color'] ?? 'muted';
    }

    public function managementSituationLabel(): string
    {
        return self::$managementSituations[$this->management_situation ?? ''] ?? ($this->management_situation ?? '—');
    }

    public function managementSituationColor(): string
    {
        return self::$managementSituationColors[$this->management_situation ?? ''] ?? 'muted';
    }

    public function optimizationTierLabel(): string
    {
        return self::$optimizationTiers[$this->optimization_tier]['label'] ?? $this->optimization_tier;
    }

    public function optimizationTierColor(): string
    {
        return self::$optimizationTiers[$this->optimization_tier]['color'] ?? 'blue';
    }

    private function optimizationTierDays(): int
    {
        return self::$optimizationTiers[$this->optimization_tier]['days'] ?? 5;
    }

    public function nextOptimizationDueAt(): ?\Illuminate\Support\Carbon
    {
        return $this->last_optimized_at?->copy()->addDays($this->optimizationTierDays());
    }

    public function isOptimizationOverdue(): bool
    {
        if (!$this->last_optimized_at) {
            return true;
        }

        return $this->nextOptimizationDueAt()->isPast();
    }

    /**
     * Verdadeiro só no ciclo em que a campanha entrou em atraso de otimização
     * e ainda não recebeu a checagem de insight desse ciclo — usado pra gerar
     * o diagnóstico de IA uma única vez por atraso, não todo dia enquanto a
     * campanha permanece parada esperando otimização.
     */
    public function isDueForInsightCheck(): bool
    {
        if (!$this->isOptimizationOverdue()) {
            return false;
        }

        $cycleStart = $this->last_optimized_at ?? $this->created_at;

        if ($cycleStart && $this->last_insight_checked_at?->gte($cycleStart)) {
            return false;
        }

        return true;
    }
}
