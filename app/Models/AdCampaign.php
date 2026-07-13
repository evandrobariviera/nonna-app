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
        'platform', 'external_id', 'name', 'status',
        'objective', 'start_date', 'end_date',
        'raw_data', 'last_synced_at',
        'management_status', 'management_situation', 'optimization_tier', 'last_optimized_at',
    ];

    protected $casts = [
        'raw_data'         => 'array',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'last_synced_at'   => 'datetime',
        'last_optimized_at' => 'datetime',
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

    public function adsets(): HasMany
    {
        return $this->hasMany(AdAdset::class);
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
}
