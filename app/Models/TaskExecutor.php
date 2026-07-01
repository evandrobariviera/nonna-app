<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecutor extends Model
{
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
