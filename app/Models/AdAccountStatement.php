<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extrato mensal de uma conta de anúncio (Prestação de contas). Só guarda o
 * que não dá para calcular: saldo inicial (primeiro mês ou correção manual)
 * e a observação do mês. Créditos, consumo e notas são calculados na hora
 * por App\Services\Billing\AccountStatement.
 */
class AdAccountStatement extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'client_ad_account_id', 'month', 'opening_balance', 'opening_balance_source', 'notes'];

    protected $casts = [
        'month'           => 'date',
        'opening_balance' => 'decimal:2',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }
}
