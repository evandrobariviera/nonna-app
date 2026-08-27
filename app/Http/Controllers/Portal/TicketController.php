<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\NotificationDispatchService;
use App\Support\UploadOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $client = app('currentPortalClient');

        $query = Task::where('is_ticket', true)
            ->where('client_id', $client->id)
            ->orderByDesc('created_at');

        if (!$request->boolean('mostrar_fechados')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        $tickets = $query->get();

        return view('portal.tickets.index', compact('client', 'tickets'));
    }

    public function show(Task $task): View
    {
        $client = app('currentPortalClient');

        abort_if(!$task->is_ticket || $task->client_id !== $client->id, 403);

        $task->load('comments.user', 'comments.contact');
        $deliverables = $task->attachments()->where('is_deliverable', true)->get();

        return view('portal.tickets.show', compact('client', 'task', 'deliverables'));
    }

    public function create(): View
    {
        $client = app('currentPortalClient');

        return view('portal.tickets.create', compact('client'));
    }

    public function store(Request $request): RedirectResponse
    {
        $client = app('currentPortalClient');
        $contact = Auth::guard('portal')->user();

        $data = $request->validate([
            'title'       => 'required|string|max:300',
            'description' => 'nullable|string|max:5000',
            'task_type'   => 'required|in:' . implode(',', array_keys(Task::$types)),
            'priority'    => 'nullable|in:' . implode(',', array_keys(Task::$priorities)),
            'files'       => 'nullable|array',
            'files.*'     => 'file|max:153600', // 150 MB
        ]);

        $task = Task::create([
            ...collect($data)->except('files')->all(),
            'organization_id' => $client->organization_id,
            'client_id'       => $client->id,
            'is_ticket'       => true,
            'origin'          => 'ticket',
            'status'          => 'backlog',
            'requester_name'  => $contact->name,
            'contact_id'      => $contact->id,
        ]);

        // Anexos enviados junto na abertura — mesma lógica de TicketController::store()
        // (interno), sempre como "insumo"; só que aqui quem enviou é um Contact, não um
        // User (uploaded_by_contact_id, ver migration 2026_08_12_134434).
        if ($request->hasFile('files')) {
            $disk = config('filesystems.default', 'r2');
            foreach ($request->file('files') as $file) {
                $mimeType = $file->getMimeType();
                $path = $file->store("tasks/{$task->id}", UploadOptions::forStore($mimeType, $disk));
                TaskAttachment::create([
                    'task_id'                => $task->id,
                    'filename'                => $file->getClientOriginalName(),
                    'disk_path'               => $path,
                    'disk'                    => $disk,
                    'mime_type'               => $mimeType,
                    'size'                    => $file->getSize(),
                    'uploaded_by_contact_id'  => $contact->id,
                    'kind'                    => 'insumo',
                ]);
            }
        }

        app(NotificationDispatchService::class)->send('chamado_aberto', $client, [
            'chamado_titulo' => $task->title,
            'link_chamado'   => route('portal.tickets.show', $task),
        ]);

        return redirect()->route('portal.tickets.show', $task)->with('success', 'Chamado aberto com sucesso.');
    }
}
