<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'type',
        'category_id',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'payment_method',
        'description',
        'client_id',
        'contract_id',
        'recurring_group_id',
        'installment_number',
        'installment_total',
        'created_by',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'paid_at'  => 'date',
    ];

    public static array $types = [
        'entrada' => 'Entrada',
        'saida'   => 'Saída',
    ];

    // "atrasado" nunca é gravado em status — é previsto + due_date no passado,
    // calculado em displayStatus(). Existe aqui só pra dar label/color a essa
    // apresentação, igual Contract::$statuses.
    public static array $statuses = [
        'previsto'  => ['label' => 'Previsto',  'color' => 'blue'],
        'pago'      => ['label' => 'Pago',      'color' => 'green'],
        'cancelado' => ['label' => 'Cancelado', 'color' => 'muted'],
        'atrasado'  => ['label' => 'Atrasado',  'color' => 'red'],
    ];

    public function isOverdue(): bool
    {
        return $this->status === 'previsto' && $this->due_date->isPast();
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'atrasado' : $this->status;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->displayStatus()]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->displayStatus()]['color'] ?? 'muted';
    }

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
