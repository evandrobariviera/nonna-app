<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\DeliverableFeedback;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TaskApprovalService
{
    /**
     * Submete a tarefa para aprovação do cliente.
     * Cria uma rodada, marca os anexos como entregáveis,
     * gera tokens por contato e dispara webhooks para o n8n.
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

        $contacts = $this->getApprovalContacts($task);

        foreach ($contacts as $contact) {
            $token = TaskApprovalToken::create([
                'round_id'   => $round->id,
                'contact_id' => $contact->id,
                'token'      => Str::uuid()->toString(),
                'status'     => 'pending',
                'expires_at' => now()->addDays(7),
            ]);

            $this->dispatchWebhook($round, $token, $contact);
        }

        return $round;
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
                'name' => $round->task->client->name,
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
