<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalNotification extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'user_id',
        'kind',
        'title',
        'body',
        'link',
        'source_type',
        'source_id',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public static array $statuses = [
        'novo'       => ['label' => 'Novo',       'color' => 'purple'],
        'lido'       => ['label' => 'Lido',       'color' => 'blue'],
        'resolvido'  => ['label' => 'Resolvido',  'color' => 'green'],
        'descartado' => ['label' => 'Descartado', 'color' => 'muted'],
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
