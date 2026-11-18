<?php

namespace App\Models;

use App\Traits\TenantViaParent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusTransition extends Model
{
    use HasUuids, TenantViaParent;

    // Filtro de organização herdado do pai (ver TenantViaParent).
    public function tenantParentRelation(): string
    {
        return 'task';
    }

    protected $connection = 'pgsql';

    protected $fillable = [
        'task_id', 'from_status', 'to_status', 'changed_by', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
