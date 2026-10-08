<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskApprovalRound;
use App\Services\ProjectApprovalPageService;
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

    // Página do Projeto: o projeto inteiro (destaque + cards de tudo que passou
    // por aprovação). Cada card leva pra aprovação da própria tarefa (show()).
    public function project(Project $project, ProjectApprovalPageService $pages): View
    {
        $client = app('currentPortalClient');

        abort_if($project->resolvedClientId() !== $client->id, 403);

        $items = $pages->items($project);
        abort_if($items->isEmpty(), 404);

        $highlight = $items->firstWhere('is_highlight', true);
        $pieces    = $items->reject(fn ($i) => $i['is_highlight'])->values();
        $summary   = $pages->summary($items);

        return view('portal.approvals.project', compact('client', 'project', 'highlight', 'pieces', 'summary'));
    }

    public function show(TaskApprovalRound $round, ProjectApprovalPageService $pages): View
    {
        $client = app('currentPortalClient');

        abort_if($round->task->client_id !== $client->id, 403);

        // "Ver projeto completo" só quando a tarefa é de um projeto com página
        // (pelo menos uma tarefa dele já passou por aprovação — essa, no mínimo).
        $project = $round->task->project;
        if ($project && $pages->items($project)->isEmpty()) {
            $project = null;
        }

        // Quem mais precisa aprovar/já aprovou nesta rodada + histórico de rodadas
        // anteriores da mesma tarefa (com o que foi pedido de ajuste em cada uma).
        $round->load([
            'tokens.contact',
            'task.approvalRounds.tokens.contact',
            'task.approvalRounds.tokens.feedbacks.attachment',
        ]);

        $deliverables = $round->deliverables();

        return view('portal.approvals.show', compact('client', 'round', 'deliverables', 'project'));
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
