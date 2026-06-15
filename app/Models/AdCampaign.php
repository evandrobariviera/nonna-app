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
    ];

    protected $casts = [
        'raw_data'       => 'array',
        'start_date'     => 'date',
        'end_date'       => 'date',
        'last_synced_at' => 'datetime',
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
}
