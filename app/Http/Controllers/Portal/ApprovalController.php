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

    // Central de Aprovações: um card por projeto (+ "Peças avulsas"), abas
    // Em andamento / Concluídos. Central → Projeto → Peça.
    public function index(ProjectApprovalPageService $pages): View
    {
        $client = app('currentPortalClient');

        $groups       = $pages->central($client);
        $openGroups   = $groups->reject(fn ($g) => $g['finished'])->values();
        $doneGroups   = $groups->filter(fn ($g) => $g['finished'])->values();
        $totalPending = $groups->sum(fn ($g) => $g['summary']['pending']);
        $groupsWithPending = $groups->filter(fn ($g) => $g['summary']['pending'] > 0)->count();

        return view('portal.approvals.index', compact(
            'client', 'openGroups', 'doneGroups', 'totalPending', 'groupsWithPending'
        ));
    }

    // Página do Projeto: o projeto inteiro (destaque + cards de tudo que passou
    // por aprovação). Cada card leva pra aprovação da própria tarefa (show()).
    public function project(Project $project, ProjectApprovalPageService $pages): View
    {
        $client = app('currentPortalClient');

        abort_if($project->resolvedClientId() !== $client->id, 403);

        $items = $pages->items($project);
        abort_if($items->isEmpty(), 404);

        return view('portal.approvals.project', [
            'client'      => $client,
            'pageTitle'   => $project->title,
            'pageType'    => $project->typeLabel(),
            'description' => $project->client_description,
            'highlight'   => $items->firstWhere('is_highlight', true),
            'pieces'      => $items->reject(fn ($i) => $i['is_highlight'])->values(),
            'summary'     => $pages->summary($items),
        ]);
    }

    // Mesma página do projeto, pras tarefas sem projeto (posts soltos, chamados).
    public function loose(ProjectApprovalPageService $pages): View
    {
        $client = app('currentPortalClient');

        $items = $pages->looseItems($client);
        abort_if($items->isEmpty(), 404);

        return view('portal.approvals.project', [
            'client'      => $client,
            'pageTitle'   => 'Peças avulsas',
            'pageType'    => 'Avulsas',
            'description' => 'Materiais que não fazem parte de um projeto.',
            'highlight'   => null,
            'pieces'      => $items,
            'summary'     => $pages->summary($items),
        ]);
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
        // Sem projeto → a trilha leva pra "Peças avulsas" na Central.
        $isLoose = !$round->task->project_id;

        // Quem mais precisa aprovar/já aprovou nesta rodada + histórico de rodadas
        // anteriores da mesma tarefa (com o que foi pedido de ajuste em cada uma).
        $round->load([
            'tokens.contact',
            'task.approvalRounds.tokens.contact',
            'task.approvalRounds.tokens.feedbacks.attachment',
        ]);

        $deliverables = $round->deliverables();

        return view('portal.approvals.show', compact('client', 'round', 'deliverables', 'project', 'isLoose'));
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
