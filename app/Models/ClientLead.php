<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientLead extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'first_seen_channel_id',
        'name',
        'email',
        'phone',
        'city',
        'state',
        'first_contacted_at',
        'last_contacted_at',
    ];

    protected $casts = [
        'first_contacted_at' => 'datetime',
        'last_contacted_at'  => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function firstSeenChannel(): BelongsTo
    {
        return $this->belongsTo(LeadChannel::class, 'first_seen_channel_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(ClientLeadOpportunity::class)->orderByDesc('created_at');
    }

    public function openOpportunity(): ?ClientLeadOpportunity
    {
        return $this->opportunities()->whereNotIn('stage', ['ganho', 'perdido'])->first();
    }
}
