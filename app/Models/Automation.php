<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'name', 'description',
        'entity_type',
        'trigger_type', 'trigger_config',
        'action_type', 'action_config',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'action_config'  => 'array',
        'is_active'      => 'boolean',
    ];

    // ── Definições de tipos ────────────────────────────────────────────────

    public static array $entityTypes = [
        'task'     => 'Tarefa',
        'project'  => 'Projeto',
        'campaign' => 'Campanha',
    ];

    public static array $triggerTypes = [
        'status_changed' => 'Status mudou',
        'field_updated'  => 'Campo atualizado',
        'created'        => 'Criado',
        'manual'         => 'Manual (botão)',
    ];

    public static array $actionTypes = [
        'run_ai_agent'      => 'Rodar Agente de IA',
        'send_webhook'      => 'Enviar Webhook',
        'update_field'      => 'Atualizar Campo',
        'send_notification' => 'Enviar Notificação',
    ];

    // Campos que cada entity_type expõe para triggers
    public static array $entityFields = [
        'task' => [
            'status'    => ['label' => 'Status',    'values' => 'Task::$statuses'],
            'situation' => ['label' => 'Situação',   'values' => 'Task::$situations'],
            'task_type' => ['label' => 'Tipo',       'values' => 'Task::$types'],
        ],
        'project' => [
            'status' => ['label' => 'Status', 'values' => []],
        ],
        'campaign' => [
            'status' => ['label' => 'Status', 'values' => []],
        ],
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class)->orderByDesc('ran_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function entityTypeLabel(): string
    {
        return self::$entityTypes[$this->entity_type] ?? $this->entity_type;
    }

    public function triggerTypeLabel(): string
    {
        return self::$triggerTypes[$this->trigger_type] ?? $this->trigger_type;
    }

    public function actionTypeLabel(): string
    {
        return self::$actionTypes[$this->action_type] ?? $this->action_type;
    }

    public function triggerSummary(): string
    {
        $config = $this->trigger_config ?? [];

        return match ($this->trigger_type) {
            'status_changed' => sprintf(
                'Status "%s" → "%s"',
                $config['from'] ?? '*',
                $config['to'] ?? '*'
            ),
            'field_updated'  => 'Campo "' . ($config['field'] ?? '?') . '" atualizado',
            'created'        => 'Ao ser criado',
            'manual'         => 'Acionado manualmente',
            default          => $this->trigger_type,
        };
    }

    public function actionSummary(): string
    {
        $config = $this->action_config ?? [];

        return match ($this->action_type) {
            'run_ai_agent'      => 'Agente: ' . ($config['agent_name'] ?? $config['agent_id'] ?? '?'),
            'send_webhook'      => 'POST → ' . ($config['url'] ?? '?'),
            'update_field'      => 'Campo "' . ($config['field'] ?? '?') . '" = "' . ($config['value'] ?? '?') . '"',
            'send_notification' => 'Notificar ' . ($config['to'] ?? '?'),
            default             => $this->action_type,
        };
    }

    // Verifica se esta automação deve disparar dado um gatilho e dados de mudança.
    // $entity é opcional — só é usado hoje pelo filtro extra de `destination`
    // (status_changed), que precisa olhar o estado atual da entidade, não só
    // o from/to do próprio changeData.
    public function matches(string $triggerType, array $changeData, ?Model $entity = null): bool
    {
        if ($this->trigger_type !== $triggerType) {
            return false;
        }

        $config = $this->trigger_config ?? [];

        if ($triggerType === 'status_changed') {
            $fromMatch = empty($config['from']) || $config['from'] === '*' || $config['from'] === ($changeData['from'] ?? null);
            $toMatch   = empty($config['to'])   || $config['to']   === '*' || $config['to']   === ($changeData['to'] ?? null);

            $destinationMatch = true;
            if (!empty($config['destination']) && $config['destination'] !== '*') {
                $destinationMatch = $entity && $config['destination'] === ($entity->destination ?? null);
            }

            return $fromMatch && $toMatch && $destinationMatch;
        }

        if ($triggerType === 'field_updated') {
            return ($config['field'] ?? null) === ($changeData['field'] ?? null);
        }

        return true;
    }
}
