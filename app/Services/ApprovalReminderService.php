<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TaskApprovalToken;

/**
 * Lembrete único: em vez de reenviar peça por peça, UMA mensagem por contato
 * do cliente — "você tem N peças esperando a sua resposta" + link que abre a
 * Central de Aprovações dele (login mágico, ver PortalMagicAccess). Só conta
 * o que espera a resposta DAQUELE contato (link válido, ligado, rodada enviada).
 *
 * Mensagem: Mensagens Padrão, gatilho "aprovacao_lembrete" (sem modelo
 * cadastrado pro canal, não envia nada — mesma regra dos outros gatilhos).
 */
class ApprovalReminderService
{
    /**
     * @return int  quantos contatos foram lembrados
     */
    public function remindClient(Client $client): int
    {
        $byContact = TaskApprovalToken::query()
            ->where('status', 'pending')
            ->where('will_notify', true)
            ->where('expires_at', '>', now())
            ->whereHas('round', fn ($q) => $q->where('status', 'pending')
                ->where('type', 'aprovacao')
                ->whereNotNull('sent_at')
                ->whereHas('task', fn ($t) => $t->where('client_id', $client->id)))
            ->with(['contact', 'round.task:id,title'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('contact_id');

        $reminded = 0;

        foreach ($byContact as $tokens) {
            $contact  = $tokens->first()->contact;
            $channels = $tokens->pluck('channels')->flatten()->filter()->unique()->values();
            if (!$contact || $channels->isEmpty()) {
                continue;
            }

            // Cada notificação renova a validade dos links (TaskApprovalService::LINK_DAYS).
            TaskApprovalToken::whereIn('id', $tokens->pluck('id'))->update([
                'expires_at'  => now()->addDays(TaskApprovalService::LINK_DAYS),
                'notified_at' => now(),
            ]);

            $variables = [
                'quantidade'   => $tokens->count(),
                'lista_pecas'  => $tokens->map(fn ($t) => '• ' . $t->round->task->title)->implode("\n"),
                'link_central' => route('approval.central', $tokens->first()->token),
            ];

            foreach ($channels as $channel) {
                app(NotificationDispatchService::class)->dispatch('aprovacao_lembrete', $channel, $client, $contact, $variables);
            }

            $reminded++;
        }

        return $reminded;
    }
}
