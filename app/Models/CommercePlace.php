<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Empresa mapeada na prospecção. Termos do Google: só o place_id é guardado.
 * Nome, endereço, telefone e avaliações nunca vão para o banco — são lidos ao vivo
 * (App\Services\Commerce\GooglePlaces::details). Ver .claude/docs/central-comercio.md.
 */
class CommercePlace extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'place_id', 'city_id', 'segment_id', 'status', 'score', 'score_parts',
        'last_seen_at', 'place_id_checked_at', 'site_signals', 'collected_at', 'collect_error',
        'ads_meta', 'ads_google', 'ads_checked_by', 'ads_checked_at',
        'google_data', 'google_data_at', // dados do Google guardados (decisão 25/11/2026, ver migration)
    ];

    protected $casts = [
        'score_parts'         => 'array',
        'last_seen_at'        => 'datetime',
        'place_id_checked_at' => 'datetime',
        'site_signals'        => 'array',
        'collected_at'        => 'datetime',
        'ads_checked_at'      => 'datetime',
        'google_data'         => 'array',
        'google_data_at'      => 'datetime',
    ];

    public static array $statuses = [
        'mapped'    => ['label' => 'Mapeada',          'color' => 'muted'],
        'scored'    => ['label' => 'Com nota',         'color' => 'purple'],
        'report'    => ['label' => 'Raio-x pronto',    'color' => 'orange'],
        'sent'      => ['label' => 'Enviada',          'color' => 'purple'],
        'replied'   => ['label' => 'Respondeu',        'color' => 'green'],
        'opted_out' => ['label' => 'Pediu pra sair',   'color' => 'red'],
        'discarded' => ['label' => 'Descartada',       'color' => 'muted'],
    ];

    public function adsChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ads_checked_by');
    }

    /** Nota parcial enquanto as rodadas de IA (etapa 4) não existem: vai até 80. */
    public function scoreMax(): int
    {
        return (int) ($this->score_parts['max'] ?? 100);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(CommerceCity::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CommerceSegment::class);
    }

    public function scans(): BelongsToMany
    {
        return $this->belongsToMany(CommerceScan::class, 'commerce_scan_place', 'place_id', 'scan_id');
    }
}
