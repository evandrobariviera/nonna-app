<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdAd extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'ad_adset_id',
        'platform', 'external_id', 'adset_external_id',
        'name', 'status', 'creative_type', 'creative_url',
        'raw_data', 'last_synced_at',
    ];

    protected $casts = [
        'raw_data'       => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function adset(): BelongsTo
    {
        return $this->belongsTo(AdAdset::class, 'ad_adset_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AdDailySnapshot::class, 'entity_id', 'external_id')
            ->where('entity_level', 'ad');
    }
}
