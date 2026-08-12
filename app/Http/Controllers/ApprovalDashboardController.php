<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
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
            'changes'        => TaskApprovalRound::where('status', 'changes_requested')->whereNull('handled_at')->count(),
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
            // Rodada com ajuste pedido some daqui assim que a agência "trata" ela
            // (roteia a tarefa de volta via o quick-select de status/situação) —
            // continua intacta no histórico da tarefa/portal, só não polui mais
            // a Central de Aprovações. Cancelada some sempre — cancelar já é a
            // ação definitiva, não precisa de "tratar" depois (não fica visível
            // nem filtrando por status=cancelled de propósito; histórico continua
            // na tarefa/portal).
            ->where(fn ($q) => $q->where('status', '!=', 'changes_requested')->orWhereNull('handled_at'))
            ->where('status', '!=', 'cancelled')
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
            // Ver comentário em roundsAndBoard() sobre handled_at.
            'changes_requested' => $base()->where('status', 'changes_requested')->whereNull('handled_at')
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

        if ($round->tokens()->where('will_notify', true)->count() === 0) {
            return back()->with('warning', 'Todos os contatos estão desligados nessa rodada — ligue pelo menos um antes de enviar.');
        }

        $service->sendToClient($round);

        return back()->with('success', 'Enviado! Os contatos ligados foram notificados.');
    }

    // Liga/desliga um contato específico pra essa rodada — AJAX (inlinePatch),
    // só permitido antes do envio. Ver TaskApprovalService::toggleNotify().
    public function toggleNotify(Request $request, TaskApprovalToken $token, TaskApprovalService $service)
    {
        $data = $request->validate(['will_notify' => ['required', 'boolean']]);

        try {
            $service->toggleNotify($token, $data['will_notify']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    // Reenvia só pra quem ainda não respondeu (ex: e-mail estava errado, foi
    // corrigido, precisa notificar de novo) — não mexe em quem já aprovou ou
    // pediu ajuste.
    public function resend(TaskApprovalRound $round, TaskApprovalService $service)
    {
        if (!$round->sent_at) {
            return back()->with('warning', 'Essa rodada ainda não foi enviada — use "Enviar pro Cliente" primeiro.');
        }

        if ($round->status !== 'pending') {
            return back()->with('warning', 'Essa rodada já foi resolvida — não há mais ninguém aguardando resposta.');
        }

        $count = $service->resendPending($round);

        if ($count === 0) {
            return back()->with('warning', 'Não há ninguém pendente nessa rodada pra reenviar.');
        }

        return back()->with('success', "Reenviado pra {$count} aprovador(es) que ainda não responderam.");
    }

    // Reenvia só pra um aprovador específico — caso mais cirúrgico (só o
    // e-mail dele estava errado, os outros já receberam certo).
    public function resendToken(TaskApprovalToken $token, TaskApprovalService $service)
    {
        if (!$token->isPending()) {
            return back()->with('warning', 'Esse aprovador já respondeu — não há o que reenviar.');
        }

        $service->resendToken($token);

        return back()->with('success', 'Reenviado.');
    }

    // Cancela a rodada — criada errada, cliente errado, motivo interno qualquer.
    // Fecha o link público/Portal pra quem ainda não respondeu; quem já tinha
    // respondido mantém o registro (histórico não muda).
    public function cancel(TaskApprovalRound $round, TaskApprovalService $service)
    {
        if ($round->status === 'cancelled') {
            return back()->with('warning', 'Essa rodada já estava cancelada.');
        }

        $service->cancelRound($round);

        return back()->with('success', 'Rodada cancelada.');
    }

    // Quick fill-cell de Status/Situação direto na Central de Aprovações — mesmo
    // padrão usado nas tabelas de tarefas (Filas/Tickets) — pra depois de um
    // "Ajustes Solicitados" já rotear a tarefa (Sprint) sem precisar abrir a
    // tarefa. Cada dropdown submete só o campo dele (status OU situação); "sometimes"
    // faz o outro ser ignorado em vez de exigido. Rodada com ajuste pedido sai da
    // Central assim que isso é usado nela (handled_at) — continua intacta no
    // histórico, só some daqui.
    public function updateTaskStatus(Request $request, TaskApprovalRound $round)
    {
        $situationKeys = array_keys(array_filter(Task::$situations, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY));

        $data = $request->validate([
            'status'    => ['sometimes', 'required', 'in:' . implode(',', array_keys(Task::$statuses))],
            'situation' => ['sometimes', 'nullable', 'in:' . implode(',', $situationKeys)],
        ]);

        if (empty($data)) {
            return back()->with('warning', 'Nada pra atualizar.');
        }

        $round->task->update($data);

        if ($round->status === 'changes_requested') {
            $round->update(['handled_at' => now()]);
        }

        return back()->with('success', 'Status da tarefa atualizado.');
    }
}
