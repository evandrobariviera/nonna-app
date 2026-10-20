<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Pendency;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\PendencyService;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

// Central de Pendências + ações do bloco "Pendências" (Tarefa/Ticket, Projeto,
// Planejamento). Regras em PendencyService.
class PendencyController extends Controller
{
    public function __construct(private PendencyService $service) {}

    public function index(Request $request)
    {
        $data    = $this->boardData($request);
        $clients = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users   = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);

        // Contatos por cliente pro seletor "Deve: cliente" (nova avulsa e o form único de
        // editar da página) — 1 query só, o form filtra no navegador pelo cliente.
        $newFormContacts = ClientContact::query()
            ->join('contacts', 'contacts.id', '=', 'client_contacts.contact_id')
            ->whereIn('client_contacts.client_id', $clients->pluck('id'))
            ->orderBy('contacts.name')
            ->get(['client_contacts.client_id', 'contacts.id', 'contacts.name'])
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values());

        return view('pendencies.index', array_merge($data, compact('clients', 'users', 'newFormContacts')));
    }

    public function results(Request $request)
    {
        return view('pendencies._results', $this->boardData($request));
    }

    private function boardData(Request $request): array
    {
        $view = $request->get('ver') === 'resolvidas' ? 'resolvidas' : 'abertas';

        $query = Pendency::with([
            'client:id,company_name,nickname',
            'pendable',
            'owedContact:id,name,whatsapp,phone',
            'requester:id,name',
            'owedUser:id,name',
            'owner:id,name,avatar_path,avatar_disk',
            'resolvedBy:id,name',
        ]);

        if ($view === 'abertas') {
            // Status dentro das abertas: só as que ninguém cobrou ainda, ou só as já cobradas.
            $status = $request->get('status');
            in_array($status, ['aberta', 'cobrada'], true) ? $query->where('status', $status) : $query->open();
            $query
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderBy('created_at');
        } else {
            // Histórico: as 150 últimas fechadas bastam pra consulta rápida.
            $query->whereIn('status', ['resolvida', 'cancelada'])
                ->orderByDesc('resolved_at')
                ->limit(150);
        }

        $owner = $request->get('responsavel', 'todos');
        if ($owner === 'meus') {
            $query->forUser((int) Auth::id()); // cobro OU devo entregar
        } elseif ($owner === 'pedi') {
            $query->where('requested_by', Auth::id());
        } elseif (ctype_digit((string) $owner)) {
            $query->where('owner_id', (int) $owner);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->get('client_id'));
        }
        if ($request->filled('kind') && isset(Pendency::$kinds[$request->get('kind')])) {
            $query->where('kind', $request->get('kind'));
        }
        if ($request->boolean('vencidas')) {
            $query->whereNotNull('due_date')->where('due_date', '<', today());
        }
        // Cliente inativo some por padrão (filtro de leitura, igual Fila/Planejamentos).
        // Pendência sem cliente nenhum continua aparecendo.
        if (!$request->boolean('mostrar_inativos')) {
            $query->where(fn ($q) => $q->whereNull('client_id')
                ->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')));
        }

        $pendencies = $query->get();
        $columns    = collect(array_keys(Pendency::$owedBy))
            ->mapWithKeys(fn ($k) => [$k => $pendencies->where('owed_by', $k)->values()]);

        return [
            'view'         => $view,
            'columns'      => $columns,
            'overdueCount' => $view === 'abertas' ? $pendencies->filter->isOverdue()->count() : 0,
            'totalCount'   => $pendencies->count(),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('pendency', $this->rules() + [
            'pendable_type' => ['nullable', Rule::in(array_keys(Pendency::PENDABLE_TYPES))],
            'pendable_id'   => 'nullable|required_with:pendable_type|uuid',
            'client_id'     => 'nullable|required_without:pendable_type|uuid|exists:pgsql.clients,id',
        ], [
            'client_id.required_without' => 'Escolha o cliente da pendência.',
        ]);

        $pendable = null;
        if (!empty($data['pendable_type'])) {
            $class    = Pendency::PENDABLE_TYPES[$data['pendable_type']];
            $pendable = $class::findOrFail($data['pendable_id']);
        }
        unset($data['pendable_type'], $data['pendable_id']);

        $this->service->create($data, $pendable, Auth::id());

        return $this->done($request, 'Pendência registrada.');
    }

    /** Comentário da tarefa vira pendência num clique (substitui o antigo "Item de ação"). */
    public function storeFromComment(Request $request, Task $task)
    {
        $data = $request->validate([
            'comment_id'   => 'required|uuid|exists:pgsql.task_comments,id',
            'owed_by'      => ['required', Rule::in(['cliente', 'agencia'])],
            'owed_user_id' => 'nullable|required_if:owed_by,agencia|exists:pgsql.users,id',
        ]);

        $comment = TaskComment::findOrFail($data['comment_id']);
        abort_unless($comment->task_id === $task->id, 403);

        $title = RichTextSanitizer::toPlainText($comment->body);
        if ($title === null || trim($title) === '') {
            return back()->with('warning', 'Não foi possível criar a pendência — comentário sem texto.');
        }

        $this->service->create([
            'source_comment_id' => $comment->id,
            'title'             => $title,
            'kind'              => 'outro',
            'owed_by'           => $data['owed_by'],
            'owed_user_id'      => $data['owed_user_id'] ?? null,
        ], $task, Auth::id());

        return $this->done($request, 'Pendência criada a partir do comentário.');
    }

    public function update(Request $request, Pendency $pendency)
    {
        $data = $request->validateWithBag('pendency', $this->rules());
        $this->service->update($pendency, $data, Auth::id());

        return $this->done($request, 'Pendência atualizada.');
    }

    public function nudge(Request $request, Pendency $pendency)
    {
        abort_unless($pendency->canChase((int) Auth::id(), $this->isAdmin()), 403, 'Só quem cobra registra a cobrança.');
        $data = $request->validate(['channel' => ['required', Rule::in(array_keys(\App\Models\PendencyNudge::$channels))]]);

        $this->service->nudge($pendency, (int) Auth::id(), $data['channel']);

        return $this->done($request, $data['channel'] === 'lembrete' ? 'Lembrete enviado.' : 'Cobrança registrada.');
    }

    public function remind(Request $request, Pendency $pendency)
    {
        abort_unless($pendency->canRemindOwner((int) Auth::id(), $this->isAdmin()), 403);
        $this->service->remindOwner($pendency, (int) Auth::id());

        return $this->done($request, 'Lembrete enviado pra ' . ($pendency->owner?->name ?? 'quem cobra') . '.');
    }

    private function isAdmin(): bool
    {
        return in_array(app()->has('currentOrgRole') ? app('currentOrgRole') : null, ['owner', 'admin'], true);
    }

    public function resolve(Request $request, Pendency $pendency)
    {
        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);
        $this->service->resolve($pendency, Auth::id(), $data['resolution_note'] ?? null);

        return $this->done($request, 'Pendência resolvida.');
    }

    public function cancel(Request $request, Pendency $pendency)
    {
        $this->service->cancel($pendency, Auth::id());

        return $this->done($request, 'Pendência cancelada.');
    }

    public function reopen(Request $request, Pendency $pendency)
    {
        $this->service->reopen($pendency);

        return $this->done($request, 'Pendência reaberta.');
    }

    public function destroy(Request $request, Pendency $pendency)
    {
        $this->service->delete($pendency);

        return $this->done($request, 'Pendência excluída.');
    }

    /**
     * Volta pra onde o form pediu (return_to — ex.: bloco Pendências do Planejamento),
     * só se for URL do próprio App; senão, página anterior.
     */
    private function done(Request $request, string $message)
    {
        $to = (string) $request->input('return_to');
        $redirect = $to !== '' && str_starts_with($to, url('/') . '/') ? redirect()->to($to) : back();

        return $redirect->with('success', $message);
    }

    private function rules(): array
    {
        return [
            'title'           => 'required|string|max:500',
            'description'     => 'nullable|string|max:5000',
            'kind'            => ['required', Rule::in(array_keys(Pendency::$kinds))],
            'owed_by'         => ['required', Rule::in(array_keys(Pendency::$owedBy))],
            'owed_contact_id' => 'nullable|uuid|exists:pgsql.contacts,id',
            'owed_user_id'    => 'nullable|exists:pgsql.users,id',
            'owed_party'      => 'nullable|string|max:255',
            'owner_id'        => 'nullable|exists:pgsql.users,id',
            'requested_by'    => 'nullable|exists:pgsql.users,id',
            'due_date'        => 'nullable|date',
        ];
    }
}
