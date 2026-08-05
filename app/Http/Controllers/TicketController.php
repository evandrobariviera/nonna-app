<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets  = $this->filteredTickets($request);
        $clients  = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $projects = $this->visibleProjects($request);
        $sprints  = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get(['id', 'title', 'status']);

        return view('tickets.index', compact('tickets', 'clients', 'users', 'projects', 'sprints'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário digita/filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $tickets  = $this->filteredTickets($request);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $projects = $this->visibleProjects($request);
        $sprints  = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get(['id', 'title', 'status']);

        return view('tickets._results', compact('tickets', 'users', 'projects', 'sprints'));
    }

    // Projetos pro dropdown "Vincular a projeto" da barra de ações em massa —
    // só ativos, só de cliente ativo, e restrito ao cliente filtrado no momento.
    private function visibleProjects(Request $request)
    {
        return Project::with('client:id,company_name,nickname')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->client_id))
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);
    }

    private function filteredTickets(Request $request)
    {
        $query = Task::with(['client', 'executor', 'executors', 'project'])
            ->where('is_ticket', true)
            // Depois que o chamado é triado pra uma Sprint, ele passa a ser
            // executado por lá — mantê-lo aqui também duplicaria a tarefa nas
            // duas telas. Some daqui automaticamente se saída da sprint reverter.
            ->whereNull('sprint_id')
            ->orderByDesc('created_at');

        if (!$request->boolean('mostrar_fechados')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('executor_id')) {
            $query->where('executor_id', $request->executor_id);
        }
        if ($request->filled('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        return $query->paginate(30)->withQueryString();
    }

    public function create()
    {
        $clients = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users   = User::orderBy('name')->get(['id', 'name']);
        $sprints = Sprint::whereIn('status', ['planning', 'active'])->orderByDesc('starts_at')->get();

        return view('tickets.create', compact('clients', 'users', 'sprints'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:300',
            'description'        => 'nullable|string',
            'task_type'          => 'required|in:' . implode(',', array_keys(Task::$types)),
            'destination'        => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'client_id'          => 'required|uuid|exists:clients,id',
            'executor_id'        => 'nullable|exists:users,id',
            'executor_ids'       => 'nullable|array',
            'executor_ids.*'     => 'exists:users,id',
            'executor_roles'     => 'nullable|array',
            'responsavel_id'     => 'nullable|exists:users,id',
            'sprint_id'          => 'nullable|uuid|exists:sprints,id',
            'due_date'           => 'nullable|date',
            'approval_date'      => 'nullable|date',
            'publish_date'       => 'nullable|date',
            'approval_method'    => 'nullable|in:' . implode(',', array_keys(Task::$approvalMethods)),
            'internal_approval'  => 'nullable|boolean',
            'situation'          => 'nullable|string|max:150',
            'requester_name'     => 'nullable|string|max:150',
            'requester_whatsapp' => 'nullable|string|max:30',
            'requester_channel'  => 'nullable|in:' . implode(',', array_keys(Task::$requesterChannels)),
        ]);

        $task = Task::create([
            ...$data,
            'is_ticket'         => true,
            'origin'            => 'ticket',
            'status'            => 'backlog',
            'internal_approval' => $request->boolean('internal_approval'),
            'created_by'        => Auth::id(),
        ]);

        // Sync executores + responsável — responsável entra por último no array pra
        // prevalecer caso a mesma pessoa também tenha sido marcada como executor
        // (task_executors só permite 1 linha por (task_id, user_id)).
        $ids   = $data['executor_ids'] ?? [];
        $roles = $data['executor_roles'] ?? [];
        $syncData = [];
        foreach ($ids as $userId) {
            $syncData[$userId] = ['role' => $roles[$userId] ?? 'executor'];
        }
        if (!empty($data['responsavel_id'])) {
            $syncData[$data['responsavel_id']] = ['role' => 'responsavel'];
        }
        if (!empty($syncData)) {
            $task->executors()->sync($syncData);
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket criado com sucesso.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        abort_unless($task->is_ticket, 403);

        // situation é opcional — usado pelo Kanban do Dashboard, ver TaskController::updateStatusDirect().
        $situationKeys = array_keys(array_filter(Task::$situations, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY));
        $data = $request->validate([
            'status'    => 'required|in:' . implode(',', array_keys(Task::$statuses)),
            'situation' => 'sometimes|in:' . implode(',', $situationKeys),
        ]);

        $task->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function destroy(Task $task)
    {
        abort_unless($task->is_ticket, 403);
        $task->update(['status' => 'cancelado']);

        return redirect()->route('tickets.index')->with('success', 'Ticket cancelado.');
    }
}
