<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Segmento que a Nonna aborda na prospecção (termos de busca + tipo do Google). */
class CommerceSegment extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'name', 'google_type', 'search_terms', 'is_active', 'sort_order'];

    protected $casts = [
        'search_terms' => 'array',
        'is_active'    => 'boolean',
    ];
}
