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
        $view = $request->get('view', 'lista');
        $filterOptions = $this->filterOptions();
        $showClosed = self::showClosed($request);

        if ($view === 'lista') {
            $leads = $this->filteredQuery($request)->paginate(30)->withQueryString();

            return view('leads.index', array_merge(compact('view', 'leads', 'showClosed'), $filterOptions));
        }

        $board = $this->filteredQuery($request)->get()->groupBy('stage');

        return view('leads.index', array_merge(compact('view', 'board', 'showClosed'), $filterOptions));
    }

    // Ganhos e perdidos ficam escondidos, a menos que pedidos ou filtrados pelo estágio.
    public static function showClosed(Request $request): bool
    {
        return $request->boolean('fechados') || in_array($request->stage, ClientLeadOpportunity::CLOSED_STAGES, true);
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $leads = $this->filteredQuery($request)->paginate(30)->withQueryString();

        return view('leads._results', [
            'leads'               => $leads,
            'showClient'          => true,
            'showAssignee'        => true,
            'showRoute'           => 'leads.show',
            'unnotifiedClientIds' => ClientLeadSource::clientIdsWithoutLeadSubscriber(),
        ]);
    }

    private function filterOptions(): array
    {
        return [
            'clients'  => Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']),
            'channels' => LeadChannel::active()->orderBy('name')->get(),
            'sources'  => ClientLeadSource::with('client')->where('is_active', true)->orderBy('label')->get(),
            // Ícone de alerta ao lado do cliente (lista e quadro).
            'unnotifiedClientIds' => ClientLeadSource::clientIdsWithoutLeadSubscriber(),
        ];
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = ClientLeadOpportunity::with(['lead.client', 'channel', 'source', 'assignedTo', 'assignedContact'])
            ->hideClosedUnless(self::showClosed($request))
            ->orderByDesc('created_at');

        if ($request->filled('responsavel')) {
            match ($request->responsavel) {
                'sem'   => $query->whereNull('assigned_to')->whereNull('assigned_contact_id'),
                'eu'    => $query->where('assigned_to', Auth::id()),
                default => null,
            };
        }

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
        $lead->load(['lead.client', 'channel', 'source', 'ad:id,name', 'campaign:id,name', 'conversation:id,message_count,last_message_at', 'assignedTo', 'assignedContact', 'createdBy', 'notes.user', 'notes.contact', 'notificationDispatches']);

        $users = User::orderBy('name')->get(['id', 'name']);
        $clientTeam = $lead->lead->client?->portalTeam() ?? collect();

        $clientWithoutSubscriber = $lead->lead->client_id
            && in_array($lead->lead->client_id, ClientLeadSource::clientIdsWithoutLeadSubscriber(), true);

        return view('leads.show', [
            'opportunity'             => $lead,
            'users'                   => $users,
            'clientTeam'              => $clientTeam,
            'clientWithoutSubscriber' => $clientWithoutSubscriber,
        ]);
    }

    public function update(Request $request, ClientLeadOpportunity $lead)
    {
        $data = $request->validate([
            'assignee'    => ['sometimes', 'nullable', 'string', 'regex:/^(user:\d+|contact:[0-9a-f-]{36})$/'],
            'deal_value'  => 'sometimes|nullable|numeric|min:0|max:9999999999',
            'lost_reason' => 'sometimes|nullable|string|max:500',
        ]);

        if ($request->has('assignee')) {
            [$userId, $contactId] = $this->parseAssignee($data['assignee'] ?? null, $lead);
            $lead->assign($userId, $contactId, Auth::id(), null);
        }

        if ($request->has('deal_value')) {
            $lead->setDealValue(isset($data['deal_value']) ? (float) $data['deal_value'] : null, Auth::id(), null);
        }

        if ($request->has('lost_reason')) {
            $lead->update(['lost_reason' => $data['lost_reason'] ?? null]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'deal_value' => $lead->deal_value]);
        }

        return back()->with('success', 'Lead atualizado.');
    }

    // "user:{id}" (time da Nonna) ou "contact:{uuid}" (time do cliente, só quem entra no Portal dele).
    private function parseAssignee(?string $assignee, ClientLeadOpportunity $lead): array
    {
        if (!$assignee) {
            return [null, null];
        }

        [$type, $id] = explode(':', $assignee, 2);

        if ($type === 'user') {
            abort_unless(User::whereKey($id)->exists(), 422);

            return [(int) $id, null];
        }

        abort_unless($lead->lead->client?->portalTeam()->contains('id', $id), 422);

        return [null, $id];
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

        // Quem puxa o lead pra "Em Contato" passa a ser o responsável, se ainda não houver.
        if ($data['stage'] === 'em_contato' && !$lead->isAssigned()) {
            $lead->assign(Auth::id(), null, Auth::id(), null);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Estágio atualizado.');
    }
}
