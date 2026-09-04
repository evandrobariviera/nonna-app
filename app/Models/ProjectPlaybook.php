<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Modelo reutilizável de estrutura de projeto: um conjunto fixo de tarefas
// (ex: "Site Institucional" → briefing, wireframe, dev, QA...) que pode ser
// aplicado de uma vez a um Projeto via TaskDraftService::applyPlaybook(),
// sem depender de IA — determinístico por design (ver CLAUDE.md/plano do
// Assistente de Lançamento de Tarefas).
class ProjectPlaybook extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'disciplines',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'disciplines' => 'array',
        'is_active'   => 'boolean',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectPlaybookTask::class)->orderBy('position');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Projetos que nasceram deste Playbook — rastreabilidade via
    // projects.project_playbook_id (não obrigatório, só histórico).
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function disciplineLabels(): array
    {
        return array_map(
            fn ($key) => Project::$disciplines[$key] ?? $key,
            $this->disciplines ?? []
        );
    }
}
