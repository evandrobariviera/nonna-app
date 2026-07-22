<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceDiagnostic extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'client_integration_id',
        'version',
        'period_start',
        'period_end',
        'status',
        'generated_by',
        'ai_agent_id',
        'created_by',
        'total_conversations',
        'sales_confirmed',
        'sales_in_negotiation',
        'conversion_rate',
        'avg_first_response_minutes',
        'funnel_summary',
        'gaps',
        'strengths',
        'campaign_insights',
        'recommendations',
        'published_at',
    ];

    protected $casts = [
        'period_start'                => 'date',
        'period_end'                  => 'date',
        'version'                     => 'integer',
        'total_conversations'         => 'integer',
        'sales_confirmed'             => 'integer',
        'sales_in_negotiation'        => 'integer',
        'conversion_rate'             => 'float',
        'avg_first_response_minutes'  => 'integer',
        'funnel_summary'              => 'array',
        'gaps'                        => 'array',
        'strengths'                   => 'array',
        'campaign_insights'           => 'array',
        'recommendations'             => 'array',
        'published_at'                => 'datetime',
    ];

    public static array $statuses = [
        'draft'     => ['label' => 'Rascunho',   'color' => 'muted'],
        'published' => ['label' => 'Publicado', 'color' => 'green'],
    ];

    public static array $generatedByOptions = [
        'scheduled' => 'Agendado',
        'manual'    => 'Manual',
    ];

    public function statusLabel(): string
    {
        return data_get(self::$statuses, "{$this->status}.label", $this->status);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    // ── Relacionamentos ──

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ClientIntegration::class, 'client_integration_id');
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(ServiceDiagnosticPersona::class, 'diagnostic_id')->orderBy('position');
    }
}
