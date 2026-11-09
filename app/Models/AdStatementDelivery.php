<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * Um envio de notas fiscais ao cliente: um aviso por envio (não por arquivo),
 * com o link público assinado do mês e quando o cliente abriu.
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

    /** Link que vai no WhatsApp/e-mail: abre sem login, vale 180 dias. */
    public function publicUrl(): string
    {
        return URL::temporarySignedRoute('billing-statement.public', now()->addDays(180), ['delivery' => $this->id]);
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
