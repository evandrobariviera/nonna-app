<?php

namespace App\Http\Controllers;

use App\Models\TaskApprovalToken;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private TaskApprovalService $service) {}

    public function show(string $token)
    {
        $approvalToken = TaskApprovalToken::where('token', $token)
            ->with([
                'round.task.client',
                'round.submittedBy',
                'round.tokens.contact',
                // Histórico de todas as rodadas da mesma tarefa (quem aprovou/pediu
                // ajuste em cada uma) — pra o aprovador ver o quadro completo, não só
                // a rodada atual.
                'round.task.approvalRounds.tokens.contact',
                'round.task.approvalRounds.tokens.feedbacks.attachment',
                'contact',
            ])
            ->firstOrFail();

        if (! $approvalToken->isValid()) {
            return view('approval.expired', compact('approvalToken'));
        }

        $deliverables = $approvalToken->round->deliverables();

        return view('approval.show', compact('approvalToken', 'deliverables'));
    }

    public function submit(Request $request, string $token)
    {
        $approvalToken = TaskApprovalToken::where('token', $token)->firstOrFail();

        if (! $approvalToken->isValid()) {
            return redirect()->route('approval.show', $token);
        }

        $hasCaption = filled($approvalToken->round->caption);

        $request->validate([
            'feedbacks'                => 'required|array|min:1',
            'feedbacks.*.attachment_id'=> 'required|uuid|exists:task_attachments,id',
            'feedbacks.*.status'       => 'required|in:approved,changes_requested',
            'feedbacks.*.comment'      => 'nullable|string|max:1000',
            'overall_comment'          => 'nullable|string|max:2000',
            'caption_status'           => [$hasCaption ? 'required' : 'nullable', 'in:approved,changes_requested'],
            'caption_comment'          => ['nullable', 'string', 'max:1000', 'required_if:caption_status,changes_requested'],
        ]);

        $this->service->submitFeedback(
            $approvalToken,
            $request->feedbacks,
            $request->overall_comment,
            $request->caption_status,
            $request->caption_comment,
        );

        $approvalToken->refresh();

        return view('approval.thanks', ['approvalToken' => $approvalToken->load('round.task')]);
    }
}
