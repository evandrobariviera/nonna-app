<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdAdset extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'ad_campaign_id',
        'platform', 'external_id', 'campaign_external_id',
        'name', 'status', 'daily_budget', 'lifetime_budget',
        'targeting', 'raw_data', 'last_synced_at', 'optimization_locked_reason',
    ];

    protected $casts = [
        'targeting'      => 'array',
        'raw_data'       => 'array',
        'daily_budget'   => 'decimal:2',
        'lifetime_budget'=> 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(AdAd::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AdDailySnapshot::class, 'entity_id', 'external_id')
            ->where('entity_level', 'adset');
    }
}
