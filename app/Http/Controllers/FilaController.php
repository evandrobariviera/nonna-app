<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Http\Request;

class FilaController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['client', 'project.macroPlan', 'executor', 'executors'])
            ->whereNull('sprint_id')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->orderByRaw("due_date NULLS LAST")
            ->orderBy('created_at');

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

        // Agrupa por cliente, ordena pelo grupo com mais tarefas primeiro
        $byClient = $tasks
            ->groupBy(fn($t) => $t->client_id ?? '__sem_cliente__')
            ->sortByDesc->count();

        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);

        $sprints = Sprint::whereIn('status', ['active', 'planning'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('starts_at')
            ->get();

        $activeSprint = $sprints->firstWhere('status', 'active');

        $stats = [
            'total'    => $tasks->count(),
            'projetos' => $tasks->where('is_ticket', false)->count(),
            'tickets'  => $tasks->where('is_ticket', true)->count(),
            'atrasadas'=> $tasks->filter(fn($t) => $t->isOverdue())->count(),
            'clientes' => $tasks->pluck('client_id')->filter()->unique()->count(),
        ];

        return view('filas.index', compact(
            'tasks', 'byClient', 'clients', 'sprints', 'activeSprint', 'stats'
        ));
    }
}
