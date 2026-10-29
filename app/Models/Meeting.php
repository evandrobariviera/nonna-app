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
use Illuminate\Database\Eloquent\Relations\MorphToMany;

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
        'transcricao',
        'transcricao_updated_at',
        'next_steps',
        'ata_recorded_at',
    ];

    protected $casts = [
        'scheduled_at'           => 'datetime',
        'ata_recorded_at'        => 'datetime',
        'transcricao_updated_at' => 'datetime',
        'duration_minutes'       => 'integer',
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
        // Despacho: ATA e planejamento prontos, com o Gestor de Projetos distribuindo o
        // trabalho (mesma nomenclatura do status de Tarefa). Só depois vira Finalizada.
        'despacho'     => ['label' => 'Despacho',        'color' => 'orange'],
        // Chave continua "realizada" (46+ reuniões e automações gravadas com ela);
        // só o rótulo mudou pra "Finalizada" (2026-09-30).
        'realizada'    => ['label' => 'Finalizada',      'color' => 'green'],
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

    // ATA vazia do editor ("<p></p>") não conta como ATA.
    public function hasAta(): bool
    {
        return trim(strip_tags((string) $this->ata)) !== '';
    }

    // Pauta = texto da pauta OU algum item citado (planejamento/campanha/tarefa). Os itens
    // só contam quando a consulta trouxe a flag (scopeWithAgendaFlag) — sem consulta por card.
    public function hasPauta(): bool
    {
        return trim(strip_tags((string) $this->agenda)) !== '' || (bool) ($this->has_agenda_items ?? false);
    }

    /** Traz has_agenda_items (algum item citado na pauta) na mesma consulta das reuniões. */
    public function scopeWithAgendaFlag(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (is_null($query->getQuery()->columns)) {
            $query->select($this->getTable() . '.*');
        }

        return $query->selectRaw('exists(select 1 from meeting_agenda_items mai where mai.meeting_id = ' . $this->getTable() . '.id) as has_agenda_items');
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

    /**
     * Pauta citada (meeting_agenda_items): além do texto livre da pauta, a reunião cita
     * planejamentos, campanhas/projetos e tarefas, na ordem da pauta. Na reunião de
     * discussão, cada campanha citada tem sua equipe (Project::members).
     */
    public function agendaProjects(): MorphToMany
    {
        return $this->agendaItems(Project::class);
    }

    public function agendaTasks(): MorphToMany
    {
        return $this->agendaItems(Task::class);
    }

    public function agendaMacroPlans(): MorphToMany
    {
        return $this->agendaItems(MacroPlan::class);
    }

    private function agendaItems(string $class): MorphToMany
    {
        return $this->morphedByMany($class, 'item', 'meeting_agenda_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('meeting_agenda_items.position');
    }

    public function hasAgendaItems(): bool
    {
        return $this->agendaMacroPlans->isNotEmpty() || $this->agendaProjects->isNotEmpty() || $this->agendaTasks->isNotEmpty();
    }

    // ── Onde a reunião aparece sozinha ──
    // Citar uma tarefa mostra a reunião na tarefa, no projeto e no planejamento dela; citar
    // um projeto, no projeto e no planejamento (decisão do Evandro, 2026-10-22).

    public function scopeRelatedToTask(Builder $query, Task $task): Builder
    {
        return $query->where(fn ($q) => $q->whereHas('agendaTasks', fn ($t) => $t->whereKey($task->id))
            ->when($task->meeting_id, fn ($q) => $q->orWhere('meetings.id', $task->meeting_id)));
    }

    public function scopeRelatedToProject(Builder $query, Project $project): Builder
    {
        return $query->where(fn ($q) => $q->whereHas('agendaProjects', fn ($p) => $p->whereKey($project->id))
            ->orWhereHas('agendaTasks', fn ($t) => $t->where('tasks.project_id', $project->id)));
    }

    public function scopeRelatedToMacroPlan(Builder $query, MacroPlan $plan): Builder
    {
        return $query->where(fn ($q) => $q->where('meetings.macro_plan_id', $plan->id)
            ->orWhereHas('agendaMacroPlans', fn ($m) => $m->whereKey($plan->id))
            ->orWhereHas('agendaProjects', fn ($p) => $p->where('projects.macro_plan_id', $plan->id))
            ->orWhereHas('agendaTasks', fn ($t) => $t->where(fn ($w) => $w->where('tasks.macro_plan_id', $plan->id)
                ->orWhereIn('tasks.project_id', Project::where('macro_plan_id', $plan->id)->select('id')))));
    }

    /**
     * Por que a reunião aparece nesta página quando o item não foi citado direto — "via
     * tarefa X", "via campanha Y". Null = citado direto (ou reunião ligada ao planejamento).
     * Precisa de agendaProjects/agendaTasks carregados.
     */
    public function viaLabelFor(Model $context): ?string
    {
        $cites = fn ($items) => $items->contains(fn ($i) => $i->getKey() === $context->getKey() && $i::class === $context::class);
        if ($cites($this->agendaMacroPlans ?? collect()) || $cites($this->agendaProjects) || $cites($this->agendaTasks)
            || ($context instanceof MacroPlan && $this->macro_plan_id === $context->id)
            || ($context instanceof Task && $this->id === $context->meeting_id)) {
            return null;
        }

        if ($context instanceof MacroPlan) {
            $project = $this->agendaProjects->firstWhere('macro_plan_id', $context->id);
            if ($project) {
                return 'via ' . mb_strtolower($project->typeLabel()) . ' ' . $project->title;
            }
        }
        $task = $context instanceof Project
            ? $this->agendaTasks->firstWhere('project_id', $context->id)
            : ($this->agendaTasks->firstWhere('macro_plan_id', $context->id) ?? $this->agendaTasks->first());

        return $task ? 'via tarefa ' . $task->title : null;
    }

    // Pendência aberta pro organizador quando a reunião passa do horário sem ATA
    // (meetings:ata-pendencies); se resolve sozinha quando a ATA é preenchida.
    public const ATA_PENDENCY_PREFIX = 'Registrar a ATA';

    public function pendencies(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Pendency::class, 'pendable');
    }

    /**
     * Estado do card (<x-item-card>) nas listas por status: 'now' (é hoje), 'late' / 'soon'
     * (parada além da conta na etapa) ou null (em dia). Junto vem um lembrete curto do porquê.
     * Agendada com data passada = esqueceram de mudar o status; Pós-Reunião = falta a ATA
     * (se a ATA já existe, laranja: só falta avançar o status)
     * (2+ dias atrasada, desde ontem vence logo); Revisão Interna = ATA pronta e time sem
     * fechar a leitura (3+ dias).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function cardState(): array
    {
        if (! $this->scheduled_at) {
            return [null, null];
        }
        if ($this->scheduled_at->isToday()) {
            return ['now', null];
        }

        $days = (int) $this->scheduled_at->copy()->startOfDay()->diffInDays(today(), false);

        return match ($this->status) {
            'agendada'    => $days > 0 ? ['late', 'data passou'] : [null, null],
            // ATA já feita e a reunião ainda parada em Pós-Reunião = só falta andar o status.
            'pos_reuniao' => $this->hasAta() ? ['soon', 'avance o status']
                : ($days >= 2 ? ['late', "sem ATA há {$days} dias"] : ($days === 1 ? ['soon', 'ATA até hoje'] : [null, null])),
            'revisao_ata' => (function () {
                $since = (int) ($this->ata_recorded_at ?? $this->scheduled_at)->copy()->startOfDay()->diffInDays(today());
                return $since >= 3 ? ['late', "em revisão há {$since} dias"] : [null, null];
            })(),
            default       => [null, null],
        };
    }

    /** Fim previsto da reunião (sem duração cadastrada, 1 hora). */
    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes ?: 60);
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
