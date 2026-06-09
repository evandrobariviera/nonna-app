<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'project_id', 'macro_plan_id', 'client_id',
        'title', 'description', 'task_type', 'status', 'situation',
        'executor_id', 'created_by',
        'due_date', 'approval_date', 'publish_date', 'approval_location',
        'origin', 'is_ticket', 'clickup_task_id', 'launched_at', 'custom_fields',
    ];

    protected $casts = [
        'due_date'      => 'date',
        'approval_date' => 'date',
        'publish_date'  => 'date',
        'launched_at'   => 'datetime',
        'is_ticket'     => 'boolean',
        'custom_fields' => 'array',
    ];

    // ── Kanban: 4 colunas visuais ──────────────────────────────────────────
    public static array $kanbanColumns = [
        'backlog' => [
            'label'    => 'Backlog',
            'statuses' => ['backlog'],
            'color'    => 'muted',
        ],
        'em_andamento' => [
            'label'    => 'Em Andamento',
            'statuses' => ['em_copy', 'pronto_producao', 'em_producao'],
            'color'    => 'orange',
        ],
        'revisao' => [
            'label'    => 'Revisão',
            'statuses' => ['revisao', 'aguardando_envio', 'aguardando_resposta', 'ajuste'],
            'color'    => 'purple',
        ],
        'concluido' => [
            'label'    => 'Concluído',
            'statuses' => ['concluido'],
            'color'    => 'green',
        ],
    ];

    // Status detalhados do workflow
    public static array $statuses = [
        'backlog'               => ['label' => 'Backlog',             'col' => 'backlog',      'color' => 'muted'],
        'em_copy'               => ['label' => 'Em Copy',             'col' => 'em_andamento', 'color' => 'orange'],
        'pronto_producao'       => ['label' => 'Pronto p/ Produção',  'col' => 'em_andamento', 'color' => 'orange'],
        'em_producao'           => ['label' => 'Em Produção',         'col' => 'em_andamento', 'color' => 'orange'],
        'revisao'               => ['label' => 'Em Revisão',          'col' => 'revisao',      'color' => 'purple'],
        'aguardando_envio'      => ['label' => 'Aguardando Envio',    'col' => 'revisao',      'color' => 'purple'],
        'aguardando_resposta'   => ['label' => 'Aguardando Resposta', 'col' => 'revisao',      'color' => 'purple'],
        'ajuste'                => ['label' => 'Em Ajuste',           'col' => 'revisao',      'color' => 'purple'],
        'concluido'             => ['label' => 'Concluído',           'col' => 'concluido',    'color' => 'green'],
        'cancelado'             => ['label' => 'Cancelado',           'col' => 'cancelado',    'color' => 'red'],
    ];

    public static array $types = [
        'criacao'       => 'Criação',
        'web'           => 'Web / Dev',
        'trafego'       => 'Tráfego',
        'setup'         => 'Setup',
        'social'        => 'Social Media',
        'seo'           => 'SEO',
        'email'         => 'E-mail',
        'estrategia'    => 'Estratégia',
        'administrativo'=> 'Administrativo',
    ];

    // Status padrão ao mover para cada coluna kanban
    public static array $kanbanDefaultStatus = [
        'backlog'      => 'backlog',
        'em_andamento' => 'em_producao',
        'revisao'      => 'revisao',
        'concluido'    => 'concluido',
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function kanbanColumn(): string
    {
        return self::$statuses[$this->status]['col'] ?? 'backlog';
    }

    public function typeLabel(): string
    {
        return self::$types[$this->task_type] ?? $this->task_type;
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'concluido';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
