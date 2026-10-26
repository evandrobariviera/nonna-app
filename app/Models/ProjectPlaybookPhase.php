<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Fase-modelo de um ProjectPlaybook — vira ProjectPhase ao aplicar o Playbook, com datas a
// partir do início do projeto (TaskDraftService::applyPlaybook). Sem Tenantable: isolamento
// vem do pai, igual a ProjectPlaybookTask.
class ProjectPlaybookPhase extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'project_playbook_id',
        'position',
        'name',
        'is_milestone',
        'start_offset_days',
        'duration_days',
    ];

    protected $casts = [
        'position'          => 'integer',
        'is_milestone'      => 'boolean',
        'start_offset_days' => 'integer',
        'duration_days'     => 'integer',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(ProjectPlaybook::class, 'project_playbook_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectPlaybookTask::class, 'project_playbook_phase_id');
    }
}
