<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Formato de entrega (Post estático, Reels, Landing page...) com seus pontos de sprint e as
// palavras-chave que detectam o formato pelo título da tarefa. Ver App\Services\Tasks\SprintPoints.
class TaskFormat extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'task_type', 'name', 'points', 'keywords', 'position'];

    protected $casts = [
        'points'   => 'integer',
        'position' => 'integer',
    ];

    /** Palavras-chave normalizadas (minúsculas, sem acento), na ordem cadastrada. */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map(
            fn ($k) => \Illuminate\Support\Str::ascii(mb_strtolower(trim($k))),
            explode(',', (string) $this->keywords)
        )));
    }
}
