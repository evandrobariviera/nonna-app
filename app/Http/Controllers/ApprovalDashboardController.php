<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
use App\Services\TaskApprovalService;
use App\Services\TaskDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalDashboardController extends Controller
{
    public function index(Request $request)
    {
        // "Por cliente" é a aba padrão — Quadros e Lista só são montados quando
        // a pessoa abre uma delas (a view busca o fragmento em approvals.results).
        // Link com filtro de Lista (ex: "Ver todas as aprovações" do Dashboard,
        // ?status=pending) continua abrindo direto na Lista.
        $activeTab = $request->input('view', $request->hasAny(['status', 'client_id', 'type', 'mostrar_aprovados']) ? 'list' : 'clientes');
        [$rounds, $board] = $activeTab === 'clientes' ? [null, null] : $this->roundsAndBoard($request);

        // Os 5 números do topo numa consulta só (antes: 5 counts, sem filtrar a organização).
        $s = DB::table('task_approval_rounds as r')
            ->join('tasks as t', 't.id', '=', 'r.task_id')
            ->where('t.organization_id', app('currentOrganization')->id)
            ->selectRaw("
                count(*) filter (where r.status = 'pending' and r.sent_at is null)              as awaiting_send,
                count(*) filter (where r.status = 'pending' and r.sent_at is not null)          as pending,
                count(*) filter (where r.status = 'changes_requested' and r.handled_at is null) as changes,
                count(*) filter (where r.status = 'approved' and r.resolved_at::date = ?)       as approved_today,
                count(*) filter (where r.status = 'approved')                                    as approved_total
            ", [today()->toDateString()])
            ->first();
        $stats = array_map('intval', (array) $s);

        // Clientes do filtro: só os ids distintos (antes carregava todas as rodadas já feitas).
        $clientIds = Task::query()
            ->join('task_approval_rounds as r', 'r.task_id', '=', 'tasks.id')
            ->whereNotNull('tasks.client_id')
            ->distinct()
            ->pluck('tasks.client_id');

        // nickname carregado (senão displayName() cai na razão social) e ordenação
        // pelo mesmo texto que aparece na opção — sem isso, um cliente cujo apelido
        // difere muito da razão social aparece sob um nome que a equipe não
        // reconhece e numa posição alfabética inesperada ("parece que sumiu").
        $clients = Client::whereIn('id', $clientIds)
            ->get(['id', 'company_name', 'nickname'])
            ->sortBy(fn ($c) => mb_strtolower($c->displayName()), SORT_NATURAL)
            ->values();

        // Aba "Por cliente": o recorte que o cliente vê na Central dele.
        $byClient = app(\App\Services\ApprovalOverviewService::class)->byClient();

        return view('approvals.index', compact('rounds', 'stats', 'clients', 'board', 'byClient', 'activeTab'));
    }

    // Lembrete único (aba "Por cliente"): uma mensagem por contato com tudo que
    // espera a resposta dele + link da Central — em vez de reenviar peça por peça.
    public function remindClient(Client $client, \App\Services\ApprovalReminderService $reminders)
    {
        $back = redirect()->route('approvals.index', ['view' => 'clientes']);

        // Sem modelo cadastrado o disparo não manda nada — avisa em vez de fingir que enviou.
        $hasTemplate = \App\Models\NotificationTemplate::where('organization_id', $client->organization_id)
            ->where('type', 'aprovacao_lembrete')->exists();
        if (!$hasTemplate) {
            return $back->with('warning', 'Lembrete não enviado: cadastre a mensagem "Lembrete — Peças esperando na Central de Aprovações" em Mensagens Padrão.');
        }

        $count = $reminders->remindClient($client);

        return $back->with(
            $count ? 'success' : 'warning',
            match (true) {
                $count === 0 => 'Nenhum contato de ' . $client->displayName() . ' tem peça esperando resposta.',
                default      => "Lembrete enviado pra {$count} " . ($count === 1 ? 'contato' : 'contatos') . ' de ' . $client->displayName() . '.',
            }
        );
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
            // Cancelada some sempre — cancelar já é a ação definitiva, não precisa
            // de "tratar" depois (não fica visível nem filtrando por
            // status=cancelled de propósito; histórico continua na tarefa/portal).
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Ajuste solicitado já significa que a tarefa voltou pra produção —
            // some da Lista assim que o cliente pede, sem esperar handled_at
            // (esse continua valendo só pro Quadro e pro badge/sino, que rastreiam
            // "ainda precisa rotear de volta pra Sprint" separado disso). Só
            // aparece de novo aqui se o usuário filtrar por status explicitamente.
            $query->where('status', '!=', 'changes_requested');
        }

        if ($request->filled('client_id')) {
            $query->whereHas('task', fn ($q) => $q->where('client_id', $request->client_id));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Aprovado some da Lista por padrão — igual "mostrar_concluidos" em Tarefas
        // (TaskController::filteredTasks()) — a menos que o usuário já tenha filtrado
        // por um status específico, aí o filtro manda.
        if (!$request->boolean('mostrar_aprovados') && !$request->filled('status')) {
            $query->where('status', '!=', 'approved');
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

    // Envia o Aviso (rodada sem entregável) com a mensagem escrita na hora —
    // diferente de send(), resolve a rodada na hora (não espera decisão do
    // cliente, ver TaskApprovalService::sendAviso()).
    public function sendAviso(Request $request, TaskApprovalRound $round, TaskApprovalService $service)
    {
        if (!$round->isAviso() || $round->status !== 'pending') {
            return back()->with('warning', 'Essa rodada não é um aviso pendente.');
        }

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $service->sendAviso($round, $data['message']);

        return back()->with('success', 'Aviso enviado ao cliente.');
    }

    // Tira o Aviso da fila sem notificar ninguém — pra quando o retorno já foi
    // combinado por outro canal ou simplesmente não precisa avisar o cliente.
    public function resolveAviso(TaskApprovalRound $round, TaskApprovalService $service)
    {
        if (!$round->isAviso() || $round->status !== 'pending') {
            return back()->with('warning', 'Essa rodada não é um aviso pendente.');
        }

        $service->resolveAvisoWithoutNotifying($round);

        return back()->with('success', 'Marcado como resolvido, sem notificar o cliente.');
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

    // Marca manualmente a decisão de um aprovador pendente (em nome dele) —
    // form tradicional, chamado da tela da tarefa. Erro vira ValidationException
    // pra aparecer na caixa global de $errors (layouts/app.blade.php).
    public function manualDecision(Request $request, TaskApprovalToken $token, TaskApprovalService $service)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,changes_requested',
        ]);

        try {
            $service->manuallyDecideToken($token, $data['decision'], $request->user());
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Decisão registrada manualmente.');
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

        TaskDeliveryService::guard($request, $round->task, $data['status'] ?? null);

        $round->task->update($data);

        if ($round->status === 'changes_requested') {
            $round->update(['handled_at' => now()]);
        }

        return back()->with('success', 'Status da tarefa atualizado.');
    }
}
