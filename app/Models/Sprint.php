<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'title', 'starts_at', 'ends_at', 'status',
        'locked_at', 'locked_by', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
        'locked_at' => 'datetime',
    ];

    public static array $statuses = [
        'planning' => ['label' => 'Planejamento', 'color' => 'muted'],
        'active'   => ['label' => 'Ativa',        'color' => 'orange'],
        'closed'   => ['label' => 'Encerrada',    'color' => 'green'],
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function isLocked(): bool
    {
        return !is_null($this->locked_at);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function activeSprint(): ?self
    {
        return self::where('status', 'active')->latest('starts_at')->first();
    }
}
