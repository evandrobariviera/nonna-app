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
        'organization_id',
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
        'briefing_estrategia',
        'briefing_relacionamento',
        'status',
        'type',
        'brief_status',
        'tags',
        'content_ideas',
        'traffic_phases',
        'start_date',
        'end_date',
        'budget',
        'clickup_task_id',
        'clickup_list_id',
        'clickup_attachments',
        'launched_at',
        // Brief criativo de campanha
        'big_idea_titulo',
        'big_idea_manifesto',
        'territorio_alternativo',
        'racional_estrategico',
        'frase_voz',
        'assinatura',
        'ponto_atencao',
        'tom_comunicacao',
        'angulos',
        'mecanica',
        'referencias_visuais',
        'pecas',
    ];

    public static array $types = [
        'projeto'  => ['label' => 'Projeto',  'color' => 'purple'],
        'campanha' => ['label' => 'Campanha', 'color' => 'green'],
    ];

    public static array $briefStatuses = [
        'basico'    => ['label' => 'Básico',    'color' => 'muted'],
        'detalhado' => ['label' => 'Detalhado', 'color' => 'orange'],
    ];

    public function typeLabel(): string
    {
        return self::$types[$this->type]['label'] ?? $this->type;
    }

    public function typeColor(): string
    {
        return self::$types[$this->type]['color'] ?? 'muted';
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            'campanha' => 'megaphone',
            default    => 'folder-kanban',
        };
    }

    public function briefStatusLabel(): string
    {
        return self::$briefStatuses[$this->brief_status]['label'] ?? $this->brief_status;
    }

    public function briefStatusColor(): string
    {
        return self::$briefStatuses[$this->brief_status]['color'] ?? 'muted';
    }

    public function isCampaignDetailed(): bool
    {
        return $this->type === 'campanha' && $this->brief_status === 'detalhado';
    }

    protected $casts = [
        'disciplines'   => 'array',
        'tags'          => 'array',
        'content_ideas' => 'array',
        'traffic_phases'=> 'array',
        'start_date'    => 'date',
        'end_date'      => 'date',
        'budget'              => 'decimal:2',
        'launched_at'         => 'datetime',
        'position'            => 'integer',
        'clickup_attachments' => 'array',
        'tom_comunicacao'     => 'array',
        'angulos'             => 'array',
        'mecanica'            => 'array',
        'referencias_visuais' => 'array',
        'pecas'               => 'array',
    ];

    public static array $disciplines = [
        'criacao'  => 'Criação / Audiovisual',
        'web'      => 'Web / Dev',
        'trafego'  => 'Tráfego / Performance',
        'setup'    => 'Setup / Tracking',
        'social'   => 'Social Media',
        'seo'      => 'SEO',
        'email'    => 'E-mail Marketing',
        'estrategia'     => 'Estratégia',
        'relacionamento' => 'Relacionamento / CS',
    ];

    public static array $statuses = [
        'em_planejamento' => ['label' => 'Em Planejamento', 'color' => 'muted'],
        'aprovacao'       => ['label' => 'Aprovação',       'color' => 'orange'],
        'em_execucao'     => ['label' => 'Em Execução',     'color' => 'blue'],
        'stand_by'        => ['label' => 'Stand By',        'color' => 'orange'],
        'concluido'       => ['label' => 'Concluído',       'color' => 'green'],
        // Só usado pelo mirror de exclusão do ClickUp (deleted:true) — nunca oferecido num <select>,
        // já que o ClickUp não tem um bucket "Cancelado" pra Projetos.
        'cancelado'       => ['label' => 'Cancelado',       'color' => 'red'],
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

    // Campanhas de anúncio (Meta/Google, sincronizadas) vinculadas manualmente
    // a este projeto — mesmo princípio de tasks(), mas pro lado "campanha
    // patrocinada" do trabalho, que não nasce como Task.
    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->orderBy('created_at');
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
        foreach (Task::$statuses as $statusKey => $meta) {
            $counts[$statusKey] = $tasks->where('status', $statusKey)->count();
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
