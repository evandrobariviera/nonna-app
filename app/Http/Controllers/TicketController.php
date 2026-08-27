<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskExecutor;
use App\Models\User;
use App\Services\TaskExecutorSync;
use App\Support\UploadOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets  = $this->filteredTickets($request);
        $clients  = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users    = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);
        $projects = $this->visibleProjects($request);
        $sprints  = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get(['id', 'title', 'status']);

        return view('tickets.index', compact('tickets', 'clients', 'users', 'projects', 'sprints'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário digita/filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $tickets  = $this->filteredTickets($request);
        $users    = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);
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

    // Planejamentos pro select "Planejamento" do form de Ticket — só ativos, só de
    // cliente ativo, restrito ao cliente filtrado quando houver (mesmo recorte de
    // visibleProjects()). Cobre o caso de vincular direto ao Planejamento, sem Projeto.
    private function visibleMacroPlans(Request $request)
    {
        return MacroPlan::where('status', '!=', 'concluido')
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->client_id))
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);
    }

    private function filteredTickets(Request $request)
    {
        $query = Task::with(['client', 'executor', 'executors', 'project.macroPlan', 'macroPlan', 'meeting'])
            ->where('is_ticket', true)
            // Depois que o chamado é triado pra uma Sprint, ele passa a ser
            // executado por lá — mantê-lo aqui também duplicaria a tarefa nas
            // duas telas. Some daqui automaticamente se saída da sprint reverter.
            ->whereNull('sprint_id')
            // Chamado nasce só aqui (triagem — protege a Fila de coisa ainda não
            // avaliada, já que cai primeiro no Atendimento). Some daqui assim que
            // alguém manda pra Fila (queued_at) — some também naturalmente se for
            // direto pra uma Sprint (sprint_id, acima), sem nunca passar pela Fila.
            ->whereNull('queued_at')
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

    public function create(Request $request)
    {
        $clients    = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users      = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);
        $sprints    = Sprint::whereIn('status', ['planning', 'active'])->orderByDesc('starts_at')->get();
        $projects   = $this->visibleProjects($request);
        $macroplans = $this->visibleMacroPlans($request);

        return view('tickets.create', compact('clients', 'users', 'sprints', 'projects', 'macroplans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:300',
            'description'        => 'nullable|string',
            'task_type'          => 'required|in:' . implode(',', array_keys(Task::$types)),
            'destination'        => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'client_id'          => 'required|uuid|exists:clients,id',
            'project_id'         => 'nullable|uuid|exists:projects,id',
            // Vínculo direto com o Planejamento, sem Projeto — mutuamente exclusivo com
            // project_id (a view já impede escolher os dois; se vier project_id, o
            // planejamento do ticket passa a ser o do projeto, ver abaixo).
            'macro_plan_id'      => 'nullable|uuid|exists:macro_plans,id',
            'executor_id'        => 'nullable|exists:users,id',
            'executor_ids'       => 'nullable|array',
            'executor_ids.*'     => 'exists:users,id',
            'executor_roles'     => ['nullable', 'array', Task::executorRoleCapRule()],
            'executor_roles.*'   => 'in:executor,responsavel,aprovador,observador',
            'responsavel_id'     => 'nullable|exists:users,id',
            'sprint_id'          => 'nullable|uuid|exists:sprints,id',
            'priority'           => 'nullable|in:urgente,medio,normal',
            'due_date'           => 'nullable|date',
            'approval_date'      => 'nullable|date',
            'publish_date'       => 'nullable|date',
            'approval_method'    => 'nullable|in:' . implode(',', array_keys(Task::$approvalMethods)),
            'internal_approval'  => 'nullable|boolean',
            'situation'          => 'nullable|string|max:150',
            'requester_name'     => 'nullable|string|max:150',
            'requester_whatsapp' => 'nullable|string|max:30',
            'requester_channel'  => 'nullable|in:' . implode(',', array_keys(Task::$requesterChannels)),
            'files'              => 'nullable|array',
            'files.*'            => 'file|max:153600', // 150 MB
        ]);

        if (!empty($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless($project->client_id === $data['client_id'], 422, 'Esse projeto/campanha é de outro cliente.');
            // Projeto manda no planejamento — mesmo padrão de TaskController::store().
            $data['macro_plan_id'] = $project->macro_plan_id;
        } elseif (!empty($data['macro_plan_id'])) {
            $macroplan = MacroPlan::findOrFail($data['macro_plan_id']);
            abort_unless($macroplan->client_id === $data['client_id'], 422, 'Esse planejamento é de outro cliente.');
        }

        $task = Task::create([
            ...$data,
            'is_ticket'         => true,
            'origin'            => 'ticket',
            'status'            => 'backlog',
            // Chamado sem projeto/campanha nem planejamento vinculado precisa de triagem
            // manual (fica em Tickets). Já vindo com o "nível do meio" identificado
            // (projeto ou o planejamento direto), pula direto pra Fila.
            'queued_at'         => (!empty($data['project_id']) || !empty($data['macro_plan_id'])) ? now() : null,
            'internal_approval' => $request->boolean('internal_approval'),
            'created_by'        => Auth::id(),
        ]);

        TaskExecutorSync::sync($task, $data);

        // Anexos enviados junto na criação — mesma lógica de TaskAttachmentController::store(),
        // sempre como "insumo" (material de referência; ainda não existe entregável nesse ponto).
        if ($request->hasFile('files')) {
            $disk = config('filesystems.default', 'r2');
            foreach ($request->file('files') as $file) {
                $mimeType = $file->getMimeType();
                $path = $file->store("tasks/{$task->id}", UploadOptions::forStore($mimeType, $disk));
                TaskAttachment::create([
                    'task_id'     => $task->id,
                    'filename'    => $file->getClientOriginalName(),
                    'disk_path'   => $path,
                    'disk'        => $disk,
                    'mime_type'   => $mimeType,
                    'size'        => $file->getSize(),
                    'uploaded_by' => Auth::id(),
                    'kind'        => 'insumo',
                ]);
            }
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

        if ($data['status'] === 'em_producao' && $task->status !== 'em_producao') {
            $execId = TaskExecutor::where('task_id', $task->id)->where('role', 'executor')->value('user_id')
                ?? $task->executor_id;

            if ($reason = Task::wipBlockReason($execId, $task->id)) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => $reason], 422);
                }
                throw ValidationException::withMessages(['status' => $reason]);
            }
        }

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
