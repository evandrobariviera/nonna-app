<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadChannel extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'name',
        'kind',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Valor de "kind" == o "source_channel" que o n8n manda no payload de
    // POST /api/leads/captura — é por esse campo que o LeadCaptureService
    // resolve o LeadChannel, não pelo "name" (que é só rótulo de exibição).
    public static array $kinds = [
        'site'              => 'Site',
        'facebook_lead_ad'  => 'Facebook/Instagram Lead Ads',
        'whatsapp'          => 'WhatsApp',
        'outros'            => 'Outros',
    ];

    public function kindLabel(): string
    {
        return self::$kinds[$this->kind] ?? $this->kind;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ClientLeadSource::class);
    }
}
