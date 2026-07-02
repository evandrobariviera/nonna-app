<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MacroPlan extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $table = 'macro_plans';

    protected $fillable = [
        'organization_id',
        'client_id',
        'responsible_id',
        'created_by',
        'title',
        'version',
        'period_start',
        'period_end',
        'status',
        'disciplines',
        'bloco1',
        'bloco2',
        'bloco4',
        'bloco5',
        'clickup_task_id',
        'clickup_attachments',
        'launched_at',
    ];

    protected $casts = [
        'period_start'        => 'date',
        'period_end'          => 'date',
        'launched_at'         => 'datetime',
        'disciplines'         => 'array',
        'bloco1'              => 'array',
        'bloco2'              => 'array',
        'bloco4'              => 'array',
        'bloco5'              => 'array',
        'clickup_attachments' => 'array',
    ];

    public static array $disciplineOptions = [
        'criacao'      => 'Criação / Audiovisual',
        'web'          => 'Web / Dev',
        'trafego'      => 'Tráfego / Performance',
        'setup'        => 'Setup / Tracking',
        'social'       => 'Social Media',
        'seo'          => 'SEO',
        'email'        => 'E-mail Marketing',
        'estrategia'   => 'Estratégia',
        'relacionamento' => 'Relacionamento / CS',
    ];

    public static array $statuses = [
        'draft'  => ['label' => 'Rascunho', 'color' => 'muted'],
        'active' => ['label' => 'Ativo',    'color' => 'blue'],
        'closed' => ['label' => 'Encerrado','color' => 'green'],
    ];

    public static array $blocks = [
        'bloco1' => ['num' => '01', 'label' => 'Visão Geral e Metas'],
        'bloco2' => ['num' => '02', 'label' => 'Contexto e Estratégia'],
        'bloco3' => ['num' => '03', 'label' => 'Arquitetura de Projetos'],
        'bloco4' => ['num' => '04', 'label' => 'Tarefas Isoladas e Rotina'],
        'bloco5' => ['num' => '05', 'label' => 'Checklist de Infraestrutura'],
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function isLaunched(): bool
    {
        return $this->launched_at !== null;
    }

    public function periodLabel(): string
    {
        return $this->period_start->format('d/m/Y') . ' a ' . $this->period_end->format('d/m/Y');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position');
    }
}
