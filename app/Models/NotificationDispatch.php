<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Uma tentativa de disparo de Mensagem Padrão — gravada pelo
 * NotificationDispatchService. Ver migration create_notification_dispatches_table.
 */
class NotificationDispatch extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'type', 'channel',
        'client_id', 'contact_id', 'client_name', 'contact_name', 'recipient',
        'status', 'reason', 'http_status', 'message_body', 'is_test',
        'related_type', 'related_id',
    ];

    protected $casts = [
        'is_test' => 'boolean',
    ];

    // A partir de quando os avisos de "Lead Capturado" passaram a ficar ligados
    // à oportunidade (related_*) — antes disso o registro do lead não tem histórico.
    public const LEAD_LINK_STARTED_AT = '2026-10-21 14:00:00';

    public static array $statuses = [
        'sent'    => ['label' => 'Enviado ao n8n', 'color' => 'green'],
        'failed'  => ['label' => 'Falhou',         'color' => 'red'],
        'skipped' => ['label' => 'Não enviado',    'color' => 'orange'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Registro que originou o disparo (ex: ClientLeadOpportunity). */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return NotificationTemplate::$types[$this->type] ?? $this->type;
    }

    public function channelLabel(): string
    {
        return NotificationTemplate::$channels[$this->channel] ?? $this->channel;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }
}
