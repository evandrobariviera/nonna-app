<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\TaskApprovalRound;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private TaskApprovalService $service) {}

    public function index(): View
    {
        $client = app('currentPortalClient');

        $rounds = TaskApprovalRound::whereHas('task', fn ($q) => $q->where('client_id', $client->id))
            ->with('task')
            ->orderByDesc('submitted_at')
            ->get();

        $pending = $rounds->where('status', 'pending');
        $decided = $rounds->whereIn('status', ['approved', 'changes_requested', 'cancelled']);

        return view('portal.approvals.index', compact('client', 'pending', 'decided'));
    }

    public function show(TaskApprovalRound $round): View
    {
        $client = app('currentPortalClient');

        abort_if($round->task->client_id !== $client->id, 403);

        $deliverables = $round->deliverables();

        return view('portal.approvals.show', compact('client', 'round', 'deliverables'));
    }

    public function decide(Request $request, TaskApprovalRound $round)
    {
        $client = app('currentPortalClient');

        abort_if($round->task->client_id !== $client->id, 403);

        $data = $request->validate([
            'decision' => 'required|in:approved,changes_requested',
            'comment'  => 'nullable|string|max:2000|required_if:decision,changes_requested',
        ]);

        $applied = $this->service->submitPortalDecision(
            $round,
            Auth::guard('portal')->user(),
            $data['decision'],
            $data['comment'] ?? null,
        );

        return redirect()->route('portal.approvals.show', $round)
            ->with('success', $applied ? 'Decisão registrada com sucesso.' : 'Essa rodada já tinha sido decidida.');
    }
}
