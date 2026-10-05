<?php

namespace App\Models;

use App\Services\Leads\FormAnswers;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'created_by',
    ];

    protected $casts = [
        'raw_payload'  => 'array',
        'form_answers' => 'array',
        'previous_submissions' => 'array',
        'won_at'     => 'datetime',
        'lost_at'      => 'datetime',
        'received_at'  => 'datetime',
    ];

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
        return !in_array($this->stage, ['ganho', 'perdido']);
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientLeadOpportunityNote::class)->orderBy('created_at');
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
