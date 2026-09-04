<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Um item de tarefa dentro de um ProjectPlaybook. Sem Tenantable — não tem
// organization_id próprio, o isolamento multi-tenant vem do pai (FK cascade
// pra project_playbooks, que é Tenantable).
class ProjectPlaybookTask extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'project_playbook_id',
        'position',
        'title',
        'description',
        'task_type',
        'destination',
        'priority',
        'due_offset_days',
        'functional_role_id',
    ];

    protected $casts = [
        'position'        => 'integer',
        'due_offset_days' => 'integer',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(ProjectPlaybook::class, 'project_playbook_id');
    }

    public function functionalRole(): BelongsTo
    {
        return $this->belongsTo(FunctionalRole::class);
    }
}
