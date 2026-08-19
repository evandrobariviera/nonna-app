<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientLeadSource extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'lead_channel_id',
        'external_id',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(LeadChannel::class, 'lead_channel_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(ClientLeadOpportunity::class);
    }

    public function displayName(): string
    {
        return $this->label ?: $this->external_id;
    }
}
