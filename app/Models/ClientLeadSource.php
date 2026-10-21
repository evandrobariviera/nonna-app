<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientLeadSource extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'lead_channel_id',
        'external_id',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(LeadChannel::class, 'lead_channel_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(ClientLeadOpportunity::class);
    }

    public function displayName(): string
    {
        return $this->label ?: $this->external_id;
    }

    /**
     * Clientes com fonte de leads ativa mas sem nenhum contato assinado em
     * "Lead Capturado" — os leads entram e ninguém do cliente é avisado
     * (NotificationDispatchService::send() não tem pra quem mandar). Uma consulta só.
     *
     * @return array<int, string>
     */
    public static function clientIdsWithoutLeadSubscriber(): array
    {
        return self::where('is_active', true)
            ->whereNotIn('client_id', ClientContact::select('client_contacts.client_id')
                ->join('client_contact_subscriptions', 'client_contact_subscriptions.client_contact_id', '=', 'client_contacts.id')
                ->where('client_contact_subscriptions.type', 'lead_capturado'))
            ->distinct()
            ->pluck('client_id')
            ->all();
    }
}
