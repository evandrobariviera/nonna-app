<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proposta comercial (Central de Comércio). Ver ProposalService pro ciclo de vida:
 * rascunho → (aguardando liberação) → enviada → aceita | recusada. Enviada congela;
 * mudar depois disso cria nova versão (parent_id).
 */
class Proposal extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'opportunity_id',
        'parent_id',
        'title',
        'version',
        'status',
        'package_id',
        'package_snapshot',
        'ad_budget',
        'discount_percent',
        'installments',
        'group_on',
        'group_name',
        'group_mode',
        'group_monthly',
        'group_one_time',
        'monthly_total',
        'one_time_total',
        'contract_total',
        'calculation',
        'released_percent',
        'released_by',
        'released_at',
        'release_note',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'created_by',
    ];

    protected $casts = [
        'package_snapshot' => 'array',
        'calculation'      => 'array',
        'ad_budget'        => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'installments'     => 'integer',
        'version'          => 'integer',
        'group_on'         => 'boolean',
        'group_monthly'    => 'decimal:2',
        'group_one_time'   => 'decimal:2',
        'monthly_total'    => 'decimal:2',
        'one_time_total'   => 'decimal:2',
        'contract_total'   => 'decimal:2',
        'released_percent' => 'decimal:2',
        'released_at'      => 'datetime',
        'sent_at'          => 'datetime',
        'accepted_at'      => 'datetime',
        'rejected_at'      => 'datetime',
    ];

    public static array $statuses = [
        'draft'           => ['label' => 'Rascunho',             'color' => 'muted'],
        'pending_release' => ['label' => 'Aguardando liberação', 'color' => 'yellow'],
        'sent'            => ['label' => 'Enviada',              'color' => 'orange'],
        'accepted'        => ['label' => 'Aceita',               'color' => 'green'],
        'rejected'        => ['label' => 'Recusada',             'color' => 'red'],
    ];

    // ── Relacionamentos ──

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'package_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    // ── Helpers ──

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    /** Rascunho e "aguardando liberação" ainda podem mudar; enviada/aceita/recusada não. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending_release'], true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['draft', 'pending_release', 'sent'], true);
    }

    /** Nome que resume a base da proposta: o pacote, o pacote personalizado ou "Itens avulsos". */
    public function baseLabel(): string
    {
        return $this->package_snapshot['name']
            ?? ($this->group_on && $this->group_name ? $this->group_name : 'Itens avulsos');
    }

    public function displayTitle(): string
    {
        return "v{$this->version} · {$this->title}";
    }
}
