<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Produto, serviço ou pacote que o cliente contratou (nasce da proposta aceita quando a oportunidade é ganha). */
class ClientProduct extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'client_id',
        'catalog_item_id',
        'proposal_id',
        'name',
        'kind',
        'quantity',
        'monthly_price',
        'one_time_price',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'quantity'       => 'integer',
        'monthly_price'  => 'decimal:2',
        'one_time_price' => 'decimal:2',
        'started_at'     => 'date',
        'ended_at'       => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
