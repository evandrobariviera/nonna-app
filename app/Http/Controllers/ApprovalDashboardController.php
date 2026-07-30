<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TaskApprovalRound;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;

class ApprovalDashboardController extends Controller
{
    public function index(Request $request)
    {
        [$rounds, $board] = $this->roundsAndBoard($request);

        $stats = [
            'awaiting_send'  => TaskApprovalRound::where('status', 'pending')->whereNull('sent_at')->count(),
            'pending'        => TaskApprovalRound::where('status', 'pending')->whereNotNull('sent_at')->count(),
            'changes'        => TaskApprovalRound::where('status', 'changes_requested')->count(),
            'approved_today' => TaskApprovalRound::where('status', 'approved')
                ->whereDate('resolved_at', today())->count(),
            'approved_total' => TaskApprovalRound::where('status', 'approved')->count(),
        ];

        $clientIds = TaskApprovalRound::whereHas('task')
            ->with('task:id,client_id')
            ->get()
            ->pluck('task.client_id')
            ->filter()
            ->unique();

        $clients = Client::whereIn('id', $clientIds)
            ->orderBy('company_name')
            ->get(['id', 'company_name']);

        return view('approvals.index', compact('rounds', 'stats', 'clients', 'board'));
    }

    // Fragmento (Quadros + Lista) — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira (cards de stats ficam intocados).
    public function results(Request $request)
    {
        [$rounds, $board] = $this->roundsAndBoard($request);

        return view('approvals._results', compact('rounds', 'board'));
    }

    private function roundsAndBoard(Request $request): array
    {
        $query = TaskApprovalRound::with(['task.client', 'tokens.contact', 'submittedBy'])
            ->whereHas('task')
            ->orderByDesc('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->whereHas('task', fn ($q) => $q->where('client_id', $request->client_id));
        }

        $rounds = $query->paginate(30)->withQueryString();

        $board = $this->buildBoard($request->input('client_id'));

        return [$rounds, $board];
    }

    /**
     * Monta as 4 colunas do quadro (view "Board" da Central de Aprovações) —
     * só visualização, sem drag-and-drop, já que a mudança de coluna reflete
     * uma ação real (botão Enviar, resposta do cliente), não uma decisão
     * manual da equipe arrastando o card.
     *
     * @return array<string, \Illuminate\Support\Collection<int, TaskApprovalRound>>
     */
    private function buildBoard(?string $clientId): array
    {
        $base = function () use ($clientId) {
            $q = TaskApprovalRound::with(['task.client', 'tokens'])->whereHas('task');
            if ($clientId) {
                $q->whereHas('task', fn ($t) => $t->where('client_id', $clientId));
            }
            return $q;
        };

        return [
            'awaiting_send'     => $base()->where('status', 'pending')->whereNull('sent_at')
                ->orderByDesc('submitted_at')->limit(20)->get(),
            'pending'           => $base()->where('status', 'pending')->whereNotNull('sent_at')
                ->orderByDesc('submitted_at')->limit(20)->get(),
            'changes_requested' => $base()->where('status', 'changes_requested')
                ->orderByDesc('resolved_at')->limit(20)->get(),
            'approved'          => $base()->where('status', 'approved')
                ->orderByDesc('resolved_at')->limit(20)->get(),
        ];
    }

    public function send(TaskApprovalRound $round, TaskApprovalService $service)
    {
        if ($round->sent_at) {
            return back()->with('warning', 'Essa rodada já foi enviada ao cliente.');
        }

        if ($round->tokens()->count() === 0) {
            return back()->with('warning', 'Nenhum contato do cliente está marcado para receber aprovações — configure isso na ficha do cliente antes de enviar.');
        }

        $service->sendToClient($round);

        return back()->with('success', 'Enviado! Os contatos foram notificados.');
    }
}
