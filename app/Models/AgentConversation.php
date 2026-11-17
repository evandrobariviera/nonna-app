<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversa com o Agente Nonna (página /agente). Uma conversa é de uma pessoa só —
 * ninguém vê a conversa do outro (ver AgentController::authorizeConversation).
 */
class AgentConversation extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['user_id', 'title', 'last_message_at'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
