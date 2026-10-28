<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LeadController as InternalLeads;
use App\Models\Client;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientLeadSource;
use App\Models\ClientModule;
use App\Models\LeadChannel;
use App\Models\User;
use App\Services\Leads\LeadFunnel;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    private const MODULE_KEY = 'central_leads';

    public function index(Request $request)
    {
        $client = app('currentPortalClient');

        if ($client->moduleStatus(self::MODULE_KEY) !== 'ativo') {
            $module = ClientModule::where('client_id', $client->id)->where('module_key', self::MODULE_KEY)->first();

            return view('portal.leads.upsell', compact('client', 'module'));
        }

        $view = $request->get('view', 'lista');
        $filterOptions = $this->filterOptions($client);
        $showClosed = InternalLeads::showClosed($request);
        $canConfigure = $client->isPrimaryContact($this->contactId());
        $hideAssigned = ClientModule::leadsHideAssigned($client->id);
        // Resultados somam todos os leads — com "lead atribuído some para os
        // outros" ligado, só o contato principal vê.
        $canSeeResults = !$this->seesOnlyOwn($client);

        if ($view === 'resultados' && $canSeeResults) {
            [$from, $to] = InternalLeads::period($request);
            $funnel = LeadFunnel::forClient($client, $from, $to);

            return view('portal.leads.index', array_merge(compact('view', 'client', 'showClosed', 'canConfigure', 'hideAssigned', 'canSeeResults', 'funnel', 'from', 'to'), $filterOptions));
        }

        $view = $view === 'resultados' ? 'lista' : $view;

        if ($view === 'lista') {
            $leads = $this->filteredQuery($request, $client)->paginate(20)->withQueryString();

            return view('portal.leads.index', array_merge(compact('view', 'leads', 'client', 'showClosed', 'canConfigure', 'hideAssigned', 'canSeeResults'), $filterOptions));
        }

        $board = $this->filteredQuery($request, $client)->get()->groupBy('stage');

        return view('portal.leads.index', array_merge(compact('view', 'board', 'client', 'showClosed', 'canConfigure', 'hideAssigned', 'canSeeResults'), $filterOptions));
    }

    public function results(Request $request)
    {
        $client = app('currentPortalClient');
        $leads  = $this->filteredQuery($request, $client)->paginate(20)->withQueryString();

        return view('leads._results', [
            'leads'        => $leads,
            'showClient'   => false,
            'showAssignee' => true,
            'markNonna'    => true,
            'showRoute'    => 'portal.leads.show',
        ]);
    }

    private function contactId(): string
    {
        return Auth::guard('portal')->id();
    }

    // Com "lead atribuído some para os outros" ligado, quem não é o contato
    // principal vê só os leads dele e os sem responsável.
    private function seesOnlyOwn(Client $client): bool
    {
        return ClientModule::leadsHideAssigned($client->id) && !$client->isPrimaryContact($this->contactId());
    }

    private function authorizeLead(ClientLeadOpportunity $lead, Client $client): void
    {
        abort_if($lead->lead->client_id !== $client->id, 403);
        abort_if($this->seesOnlyOwn($client) && !$lead->isVisibleToContact($this->contactId()), 403);
    }

    // channels/sources escopados manualmente pelo Cliente do Portal — o guard
    // "portal" não passa por SetApiTenant/currentOrganization, então o global
    // scope de Tenantable do LeadChannel não tem org pra filtrar sozinho aqui.
    private function filterOptions(Client $client): array
    {
        return [
            'channels' => LeadChannel::where('organization_id', $client->organization_id)->active()->orderBy('name')->get(),
            'sources'  => ClientLeadSource::where('client_id', $client->id)->where('is_active', true)->orderBy('label')->get(),
        ];
    }

    private function filteredQuery(Request $request, Client $client): Builder
    {
        $query = ClientLeadOpportunity::with(['lead', 'channel', 'source', 'assignedTo', 'assignedContact'])
            ->whereHas('lead', fn ($q) => $q->where('client_id', $client->id))
            ->hideClosedUnless(InternalLeads::showClosed($request))
            ->when($this->seesOnlyOwn($client), fn ($q) => $q->visibleToContact($this->contactId()))
            ->orderByDesc('created_at');

        if ($request->filled('responsavel')) {
            match ($request->responsavel) {
                'sem'   => $query->whereNull('assigned_to')->whereNull('assigned_contact_id'),
                'eu'    => $query->where('assigned_contact_id', $this->contactId()),
                default => null,
            };
        }

        $query->withQuality($request->input('qualidade'));

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
        $client = app('currentPortalClient');
        $this->authorizeLead($lead, $client);

        $lead->load(['lead', 'channel', 'assignedTo', 'assignedContact', 'notes.user', 'notes.contact', 'qualityConfirmedBy:id,name', 'qualityConfirmedContact:id,name']);

        return view('portal.leads.show', [
            'opportunity' => $lead,
            'client'      => $client,
            'clientTeam'  => $client->portalTeam(),
        ]);
    }

    // Responsável e valor da negociação. No Portal só dá pra escolher alguém
    // do próprio time; um responsável da Nonna aparece, mas não é escolhível.
    public function update(Request $request, ClientLeadOpportunity $lead)
    {
        $client = app('currentPortalClient');
        $this->authorizeLead($lead, $client);

        $data = $request->validate([
            'assignee'   => ['sometimes', 'nullable', 'string'],
            'deal_value' => 'sometimes|nullable|numeric|min:0|max:9999999999',
            'quality'    => 'sometimes|nullable|in:' . implode(',', array_keys(ClientLeadOpportunity::$qualities)),
            'outcome'    => 'sometimes|in:' . implode(',', array_keys(ClientLeadOpportunity::$outcomes)),
        ]);

        if ($request->has('assignee') && ($data['assignee'] ?? '') !== $lead->assigneeKey()) {
            $contactId = null;
            if (!empty($data['assignee'])) {
                $contactId = str_starts_with($data['assignee'], 'contact:') ? substr($data['assignee'], 8) : null;
                abort_unless($contactId && $client->portalTeam()->contains('id', $contactId), 422);
            }
            $lead->assign(null, $contactId, null, $this->contactId());
        }

        if ($request->has('outcome')) {
            $lead->confirmOutcome($data['outcome'], isset($data['deal_value']) ? (float) $data['deal_value'] : null, null, $this->contactId());
        } elseif ($request->has('deal_value')) {
            $lead->setDealValue(isset($data['deal_value']) ? (float) $data['deal_value'] : null, null, $this->contactId());
        }

        if ($request->has('quality')) {
            $lead->confirmQuality($data['quality'] ?? null, null, $this->contactId());
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'deal_value' => $lead->deal_value]);
        }

        return back()->with('success', 'Lead atualizado.');
    }

    // Só o contato principal liga/desliga "lead atribuído some para os outros".
    public function updateSettings(Request $request)
    {
        $client = app('currentPortalClient');
        abort_unless($client->isPrimaryContact($this->contactId()), 403);

        $module = ClientModule::where('client_id', $client->id)->where('module_key', self::MODULE_KEY)->firstOrFail();
        $module->update(['settings' => array_merge($module->settings ?? [], ['hide_assigned' => $request->boolean('hide_assigned')])]);

        return back()->with('success', $request->boolean('hide_assigned')
            ? 'Pronto: cada pessoa passa a ver só os leads dela e os sem responsável.'
            : 'Pronto: todo mundo volta a ver todos os leads.');
    }

    public function storeNote(Request $request, ClientLeadOpportunity $lead)
    {
        $client = app('currentPortalClient');
        $this->authorizeLead($lead, $client);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $lead->notes()->create([
            'contact_id' => Auth::guard('portal')->id(),
            'body'       => $data['body'],
        ]);

        return back()->with('success', 'Nota adicionada.')->withFragment('notas');
    }

    public function updateStage(Request $request, ClientLeadOpportunity $lead)
    {
        $client = app('currentPortalClient');
        $this->authorizeLead($lead, $client);

        $data = $request->validate([
            'stage' => 'required|in:' . implode(',', array_keys(ClientLeadOpportunity::$stages)),
        ]);

        $lead->moveToStage($data['stage'], null, $this->contactId());

        // Quem puxa o lead pra "Em Contato" passa a ser o responsável, se ainda não houver.
        if ($data['stage'] === 'em_contato' && !$lead->isAssigned()) {
            $lead->assign(null, $this->contactId(), null, $this->contactId());
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Estágio atualizado.');
    }

    // Vitrine → "Quero contratar": não ativa nada sozinho (decisão de venda
    // continua manual do lado Nonna) — só registra o pedido e avisa a
    // Direção Geral, que decide e ativa o módulo pra esse Cliente.
    public function requestModule()
    {
        $client = app('currentPortalClient');

        $module = ClientModule::firstOrCreate(
            ['client_id' => $client->id, 'module_key' => self::MODULE_KEY],
            ['status' => 'nao_contratado']
        );

        if ($module->status !== 'ativo') {
            $module->update(['requested_at' => now()]);

            $users = User::whereHas('functionalRoles', fn ($q) => $q->where('key', 'direcao_geral'))->get();
            app(SystemNotificationService::class)->send(
                'portal.modulo_solicitado',
                $users,
                [
                    'client_name'  => $client->displayName(),
                    'module_label' => $module->moduleLabel(),
                ],
                route('leads.index', ['client_id' => $client->id]),
                $module,
                $client->organization_id,
            );
        }

        return back()->with('success', 'Pedido enviado! Nosso time vai entrar em contato.');
    }
}
