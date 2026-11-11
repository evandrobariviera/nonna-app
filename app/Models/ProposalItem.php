<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de uma proposta — cópia do item do catálogo no momento em que a proposta foi montada. */
class ProposalItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'proposal_id',
        'catalog_item_id',
        'name',
        'kind',
        'quantity',
        'list_price',
        'unit_price',
        'subtotal',
        'rules',
        'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'list_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'rules'      => 'array',
        'sort_order' => 'integer',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
