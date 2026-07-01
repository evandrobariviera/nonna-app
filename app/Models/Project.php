<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'macro_plan_id',
        'client_id',
        'position',
        'title',
        'objective',
        'disciplines',
        'briefing_criacao',
        'briefing_web',
        'briefing_trafego',
        'briefing_setup',
        'briefing_social',
        'briefing_seo',
        'briefing_email',
        'status',
        'type',
        'tags',
        'content_ideas',
        'traffic_phases',
        'start_date',
        'end_date',
        'budget',
        'clickup_task_id',
        'launched_at',
    ];

    public static array $types = [
        'projeto'  => ['label' => 'Projeto',  'color' => 'purple'],
        'campanha' => ['label' => 'Campanha', 'color' => 'green'],
    ];

    public function typeLabel(): string
    {
        return self::$types[$this->type]['label'] ?? $this->type;
    }

    public function typeColor(): string
    {
        return self::$types[$this->type]['color'] ?? 'muted';
    }

    protected $casts = [
        'disciplines'   => 'array',
        'tags'          => 'array',
        'content_ideas' => 'array',
        'traffic_phases'=> 'array',
        'start_date'    => 'date',
        'end_date'      => 'date',
        'budget'        => 'decimal:2',
        'launched_at'   => 'datetime',
        'position'      => 'integer',
    ];

    public static array $disciplines = [
        'criacao'  => 'Criação / Audiovisual',
        'web'      => 'Web / Dev',
        'trafego'  => 'Tráfego / Performance',
        'setup'    => 'Setup / Tracking',
        'social'   => 'Social Media',
        'seo'      => 'SEO',
        'email'    => 'E-mail Marketing',
    ];

    public static array $statuses = [
        'draft'     => ['label' => 'Rascunho',  'color' => 'muted'],
        'active'    => ['label' => 'Ativo',     'color' => 'blue'],
        'continua'  => ['label' => 'Contínua',  'color' => 'blue'],
        'completed' => ['label' => 'Concluído', 'color' => 'green'],
        'cancelled' => ['label' => 'Cancelado', 'color' => 'red'],
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function disciplineLabels(): array
    {
        return array_map(
            fn($key) => self::$disciplines[$key] ?? $key,
            $this->disciplines ?? []
        );
    }

    public function hasBriefingFor(string $discipline): bool
    {
        $field = 'briefing_' . $discipline;
        return !empty($this->$field);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('created_at');
    }

    public function activeTasks(): HasMany
    {
        return $this->hasMany(Task::class)->where('status', '!=', 'cancelado');
    }

    public function progressPercent(): int
    {
        if ($this->relationLoaded('tasks')) {
            $total = $this->tasks->where('status', '!=', 'cancelado')->count();
            if ($total === 0) return 0;
            $done = $this->tasks->where('status', 'concluido')->count();
        } else {
            $total = $this->tasks()->where('status', '!=', 'cancelado')->count();
            if ($total === 0) return 0;
            $done = $this->tasks()->where('status', 'concluido')->count();
        }
        return (int) round(($done / $total) * 100);
    }

    public function taskCountByColumn(): array
    {
        $counts = [];
        $tasks = $this->tasks()->where('status', '!=', 'cancelado')->get(['status']);
        foreach (Task::$kanbanColumns as $colKey => $col) {
            $counts[$colKey] = $tasks->filter(
                fn($t) => in_array($t->status, $col['statuses'])
            )->count();
        }
        return $counts;
    }

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
