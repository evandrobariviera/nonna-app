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

        $clients = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);

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
        $query = Task::with(['client', 'project.macroPlan', 'macroPlan', 'executor', 'executors'])
            ->whereNull('sprint_id')
            // Chamado (is_ticket=true) só entra aqui depois de "enviado pra Fila"
            // (queued_at) — antes disso fica só na tela de Tickets (triagem, protege
            // a Fila de chamado ainda não avaliado). Tarefa de projeto não passa por
            // essa checagem, sempre aparece.
            ->where(fn ($q) => $q->where('is_ticket', false)->orWhereNotNull('queued_at'))
            ->orderByRaw("due_date NULLS LAST")
            ->orderBy('created_at');

        if (!$request->boolean('mostrar_fechados')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        // Tarefa de cliente inativo some da fila por padrão — nada é alterado no
        // cliente/tarefa, só escondido daqui (reversível: cliente reativou, some o filtro).
        if (!$request->boolean('mostrar_inativos')) {
            $query->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'));
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
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('situacao')) {
            $query->where('situation', $request->situacao);
        }
        if ($request->filled('executor_id')) {
            $id = $request->executor_id;
            $query->where(function ($q) use ($id) {
                $q->whereHas('executors', fn ($qq) => $qq->where('role', 'executor')->where('user_id', $id))
                  ->orWhere(function ($q2) use ($id) {
                      $q2->where('executor_id', $id)
                         ->whereDoesntHave('executors', fn ($qq) => $qq->where('role', 'executor'));
                  });
            });
        }
        if ($request->filled('responsavel_id')) {
            $id = $request->responsavel_id;
            $query->whereHas('executors', fn ($qq) => $qq->where('role', 'responsavel')->where('user_id', $id));
        }
        if ($request->boolean('atrasadas')) {
            $query->whereNotNull('due_date')->where('due_date', '<', today());
        }
        if ($request->boolean('pendencia')) {
            $query->pendente();
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('title', 'ilike', '%' . $search . '%')
                ->orWhere('clickup_task_id', 'ilike', '%' . $search . '%'));
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

        $users = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);

        // Projetos pro dropdown "Vincular a projeto" da barra de ações em massa —
        // só ativos, só de cliente ativo, e restrito ao cliente filtrado no momento
        // (evita listar dezenas de projetos de outros clientes misturados, e some
        // com as entradas "fantasma" de clientes duplicados/inativos).
        $projects = Project::with('client:id,company_name,nickname')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->client_id))
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);

        return compact('tasks', 'grouped', 'groupBy', 'sprints', 'activeSprint', 'stats', 'users', 'projects');
    }

}
