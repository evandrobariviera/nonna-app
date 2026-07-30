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
        $data = $this->filteredTasksData($request);

        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('filas.index', [...$data, ...compact('clients')]);
    }

    // Fragmento (stats + tabela agrupada) — chamado via fetch por live-filter.js
    // conforme o usuário filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        return view('filas._results', $this->filteredTasksData($request));
    }

    private function filteredTasksData(Request $request): array
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
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
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
        if ($request->boolean('pendencia')) {
            $query->pendente();
        }
        if ($request->filled('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        $pendenciasCount = (clone $query)->pendente()->count();

        $tasks = $query->get();

        $groupBy = $request->get('group_by', 'cliente');
        $grouped = Task::groupCollection($tasks, $groupBy)->sortByDesc->count();

        $sprints = Sprint::whereIn('status', ['active', 'planning'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('starts_at')
            ->get();

        $activeSprint = $sprints->firstWhere('status', 'active');

        $stats = [
            'total'       => $tasks->count(),
            'projetos'    => $tasks->where('is_ticket', false)->count(),
            'tickets'     => $tasks->where('is_ticket', true)->count(),
            'atrasadas'   => $tasks->filter(fn($t) => $t->isOverdue())->count(),
            'clientes'    => $tasks->pluck('client_id')->filter()->unique()->count(),
            'pendencias'  => $pendenciasCount,
        ];

        $users = User::orderBy('name')->get(['id', 'name']);
        $projects = Project::with('client:id,company_name')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);

        return compact('tasks', 'grouped', 'groupBy', 'sprints', 'activeSprint', 'stats', 'users', 'projects');
    }

}
