<?php

namespace App\Models;

use App\Traits\TenantViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecutor extends Model
{
    use TenantViaParent;

    // Filtro de organização herdado do pai (ver TenantViaParent).
    public function tenantParentRelation(): string
    {
        return 'task';
    }

    protected $connection = 'pgsql';

    protected $table = 'task_executors';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'task_id',
        'user_id',
        'role',
    ];

    public static array $roles = [
        'executor'    => 'Executor',
        'responsavel' => 'Responsável',
        'observador'  => 'Observador',
        'aprovador'   => 'Aprovador',
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
