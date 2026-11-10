<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um envio de conversão para uma plataforma (Meta, Google Ads, GA4). Um por
 * lead + plataforma + evento: o mesmo evento nunca vai duas vezes.
 */
class ConversionEvent extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_id', 'client_lead_opportunity_id', 'platform', 'event_name',
        'event_id', 'status', 'detail', 'value', 'event_time',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'value'      => 'decimal:2',
    ];

    public static array $statuses = [
        'enviado'  => ['label' => 'Enviado',          'color' => 'green'],
        'teste'    => ['label' => 'Enviado (teste)',  'color' => 'purple'],
        'erro'     => ['label' => 'Erro',             'color' => 'red'],
        'ignorado' => ['label' => 'Não enviado',      'color' => 'muted'],
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(ClientLeadOpportunity::class, 'client_lead_opportunity_id');
    }
}
