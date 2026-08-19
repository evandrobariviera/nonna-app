<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiChat;
use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskExecutor;
use App\Models\TaskStatusTransition;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\TaskExecutorSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks    = $this->filteredTasks($request);
        $clients  = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $sprints  = Sprint::orderByDesc('starts_at')->get(['id', 'title', 'status']);
        $projects = $this->visibleProjects($request);

        return view('tasks.index', compact('tasks', 'clients', 'users', 'sprints', 'projects'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário digita/filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $tasks    = $this->filteredTasks($request);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $sprints  = Sprint::orderByDesc('starts_at')->get(['id', 'title', 'status']);
        $projects = $this->visibleProjects($request);

        return view('tasks._results', compact('tasks', 'users', 'sprints', 'projects'));
    }

    // Busca global (topbar) — cobre tarefas E tickets, sem filtro de status/sprint,
    // exatamente pra achar itens antigos/concluídos ou de sprints já fechadas que
    // as listagens operacionais escondem por padrão.
    public function searchGlobal(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $tasks = Task::with(['client:id,company_name,nickname', 'sprint:id,title'])
            ->where(fn ($q) => $q->where('title', 'ilike', "%{$term}%")
                ->orWhere('clickup_task_id', 'ilike', "%{$term}%"))
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return response()->json($tasks->map(fn (Task $t) => [
            'id'           => $t->id,
            'title'        => $t->title,
            'is_ticket'    => $t->is_ticket,
            'client'       => $t->client?->displayName(),
            'status_label' => $t->statusLabel(),
            'status_color' => $t->statusColor(),
            'sprint_label' => $t->sprint?->title,
            'url'          => route('tasks.show', $t),
        ]));
    }

    // Projetos pro dropdown "Vincular a projeto" da barra de ações em massa —
    // só ativos, só de cliente ativo, e restrito ao cliente filtrado no momento
    // (evita listar dezenas de projetos de outros clientes misturados, e some
    // com as entradas "fantasma" de clientes duplicados/inativos).
    private function visibleProjects(Request $request)
    {
        return Project::with('client:id,company_name,nickname')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->client_id))
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);
    }

    private function filteredTasks(Request $request)
    {
        $query = Task::with(['client', 'executor', 'executors', 'project.macroPlan', 'sprint'])
            ->where('is_ticket', false)
            ->orderByRaw("CASE status
                WHEN 'em_producao'           THEN 1
                WHEN 'revisao_interna'       THEN 2
                WHEN 'ajuste_alteracao'      THEN 3
                WHEN 'aprovacao'             THEN 4
                WHEN 'despacho_agendamento'  THEN 5
                WHEN 'backlog'               THEN 6
                WHEN 'concluido'             THEN 7
                WHEN 'cancelado'             THEN 8
                ELSE 9 END")
            ->orderBy('due_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('executor_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('executor_id', $request->executor_id)
                  ->orWhereHas('executors', fn($q2) => $q2->where('users.id', $request->executor_id));
            });
        }
        if ($request->filled('sprint_id')) {
            $query->where('sprint_id', $request->sprint_id);
        }
        if ($request->boolean('sem_sprint')) {
            $query->whereNull('sprint_id');
        }
        if ($request->boolean('sem_projeto')) {
            $query->whereNull('project_id');
        }
        if ($request->filled('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        // Oculta status finais por padrão — a menos que o usuário filtre por status específico ou ative o toggle
        if (!$request->boolean('mostrar_concluidos') && !$request->filled('status')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        return $query->paginate(40)->withQueryString();
    }

    public function show(Task $task)
    {
        $task->load([
            'client.links',
            'project.macroPlan.client',
            'project.macroPlan.attachments',
            'macroPlan',
            'sprint',
            'executor',
            'executors',
            'responsibles',
            'observers',
            'attachments.uploadedBy',
            'attachments.uploadedByContact',
            'comments.user',
            'createdBy',
            'activities.user',
            'latestApprovalRound.tokens.contact',
            'approvalRounds.tokens.contact',
            'checklistItems.assignedTo',
        ]);

        // avatar_path/avatar_disk também — o dropdown "Item de ação" (assinar comentário
        // a alguém) mostra a foto de quem pode ser escolhido, mesmo padrão de SprintController.
        $users  = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);
        $agents = AiAgent::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Projetos do mesmo cliente da tarefa, pra permitir reatribuir direto
        // desta página (ver TaskController::updateProject()).
        $clientProjects = $task->client_id
            ? Project::where('client_id', $task->client_id)->orderBy('title')->get(['id', 'title'])
            : collect();

        $clients = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);

        // Pro botão Fila ↔ Sprint do cabeçalho: se a tarefa não estiver em
        // nenhuma sprint, "→ Sprint" manda pra essa (só existe uma ativa por vez).
        $activeSprint = Sprint::where('status', 'active')->first();

        // Pro seletor "mudar de sprint" do campo Sprint — mesmo recorte (aberta pra
        // receber tarefa) da Fila/Ticket (ver TicketController). Se a sprint atual da
        // tarefa já estiver encerrada, inclui ela também — senão o <select> perderia o
        // valor selecionado (ela continua sendo o estado real até alguém trocar).
        $sprints = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get(['id', 'title', 'status']);
        if ($task->sprint && !$sprints->contains('id', $task->sprint_id)) {
            $sprints->push($task->sprint);
        }

        $chat = AiChat::where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->first();

        $chatMessages = $chat
            ? $chat->messages()
                ->with('user:id,name', 'agent:id,name')
                ->orderBy('created_at')
                ->get()
                ->map(fn($m) => [
                    'id'         => $m->id,
                    'role'       => $m->role,
                    'content'    => $m->content,
                    'user_name'  => $m->user?->name,
                    'agent_name' => $m->agent?->name,
                    'time'       => $m->created_at->format('H:i'),
                ])
                ->values()
            : collect();

        return view('tasks.show', compact('task', 'users', 'agents', 'chatMessages', 'clientProjects', 'clients', 'activeSprint', 'sprints'));
    }

    public function updateInline(Request $request, Task $task)
    {
        $situationKeys = array_keys(array_filter(Task::$situations, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY));

        $data = $request->validate([
            'title'              => 'required|string|max:300',
            'description'        => 'nullable|string',
            'task_type'          => 'required|in:' . implode(',', array_keys(Task::$types)),
            'destination'        => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'situation'          => 'nullable|in:' . implode(',', $situationKeys),
            'status'             => 'required|in:' . implode(',', array_keys(Task::$statuses)),
            'origin'             => 'nullable|in:' . implode(',', array_keys(Task::$origins)),
            'priority'           => 'nullable|in:urgente,medio,normal',
            'approval_method'    => 'nullable|in:' . implode(',', array_keys(Task::$approvalMethods)),
            'internal_approval'  => 'nullable|boolean',
            'due_date'           => 'nullable|date',
            'approval_date'      => 'nullable|date',
            'publish_date'       => 'nullable|date',
            'executor_id'        => 'nullable|exists:users,id',
            'responsavel_id'     => 'nullable|exists:users,id',
            'observer_ids'       => 'nullable|array',
            'observer_ids.*'     => 'exists:users,id',
            'requester_name'     => 'nullable|string|max:150',
            'requester_whatsapp' => 'nullable|string|max:30',
            'requester_channel'  => 'nullable|in:' . implode(',', array_keys(Task::$requesterChannels)),
            'sprint_id'          => 'nullable|uuid|exists:sprints,id',
        ]);

        if ($reason = $this->wipBlockReasonForSave($task, $data['status'], $data['executor_id'] ?? null, $request->has('executor_id'))) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $task->update([
            ...$data,
            'internal_approval' => $request->boolean('internal_approval'),
        ]);

        if ($request->hasAny(['executor_id', 'responsavel_id', 'observer_ids'])) {
            $this->syncPersonnel($task, $data);
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Tarefa atualizada.');
    }

    public function store(Request $request, MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);

        $data = $request->validate($this->storeRules());

        if (($data['status'] ?? 'backlog') === 'em_producao') {
            if ($reason = Task::wipBlockReason($this->firstExecutorId($data))) {
                throw ValidationException::withMessages(['status' => $reason]);
            }
        }

        $task = $project->tasks()->create([
            ...$data,
            'macro_plan_id'     => $macroplan->id,
            'client_id'         => $project->client_id,
            'status'            => $data['status'] ?? 'backlog',
            'origin'            => $data['origin'] ?? 'projeto',
            'internal_approval' => $request->boolean('internal_approval'),
            'created_by'        => Auth::id(),
        ]);

        TaskExecutorSync::sync($task, $data);
        $this->storeAttachments($task, $request);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa criada.');
    }

    public function storeStandalone(Request $request, Project $project)
    {
        $data = $request->validate($this->storeRules());

        if (($data['status'] ?? 'backlog') === 'em_producao') {
            if ($reason = Task::wipBlockReason($this->firstExecutorId($data))) {
                throw ValidationException::withMessages(['status' => $reason]);
            }
        }

        $task = $project->tasks()->create([
            ...$data,
            'macro_plan_id'     => $project->macro_plan_id,
            'client_id'         => $project->client_id,
            'status'            => $data['status'] ?? 'backlog',
            'origin'            => $data['origin'] ?? 'projeto',
            'internal_approval' => $request->boolean('internal_approval'),
            'created_by'        => Auth::id(),
        ]);

        TaskExecutorSync::sync($task, $data);
        $this->storeAttachments($task, $request);

        return redirect()->route('projects.showDirect', $project)
            ->with('success', 'Tarefa criada.');
    }

    public function update(Request $request, MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $data = $request->validate($this->updateRules());

        if ($reason = $this->wipBlockReasonForSave($task, $data['status'], $this->firstExecutorId($data))) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $task->update([...$data, 'internal_approval' => $request->boolean('internal_approval')]);
        TaskExecutorSync::sync($task, $data);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa atualizada.');
    }

    public function updateStandalone(Request $request, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $data = $request->validate($this->updateRules());

        if ($reason = $this->wipBlockReasonForSave($task, $data['status'], $this->firstExecutorId($data))) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $task->update([...$data, 'internal_approval' => $request->boolean('internal_approval')]);
        TaskExecutorSync::sync($task, $data);

        return redirect()->route('projects.showDirect', $project)
            ->with('success', 'Tarefa atualizada.');
    }

    // Chamado sai da tela de Tickets (triagem) e passa a aparecer na Fila. Só faz
    // sentido pra chamado avulso — tarefa de projeto já nasce direto na Fila.
    public function sendToFila(Task $task)
    {
        abort_unless($task->is_ticket, 403);

        $task->update(['queued_at' => now()]);

        return redirect()->back()->with('success', 'Chamado enviado para a Fila.');
    }

    public function updatePriority(Request $request, Task $task)
    {
        $task->update(['priority' => $request->validate([
            'priority' => 'required|in:urgente,medio,normal',
        ])['priority']]);

        return redirect()->back()->with('success', 'Prioridade atualizada.');
    }

    public function updateSituation(Request $request, Task $task)
    {
        $situationKeys = array_keys(array_filter(Task::$situations, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY));

        $task->update(['situation' => $request->validate([
            'situation' => 'nullable|in:' . implode(',', $situationKeys),
        ])['situation']]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Situação atualizada.');
    }

    public function updateCaption(Request $request, Task $task)
    {
        $task->update(['caption' => $request->validate([
            'caption' => 'nullable|string',
        ])['caption']]);

        return redirect()->back()->with('success', 'Legenda atualizada.');
    }

    public function updateResponsavel(Request $request, Task $task)
    {
        $data = $request->validate(['responsavel_id' => 'nullable|exists:users,id']);

        // Guarda quem já era responsável antes de mexer, pra só disparar a automação de
        // "Responsável/Executor adicionado" quando for alguém genuinely novo — sem isso,
        // re-salvar o mesmo responsável pareceria uma adição nova toda vez. Mesmo registro
        // já serve pro rótulo "de" do TaskActivity (não passa pelo TaskObserver — isso é
        // pivot de task_executors, não coluna de tasks).
        $oldUser = TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->first()?->user;
        $wasAlready = $data['responsavel_id'] && $oldUser?->id === $data['responsavel_id'];

        TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->delete();

        if ($data['responsavel_id']) {
            // A mesma pessoa pode ser executor E responsável na mesma tarefa (task_executors
            // permite 1 linha por (task_id, user_id, role) — não mexe na linha de executor
            // dessa pessoa, só na de responsável).
            $task->executors()->attach($data['responsavel_id'], ['role' => 'responsavel']);

            if (!$wasAlready) {
                AutomationEngine::evaluate('executor_added', $task, ['role' => 'responsavel', 'user_id' => $data['responsavel_id']]);
            }
        }

        if (($oldUser?->id) !== ($data['responsavel_id'] ?? null)) {
            $newUser = $data['responsavel_id'] ? User::find($data['responsavel_id']) : null;
            TaskActivity::log($task, 'responsavel_changed', $oldUser?->name, $newUser?->name);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Responsável atualizado.');
    }

    public function updateExecutorDirect(Request $request, Task $task)
    {
        $data = $request->validate(['executor_id' => 'nullable|exists:users,id']);

        $oldUser = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->first()?->user;
        $wasAlready = $data['executor_id'] && $oldUser?->id === $data['executor_id'];

        if ($task->status === 'em_producao' && $data['executor_id'] && !$wasAlready) {
            if ($reason = Task::wipBlockReason($data['executor_id'], $task->id)) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => $reason], 422);
                }
                throw ValidationException::withMessages(['executor_id' => $reason]);
            }
        }

        TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->delete();

        if ($data['executor_id']) {
            // Mesma pessoa pode já ser responsável — não mexe na linha dela, só na de executor.
            $task->executors()->attach($data['executor_id'], ['role' => 'executor']);
            $task->updateQuietly(['executor_id' => $data['executor_id']]);

            if (!$wasAlready) {
                AutomationEngine::evaluate('executor_added', $task, ['role' => 'executor', 'user_id' => $data['executor_id']]);
            }
        } else {
            $task->updateQuietly(['executor_id' => null]);
        }

        if (($oldUser?->id) !== ($data['executor_id'] ?? null)) {
            $newUser = $data['executor_id'] ? User::find($data['executor_id']) : null;
            TaskActivity::log($task, 'executor_changed', $oldUser?->name, $newUser?->name);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Executor atualizado.');
    }

    // Reatribui a tarefa a um projeto direto da própria página da tarefa —
    // antes só dava pra mover em lote (bulkUpdate) ou criar a tarefa já
    // dentro de um projeto. Mesmo cuidado do bulkUpdate: só permite projeto
    // do mesmo cliente da tarefa.
    public function updateProject(Request $request, Task $task)
    {
        $data = $request->validate(['project_id' => 'nullable|uuid|exists:pgsql.projects,id']);

        if ($data['project_id']) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless((string) $task->client_id === (string) $project->client_id, 422, 'Esse projeto é de outro cliente.');
        }

        $task->update(['project_id' => $data['project_id']]);

        return redirect()->back()->with('success', 'Projeto atualizado.');
    }

    // Troca/atribui/remove a Sprint direto da própria tarefa — mesmas regras de negócio
    // de SprintController::addTask/removeTask (pendência bloqueia entrada, sprint travada
    // bloqueia saída, sprint encerrada não recebe tarefa nova), só que fica na tarefa
    // (redirect back) em vez de pular pra página da Sprint.
    public function updateSprint(Request $request, Task $task)
    {
        $data = $request->validate(['sprint_id' => 'nullable|uuid|exists:pgsql.sprints,id']);

        if ($data['sprint_id'] === $task->sprint_id) {
            return redirect()->back();
        }

        if ($task->sprint_id && $task->sprint?->isLocked()) {
            abort(403, 'Sprint atual travada. Desbloqueie antes de mover essa tarefa.');
        }

        if ($data['sprint_id']) {
            $sprint = Sprint::findOrFail($data['sprint_id']);
            abort_if($sprint->status === 'closed', 403, 'Essa sprint está encerrada.');

            if ($task->isPendente()) {
                throw ValidationException::withMessages([
                    'pendencia' => 'Essa tarefa tem pendências de cadastro e não pode ser adicionada à sprint. Corrija-a antes.',
                ]);
            }
        }

        $task->update(['sprint_id' => $data['sprint_id']]);

        return redirect()->back()->with('success', $data['sprint_id'] ? 'Tarefa movida para a sprint.' : 'Tarefa devolvida para a Fila.');
    }

    // Troca o cliente da tarefa. Bloqueia se houver rodada de aprovação pendente
    // (os tokens/contatos da rodada são do cliente atual — trocar no meio quebraria
    // o vínculo). Se a tarefa estiver vinculada a um projeto, desvincula (projeto
    // pertence a um único cliente — não dá pra manter o vínculo com o cliente novo).
    public function updateClient(Request $request, Task $task)
    {
        $data = $request->validate(['client_id' => 'required|uuid|exists:pgsql.clients,id']);

        if ($data['client_id'] === $task->client_id) {
            return redirect()->back();
        }

        abort_if($task->approvalRounds()->where('status', 'pending')->exists(), 422, 'Essa tarefa tem uma rodada de aprovação pendente — resolva ou cancele antes de trocar o cliente.');

        $task->update([
            'client_id'  => $data['client_id'],
            'project_id' => null,
        ]);

        return redirect()->back()->with('success', 'Cliente atualizado.');
    }

    public function updateStatus(Request $request, MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $newStatus = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ])['status'];

        if ($reason = $this->wipBlockReasonForTransition($task, $newStatus)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $reason], 422);
            }
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $task->update(['status' => $newStatus]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function updateStatusStandalone(Request $request, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $newStatus = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ])['status'];

        if ($reason = $this->wipBlockReasonForTransition($task, $newStatus)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $reason], 422);
            }
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $task->update(['status' => $newStatus]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function updateStatusDirect(Request $request, Task $task)
    {
        // situation é opcional — usado pelo Kanban do Dashboard, cuja coluna "Pronto para
        // Produção" não é um status próprio (é status=backlog + essa situação específica).
        $situationKeys = array_keys(array_filter(Task::$situations, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY));
        $data = $request->validate([
            'status'    => 'required|in:' . implode(',', array_keys(Task::$statuses)),
            'situation' => 'sometimes|in:' . implode(',', $situationKeys),
        ]);

        if ($reason = $this->wipBlockReasonForTransition($task, $data['status'])) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $reason], 422);
            }
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $task->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    // Trava de carga (Task::wipBlockReason()) — só entra em jogo quando o status está
    // MUDANDO pra em_producao (não em toda gravação onde o status já era esse).
    private function wipBlockReasonForTransition(Task $task, string $newStatus): ?string
    {
        if ($newStatus !== 'em_producao' || $task->status === 'em_producao') {
            return null;
        }

        $execId = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->value('user_id')
            ?? $task->executor_id;

        return Task::wipBlockReason($execId, $task->id);
    }

    // Variante pra formulários que reenviam status + executor juntos (updateInline,
    // update/updateStandalone) — dispara se o status está virando em_producao OU se
    // o executor está mudando numa tarefa que já está em_producao.
    private function wipBlockReasonForSave(Task $task, string $newStatus, ?string $newExecutorId, bool $executorSubmitted = true): ?string
    {
        $oldExecId = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->value('user_id')
            ?? $task->executor_id;

        $statusEnteringProduction        = $newStatus === 'em_producao' && $task->status !== 'em_producao';
        $executorChangingInProduction    = $executorSubmitted && $newStatus === 'em_producao' && $task->status === 'em_producao'
            && $newExecutorId !== $oldExecId;

        if (!$statusEnteringProduction && !$executorChangingInProduction) {
            return null;
        }

        return Task::wipBlockReason($newExecutorId ?? $oldExecId, $task->id);
    }

    // Resolve o id de quem vai ficar como 'executor' a partir de executor_ids[]/
    // executor_roles[] (formato usado por store/update via TaskExecutorSync::sync()) —
    // o mesmo "primeira pessoa com esse papel" que o dedupe de lá aplica.
    private function firstExecutorId(array $data): ?string
    {
        foreach (($data['executor_ids'] ?? []) as $userId) {
            if (($data['executor_roles'][$userId] ?? 'executor') === 'executor') {
                return $userId;
            }
        }

        return null;
    }

    public function bulkUpdate(Request $request)
    {
        $situationKeys = array_keys(array_filter(Task::$situations, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY));

        $data = $request->validate([
            'task_ids'    => 'required|array|min:1',
            'task_ids.*'  => 'uuid|exists:pgsql.tasks,id',
            'action'      => 'required|in:status,executor,responsavel,situation,project,sprint,delete',
            'status'      => 'required_if:action,status|in:' . implode(',', array_keys(Task::$statuses)),
            'executor_id'     => 'nullable|exists:pgsql.users,id',
            'responsavel_id'  => 'nullable|exists:pgsql.users,id',
            'situation'   => 'nullable|in:' . implode(',', $situationKeys),
            'project_id'  => 'required_if:action,project|uuid|exists:pgsql.projects,id',
            'sprint_id'   => 'required_if:action,sprint|uuid|exists:pgsql.sprints,id',
        ]);

        $tasks = Task::whereIn('id', $data['task_ids'])->get();
        $skipped = [];

        switch ($data['action']) {
            case 'status':
                // Bulk update passa por Builder::update() e pula o TaskObserver de
                // propósito (ver [[feedback-bulk-actions-no-side-effects]]) — mas o
                // registro de histórico de status não é um efeito colateral que
                // afeta o cliente, só uma trilha de auditoria interna, então precisa
                // ser gravado aqui manualmente pra não deixar buraco no painel de
                // produtividade.
                $now = now();
                $changedBy = Auth::id();
                // Tally em memória por executor — não dá pra confiar só numa query por
                // tarefa: mover 3 tarefas do mesmo executor no mesmo lote precisa contar
                // as anteriores do PRÓPRIO lote, que o banco ainda não reflete.
                $wipTally = [];
                $applyIds = [];
                foreach ($tasks as $task) {
                    if ($data['status'] === 'em_producao' && $task->status !== 'em_producao') {
                        $execId = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->value('user_id')
                            ?? $task->executor_id;
                        if ($execId) {
                            $wipTally[$execId] ??= Task::wipCount($execId);
                            if ($wipTally[$execId] >= 2) {
                                $skipped[] = ['id' => $task->id, 'title' => $task->title, 'reason' => 'limite de 2 tarefas em Em Produção do executor'];
                                continue;
                            }
                            $wipTally[$execId]++;
                        }
                    }
                    if ($task->status !== $data['status']) {
                        TaskStatusTransition::create([
                            'task_id'     => $task->id,
                            'from_status' => $task->status,
                            'to_status'   => $data['status'],
                            'changed_by'  => $changedBy,
                            'changed_at'  => $now,
                        ]);
                        TaskActivity::log($task, 'status_changed', Task::labelForField('status', $task->status), Task::labelForField('status', $data['status']), $changedBy);
                    }
                    $applyIds[] = $task->id;
                }
                Task::whereIn('id', $applyIds)->update(['status' => $data['status']]);
                break;

            case 'situation':
                foreach ($tasks as $task) {
                    if ($task->situation !== ($data['situation'] ?? null)) {
                        TaskActivity::log($task, 'situation_changed', Task::labelForField('situation', $task->situation), Task::labelForField('situation', $data['situation'] ?? null));
                    }
                }
                Task::whereIn('id', $data['task_ids'])->update(['situation' => $data['situation'] ?? null]);
                break;

            case 'executor':
                $newUser = !empty($data['executor_id']) ? User::find($data['executor_id']) : null;
                // Igual ao case 'status': tally em memória, todas as tarefas do lote vão
                // pro mesmo executor então a contagem inicial só precisa ser buscada 1x.
                $wipCount = !empty($data['executor_id']) ? Task::wipCount($data['executor_id']) : 0;
                foreach ($tasks as $task) {
                    $oldUser = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->first()?->user;

                    if ($task->status === 'em_producao' && !empty($data['executor_id']) && $oldUser?->id !== $data['executor_id']) {
                        if ($wipCount >= 2) {
                            $skipped[] = ['id' => $task->id, 'title' => $task->title, 'reason' => 'limite de 2 tarefas em Em Produção do executor'];
                            continue;
                        }
                        $wipCount++;
                    }

                    TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->delete();
                    if (!empty($data['executor_id'])) {
                        // Mesma pessoa pode já ser responsável — não mexe na linha dela.
                        $task->executors()->attach($data['executor_id'], ['role' => 'executor']);
                    }
                    $task->updateQuietly(['executor_id' => $data['executor_id'] ?? null]);
                    if ($oldUser?->id !== ($data['executor_id'] ?? null)) {
                        TaskActivity::log($task, 'executor_changed', $oldUser?->name, $newUser?->name);
                    }
                }
                break;

            case 'responsavel':
                $newUser = !empty($data['responsavel_id']) ? User::find($data['responsavel_id']) : null;
                foreach ($tasks as $task) {
                    $oldUser = TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->first()?->user;
                    TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->delete();
                    if (!empty($data['responsavel_id'])) {
                        // Mesma pessoa pode já ser executor — não mexe na linha dela.
                        $task->executors()->attach($data['responsavel_id'], ['role' => 'responsavel']);
                    }
                    if ($oldUser?->id !== ($data['responsavel_id'] ?? null)) {
                        TaskActivity::log($task, 'responsavel_changed', $oldUser?->name, $newUser?->name);
                    }
                }
                break;

            case 'project':
                $project = Project::findOrFail($data['project_id']);
                foreach ($tasks as $task) {
                    if ((string) $task->client_id !== (string) $project->client_id) {
                        $skipped[] = ['id' => $task->id, 'title' => $task->title, 'reason' => 'cliente diferente do projeto'];
                        continue;
                    }
                    $task->update(['project_id' => $project->id]);
                }
                break;

            case 'sprint':
                $sprint = Sprint::findOrFail($data['sprint_id']);
                if ($sprint->status === 'closed') {
                    return response()->json(['success' => false, 'message' => 'Essa sprint está encerrada.'], 422);
                }
                // Mesma regra de SprintController::addTask() — tarefa com pendência de
                // cadastro não entra em sprint nem individualmente nem em massa.
                foreach ($tasks as $task) {
                    if ($task->isPendente()) {
                        $skipped[] = ['id' => $task->id, 'title' => $task->title, 'reason' => 'pendência de cadastro'];
                        continue;
                    }
                    $task->update(['sprint_id' => $sprint->id]);
                }
                break;

            case 'delete':
                Task::whereIn('id', $data['task_ids'])->delete();
                break;
        }

        return response()->json([
            'success' => true,
            'updated' => $tasks->count() - count($skipped),
            'skipped' => $skipped,
        ]);
    }

    public function destroy(MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);
        $task->delete();

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa removida.');
    }

    public function destroyStandalone(Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);
        $task->delete();

        return redirect()->route('projects.showDirect', $project)
            ->with('success', 'Tarefa removida.');
    }

    private function storeRules(): array
    {
        return [
            'title'              => 'required|string|max:300',
            'description'        => 'nullable|string',
            'task_type'          => 'required|in:' . implode(',', array_keys(Task::$types)),
            'destination'        => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'priority'           => 'nullable|in:urgente,medio,normal',
            'executor_id'        => 'nullable|exists:users,id',
            'executor_ids'       => 'nullable|array',
            'executor_ids.*'     => 'exists:users,id',
            'executor_roles'     => ['nullable', 'array', Task::executorRoleCapRule()],
            'executor_roles.*'   => 'in:executor,responsavel,aprovador,observador',
            'responsavel_id'     => 'nullable|exists:users,id',
            'due_date'           => 'nullable|date',
            'approval_date'      => 'nullable|date',
            'publish_date'       => 'nullable|date',
            'approval_method'    => 'nullable|in:' . implode(',', array_keys(Task::$approvalMethods)),
            'internal_approval'  => 'nullable|boolean',
            'situation'          => 'nullable|string|max:150',
            'origin'             => 'nullable|in:' . implode(',', array_keys(Task::$origins)),
            'status'             => 'nullable|in:' . implode(',', array_keys(Task::$statuses)),
            'requester_name'     => 'nullable|string|max:150',
            'requester_whatsapp' => 'nullable|string|max:30',
            'requester_channel'  => 'nullable|in:' . implode(',', array_keys(Task::$requesterChannels)),
            'files'              => 'nullable|array',
            'files.*'            => 'file|max:102400', // 100 MB, mesmo limite de TaskAttachmentController
        ];
    }

    private function updateRules(): array
    {
        return [
            'title'              => 'required|string|max:300',
            'description'        => 'nullable|string',
            'task_type'          => 'required|in:' . implode(',', array_keys(Task::$types)),
            'destination'        => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'executor_id'        => 'nullable|exists:users,id',
            'executor_ids'       => 'nullable|array',
            'executor_ids.*'     => 'exists:users,id',
            'executor_roles'     => ['nullable', 'array', Task::executorRoleCapRule()],
            'executor_roles.*'   => 'in:executor,responsavel,aprovador,observador',
            'due_date'           => 'nullable|date',
            'approval_date'      => 'nullable|date',
            'publish_date'       => 'nullable|date',
            'situation'          => 'nullable|string|max:150',
            'approval_method'    => 'nullable|in:' . implode(',', array_keys(Task::$approvalMethods)),
            'internal_approval'  => 'nullable|boolean',
            'origin'             => 'nullable|in:' . implode(',', array_keys(Task::$origins)),
            'status'             => 'required|in:' . implode(',', array_keys(Task::$statuses)),
            'requester_name'     => 'nullable|string|max:150',
            'requester_whatsapp' => 'nullable|string|max:30',
            'requester_channel'  => 'nullable|in:' . implode(',', array_keys(Task::$requesterChannels)),
        ];
    }

    private function syncPersonnel(Task $task, array $data): void
    {
        // Guarda quem já estava em cada papel antes de apagar tudo e re-anexar — o método
        // sempre refaz o vínculo do zero mesmo quando nada mudou, então sem essa comparação a
        // automação de "Responsável/Executor adicionado" dispararia à toa em todo salvamento.
        $before = TaskExecutor::where('task_id', $task->id)
            ->whereIn('role', ['executor', 'responsavel', 'observador'])
            ->get(['user_id', 'role'])
            ->map(fn ($e) => "{$e->role}:{$e->user_id}");

        TaskExecutor::where('task_id', $task->id)
            ->whereIn('role', ['executor', 'responsavel', 'observador'])
            ->delete();

        // Papéis são independentes — a mesma pessoa pode ser executor, responsável e/ou
        // observador ao mesmo tempo na mesma tarefa (task_executors permite 1 linha por
        // (task_id, user_id, role)). $synced só evita duplicar attach() dentro do MESMO papel
        // (ex: observer_ids com id repetido).
        $synced = [];
        $newlyAdded = [];

        if (!empty($data['executor_id'])) {
            $task->executors()->attach($data['executor_id'], ['role' => 'executor']);
            $task->updateQuietly(['executor_id' => $data['executor_id']]);
            $synced[] = "executor:{$data['executor_id']}";
            if (!$before->contains("executor:{$data['executor_id']}")) {
                $newlyAdded[] = ['role' => 'executor', 'user_id' => $data['executor_id']];
            }
        } else {
            $task->updateQuietly(['executor_id' => null]);
        }

        if (!empty($data['responsavel_id'])) {
            $task->executors()->attach($data['responsavel_id'], ['role' => 'responsavel']);
            $synced[] = "responsavel:{$data['responsavel_id']}";
            if (!$before->contains("responsavel:{$data['responsavel_id']}")) {
                $newlyAdded[] = ['role' => 'responsavel', 'user_id' => $data['responsavel_id']];
            }
        }

        foreach (array_unique($data['observer_ids'] ?? []) as $uid) {
            if (!in_array("observador:{$uid}", $synced)) {
                $task->executors()->attach($uid, ['role' => 'observador']);
                $synced[] = "observador:{$uid}";
                if (!$before->contains("observador:{$uid}")) {
                    $newlyAdded[] = ['role' => 'observador', 'user_id' => $uid];
                }
            }
        }

        foreach ($newlyAdded as $added) {
            AutomationEngine::evaluate('executor_added', $task, $added);
        }
    }

    // Anexos enviados junto na criação — mesma lógica de TaskAttachmentController::store(),
    // sempre como "insumo" (material de referência; ainda não existe entregável nesse ponto).
    private function storeAttachments(Task $task, Request $request): void
    {
        if (!$request->hasFile('files')) {
            return;
        }

        $disk = config('filesystems.default', 'r2');
        foreach ($request->file('files') as $file) {
            $path = $file->store("tasks/{$task->id}", $disk);
            TaskAttachment::create([
                'task_id'     => $task->id,
                'filename'    => $file->getClientOriginalName(),
                'disk_path'   => $path,
                'disk'        => $disk,
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'kind'        => 'insumo',
            ]);
        }
    }
}
