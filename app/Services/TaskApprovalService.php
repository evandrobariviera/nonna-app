<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\Task;
use App\Models\TaskActivity;
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

        $attachmentIds = $task->attachments()
            ->where('is_deliverable', false)
            ->where('kind', 'entregavel')
            ->pluck('id')
            ->toArray();

        $pendingRound = $task->approvalRounds()->where('status', 'pending')->first();
        if ($pendingRound) {
            // Achado real: quando a situação vira "Enviar para o cliente" ANTES do
            // entregável existir, isso cria um Aviso (sem entregável nenhum ainda) —
            // e esse Aviso pending bloqueava pra sempre a criação da rodada de
            // verdade quando o arquivo chegava logo depois (segundos/minutos
            // depois, mesmo dia), porque esse guard sempre devolvia false. Só o
            // Aviso AINDA NÃO ENVIADO pode ser substituído — Aviso já enviado ou
            // rodada de aprovação normal pending não mexe (decisão real em jogo).
            if (!$pendingRound->isAviso() || $pendingRound->sent_at || empty($attachmentIds)) {
                return false;
            }

            $pendingRound->update(['status' => 'cancelled', 'resolved_at' => now()]);
        }

        $submitter = Auth::user();
        if (!$submitter) {
            return false;
        }

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
        // Reenviar já significa "tratamos o retorno do cliente" — sem isso, a rodada antiga
        // (Ajustes Solicitados, sem handled_at) ficava viva na Central de Aprovações ao mesmo
        // tempo que a rodada nova (Aguardando Envio/Resposta), fazendo a MESMA tarefa aparecer
        // em duas colunas do quadro simultaneamente (confusão relatada pelo Alisson).
        $task->approvalRounds()
            ->where('status', 'changes_requested')
            ->whereNull('handled_at')
            ->update(['handled_at' => now()]);

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

        // Só a situação — o status da tarefa continua "Aprovação", intocado.
        $round->task->update(['situation' => 'Em Aprovação Cliente']);
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

        // Libera os entregáveis dessa rodada (is_deliverable volta a false) —
        // sem isso, maybeAutoSubmitOnApprovalTransition() nunca mais encontra
        // esses arquivos como "novos" pra submeter (ela procura is_deliverable
        // = false), e toda tentativa seguinte de reenviar a MESMA tarefa pra
        // aprovação cai no fallback de "sem entregável" e cria um Aviso em vez
        // de uma rodada de verdade — mesmo com os arquivos certos ali na tarefa.
        TaskAttachment::where('task_id', $round->task_id)
            ->where('kind', 'entregavel')
            ->where('round_number', $round->round_number)
            ->update(['is_deliverable' => false]);

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

        // Só a situação — o status da tarefa continua "Aprovação", intocado.
        $round->task->update(['situation' => 'Em Aprovação Cliente']);
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
     * Marca manualmente a decisão de um aprovador que ainda não respondeu —
     * pra quando o time já sabe que a aprovação de outra pessoa cobre o
     * resto e não quer esperar o clique de quem falta. Só entra num token
     * ainda pending, de rodada ainda pending, e que não esteja desligado
     * (will_notify) — nunca sobrescreve uma decisão real do contato. Fica
     * registrado quem da equipe decidiu (manually_decided_by) pra nunca
     * passar como se fosse o clique do próprio cliente.
     */
    public function manuallyDecideToken(TaskApprovalToken $token, string $status, User $actor, ?string $comment = null): void
    {
        $round = $token->round;

        if ($round->status !== 'pending') {
            throw new \RuntimeException('Essa rodada já foi encerrada.');
        }
        if (! $token->isPending()) {
            throw new \RuntimeException('Esse aprovador já respondeu — não dá pra sobrescrever a decisão dele.');
        }
        if (! $token->will_notify) {
            throw new \RuntimeException('Esse contato está desligado desta rodada.');
        }

        $token->update([
            'status'              => $status,
            'overall_comment'     => $comment,
            'reviewed_at'         => now(),
            'manually_decided_by' => $actor->id,
        ]);

        TaskActivity::log(
            $round->task,
            'approval_manual_decision',
            null,
            ($status === 'approved' ? 'Aprovado' : 'Ajustes solicitados') . " manualmente em nome de {$token->contact->name}",
            $actor->id,
        );

        // Recarrega a rodada com os tokens frescos do banco — $round->tokens
        // (relação já resolvida antes do update acima) ficaria com o status
        // antigo do próprio $token que acabamos de decidir, e tryResolveRound()
        // nunca veria unanimidade. Mesmo motivo pelo qual submitDecision() só
        // recarrega depois de gravar a decisão.
        $this->tryResolveRound($round->fresh('tokens'));
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
