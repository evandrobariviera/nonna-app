<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'project_id', 'macro_plan_id', 'sprint_id', 'client_id',
        'title', 'description', 'task_type', 'destination', 'status', 'situation',
        'executor_id', 'created_by',
        'due_date', 'approval_date', 'publish_date',
        'approval_location', 'approval_method', 'internal_approval',
        'requester_name', 'requester_whatsapp', 'requester_channel',
        'origin', 'is_ticket', 'clickup_task_id', 'launched_at', 'custom_fields',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'approval_date'     => 'date',
        'publish_date'      => 'date',
        'launched_at'       => 'datetime',
        'is_ticket'         => 'boolean',
        'internal_approval' => 'boolean',
        'custom_fields'     => 'array',
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

    public static array $statuses = [
        'backlog'               => ['label' => 'Backlog',             'col' => 'backlog',      'color' => 'muted'],
        'em_copy'               => ['label' => 'Em Copy',             'col' => 'em_andamento', 'color' => 'orange'],
        'pronto_producao'       => ['label' => 'Pronto p/ Produção',  'col' => 'em_andamento', 'color' => 'orange'],
        'em_producao'           => ['label' => 'Em Produção',         'col' => 'em_andamento', 'color' => 'orange'],
        'revisao'               => ['label' => 'Em Revisão',          'col' => 'revisao',      'color' => 'purple'],
        'aguardando_envio'      => ['label' => 'Aguardando Envio',    'col' => 'revisao',      'color' => 'purple'],
        'aguardando_resposta'   => ['label' => 'Aguardando Resposta', 'col' => 'revisao',      'color' => 'purple'],
        'ajuste'                    => ['label' => 'Em Ajuste',              'col' => 'revisao',      'color' => 'purple'],
        'aguardando_aprovacao'      => ['label' => 'Aguardando Aprovação',   'col' => 'revisao',      'color' => 'blue'],
        'concluido'                 => ['label' => 'Concluído',              'col' => 'concluido',    'color' => 'green'],
        'aprovado'                  => ['label' => 'Aprovado pelo Cliente',  'col' => 'concluido',    'color' => 'green'],
        'cancelado'                 => ['label' => 'Cancelado',              'col' => 'cancelado',    'color' => 'red'],
    ];

    public static array $types = [
        'criacao'        => 'Criação / Audiovisual',
        'web'            => 'Web / Dev',
        'trafego'        => 'Tráfego / Performance',
        'setup'          => 'Setup / Tracking',
        'social'         => 'Social Media',
        'seo'            => 'SEO',
        'email'          => 'E-mail Marketing',
        'estrategia'     => 'Estratégia / Planejamento',
        'administrativo' => 'Administrativo / Financeiro',
        'reunioes'       => 'Reuniões / Atendimento',
    ];

    public static array $destinations = [
        'publicacao_organica'      => 'Publicação Orgânica',
        'campanhas_patrocinadas'   => 'Campanhas Patrocinadas',
        'projeto_web'              => 'Projeto Web',
        'administrativo_financeiro'=> 'Administrativo / Financeiro',
        'envio_resposta_cliente'   => 'Envio / Resposta ao Cliente',
    ];

    public static array $approvalMethods = [
        'aprovaaí'  => 'Aprova aí',
        'whatsapp'  => 'WhatsApp',
        'email'     => 'E-mail',
    ];

    public static array $origins = [
        'onboarding' => 'Onboarding',
        'projeto'    => 'Projeto',
        'roadmap'    => 'Roadmap',
        'ticket'     => 'Ticket',
    ];

    public static array $situations = [
        ''                        => '—',
        'em_producao'             => 'Em Produção',
        'aguardando_referencias'  => 'Aguardando Referências',
        'em_revisao_interna'      => 'Em Revisão Interna',
        'enviar_para_cliente'     => 'Enviar para o Cliente',
        'agendado_publicacao'     => 'Agendado para Publicação',
        'publicado'               => 'Publicado',
    ];

    public static array $requesterChannels = [
        'whatsapp'  => 'WhatsApp',
        'email'     => 'E-mail',
        'presencial'=> 'Presencial',
    ];

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

    public function situationLabel(): string
    {
        return self::$situations[$this->situation ?? ''] ?? ($this->situation ?? '—');
    }

    public function destinationLabel(): string
    {
        return self::$destinations[$this->destination] ?? '';
    }

    public function approvalMethodLabel(): string
    {
        return self::$approvalMethods[$this->approval_method] ?? '';
    }

    public function originLabel(): string
    {
        return self::$origins[$this->origin] ?? $this->origin;
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'concluido';
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
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

    public function executorLinks(): HasMany
    {
        return $this->hasMany(TaskExecutor::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->orderBy('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    public function approvalRounds(): HasMany
    {
        return $this->hasMany(TaskApprovalRound::class)->orderBy('round_number');
    }

    public function latestApprovalRound(): HasOne
    {
        return $this->hasOne(TaskApprovalRound::class)->orderByDesc('round_number');
    }

    public function executors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_executors', 'task_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }
}
