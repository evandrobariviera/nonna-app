<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientLeadSource;
use App\Models\LeadChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    // Sentinela pro filtro "sem cliente identificado" (leads em triagem, sem
    // match de client_lead_sources na captura) — precisa de valor não-vazio
    // pra não sumir da query string do <select> (mesmo padrão de outras telas
    // com filtro "Todos").
    private const UNMATCHED_SENTINEL = 'sem_cliente';

    public function index(Request $request)
    {
        $view = $request->get('view', 'kanban');
        $filterOptions = $this->filterOptions();

        if ($view === 'lista') {
            $leads = $this->filteredQuery($request)->paginate(30)->withQueryString();

            return view('leads.index', array_merge(compact('view', 'leads'), $filterOptions));
        }

        $board = $this->filteredQuery($request)->get()->groupBy('stage');

        return view('leads.index', array_merge(compact('view', 'board'), $filterOptions));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $leads = $this->filteredQuery($request)->paginate(30)->withQueryString();

        return view('leads._results', [
            'leads'        => $leads,
            'showClient'   => true,
            'showAssignee' => true,
            'showRoute'    => 'leads.show',
        ]);
    }

    private function filterOptions(): array
    {
        return [
            'clients'  => Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']),
            'channels' => LeadChannel::active()->orderBy('name')->get(),
            'sources'  => ClientLeadSource::with('client')->where('is_active', true)->orderBy('label')->get(),
        ];
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = ClientLeadOpportunity::with(['lead.client', 'channel', 'source', 'assignedTo'])
            ->orderByDesc('created_at');

        if ($request->filled('client_id')) {
            if ($request->client_id === self::UNMATCHED_SENTINEL) {
                $query->whereHas('lead', fn ($q) => $q->whereNull('client_id'));
            } else {
                $query->whereHas('lead', fn ($q) => $q->where('client_id', $request->client_id));
            }
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->filled('channel_id')) {
            $query->where('lead_channel_id', $request->channel_id);
        }

        if ($request->filled('source_id')) {
            $query->where('client_lead_source_id', $request->source_id);
        }

        if ($request->filled('utm_source')) {
            $query->where('utm_source', 'ilike', '%' . $request->utm_source . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('q')) {
            $query->whereHas('lead', function ($q) use ($request) {
                $q->where('name', 'ilike', '%' . $request->q . '%')
                    ->orWhere('phone', 'ilike', '%' . $request->q . '%')
                    ->orWhere('email', 'ilike', '%' . $request->q . '%');
            });
        }

        return $query;
    }

    public function show(ClientLeadOpportunity $lead)
    {
        $lead->load(['lead.client', 'channel', 'source', 'assignedTo', 'createdBy', 'notes.user', 'notes.contact']);

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('leads.show', ['opportunity' => $lead, 'users' => $users]);
    }

    public function update(Request $request, ClientLeadOpportunity $lead)
    {
        $data = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'lost_reason' => 'nullable|string|max:500',
        ]);

        $lead->update($data);

        return back()->with('success', 'Lead atualizado.');
    }

    public function storeNote(Request $request, ClientLeadOpportunity $lead)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $lead->notes()->create([
            'user_id' => Auth::id(),
            'body'    => $data['body'],
        ]);

        return back()->with('success', 'Nota adicionada.')->withFragment('notas');
    }

    public function updateStage(Request $request, ClientLeadOpportunity $lead)
    {
        $data = $request->validate([
            'stage' => 'required|in:' . implode(',', array_keys(ClientLeadOpportunity::$stages)),
        ]);

        $fromStage = $lead->stage;
        $updates   = ['stage' => $data['stage']];
        if ($data['stage'] === 'ganho' && !$lead->won_at) {
            $updates['won_at'] = now();
        }
        if ($data['stage'] === 'perdido' && !$lead->lost_at) {
            $updates['lost_at'] = now();
        }

        $lead->update($updates);

        if ($fromStage !== $data['stage']) {
            $lead->logStageChange($fromStage, $data['stage'], Auth::id(), null);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Estágio atualizado.');
    }
}
