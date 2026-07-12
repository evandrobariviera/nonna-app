<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\DeliverableFeedback;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TaskApprovalService
{
    /**
     * Reavalia se a tarefa já reúne as condições pra entrar em aprovação
     * sozinha — status "Aprovação" + situação "Enviar para o cliente" + pelo
     * menos um anexo. Chamado de todo lugar que possa completar essa condição,
     * não importa a ordem em que as coisas aconteçam: TaskObserver (mudança de
     * status/situação) e TaskAttachmentController (upload de anexo) — porque
     * dá pra marcar a situação antes ou depois de subir o arquivo, e os dois
     * caminhos precisam terminar no mesmo lugar. Só cria a rodada — o envio de
     * fato pro cliente continua sendo manual, pelo botão na Central de Aprovações.
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
            ->pluck('id')
            ->toArray();

        if (empty($attachmentIds)) {
            session()->flash('warning', 'A tarefa "' . $task->title . '" está em Aprovação com situação "Enviar para o cliente", mas não há nenhum arquivo pra enviar — assim que você subir o arquivo, a rodada é criada automaticamente.');
            return false;
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
        ]);

        $task->update(['status' => 'aprovacao']);

        foreach ($this->getApprovalContacts($task) as $contact) {
            TaskApprovalToken::create([
                'round_id'   => $round->id,
                'contact_id' => $contact->id,
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

        foreach ($round->tokens as $token) {
            $this->dispatchWebhook($round, $token, $token->contact);
        }

        $round->update(['sent_at' => now()]);
    }

    /**
     * Registra o feedback peça por peça de um contato.
     * Depois verifica se todos os aprovadores já responderam (unanimidade).
     *
     * @param  array<array{attachment_id: string, status: string, comment: ?string}>  $feedbacks
     */
    public function submitFeedback(TaskApprovalToken $token, array $feedbacks, ?string $overallComment = null): void
    {
        foreach ($feedbacks as $item) {
            DeliverableFeedback::create([
                'token_id'      => $token->id,
                'attachment_id' => $item['attachment_id'],
                'status'        => $item['status'],
                'comment'       => $item['comment'] ?? null,
            ]);
        }

        $hasChanges = collect($feedbacks)->contains('status', 'changes_requested');

        $token->update([
            'status'          => $hasChanges ? 'changes_requested' : 'approved',
            'overall_comment' => $overallComment,
            'reviewed_at'     => now(),
        ]);

        $this->tryResolveRound($token->round()->with('tokens')->first());
    }

    private function getApprovalContacts(Task $task): \Illuminate\Database\Eloquent\Collection
    {
        return $task->client->contacts()
            ->wherePivot('receives_approvals', true)
            ->get();
    }

    private function tryResolveRound(TaskApprovalRound $round): void
    {
        $tokens = $round->tokens;

        if ($tokens->contains('status', 'pending')) {
            return;
        }

        $hasChanges = $tokens->contains('status', 'changes_requested');
        $status     = $hasChanges ? 'changes_requested' : 'approved';

        $round->update(['status' => $status, 'resolved_at' => now()]);

        $round->task->update(['status' => $hasChanges ? 'ajuste_alteracao' : 'despacho_agendamento']);
    }

    private function dispatchWebhook(TaskApprovalRound $round, TaskApprovalToken $approvalToken, Contact $contact): void
    {
        $webhookUrl = config('services.n8n.approval_webhook_url');

        if (! $webhookUrl) {
            return;
        }

        $deliverablesCount = TaskAttachment::where('task_id', $round->task_id)
            ->where('is_deliverable', true)
            ->where('round_number', $round->round_number)
            ->count();

        Http::timeout(5)->post($webhookUrl, [
            'event' => 'approval_requested',
            'round' => [
                'id'         => $round->id,
                'number'     => $round->round_number,
                'task_title' => $round->task->title,
                'notes'      => $round->notes,
            ],
            'client' => [
                'id'   => $round->task->client_id,
                'name' => $round->task->client->company_name,
            ],
            'contact' => [
                'name'  => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone ?? null,
            ],
            'link'               => route('approval.show', $approvalToken->token),
            'expires_at'         => $approvalToken->expires_at->toIso8601String(),
            'deliverables_count' => $deliverablesCount,
        ]);

        $approvalToken->update(['notified_at' => now()]);
    }
}
