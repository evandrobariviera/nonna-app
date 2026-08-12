<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Builder;
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
        'title', 'description', 'caption', 'task_type', 'destination', 'status', 'situation',
        'priority',
        'executor_id', 'created_by', 'contact_id',
        'due_date', 'approval_date', 'publish_date',
        'approval_location', 'approval_method', 'internal_approval',
        'requester_name', 'requester_whatsapp', 'requester_channel',
        'origin', 'is_ticket', 'queued_at', 'clickup_task_id', 'launched_at', 'custom_fields', 'clickup_attachments',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'approval_date'     => 'date',
        'publish_date'      => 'date',
        'launched_at'       => 'datetime',
        'queued_at'         => 'datetime',
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
        'automation' => 'Automação',
    ];

    // Situação = sub-status cru vindo do ClickUp (texto livre). As chaves abaixo são
    // os valores reais observados na produção — mantidas como o próprio texto, sem
    // slug, pra bater exatamente com o que o import grava em tasks.situation.
    public static array $situations = [
        ''                            => '—',
        'Aguardando Copy / Redação'   => 'Aguardando Copy / Redação',
        'Em design'                  => 'Em design',
        'Em Setup / Dev'             => 'Em Setup / Dev',
        'Pronto para produção'       => 'Pronto para produção',
        'Revisão Interna'            => 'Revisão Interna',
        'Pronta para publicação'     => 'Pronta para publicação',
        'Agendado Plataforma'        => 'Agendado Plataforma',
        'Enviar para o cliente'      => 'Enviar para o cliente',
        'Em Aprovação Cliente'       => 'Em Aprovação Cliente',
        'Pendente de Informações'    => 'Pendente de Informações',
        'Alteração Solicitada'       => 'Alteração Solicitada',
        'Aprovado'                   => 'Aprovado',
        'Publicado / No Ar'          => 'Publicado / No Ar',
    ];

    public static array $situationColors = [
        'Aguardando Copy / Redação'   => '#94a3b8',
        'Em design'                  => '#2563eb',
        'Em Setup / Dev'             => '#2563eb',
        'Pronto para produção'       => '#94a3b8',
        'Revisão Interna'            => '#6A5ACD',
        'Pronta para publicação'     => '#2563eb',
        'Agendado Plataforma'        => '#2563eb',
        'Enviar para o cliente'      => '#6A5ACD',
        'Em Aprovação Cliente'       => '#FF8C00',
        'Pendente de Informações'    => '#FF8C00',
        'Alteração Solicitada'       => '#FF8C00',
        'Aprovado'                   => '#059669',
        'Publicado / No Ar'          => '#059669',
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

    // Resolve uma chave crua (ex: status="em_producao") pro rótulo humano, dado o
    // nome do campo — usado pelo TaskObserver pra logar TaskActivity sem duplicar
    // os mesmos mapas de $statuses/$situations/etc. em outro lugar. Campo sem
    // mapa conhecido (ex: title) não deve chamar isto — não é o caso de uso.
    public static function labelForField(string $field, ?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return match ($field) {
            'status'          => self::$statuses[$key]['label'] ?? $key,
            'situation'       => self::$situations[$key] ?? $key,
            'priority'        => self::$priorities[$key]['label'] ?? $key,
            'destination'     => self::$destinations[$key] ?? $key,
            'task_type'       => self::$types[$key] ?? $key,
            'approval_method' => self::$approvalMethods[$key] ?? $key,
            default           => $key,
        };
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

    public function createdByContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    // Quem criou a tarefa — sempre exatamente um dos dois (created_by XOR
    // contact_id, garantido por constraint no banco).
    public function creator(): User|Contact|null
    {
        return $this->createdBy ?? $this->createdByContact;
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

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(TaskStatusTransition::class)->orderBy('changed_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderByDesc('created_at');
    }

    /**
     * Tarefa "pendente" = falta algum dado essencial pra entrar em produção/sprint (datas,
     * destino, executor, responsável, descrição, origem, situação). Usado pro card de
     * pendências do Dashboard, filtro da Fila e pra bloquear entrada em Sprint (ver
     * SprintController::addTask()). Antes travava também por task_type=criacao e
     * origin=projeto (bandeira fixa, não checagem de campo faltando) e cliente interno —
     * critérios tirados por pedido explícito do usuário (2026-08-05): rígido demais, travava
     * tarefa completa só por causa do tipo/origem.
     */
    public function scopePendente(Builder $query): Builder
    {
        // Tarefa encerrada (concluída/cancelada) não precisa de dado completo —
        // é histórico, não vai mais ser trabalhada. Pendência só importa pra
        // quem ainda vai entrar em produção/sprint.
        return $query->whereNotIn('status', ['concluido', 'cancelado'])
            ->where(function (Builder $q) {
                $q->whereNull('destination')
                    ->orWhere(fn (Builder $q2) => $q2->whereNull('situation')->orWhere('situation', ''))
                    ->orWhereNull('due_date')
                    ->orWhereNull('approval_date')
                    ->orWhereNull('origin')
                    ->orWhere(fn (Builder $q2) => $q2->whereNull('description')->orWhere('description', ''))
                    ->orWhereDoesntHave('executors', fn (Builder $q2) => $q2->where('task_executors.role', 'executor'))
                    ->orWhereDoesntHave('responsibles');
            });
    }

    /**
     * Checagem pontual (1 registro) — reaproveita scopePendente() em vez de
     * duplicar a regra em PHP. Custa 1 query por chamada: não usar em loop
     * sobre uma lista grande (ver SprintController::show(), que é a única
     * exceção aceita porque a lista de uma sprint é pequena).
     */
    public function isPendente(): bool
    {
        return static::whereKey($this->getKey())->pendente()->exists();
    }

    /**
     * Agrupamento padrão usado nas telas de lista de tarefas (Filas, Sprint) —
     * mesma regra em todo o sistema pra dar consistência de uso.
     *
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     */
    public static function groupCollection(\Illuminate\Support\Collection $tasks, string $groupBy): \Illuminate\Support\Collection
    {
        return match ($groupBy) {
            'executor'    => $tasks->groupBy(fn ($t) => self::executorGroupKey($t)),
            'responsavel' => $tasks->groupBy(fn ($t) => self::responsavelGroupKey($t)),
            'status'      => $tasks->groupBy(fn ($t) => $t->status),
            'situacao'    => $tasks->groupBy(fn ($t) => $t->situation ?? ''),
            default       => $tasks->groupBy(fn ($t) => $t->client_id ?? '__sem_cliente__'),
        };
    }

    public static function executorGroupKey(self $task): string
    {
        $exec = $task->executors->first(fn ($u) => $u->pivot->role === 'executor') ?? $task->executor;
        return $exec ? $exec->id . '|' . $exec->name : '__sem_executor__|Sem executor';
    }

    public static function responsavelGroupKey(self $task): string
    {
        $resp = $task->executors->first(fn ($u) => $u->pivot->role === 'responsavel');
        return $resp ? $resp->id . '|' . $resp->name : '__sem_responsavel__|Sem responsável';
    }
}
