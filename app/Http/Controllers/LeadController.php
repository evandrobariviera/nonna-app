<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientLeadOpportunity;
use App\Models\User;
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
        $clients = Client::where('status', 'active')
            ->orderByRaw('COALESCE(nickname, company_name)')
            ->get(['id', 'company_name', 'nickname']);

        $query = ClientLeadOpportunity::with(['lead.client', 'channel'])
            ->orderByDesc('created_at');

        if ($request->filled('client_id')) {
            if ($request->client_id === self::UNMATCHED_SENTINEL) {
                $query->whereHas('lead', fn ($q) => $q->whereNull('client_id'));
            } else {
                $query->whereHas('lead', fn ($q) => $q->where('client_id', $request->client_id));
            }
        }

        $board = $query->get()->groupBy('stage');

        return view('leads.index', compact('board', 'clients'));
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
