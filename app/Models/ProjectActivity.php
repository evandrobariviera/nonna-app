<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

// Histórico de ações do Projeto — mesmo formato de TaskActivity (só rótulos
// de/para, nunca conteúdo de texto). Gravado pelo ProjectObserver.
class ProjectActivity extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'action', 'from_label', 'to_label', 'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static array $actions = [
        'created'                 => 'Projeto criado',
        'title_changed'           => 'Nome alterado',
        'status_changed'          => 'Status alterado',
        'type_changed'            => 'Tipo alterado',
        'brief_status_changed'    => 'Nível do brief alterado',
        'macro_plan_changed'      => 'Planejamento alterado',
        'client_changed'          => 'Cliente alterado',
        'start_date_changed'      => 'Data de início alterada',
        'pieces_due_date_changed' => 'Entrega das peças alterada',
        'end_date_changed'        => 'Data de término alterada',
        'budget_changed'          => 'Verba alterada',
        'disciplines_changed'     => 'Disciplinas alteradas',
        'briefing_updated'        => 'Briefing editado',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::$actions[$this->action] ?? $this->action;
    }

    public static function log(Project $project, string $action, ?string $fromLabel, ?string $toLabel, ?int $userId = null): void
    {
        self::create([
            'project_id' => $project->id,
            'action'     => $action,
            'from_label' => $fromLabel,
            'to_label'   => $toLabel,
            'user_id'    => $userId ?? Auth::id(),
            'created_at' => now(),
        ]);
    }
}
