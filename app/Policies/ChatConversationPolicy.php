<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;

/**
 * Porta única de acesso ao chat interno.
 *
 * Regra do Evandro (2026-10-02): só participa quem está na conversa. NÃO existe
 * atalho pra dono/admin/superadmin — por isso não há before() nem checagem de papel
 * aqui, de propósito. Qualquer rota nova de chat precisa passar por esta policy.
 */
class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): bool
    {
        return $conversation->hasParticipant($user->id);
    }
}
