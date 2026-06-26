<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTokenUsage extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = true;
    const UPDATED_AT = null; // só created_at importa aqui

    protected $fillable = [
        'agent_id', 'user_id', 'client_id',
        'model', 'provider',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'estimated_cost_usd', 'trigger',
    ];

    protected $casts = [
        'prompt_tokens'      => 'integer',
        'completion_tokens'  => 'integer',
        'total_tokens'       => 'integer',
        'estimated_cost_usd' => 'float',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
