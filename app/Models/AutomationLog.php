<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'automation_id',
        'entity_type', 'entity_id',
        'status',
        'input_snapshot', 'output', 'error_message',
        'duration_ms', 'ran_at',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'ran_at'         => 'datetime',
    ];

    public static array $statuses = [
        'running' => ['label' => 'Rodando',    'color' => 'orange'],
        'success' => ['label' => 'Concluído',  'color' => 'green'],
        'failed'  => ['label' => 'Erro',       'color' => 'red'],
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function entity(): ?Model
    {
        return match ($this->entity_type) {
            'task'     => Task::find($this->entity_id),
            'project'  => Project::find($this->entity_id),
            'campaign' => AdCampaign::find($this->entity_id),
            default    => null,
        };
    }
}
