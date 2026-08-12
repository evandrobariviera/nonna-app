<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class TaskActivity extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'action', 'from_label', 'to_label', 'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Rótulo de cada ação — usado tanto na exibição (ícone+texto do histórico)
    // quanto pra escolher o ícone (ver task-activity-icon() na view).
    public static array $actions = [
        'created'             => 'Tarefa criada',
        'status_changed'      => 'Status alterado',
        'situation_changed'   => 'Situação alterada',
        'priority_changed'    => 'Prioridade alterada',
        'destination_changed' => 'Destino alterado',
        'task_type_changed'   => 'Tipo alterado',
        'client_changed'      => 'Cliente alterado',
        'project_changed'     => 'Projeto alterado',
        'sprint_changed'      => 'Sprint alterada',
        'responsavel_changed' => 'Responsável alterado',
        'executor_changed'    => 'Executor alterado',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::$actions[$this->action] ?? $this->action;
    }

    // Helper central — toda gravação de histórico passa por aqui (observer,
    // TaskController, bulk update) pra manter o formato consistente.
    public static function log(Task $task, string $action, ?string $fromLabel, ?string $toLabel, ?int $userId = null): void
    {
        self::create([
            'task_id'    => $task->id,
            'action'     => $action,
            'from_label' => $fromLabel,
            'to_label'   => $toLabel,
            'user_id'    => $userId ?? Auth::id(),
            'created_at' => now(),
        ]);
    }
}
