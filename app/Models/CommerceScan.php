<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Uma varredura: busca no Google as empresas de um segmento (ou nicho) numa cidade.
 * Só traz os IDs (busca gratuita no Google); nome e endereço são lidos ao vivo na tela.
 */
class CommerceScan extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'city_id', 'segment_id', 'niche', 'status', 'terms', 'places_found', 'places_new',
        'saturated', 'api_calls', 'error', 'created_by', 'finished_at',
    ];

    protected $casts = [
        'terms'       => 'array',
        'saturated'   => 'boolean',
        'finished_at' => 'datetime',
    ];

    public static array $statuses = [
        'queued'  => ['label' => 'Na fila',     'color' => 'muted'],
        'running' => ['label' => 'Varrendo…',   'color' => 'orange'],
        'done'    => ['label' => 'Concluída',   'color' => 'green'],
        'error'   => ['label' => 'Deu erro',    'color' => 'red'],
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(CommerceCity::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CommerceSegment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(CommercePlace::class, 'commerce_scan_place', 'scan_id', 'place_id');
    }

    public function label(): string
    {
        $what = $this->niche ?: ($this->segment?->name ?? 'Varredura');

        return $what . ' · ' . ($this->city?->name ?? '');
    }
}
