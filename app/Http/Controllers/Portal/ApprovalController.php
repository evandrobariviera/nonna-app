<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
use App\Services\PortalMagicAccess;
use App\Services\ProjectApprovalPageService;
use Illuminate\Support\Collection;
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

        $items = $this->withUrls($pages->items($project));
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

    /**
     * Pra onde cada card leva. Na sessão do link de aprovação, se o contato
     * tem um link válido daquela rodada, vai pra página do link (a mesma que
     * ele já conhece, com a regra de todos os aprovadores). Senão, pra tela
     * da peça no Portal.
     */
    private function withUrls(Collection $items): Collection
    {
        $tokens = collect();

        if (app(PortalMagicAccess::class)->active()) {
            $tokens = TaskApprovalToken::where('contact_id', Auth::guard('portal')->id())
                ->whereIn('round_id', $items->pluck('round.id'))
                ->get()
                ->filter(fn ($t) => $t->isValid())
                ->keyBy('round_id');
        }

        return $items->map(function ($i) use ($tokens) {
            $token = $tokens->get($i['round']->id);
            $i['url'] = $token
                ? route('approval.show', $token->token)
                : route('portal.approvals.show', $i['round']);

            return $i;
        });
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
            'pieces'      => $this->withUrls($items),
            'summary'     => $pages->summary($items),
        ]);
    }

    public function show(TaskApprovalRound $round, ProjectApprovalPageService $pages)
    {
        $client = app('currentPortalClient');

        abort_if($round->task->client_id !== $client->id, 403);

        // Sessão do link de aprovação: só olha. Decidir é pela página do link
        // do próprio contato (token), que mantém a regra de todos os aprovadores.
        $readOnly = app(PortalMagicAccess::class)->active();
        if ($readOnly) {
            $token = TaskApprovalToken::where('round_id', $round->id)
                ->where('contact_id', Auth::guard('portal')->id())
                ->first();
            if ($token?->isValid()) {
                return redirect()->route('approval.show', $token->token);
            }
        }

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

        return view('portal.approvals.show', compact('client', 'round', 'deliverables', 'project', 'isLoose', 'readOnly'));
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
