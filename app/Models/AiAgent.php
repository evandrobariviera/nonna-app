<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgent extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'name', 'description', 'system_prompt',
        'provider_id', 'api_key_id',
        'model', 'temperature', 'max_tokens',
        'context_scope', 'is_active', 'created_by',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens'  => 'integer',
        'is_active'   => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(AiApiKey::class, 'api_key_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tokenUsage(): HasMany
    {
        return $this->hasMany(AiTokenUsage::class, 'agent_id');
    }

    // Retorna a chave efetiva: a do agente se configurada, senão a padrão do provider
    public function resolvedApiKey(): ?AiApiKey
    {
        return $this->apiKey ?? $this->provider->activeKey();
    }
}
