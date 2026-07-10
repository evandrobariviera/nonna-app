<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiChat;
use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskExecutor;
use App\Models\User;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index(Request $request)
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

        $tasks    = $query->paginate(40)->withQueryString();
        $clients  = Client::orderBy('company_name')->get(['id', 'company_name']);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $sprints  = Sprint::orderByDesc('starts_at')->get(['id', 'title', 'status']);
        $projects = Project::with('client:id,company_name')->orderBy('title')->get(['id', 'title', 'client_id']);

        return view('tasks.index', compact('tasks', 'clients', 'users', 'sprints', 'projects'));
    }

    public function show(Task $task)
    {
        $task->load([
            'client.links',
            'project.macroPlan.client',
            'macroPlan',
            'sprint',
            'executor',
            'executors',
            'responsibles',
            'observers',
            'attachments.uploadedBy',
            'comments.user',
            'createdBy',
            'latestApprovalRound.tokens.contact',
            'approvalRounds.tokens.contact',
            'approvalRounds.tokens.feedbacks.attachment',
        ]);

        $users  = User::orderBy('name')->get(['id', 'name']);
        $agents = AiAgent::where('is_active', true)->orderBy('name')->get(['id', 'name']);

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

        return view('tasks.show', compact('task', 'users', 'agents', 'chatMessages'));
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

        $triggerApproval = ($data['situation'] ?? null) === 'Enviar para o cliente'
            && $task->situation !== 'Enviar para o cliente'
            && $task->status !== 'aprovacao';

        $task->update([
            ...$data,
            'internal_approval' => $request->boolean('internal_approval'),
        ]);

        if ($request->hasAny(['executor_id', 'responsavel_id', 'observer_ids'])) {
            $this->syncPersonnel($task, $data);
        }

        if ($triggerApproval) {
            $task->load('attachments');
            $newAttachments = $task->attachments
                ->where('is_deliverable', false)
                ->pluck('id')
                ->toArray();

            if (!empty($newAttachments)) {
                app(TaskApprovalService::class)->submitForApproval(
                    $task,
                    Auth::user(),
                    $newAttachments,
                );

                return redirect()->route('tasks.show', $task)
                    ->with('success', 'Material enviado para aprovação do cliente. Os contatos serão notificados.');
            }

            return redirect()->route('tasks.show', $task)
                ->with('success', 'Tarefa atualizada.')
                ->with('warning', 'Nenhum arquivo encontrado para enviar. Faça o upload dos arquivos e altere a situação novamente.');
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Tarefa atualizada.');
    }

    public function store(Request $request, MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);

        $data = $request->validate($this->storeRules());

        $task = $project->tasks()->create([
            ...$data,
            'macro_plan_id'     => $macroplan->id,
            'client_id'         => $project->client_id,
            'status'            => $data['status'] ?? 'backlog',
            'origin'            => $data['origin'] ?? 'projeto',
            'internal_approval' => $request->boolean('internal_approval'),
            'created_by'        => Auth::id(),
        ]);

        $this->syncExecutors($task, $data);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa criada.');
    }

    public function storeStandalone(Request $request, Project $project)
    {
        $data = $request->validate($this->storeRules());

        $task = $project->tasks()->create([
            ...$data,
            'macro_plan_id'     => $project->macro_plan_id,
            'client_id'         => $project->client_id,
            'status'            => $data['status'] ?? 'backlog',
            'origin'            => $data['origin'] ?? 'projeto',
            'internal_approval' => $request->boolean('internal_approval'),
            'created_by'        => Auth::id(),
        ]);

        $this->syncExecutors($task, $data);

        return redirect()->route('projects.showDirect', $project)
            ->with('success', 'Tarefa criada.');
    }

    public function update(Request $request, MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $data = $request->validate($this->updateRules());

        $task->update([...$data, 'internal_approval' => $request->boolean('internal_approval')]);
        $this->syncExecutors($task, $data);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa atualizada.');
    }

    public function updateStandalone(Request $request, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $data = $request->validate($this->updateRules());

        $task->update([...$data, 'internal_approval' => $request->boolean('internal_approval')]);
        $this->syncExecutors($task, $data);

        return redirect()->route('projects.showDirect', $project)
            ->with('success', 'Tarefa atualizada.');
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

        return redirect()->back()->with('success', 'Situação atualizada.');
    }

    public function updateResponsavel(Request $request, Task $task)
    {
        $data = $request->validate(['responsavel_id' => 'nullable|exists:users,id']);

        TaskExecutor::where('task_id', $task->id)->where('role', 'responsavel')->delete();

        if ($data['responsavel_id']) {
            $task->executors()->attach($data['responsavel_id'], ['role' => 'responsavel']);
        }

        return redirect()->back()->with('success', 'Responsável atualizado.');
    }

    public function updateExecutorDirect(Request $request, Task $task)
    {
        $data = $request->validate(['executor_id' => 'nullable|exists:users,id']);

        TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->delete();

        if ($data['executor_id']) {
            $task->executors()->attach($data['executor_id'], ['role' => 'executor']);
            $task->updateQuietly(['executor_id' => $data['executor_id']]);
        } else {
            $task->updateQuietly(['executor_id' => null]);
        }

        return redirect()->back()->with('success', 'Executor atualizado.');
    }

    public function updateStatus(Request $request, MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $task->update(['status' => $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ])['status']]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function updateStatusStandalone(Request $request, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $task->update(['status' => $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ])['status']]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function updateStatusDirect(Request $request, Task $task)
    {
        $task->update(['status' => $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ])['status']]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function bulkUpdate(Request $request)
    {
        $situationKeys = array_keys(array_filter(Task::$situations, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY));

        $data = $request->validate([
            'task_ids'    => 'required|array|min:1',
            'task_ids.*'  => 'uuid|exists:pgsql.tasks,id',
            'action'      => 'required|in:status,executor,situation,project,sprint,delete',
            'status'      => 'required_if:action,status|in:' . implode(',', array_keys(Task::$statuses)),
            'executor_id' => 'nullable|exists:pgsql.users,id',
            'situation'   => 'nullable|in:' . implode(',', $situationKeys),
            'project_id'  => 'required_if:action,project|uuid|exists:pgsql.projects,id',
            'sprint_id'   => 'required_if:action,sprint|uuid|exists:pgsql.sprints,id',
        ]);

        $tasks = Task::whereIn('id', $data['task_ids'])->get();
        $skipped = [];

        switch ($data['action']) {
            case 'status':
                Task::whereIn('id', $data['task_ids'])->update(['status' => $data['status']]);
                break;

            case 'situation':
                Task::whereIn('id', $data['task_ids'])->update(['situation' => $data['situation'] ?? null]);
                break;

            case 'executor':
                foreach ($tasks as $task) {
                    TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->delete();
                    if (!empty($data['executor_id'])) {
                        $task->executors()->attach($data['executor_id'], ['role' => 'executor']);
                    }
                    $task->updateQuietly(['executor_id' => $data['executor_id'] ?? null]);
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
                Task::whereIn('id', $data['task_ids'])->update(['sprint_id' => $sprint->id]);
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
            'executor_id'        => 'nullable|exists:users,id',
            'executor_ids'       => 'nullable|array',
            'executor_ids.*'     => 'exists:users,id',
            'executor_roles'     => 'nullable|array',
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
            'executor_roles'     => 'nullable|array',
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
        TaskExecutor::where('task_id', $task->id)
            ->whereIn('role', ['executor', 'responsavel', 'observador'])
            ->delete();

        $synced = [];

        if (!empty($data['executor_id'])) {
            $task->executors()->attach($data['executor_id'], ['role' => 'executor']);
            $task->updateQuietly(['executor_id' => $data['executor_id']]);
            $synced[] = $data['executor_id'];
        } else {
            $task->updateQuietly(['executor_id' => null]);
        }

        if (!empty($data['responsavel_id']) && !in_array($data['responsavel_id'], $synced)) {
            $task->executors()->attach($data['responsavel_id'], ['role' => 'responsavel']);
            $synced[] = $data['responsavel_id'];
        }

        foreach ($data['observer_ids'] ?? [] as $uid) {
            if (!in_array($uid, $synced)) {
                $task->executors()->attach($uid, ['role' => 'observador']);
                $synced[] = $uid;
            }
        }
    }

    private function syncExecutors(Task $task, array $data): void
    {
        $ids   = $data['executor_ids'] ?? [];
        $roles = $data['executor_roles'] ?? [];

        if (empty($ids)) {
            return;
        }

        $syncData = [];
        foreach ($ids as $userId) {
            $syncData[$userId] = ['role' => $roles[$userId] ?? 'executor'];
        }

        $task->executors()->sync($syncData);

        if (!$task->executor_id) {
            $task->updateQuietly(['executor_id' => $ids[0]]);
        }
    }
}
