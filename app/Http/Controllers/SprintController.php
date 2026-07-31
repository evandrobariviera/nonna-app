<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SprintController extends Controller
{
    /**
     * Regra de negócio: só pode existir 1 sprint ativa por vez. Futuramente isso vira
     * automático por data; por enquanto é travado manualmente aqui.
     */
    private function assertSingleActiveSprint(string $status, ?string $exceptSprintId = null): void
    {
        if ($status !== 'active') {
            return;
        }

        $existing = Sprint::where('status', 'active')
            ->when($exceptSprintId, fn ($q) => $q->where('id', '!=', $exceptSprintId))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'status' => "Já existe uma sprint ativa: \"{$existing->title}\". Encerre ou reabra ela antes de ativar outra.",
            ]);
        }
    }

    public function index()
    {
        $sprints = Sprint::withCount('tasks')
            ->orderByDesc('starts_at')
            ->get();

        $active = $sprints->where('status', 'active')->first();

        return view('sprints.index', compact('sprints', 'active'));
    }

    public function create()
    {
        return view('sprints.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:150',
            'starts_at'  => 'required|date',
            'ends_at'    => 'required|date|after_or_equal:starts_at',
            'status'     => 'required|in:planning,active,closed',
        ]);

        $this->assertSingleActiveSprint($data['status']);

        Sprint::create([...$data, 'created_by' => Auth::id()]);

        return redirect()->route('sprints.index')->with('success', 'Sprint criada.');
    }

    public function show(Request $request, Sprint $sprint)
    {
        $sprint->load([
            'tasks.executor',
            'tasks.executors',
            'tasks.client',
            'tasks.project',
            'tasks.attachments',
        ]);

        // Board é a mesa de produção ativa — tarefa de cliente inativo nunca aparece
        // aqui (sem toggle; quem precisa ver isso usa a aba Lista, que tem o filtro).
        $activeClientTasks = $sprint->tasks->filter(fn ($t) => $t->client?->status !== 'inactive');

        // Agrupar por status (a coluna do board é o próprio status)
        $kanban = [];
        foreach (Task::$statuses as $statusKey => $meta) {
            $kanban[$statusKey] = $activeClientTasks
                ->where('status', $statusKey)
                ->values();
        }

        [$listTasks, $listGrouped, $listGroupBy] = $this->filteredListTasks($request, $sprint);

        // Backlog disponível para adicionar (sem sprint, status backlog) — cliente
        // inativo some daqui também, mesma regra da Fila (não faz sentido puxar
        // pra sprint uma tarefa de cliente que já saiu).
        $backlogTasks = Task::with(['client', 'project.macroPlan', 'executor'])
            ->whereNull('sprint_id')
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('due_date')
            ->get();

        $clients      = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users        = User::orderBy('name')->get(['id', 'name']);
        $projects     = Project::with('client:id,company_name')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);
        $sprints      = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get();
        $activeSprint = $sprints->firstWhere('status', 'active');

        return view('sprints.show', compact(
            'sprint', 'kanban', 'listTasks', 'listGrouped', 'listGroupBy',
            'backlogTasks', 'clients', 'users', 'projects', 'sprints', 'activeSprint'
        ));
    }

    // Fragmento da aba Lista — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira (Board/Planejamento ficam intocados).
    public function listResults(Request $request, Sprint $sprint)
    {
        $sprint->load(['tasks.executor', 'tasks.executors', 'tasks.client', 'tasks.project', 'tasks.attachments']);

        [$listTasks, $listGrouped, $listGroupBy] = $this->filteredListTasks($request, $sprint);

        $sprints      = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get();
        $activeSprint = $sprints->firstWhere('status', 'active');

        return view('sprints._list-results', compact('listTasks', 'listGrouped', 'listGroupBy', 'sprints', 'activeSprint'));
    }

    // Lista filtrável (aba "Lista") — mesmo conjunto de tarefas da sprint, filtrado com
    // exatamente os mesmos filtros da Fila (ver partials/_task-filter-bar.blade.php).
    private function filteredListTasks(Request $request, Sprint $sprint): array
    {
        $listTasks = $sprint->tasks;
        if (!$request->boolean('list_mostrar_fechados')) {
            $listTasks = $listTasks->whereNotIn('status', ['concluido', 'cancelado']);
        }
        if (!$request->boolean('list_mostrar_inativos')) {
            $listTasks = $listTasks->filter(fn ($t) => $t->client?->status !== 'inactive');
        }
        if ($request->filled('list_client_id')) {
            $listTasks = $listTasks->where('client_id', $request->get('list_client_id'));
        }
        if ($request->filled('list_project_id')) {
            $listTasks = $listTasks->where('project_id', $request->get('list_project_id'));
        }
        if ($request->filled('list_origin')) {
            $listTasks = $listTasks->where('origin', $request->get('list_origin'));
        }
        if ($request->filled('list_task_type')) {
            $listTasks = $listTasks->where('task_type', $request->get('list_task_type'));
        }
        if ($request->boolean('list_atrasadas')) {
            $listTasks = $listTasks->filter(fn ($t) => $t->isOverdue());
        }
        if ($request->boolean('list_pendencia')) {
            $listTasks = $listTasks->filter(fn ($t) => $t->isPendente());
        }
        if ($request->filled('list_search')) {
            $search = mb_strtolower($request->get('list_search'));
            $listTasks = $listTasks->filter(fn ($t) => str_contains(mb_strtolower($t->title), $search));
        }
        $listTasks = $listTasks->values();

        $listGroupBy = $request->get('list_group_by', 'cliente');
        $listGrouped = Task::groupCollection($listTasks, $listGroupBy)->sortByDesc->count();

        return [$listTasks, $listGrouped, $listGroupBy];
    }

    public function update(Request $request, Sprint $sprint)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:150',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after_or_equal:starts_at',
            'status'    => 'required|in:planning,active,closed',
        ]);

        $this->assertSingleActiveSprint($data['status'], $sprint->id);

        $sprint->update($data);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint atualizada.');
    }

    public function lock(Sprint $sprint)
    {
        $this->assertSingleActiveSprint('active', $sprint->id);

        $sprint->update([
            'locked_at' => now(),
            'locked_by' => Auth::id(),
            'status'    => 'active',
        ]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint travada — em execução.');
    }

    public function unlock(Sprint $sprint)
    {
        $sprint->update([
            'locked_at' => null,
            'locked_by' => null,
            'status'    => 'planning',
        ]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint reaberta para planejamento.');
    }

    public function close(Sprint $sprint)
    {
        // Tarefas não concluídas voltam ao backlog
        $sprint->tasks()
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->update(['sprint_id' => null]);

        $sprint->update(['status' => 'closed']);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint encerrada. Tarefas pendentes voltaram ao backlog.');
    }

    public function addTask(Sprint $sprint, Task $task)
    {
        abort_if($sprint->status === 'closed', 403, 'Sprint encerrada.');

        if ($task->isPendente()) {
            throw ValidationException::withMessages([
                'pendencia' => "Tarefa \"{$task->title}\" tem pendências de cadastro e não pode ser adicionada à sprint. Corrija-a antes de adicioná-la.",
            ]);
        }

        $task->update(['sprint_id' => $sprint->id]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Tarefa adicionada à sprint.');
    }

    public function removeTask(Sprint $sprint, Task $task)
    {
        abort_if($sprint->isLocked(), 403, 'Sprint travada. Desbloqueie antes de remover tarefas.');

        $task->update(['sprint_id' => null]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Tarefa removida da sprint.');
    }

    public function destroy(Sprint $sprint)
    {
        $sprint->tasks()->update(['sprint_id' => null]);
        $sprint->delete();

        return redirect()->route('sprints.index')->with('success', 'Sprint removida.');
    }
}
