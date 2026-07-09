<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SprintController extends Controller
{
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

        // Agrupar por status (a coluna do board é o próprio status)
        $kanban = [];
        foreach (Task::$statuses as $statusKey => $meta) {
            $kanban[$statusKey] = $sprint->tasks
                ->where('status', $statusKey)
                ->values();
        }

        // Lista filtrável (aba "Lista") — mesmo conjunto de tarefas da sprint, só filtrado
        $listTasks = $sprint->tasks;
        if ($request->filled('list_client_id')) {
            $listTasks = $listTasks->where('client_id', $request->get('list_client_id'));
        }
        if ($request->filled('list_status')) {
            $listTasks = $listTasks->where('status', $request->get('list_status'));
        }
        if ($request->filled('list_search')) {
            $search = mb_strtolower($request->get('list_search'));
            $listTasks = $listTasks->filter(fn ($t) => str_contains(mb_strtolower($t->title), $search));
        }
        $listTasks = $listTasks->values();

        $listGroupBy = $request->get('list_group_by', 'cliente');
        $listGrouped = Task::groupCollection($listTasks, $listGroupBy)->sortByDesc->count();

        // Backlog disponível para adicionar (sem sprint, status backlog)
        $backlogTasks = Task::with(['client', 'project.macroPlan', 'executor'])
            ->whereNull('sprint_id')
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->orderBy('due_date')
            ->get();

        $clients      = Client::orderBy('company_name')->get(['id', 'company_name']);
        $users        = User::orderBy('name')->get(['id', 'name']);
        $projects     = Project::with('client:id,company_name')->orderBy('title')->get(['id', 'title', 'client_id']);
        $sprints      = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get();
        $activeSprint = $sprints->firstWhere('status', 'active');

        return view('sprints.show', compact(
            'sprint', 'kanban', 'listTasks', 'listGrouped', 'listGroupBy',
            'backlogTasks', 'clients', 'users', 'projects', 'sprints', 'activeSprint'
        ));
    }

    public function update(Request $request, Sprint $sprint)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:150',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after_or_equal:starts_at',
            'status'    => 'required|in:planning,active,closed',
        ]);

        $sprint->update($data);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint atualizada.');
    }

    public function lock(Sprint $sprint)
    {
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
