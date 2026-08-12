<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskApprovalToken extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'round_id', 'contact_id', 'token', 'status', 'channels', 'will_notify',
        'overall_comment', 'notified_at', 'reviewed_at', 'expires_at',
    ];

    protected $casts = [
        'channels'    => 'array',
        'will_notify' => 'boolean',
        'notified_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(TaskApprovalRound::class, 'round_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(DeliverableFeedback::class, 'token_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }
}
