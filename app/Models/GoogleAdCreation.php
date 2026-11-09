<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anúncio de Pesquisa criado no Google pelo App (sempre nasce pausado).
 */
class GoogleAdCreation extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'ad_campaign_id', 'client_ad_account_id', 'ad_group_id', 'ad_group_name',
        'ad_id', 'ad_resource', 'status', 'headlines', 'descriptions', 'path1', 'path2', 'final_url',
        'ai_suggested', 'error', 'created_by', 'activated_by', 'activated_at',
    ];

    protected $casts = [
        'headlines'    => 'array',
        'descriptions' => 'array',
        'ai_suggested' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return MetaAdCreation::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return MetaAdCreation::$statuses[$this->status]['color'] ?? 'muted';
    }
}
