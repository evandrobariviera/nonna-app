<?php

namespace App\Models;

use App\Traits\TenantViaParent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Retorno de entrega (ida pra Revisão Interna) — ver TaskDeliveryService.
class TaskDelivery extends Model
{
    use HasUuids, TenantViaParent;

    // Filtro de organização herdado do pai (ver TenantViaParent).
    public function tenantParentRelation(): string
    {
        return 'task';
    }

    protected $connection = 'pgsql';

    protected $fillable = ['task_id', 'user_id', 'fully_done', 'missing', 'body', 'from_status'];

    protected $casts = [
        'fully_done' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
