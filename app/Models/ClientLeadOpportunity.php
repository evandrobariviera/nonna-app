<?php

namespace App\Models;

use App\Services\Leads\FormAnswers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class ClientLeadOpportunity extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_lead_id',
        'client_lead_source_id',
        'lead_channel_id',
        'stage',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'landing_page_url',
        'fbclid',
        'gclid',
        'ctwa_clid',
        'ad_external_id',
        'campaign_external_id',
        'ad_ad_id',
        'ad_campaign_id',
        'service_conversation_id',
        'event_id',
        'form_name',
        'received_at',
        'raw_payload',
        'form_answers',
        'previous_submissions',
        'lost_reason',
        'won_at',
        'lost_at',
        'assigned_to',
        'assigned_contact_id',
        'assigned_at',
        'deal_value',
        'created_by',
    ];

    protected $casts = [
        'raw_payload'  => 'array',
        'form_answers' => 'array',
        'previous_submissions' => 'array',
        'won_at'     => 'datetime',
        'lost_at'      => 'datetime',
        'received_at'  => 'datetime',
        'assigned_at'  => 'datetime',
        'deal_value'   => 'decimal:2',
    ];

    public const CLOSED_STAGES = ['ganho', 'perdido'];

    public static array $stages = [
        'novo'             => ['label' => 'Novo',             'color' => 'muted'],
        'em_contato'       => ['label' => 'Em Contato',       'color' => 'purple'],
        'qualificado'      => ['label' => 'Qualificado',      'color' => 'purple'],
        'proposta_enviada' => ['label' => 'Proposta Enviada', 'color' => 'orange'],
        'negociando'       => ['label' => 'Em Negociação',    'color' => 'orange'],
        'ganho'            => ['label' => 'Ganho',            'color' => 'green'],
        'perdido'          => ['label' => 'Perdido',          'color' => 'red'],
    ];

    public function stageLabel(): string
    {
        return self::$stages[$this->stage]['label'] ?? $this->stage;
    }

    public function stageColor(): string
    {
        return self::$stages[$this->stage]['color'] ?? 'muted';
    }

    public function isOpen(): bool
    {
        return !in_array($this->stage, self::CLOSED_STAGES);
    }

    public function isAssigned(): bool
    {
        return $this->assigned_to || $this->assigned_contact_id;
    }

    // Nome de quem atende — do time do cliente ou da Nonna (prefixo pra distinguir no Portal).
    public function assigneeName(bool $markNonna = false): ?string
    {
        if ($this->assignedContact) {
            return $this->assignedContact->name;
        }

        if ($this->assignedTo) {
            return $markNonna ? "Nonna · {$this->assignedTo->name}" : $this->assignedTo->name;
        }

        return null;
    }

    // Valor no select de responsável: "contact:{uuid}" ou "user:{id}".
    public function assigneeKey(): string
    {
        return match (true) {
            (bool) $this->assigned_contact_id => "contact:{$this->assigned_contact_id}",
            (bool) $this->assigned_to         => "user:{$this->assigned_to}",
            default                           => '',
        };
    }

    /**
     * Troca o responsável (no máximo um, do cliente OU da Nonna) e registra no
     * histórico. Devolve false quando nada mudou.
     */
    public function assign(?int $userId, ?string $contactId, ?int $byUserId, ?string $byContactId): bool
    {
        if ($this->assigned_to === $userId && $this->assigned_contact_id === $contactId) {
            return false;
        }

        $this->update([
            'assigned_to'         => $userId,
            'assigned_contact_id' => $contactId,
            'assigned_at'         => ($userId || $contactId) ? now() : null,
        ]);
        $this->unsetRelation('assignedTo')->unsetRelation('assignedContact');

        $this->notes()->create([
            'user_id'    => $byUserId,
            'contact_id' => $byContactId,
            'body'       => ($name = $this->assigneeName()) ? "Atribuiu o lead a {$name}." : 'Tirou o responsável do lead.',
        ]);

        return true;
    }

    public function setDealValue(?float $value, ?int $byUserId, ?string $byContactId): void
    {
        $value = $value !== null ? round($value, 2) : null;
        $current = $this->deal_value !== null ? round((float) $this->deal_value, 2) : null;
        if ($current === $value) {
            return;
        }

        $this->update(['deal_value' => $value]);

        $this->notes()->create([
            'user_id'    => $byUserId,
            'contact_id' => $byContactId,
            'body'       => $value !== null
                ? 'Valor da negociação: R$ ' . number_format($value, 2, ',', '.') . '.'
                : 'Tirou o valor da negociação.',
        ]);
    }

    public static function formatMoney(float|string|null $value): string
    {
        return 'R$ ' . number_format((float) $value, ((float) $value == floor((float) $value)) ? 0 : 2, ',', '.');
    }

    // Ganhos e perdidos ficam escondidos por padrão nas listas — só aparecem
    // quando pedidos (?fechados=1) ou quando o filtro de estágio é um deles.
    public function scopeHideClosedUnless(Builder $query, bool $showClosed): Builder
    {
        return $showClosed ? $query : $query->whereNotIn('stage', self::CLOSED_STAGES);
    }

    // Portal com "lead atribuído some para os outros": só os meus e os sem responsável.
    public function scopeVisibleToContact(Builder $query, string $contactId): Builder
    {
        return $query->where(fn ($q) => $q->where('assigned_contact_id', $contactId)
            ->orWhere(fn ($q) => $q->whereNull('assigned_contact_id')->whereNull('assigned_to')));
    }

    public function isVisibleToContact(string $contactId): bool
    {
        return $this->assigned_contact_id === $contactId || !$this->isAssigned();
    }

    // Leads anteriores ao campo form_answers caem no que der pra extrair do raw_payload.
    public function answers(): array
    {
        return $this->form_answers
            ? FormAnswers::withoutIdentity($this->form_answers)
            : FormAnswers::fromRawPayload($this->raw_payload);
    }

    public function messagePreview(int $limit = 120): ?string
    {
        $candidates = array_filter($this->answers(), fn ($a) => FormAnswers::isMessage($a['label']));
        $best = collect($candidates)->first(fn ($a) => preg_match('/mensag|message/i', $a['label']))
            ?? reset($candidates) ?: null;

        return $best ? Str::limit($best['value'], $limit) : null;
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(ClientLead::class, 'client_lead_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ClientLeadSource::class, 'client_lead_source_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(LeadChannel::class, 'lead_channel_id');
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(AdAd::class, 'ad_ad_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ServiceConversation::class, 'service_conversation_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'assigned_contact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientLeadOpportunityNote::class)->orderBy('created_at');
    }

    /** Avisos de "Lead Capturado" disparados pros contatos do cliente (NotificationDispatchService). */
    public function notificationDispatches(): MorphMany
    {
        return $this->morphMany(NotificationDispatch::class, 'related')->orderBy('created_at');
    }

    // Registra tanto uma nota escrita (User interno OU Contact do Portal, XOR)
    // quanto uma mudança de estágio automática (from_stage/to_stage
    // preenchidos) — mesma tabela, evita uma segunda pra histórico.
    public function logStageChange(string $from, string $to, ?int $userId, ?string $contactId): ClientLeadOpportunityNote
    {
        $fromLabel = self::$stages[$from]['label'] ?? $from;
        $toLabel   = self::$stages[$to]['label'] ?? $to;

        return $this->notes()->create([
            'user_id'    => $userId,
            'contact_id' => $contactId,
            'body'       => "Moveu de \"{$fromLabel}\" para \"{$toLabel}\".",
            'from_stage' => $from,
            'to_stage'   => $to,
        ]);
    }
}
