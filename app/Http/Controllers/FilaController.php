<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class FilaController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['client', 'project.macroPlan', 'executor', 'executors'])
            ->whereNull('sprint_id')
            ->orderByRaw("due_date NULLS LAST")
            ->orderBy('created_at');

        if (!$request->boolean('mostrar_fechados')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }
        if ($request->filled('task_type')) {
            $query->where('task_type', $request->task_type);
        }
        if ($request->boolean('atrasadas')) {
            $query->whereNotNull('due_date')->where('due_date', '<', today());
        }

        $tasks = $query->get();

        $groupBy = $request->get('group_by', 'cliente');

        $grouped = match ($groupBy) {
            'executor'    => $tasks->groupBy(fn($t) => $this->executorKey($t)),
            'responsavel' => $tasks->groupBy(fn($t) => $this->responsavelKey($t)),
            'status'      => $tasks->groupBy(fn($t) => $t->status),
            default       => $tasks->groupBy(fn($t) => $t->client_id ?? '__sem_cliente__'),
        };

        $grouped = $grouped->sortByDesc->count();

        $clients  = Client::orderBy('company_name')->get(['id', 'company_name']);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $projects = Project::with('client:id,company_name')->orderBy('title')->get(['id', 'title', 'client_id']);

        $sprints = Sprint::whereIn('status', ['active', 'planning'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('starts_at')
            ->get();

        $activeSprint = $sprints->firstWhere('status', 'active');

        $stats = [
            'total'     => $tasks->count(),
            'projetos'  => $tasks->where('is_ticket', false)->count(),
            'tickets'   => $tasks->where('is_ticket', true)->count(),
            'atrasadas' => $tasks->filter(fn($t) => $t->isOverdue())->count(),
            'clientes'  => $tasks->pluck('client_id')->filter()->unique()->count(),
        ];

        return view('filas.index', compact(
            'tasks', 'grouped', 'groupBy', 'clients', 'users', 'projects',
            'sprints', 'activeSprint', 'stats'
        ));
    }

    private function executorKey(Task $task): string
    {
        $exec = $task->executors->first(fn($u) => $u->pivot->role === 'executor')
            ?? $task->executor;
        return $exec ? $exec->id . '|' . $exec->name : '__sem_executor__|Sem executor';
    }

    private function responsavelKey(Task $task): string
    {
        $resp = $task->executors->first(fn($u) => $u->pivot->role === 'responsavel');
        return $resp ? $resp->id . '|' . $resp->name : '__sem_responsavel__|Sem responsável';
    }
}
