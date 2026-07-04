<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignInsight extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'client_id',
        'client_ad_account_id',
        'ad_campaign_id',
        'kind',
        'severity',
        'status',
        'title',
        'summary',
        'metrics_snapshot',
        'agent_id',
        'generated_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'metrics_snapshot' => 'array',
        'generated_at'     => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    public static array $kinds = [
        'budget_overrun' => 'Orçamento estourado',
        'budget_idle'    => 'Verba parada',
        'cpa_spike'      => 'CPA em alta',
        'ctr_drop'       => 'CTR em queda',
        'roas_drop'      => 'ROAS em queda',
    ];

    public static array $severities = [
        'info'     => ['label' => 'Info',     'color' => 'blue'],
        'atencao'  => ['label' => 'Atenção',  'color' => 'orange'],
        'critico'  => ['label' => 'Crítico',  'color' => 'red'],
    ];

    public static array $statuses = [
        'novo'       => ['label' => 'Novo',       'color' => 'purple'],
        'lido'       => ['label' => 'Lido',       'color' => 'blue'],
        'resolvido'  => ['label' => 'Resolvido',  'color' => 'green'],
        'descartado' => ['label' => 'Descartado', 'color' => 'muted'],
    ];

    public function kindLabel(): string
    {
        return self::$kinds[$this->kind] ?? $this->kind;
    }

    public function severityLabel(): string
    {
        return self::$severities[$this->severity]['label'] ?? $this->severity;
    }

    public function severityColor(): string
    {
        return self::$severities[$this->severity]['color'] ?? 'muted';
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
