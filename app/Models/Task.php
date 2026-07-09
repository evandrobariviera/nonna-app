<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'project_id', 'macro_plan_id', 'sprint_id', 'client_id',
        'title', 'description', 'task_type', 'destination', 'status', 'situation',
        'priority',
        'executor_id', 'created_by',
        'due_date', 'approval_date', 'publish_date',
        'approval_location', 'approval_method', 'internal_approval',
        'requester_name', 'requester_whatsapp', 'requester_channel',
        'origin', 'is_ticket', 'clickup_task_id', 'launched_at', 'custom_fields', 'clickup_attachments',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'approval_date'     => 'date',
        'publish_date'      => 'date',
        'launched_at'       => 'datetime',
        'is_ticket'         => 'boolean',
        'internal_approval' => 'boolean',
        'custom_fields'          => 'array',
        'clickup_attachments'    => 'array',
    ];

    // ── Status: espelha 1:1 os status reais da lista de Produção/Sprint no ClickUp ──
    public static array $statuses = [
        'backlog'               => ['label' => 'Backlog / A Fazer',    'color' => 'muted'],
        'em_producao'           => ['label' => 'Em Produção',          'color' => 'blue'],
        'revisao_interna'       => ['label' => 'Revisão Interna',      'color' => 'purple'],
        'ajuste_alteracao'      => ['label' => 'Ajuste / Alteração',   'color' => 'blue'],
        'aprovacao'             => ['label' => 'Aprovação',            'color' => 'orange'],
        'despacho_agendamento'  => ['label' => 'Despacho / Agendamento', 'color' => 'blue'],
        'concluido'             => ['label' => 'Concluído',            'color' => 'green'],
        'cancelado'             => ['label' => 'Cancelado',            'color' => 'red'],
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
        'triagem'                 => 'Triagem',
        'em_producao'             => 'Em Produção',
        'aguardando_referencias'  => 'Aguardando Referências',
        'em_revisao_interna'      => 'Em Revisão Interna',
        'enviar_para_cliente'     => 'Enviar para o Cliente',
        'agendado_publicacao'     => 'Agendado para Publicação',
        'publicado'               => 'Publicado',
    ];

    public static array $situationColors = [
        'triagem'                => '#94a3b8',
        'em_producao'            => '#2563eb',
        'aguardando_referencias' => '#FF8C00',
        'em_revisao_interna'     => '#d97706',
        'enviar_para_cliente'    => '#6A5ACD',
        'agendado_publicacao'    => '#0d9488',
        'publicado'              => '#059669',
    ];

    public static array $requesterChannels = [
        'whatsapp'  => 'WhatsApp',
        'email'     => 'E-mail',
        'presencial'=> 'Presencial',
    ];

    public static array $priorities = [
        'urgente' => ['label' => 'Urgente', 'color' => 'red'],
        'medio'   => ['label' => 'Médio',   'color' => 'orange'],
        'normal'  => ['label' => 'Normal',  'color' => 'muted'],
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
        return $this->status ?? 'backlog';
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

    public function priorityLabel(): string
    {
        return self::$priorities[$this->priority ?? 'normal']['label'] ?? 'Normal';
    }

    public function priorityColor(): string
    {
        return self::$priorities[$this->priority ?? 'normal']['color'] ?? 'muted';
    }

    public static function colorHex(string $color): string
    {
        return match($color) {
            'green'  => '#059669',
            'blue'   => '#2563eb',
            'purple' => '#6A5ACD',
            'orange' => '#FF8C00',
            'red'    => '#dc2626',
            'yellow' => '#d97706',
            'teal'   => '#0d9488',
            default  => '#94a3b8',
        };
    }

    public function statusHex(): string
    {
        return self::colorHex($this->statusColor());
    }

    public function priorityHex(): string
    {
        return self::colorHex($this->priorityColor());
    }

    public function situationColor(): string
    {
        return self::$situationColors[$this->situation ?? ''] ?? '#94a3b8';
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

    public function firstImageAttachmentUrl(): ?string
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($this->clickup_attachments ?? [] as $file) {
            $url = $file['url'] ?? null;
            if ($url && in_array(strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)), $imageExtensions)) {
                return $url;
            }
        }

        $local = $this->relationLoaded('attachments')
            ? $this->attachments->first(fn ($a) => $a->isImage())
            : $this->attachments()->get()->first(fn ($a) => $a->isImage());

        return $local?->url();
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

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_executors', 'task_id', 'user_id')
            ->wherePivot('role', 'responsavel')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function observers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_executors', 'task_id', 'user_id')
            ->wherePivot('role', 'observador')
            ->withPivot('role')
            ->withTimestamps();
    }
}
