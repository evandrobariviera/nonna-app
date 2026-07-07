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
        $query = Task::with(['client', 'executor', 'executors'])
            ->where('is_ticket', true)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('executor_id')) {
            $query->where('executor_id', $request->executor_id);
        }

        $tickets  = $query->paginate(30)->withQueryString();
        $clients  = Client::orderBy('company_name')->get(['id', 'company_name']);
        $users    = User::orderBy('name')->get(['id', 'name']);
        $projects = Project::with('client:id,company_name')->orderBy('title')->get(['id', 'title', 'client_id']);

        return view('tickets.index', compact('tickets', 'clients', 'users', 'projects'));
    }

    public function create()
    {
        $clients = Client::where('status', 'active')->orderBy('company_name')->get(['id', 'company_name']);
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

        // Sync executores adicionais
        $ids   = $data['executor_ids'] ?? [];
        $roles = $data['executor_roles'] ?? [];
        if (!empty($ids)) {
            $syncData = [];
            foreach ($ids as $userId) {
                $syncData[$userId] = ['role' => $roles[$userId] ?? 'executor'];
            }
            $task->executors()->sync($syncData);
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket criado com sucesso.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        abort_unless($task->is_ticket, 403);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ]);

        $task->update(['status' => $data['status']]);

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function destroy(Task $task)
    {
        abort_unless($task->is_ticket, 403);
        $task->update(['status' => 'cancelado']);

        return redirect()->route('tickets.index')->with('success', 'Ticket cancelado.');
    }
}
