<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'title',
        'type',
        'modality',
        'status',
        'client_id',
        'opportunity_id',
        'macro_plan_id',
        'source_meeting_id',
        'organized_by',
        'created_by',
        'scheduled_at',
        'duration_minutes',
        'location',
        'online_link',
        'agenda',
        'ata',
        'next_steps',
        'ata_recorded_at',
    ];

    protected $casts = [
        'scheduled_at'    => 'datetime',
        'ata_recorded_at' => 'datetime',
        'duration_minutes'=> 'integer',
    ];

    public static array $types = [
        'comercial_vendas'          => 'Comercial / Vendas',
        'boas_vindas'               => 'Boas Vindas (Onboarding)',
        'kickoff_estrategico'       => 'Kick-off Estratégico',
        'macroplanejamento'         => 'Macroplanejamento Periódico',
        'alinhamento_projeto'       => 'Alinhamento de Projeto',
        'setor_sync'                => 'De Setor (Sync de Qualidade)',
        'distribuicao_sprint'       => 'Distribuição (Sprint Planning)',
        'evento_captacao'           => 'Evento ou Captação Externa',
        'revisao_interna'           => 'Revisão Interna (Planejamento)',
        'outros'                    => 'Outros',
    ];

    public static array $modalities = [
        'online'                => 'Online',
        'presencial_agencia'    => 'Presencial — Agência',
        'presencial_cliente'    => 'Presencial — Cliente',
    ];

    public static array $statuses = [
        'para_agendar' => ['label' => 'Para Agendar',    'color' => 'muted'],
        'agendada'     => ['label' => 'Agendada',        'color' => 'green'],
        'pos_reuniao'  => ['label' => 'Pós-Reunião',     'color' => 'red'],
        'revisao_ata'  => ['label' => 'Revisão Interna', 'color' => 'purple'],
        'realizada'    => ['label' => 'Realizada',       'color' => 'green'],
        'cancelada'    => ['label' => 'Cancelada',       'color' => 'red'],
    ];

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function typeIcon(): string
    {
        // Ícone fixo — reunião não varia por tipo, só a cor (status) muda.
        return 'calendar';
    }

    public function modalityLabel(): string
    {
        return self::$modalities[$this->modality] ?? $this->modality;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function hasAta(): bool
    {
        return !empty($this->ata);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    // Tarefas nascidas direto desta Reunião (ver Task::meeting()) — antes até de existir
    // Planejamento. Quando a Reunião entra num Macro depois, MeetingObserver replica
    // macro_plan_id pra essas tarefas.
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // Reunião com o cliente que originou esta Reunião Interna (pauta gerada por IA
    // a partir da ATA dela) — só preenchido em reuniões type=revisao_interna criadas
    // pela automação.
    public function sourceMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'source_meeting_id');
    }

    // Reunião Interna gerada a partir desta (lado inverso de sourceMeeting).
    public function internalReview(): HasOne
    {
        return $this->hasOne(Meeting::class, 'source_meeting_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organized_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participantLinks(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants', 'meeting_id', 'user_id')
            ->withTimestamps();
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'meeting_contacts', 'meeting_id', 'contact_id')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class);
    }
}
