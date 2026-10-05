<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

// Histórico de ações do Planejamento — mesmo formato de TaskActivity (só
// rótulos de/para, nunca conteúdo dos blocos). Gravado pelo MacroPlanObserver
// e, pra entrada/saída de projetos, pelo ProjectObserver.
class MacroPlanActivity extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'macro_plan_activities';

    public $timestamps = false;

    protected $fillable = [
        'macro_plan_id', 'action', 'from_label', 'to_label', 'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static array $actions = [
        'created'             => 'Planejamento criado',
        'title_changed'       => 'Nome alterado',
        'version_changed'     => 'Versão alterada',
        'status_changed'      => 'Status alterado',
        'responsible_changed' => 'Responsável alterado',
        'client_changed'      => 'Cliente alterado',
        'period_changed'      => 'Período alterado',
        'disciplines_changed' => 'Disciplinas alteradas',
        'block_updated'       => 'Bloco editado',
        'project_added'       => 'Projeto adicionado',
        'project_removed'     => 'Projeto removido',
    ];

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::$actions[$this->action] ?? $this->action;
    }

    public static function log(MacroPlan|string $macroPlan, string $action, ?string $fromLabel, ?string $toLabel, ?int $userId = null): void
    {
        self::create([
            'macro_plan_id' => $macroPlan instanceof MacroPlan ? $macroPlan->id : $macroPlan,
            'action'        => $action,
            'from_label'    => $fromLabel,
            'to_label'      => $toLabel,
            'user_id'       => $userId ?? Auth::id(),
            'created_at'    => now(),
        ]);
    }
}
