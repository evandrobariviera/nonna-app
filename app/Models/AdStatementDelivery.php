<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um envio de notas fiscais ao cliente: um aviso por envio (não por arquivo)
 * levando ao Portal, e quando o cliente abriu a página lá. Não existe link
 * público: os documentos só abrem no Portal, com login (decisão 09/11/2026).
 */
class AdStatementDelivery extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'client_id', 'month', 'document_ids', 'total', 'sent_by', 'sent_at', 'viewed_at', 'view_count'];

    protected $casts = [
        'month'        => 'date',
        'document_ids' => 'array',
        'total'        => 'decimal:2',
        'sent_at'      => 'datetime',
        'viewed_at'    => 'datetime',
    ];

    /** Link que vai no aviso: a Prestação de contas no Portal (pede login). */
    public function portalUrl(): string
    {
        return route('portal.boletos.index');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
