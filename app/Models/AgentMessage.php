<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensagem de uma conversa do Agente Nonna. Guarda o formato que a OpenAI espera
 * (assistant com tool_calls → uma mensagem "tool" por chamada) pra remontar o
 * histórico; proposal* = ação (tarefa/reunião) esperando o "Confirmar" da pessoa.
 */
class AgentMessage extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    // created_at com microssegundos (ver migration) — a ordem das mensagens importa.
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'conversation_id', 'role', 'content',
        'tool_calls', 'tool_call_id', 'tool_name', 'tool_args',
        'proposal', 'proposal_status', 'proposal_result',
    ];

    protected $casts = [
        'tool_calls'      => 'array',
        'tool_args'       => 'array',
        'proposal'        => 'array',
        'proposal_result' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }
}
