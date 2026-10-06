<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Ponto padrão de um tipo de tarefa — usado quando nenhum formato do catálogo (TaskFormat)
// bate com a tarefa. Ver App\Services\Tasks\SprintPoints.
class TaskTypePoint extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'task_type', 'points'];

    protected $casts = ['points' => 'integer'];
}
