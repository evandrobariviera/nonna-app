<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'title',
        'status',
        'start_date',
        'end_date',
        'signed_at',
        'fee_value',
        'fee_type',
        'line_items',
        'payment_method',
        'billing_day',
        'renewal_type',
        'notice_period_days',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'signed_at'   => 'date',
        'fee_value'   => 'decimal:2',
        'line_items'  => 'array',
        'billing_day' => 'integer',
    ];

    public static array $statuses = [
        'rascunho'  => ['label' => 'Rascunho',  'color' => 'muted'],
        'enviado'   => ['label' => 'Enviado',   'color' => 'blue'],
        'assinado'  => ['label' => 'Assinado',  'color' => 'cyan'],
        'ativo'     => ['label' => 'Ativo',     'color' => 'green'],
        'encerrado' => ['label' => 'Encerrado', 'color' => 'muted'],
        'cancelado' => ['label' => 'Cancelado', 'color' => 'red'],
    ];

    public static array $feeTypes = [
        'mensal'         => 'Mensal',
        'anual'          => 'Anual',
        'projeto_unico'  => 'Projeto único',
        'personalizado'  => 'Personalizado',
    ];

    public static array $renewalTypes = [
        'automatica'    => 'Automática',
        'manual'        => 'Manual',
        'sem_renovacao' => 'Sem renovação',
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function feeTypeLabel(): string
    {
        return self::$feeTypes[$this->fee_type] ?? '—';
    }

    public function renewalTypeLabel(): string
    {
        return self::$renewalTypes[$this->renewal_type] ?? '—';
    }

    public function periodLabel(): string
    {
        if (!$this->start_date) {
            return '—';
        }

        $start = $this->start_date->format('d/m/Y');
        $end = $this->end_date?->format('d/m/Y') ?? 'indeterminado';

        return "{$start} até {$end}";
    }

    public function lineItemsTotal(): float
    {
        return collect($this->line_items ?? [])->sum(fn ($item) => (float) ($item['valor'] ?? 0));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class)->latest();
    }
}
