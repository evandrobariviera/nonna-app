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

        $data = $request->validate([
            'decision' => 'required|in:approved,changes_requested',
            'comment'  => ['nullable', 'string', 'max:2000', 'required_if:decision,changes_requested'],
        ]);

        $this->service->submitDecision($approvalToken, $data['decision'], $data['comment'] ?? null);

        $approvalToken->refresh();

        return view('approval.thanks', ['approvalToken' => $approvalToken->load('round.task')]);
    }
}
