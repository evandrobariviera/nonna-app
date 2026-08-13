<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TaskApprovalService
{
    /**
     * Reavalia se a tarefa já reúne as condições pra entrar em aprovação
     * sozinha — status "Aprovação" + situação "Enviar para o cliente". Com
     * pelo menos um anexo "entregavel" (insumos/referências nunca entram na
     * rodada), cria uma rodada normal; sem entregável nenhum (ex: correções
     * de Dev/Web que não geram arquivo), cria uma rodada tipo "aviso" — retorno
     * informativo pro cliente, sem pedir decisão. Chamado de todo lugar que
     * possa completar essa condição, não importa a ordem em que as coisas
     * acontecem: TaskObserver (mudança de status/situação) e
     * TaskAttachmentController (upload de anexo) — porque dá pra marcar a
     * situação antes ou depois de subir o arquivo, e os dois caminhos precisam
     * terminar no mesmo lugar. Só cria a rodada — o envio de fato pro cliente
     * continua sendo manual, pelo botão na Central de Aprovações.
     *
     * @return bool  true se uma rodada foi criada nesta chamada
     */
    public function maybeAutoSubmitOnApprovalTransition(Task $task): bool
    {
        if ($task->status !== 'aprovacao' || $task->situation !== 'Enviar para o cliente') {
            return false;
        }

        if ($task->approvalRounds()->where('status', 'pending')->exists()) {
            return false;
        }

        $submitter = Auth::user();
        if (!$submitter) {
            return false;
        }

        $attachmentIds = $task->attachments()
            ->where('is_deliverable', false)
            ->where('kind', 'entregavel')
            ->pluck('id')
            ->toArray();

        if (empty($attachmentIds)) {
            TaskApprovalRound::create([
                'task_id'      => $task->id,
                'round_number' => $task->approvalRounds()->count() + 1,
                'type'         => 'aviso',
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
                'status'       => 'pending',
            ]);

            $task->update(['status' => 'aprovacao']);

            session()->flash('success', 'Tarefa "' . $task->title . '" está sem entregável — um Aviso foi criado na Central de Aprovações. Lá você escreve o retorno pro cliente (ou resolve sem notificar).');

            return true;
        }

        $this->submitForApproval($task, $submitter, $attachmentIds);

        session()->flash('success', 'Tarefa "' . $task->title . '" entrou em Aprovação — envie pro cliente na Central de Aprovações.');

        return true;
    }

    /**
     * Submete a tarefa para aprovação do cliente.
     * Cria uma rodada, marca os anexos como entregáveis e gera um token por
     * contato — mas NÃO notifica ninguém ainda. O envio de fato (webhook pro
     * n8n) é um passo manual à parte, disparado por sendToClient() a partir
     * da Central de Aprovações.
     *
     * @param  array<string>  $attachmentIds  UUIDs dos task_attachments que serão entregáveis
     */
    public function submitForApproval(Task $task, User $submitter, array $attachmentIds, ?string $notes = null): TaskApprovalRound
    {
        $roundNumber = $task->approvalRounds()->count() + 1;

        TaskAttachment::whereIn('id', $attachmentIds)
            ->where('task_id', $task->id)
            ->update(['is_deliverable' => true, 'round_number' => $roundNumber]);

        $round = TaskApprovalRound::create([
            'task_id'      => $task->id,
            'round_number' => $roundNumber,
            'submitted_by' => $submitter->id,
            'submitted_at' => now(),
            'status'       => 'pending',
            'notes'        => $notes,
            // Snapshot: se a legenda mudar depois, essa rodada continua mostrando
            // o texto exato que foi avaliado — igual aos anexos (round_number).
            'caption'      => $task->caption,
        ]);

        $task->update(['status' => 'aprovacao']);

        foreach ($this->getApprovalRecipients($task) as $clientContact) {
            TaskApprovalToken::create([
                'round_id'   => $round->id,
                'contact_id' => $clientContact->contact_id,
                'channels'   => $clientContact->subscriptions->first()->channels ?? [],
                'token'      => Str::uuid()->toString(),
                'status'     => 'pending',
                'expires_at' => now()->addDays(7),
            ]);
        }

        return $round;
    }

    /**
     * Dispara de fato a notificação pro cliente (webhook pro n8n, um POST por
     * contato) e marca a rodada como enviada. Ação manual, disparada pelo
     * botão "Enviar pro Cliente" na Central de Aprovações — nunca automática,
     * pra nunca mandar mais de uma tarefa pro cliente sem controle.
     */
    public function sendToClient(TaskApprovalRound $round): void
    {
        if ($round->sent_at) {
            return;
        }

        $round->loadMissing('tokens.contact');

        // Só notifica quem ficou "ligado" — contato desligado antes do envio
        // mantém o token (histórico/link continuam existindo), só não recebe
        // a notificação nem entra na exigência de unanimidade (tryResolveRound).
        foreach ($round->tokens->where('will_notify', true) as $token) {
            $this->dispatchWebhook($round, $token, $token->contact);
        }

        $round->update(['sent_at' => now()]);
    }

    /**
     * Liga/desliga um contato específico pra uma rodada específica — decidido
     * caso a caso porque tem material que só precisa da aprovação de um dos
     * contatos do cliente, não de todos os assinantes de "aprovacao". Só pode
     * mexer antes do envio (rodada ainda "Aguardando Envio") — depois disso a
     * notificação (ou a ausência dela) já é fato consumado.
     */
    public function toggleNotify(TaskApprovalToken $token, bool $willNotify): void
    {
        if ($token->round->sent_at) {
            throw new \RuntimeException('Essa rodada já foi enviada — não dá mais pra mexer em quem recebe.');
        }

        $token->update(['will_notify' => $willNotify]);
    }

    /**
     * Reenvia a notificação só pra quem ainda não respondeu (status pending) —
     * ex: e-mail de um aprovador estava errado, foi corrigido no cadastro do
     * contato, e agora precisa notificar de novo. Diferente de sendToClient():
     * não mexe em sent_at (a rodada já foi enviada uma vez) e não reenvia pra
     * quem já aprovou/pediu ajuste (já respondeu, não precisa de novo aviso).
     *
     * @return int  quantos contatos foram notificados de novo
     */
    public function resendPending(TaskApprovalRound $round): int
    {
        $round->loadMissing('tokens.contact');

        $pending = $round->tokens->where('status', 'pending')->where('will_notify', true);

        foreach ($pending as $token) {
            $this->dispatchWebhook($round, $token, $token->contact);
        }

        return $pending->count();
    }

    /**
     * Reenvia pra um único aprovador específico (não a rodada inteira) — caso
     * mais cirúrgico: só esse contato tinha o e-mail errado, os outros já
     * receberam certo e não precisam de outro aviso.
     */
    public function resendToken(TaskApprovalToken $token): void
    {
        $token->loadMissing('contact', 'round');

        $this->dispatchWebhook($token->round, $token, $token->contact);
    }

    /**
     * Cancela a rodada — criada errada, cliente errado, motivo interno qualquer.
     * Fecha todos os tokens ainda pending (o link público/Portal para de aceitar
     * decisão, `TaskApprovalToken::isValid()` passa a recusar). Só tira a tarefa
     * de "Aprovação" automaticamente se a rodada cancelada era a que estava
     * ativa (pending) — se já tinha sido resolvida antes (approved/changes_
     * requested), o status da tarefa já seguiu outro caminho depois disso e não
     * deve ser mexido aqui.
     *
     * Reseta status E situação juntos — se a situação ficasse "Enviar para o
     * cliente" armada, qualquer toque de volta no status "Aprovação" recria
     * uma rodada sozinho (maybeAutoSubmitOnApprovalTransition, disparado pelo
     * TaskObserver a cada mudança de status/situação), mesmo sem ninguém pedir
     * isso de propósito — reenviar precisa ser uma escolha consciente de novo.
     */
    public function cancelRound(TaskApprovalRound $round): void
    {
        $wasPending = $round->status === 'pending';

        $round->tokens()->where('status', 'pending')->update(['status' => 'cancelled']);

        $round->update(['status' => 'cancelled', 'resolved_at' => now()]);

        if ($wasPending) {
            $round->task->update(['status' => 'revisao_interna', 'situation' => 'Revisão Interna']);
        }
    }

    /**
     * Envia o Aviso ao cliente — mensagem escrita na hora, não uma legenda/
     * anexo. Reaproveita os mesmos contatos assinados em "aprovacao" e o
     * mecanismo de token, mas o token já nasce "approved": um aviso não pede
     * decisão de ninguém, então a rodada resolve na hora, sem esperar resposta
     * (diferente de sendToClient(), que fica pending até o cliente decidir).
     */
    public function sendAviso(TaskApprovalRound $round, string $message): void
    {
        $round->update(['notes' => $message]);

        foreach ($this->getApprovalRecipients($round->task) as $clientContact) {
            $token = TaskApprovalToken::create([
                'round_id'    => $round->id,
                'contact_id'  => $clientContact->contact_id,
                'channels'    => $clientContact->subscriptions->first()->channels ?? [],
                'token'       => Str::uuid()->toString(),
                'status'      => 'approved',
                'reviewed_at' => now(),
                'expires_at'  => now()->addDays(7),
            ]);

            $this->dispatchWebhook($round, $token, $clientContact->contact, 'aviso_tarefa', ['mensagem' => $message]);
        }

        $round->update(['sent_at' => now(), 'status' => 'approved', 'resolved_at' => now()]);
    }

    /**
     * Resolve o Aviso sem notificar ninguém — tira da fila da Central sem
     * gerar token nem disparar nada, pra quando o retorno já foi combinado
     * por outro canal (ou simplesmente não precisa avisar o cliente).
     */
    public function resolveAvisoWithoutNotifying(TaskApprovalRound $round): void
    {
        $round->update(['status' => 'approved', 'resolved_at' => now()]);
    }

    /**
     * Registra a decisão de um contato sobre a rodada inteira (todos os
     * arquivos + legenda juntos, não peça por peça — decidido com o Evandro:
     * pra granularidade por peça, a orientação é separar em tarefas distintas).
     * Depois verifica se todos os aprovadores já responderam (unanimidade).
     */
    public function submitDecision(TaskApprovalToken $token, string $status, ?string $comment = null): void
    {
        $token->update([
            'status'          => $status,
            'overall_comment' => $comment,
            'reviewed_at'     => now(),
        ]);

        $this->tryResolveRound($token->round()->with('tokens')->first());
    }

    /**
     * Decisão direta do usuário autenticado do Portal — um caminho separado
     * do fluxo por contato/token, sem granularidade por peça: o Portal decide
     * a rodada inteira de uma vez. Resolve na hora, mesmo que ainda existam
     * contatos nomeados sem responder pelo link público — o login do Portal
     * é considerado mais confiável que o contato público. Sai sem fazer nada
     * se a rodada já não estiver mais pending (já foi decidida por qualquer
     * um dos dois caminhos).
     *
     * @return bool  true se a decisão foi aplicada
     */
    public function submitPortalDecision(TaskApprovalRound $round, Contact $contact, string $decision, ?string $comment = null): bool
    {
        if ($round->status !== 'pending') {
            return false;
        }

        $round->update([
            'portal_decided_by_contact_id' => $contact->id,
            'portal_decision'   => $decision,
            'portal_comment'    => $comment,
            'portal_decided_at' => now(),
        ]);

        $this->resolveRound($round, $decision);

        // A rodada já foi decidida por outro caminho — fecha qualquer link
        // público de contato ainda pendente pra essa mesma rodada, pra ele
        // parar de aceitar submissão (TaskApprovalToken::isValid() checa
        // isPending()).
        $round->tokens()->where('status', 'pending')->update(['status' => $decision]);

        return true;
    }

    /**
     * Contatos do cliente assinados pra receber aprovação de materiais —
     * cada um pode ter um conjunto de canais diferente (WhatsApp, e-mail, ou
     * os dois). Ver ClientContactSubscription.
     *
     * @return Collection<int, ClientContact>
     */
    private function getApprovalRecipients(Task $task): Collection
    {
        return ClientContact::where('client_id', $task->client_id)
            ->whereHas('subscriptions', fn ($q) => $q->where('type', 'aprovacao'))
            ->with(['contact', 'subscriptions' => fn ($q) => $q->where('type', 'aprovacao')])
            ->get();
    }

    private function tryResolveRound(TaskApprovalRound $round): void
    {
        // Contato desligado antes do envio não entra na exigência de unanimidade
        // — senão o token dele, que nunca vai ser notificado nem respondido,
        // travaria a rodada em "pending" pra sempre.
        $tokens = $round->tokens->where('will_notify', true);

        if ($tokens->isEmpty() || $tokens->contains('status', 'pending')) {
            return;
        }

        $hasChanges = $tokens->contains('status', 'changes_requested');
        $this->resolveRound($round, $hasChanges ? 'changes_requested' : 'approved');
    }

    private function resolveRound(TaskApprovalRound $round, string $status): void
    {
        $round->update(['status' => $status, 'resolved_at' => now()]);

        $round->task->update($status === 'changes_requested'
            ? ['status' => 'ajuste_alteracao', 'situation' => 'Alteração Solicitada']
            : ['status' => 'despacho_agendamento']);
    }

    private function dispatchWebhook(TaskApprovalRound $round, TaskApprovalToken $approvalToken, Contact $contact, string $trigger = 'aprovacao', array $extraVariables = []): void
    {
        $channels = $approvalToken->channels ?? [];

        if (empty($channels)) {
            return;
        }

        $client = $round->task->client;

        $variables = array_merge([
            'tarefa'         => $round->task->title,
            'link_aprovacao' => route('approval.show', $approvalToken->token),
        ], $extraVariables);

        foreach ($channels as $channel) {
            app(NotificationDispatchService::class)->dispatch($trigger, $channel, $client, $contact, $variables);
        }

        $approvalToken->update(['notified_at' => now()]);
    }
}
