<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'name', 'slug', 'base_url', 'models', 'is_active',
    ];

    protected $casts = [
        'models'    => 'array',
        'is_active' => 'boolean',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(AiApiKey::class, 'provider_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(AiAgent::class, 'provider_id');
    }

    public function activeKey(): ?AiApiKey
    {
        return $this->apiKeys()->where('is_active', true)->latest()->first();
    }
}
