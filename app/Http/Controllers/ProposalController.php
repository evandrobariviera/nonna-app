<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Services\Proposals\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Central de Comércio → Propostas. A montagem (create/edit) é uma tela Alpine que recalcula
 * a cada mudança em preview(); store/update/preview falam JSON (validação = 422, nunca 302).
 * O resto (enviar, liberar, nova versão, aceitar, recusar) são forms comuns.
 */
class ProposalController extends Controller
{
    public function __construct(private ProposalService $service) {}

    public function index(Request $request): View
    {
        $status = $request->get('situacao');
        $proposals = Proposal::with('opportunity.contact')
            ->when(isset(Proposal::$statuses[$status]), fn ($q) => $q->where('status', $status))
            ->latest('updated_at')
            ->paginate(30)->withQueryString();

        $pendingRelease = Proposal::where('status', 'pending_release')->count();

        return view('proposals.index', compact('proposals', 'status', 'pendingRelease'));
    }

    public function create(Request $request): View
    {
        $opportunity = Opportunity::with('contact')->find($request->get('oportunidade'));

        return view('proposals.builder', $this->builderData(new Proposal(), $opportunity));
    }

    public function edit(Proposal $proposal): View|RedirectResponse
    {
        if (!$proposal->isEditable()) {
            return redirect()->route('proposals.show', $proposal);
        }
        $proposal->load('items', 'opportunity.contact');

        return view('proposals.builder', $this->builderData($proposal, $proposal->opportunity));
    }

    public function show(Proposal $proposal): View
    {
        $proposal->load('items', 'opportunity.contact', 'createdBy', 'releasedBy', 'parent');

        return view('proposals.show', compact('proposal'));
    }

    public function preview(Request $request): JsonResponse
    {
        $result = $this->service->preview((array) $request->input('state', []));

        return response()->json([
            'calc'     => $result['calc'],
            'upgrade'  => $result['upgrade'],
            'errors'   => $result['errors'],
            'warnings' => $result['warnings'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Para quem: oportunidade existente OU nova (com contato existente ou contato novo)
        $target = $request->input('target', 'opportunity');
        $v = Validator::make($request->all(), [
            'title'                     => 'nullable|string|max:160',
            'state'                     => 'required|array',
            'target'                    => 'nullable|in:opportunity,contact,new_contact',
            'opportunity_id'            => 'exclude_unless:target,opportunity|required|uuid|exists:opportunities,id',
            'new.contact_id'            => 'exclude_unless:target,contact|required|uuid|exists:contacts,id',
            'new.contact.name'          => 'exclude_unless:target,new_contact|required|string|max:255',
            'new.contact.company_name'  => 'exclude_unless:target,new_contact|nullable|string|max:255',
            'new.contact.email'         => 'exclude_unless:target,new_contact|nullable|email|max:255',
            'new.contact.whatsapp'      => 'exclude_unless:target,new_contact|nullable|string|max:30',
            'new.contact.source'        => ['exclude_unless:target,new_contact', 'required', 'in:' . implode(',', array_keys(Contact::$sources))],
            'new.title'                 => 'nullable|string|max:255',
            'new.type'                  => 'nullable|in:' . implode(',', array_keys(Opportunity::$types)),
        ], [
            'opportunity_id.required'   => 'Escolha a oportunidade desta proposta.',
            'new.contact_id.required'   => 'Escolha o contato desta proposta.',
            'new.contact.name.required' => 'Informe o nome do contato.',
            'new.contact.source.required' => 'Informe de onde veio o contato.',
            'new.contact.email.email'   => 'O e-mail do contato não parece válido.',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()->all()], 422);
        }

        // Contato/oportunidade novos e proposta numa transação só: proposta inválida não deixa órfãos
        try {
            [$saved, $notice] = DB::transaction(function () use ($request, $target) {
                $notice = null;
                if ($target === 'opportunity') {
                    $opportunity = Opportunity::findOrFail($request->input('opportunity_id'));
                } else {
                    [$opportunity, $notice] = $this->service->createOpportunity((array) $request->input('new'), Auth::id());
                }
                $saved = $this->service->save(new Proposal(), $opportunity, (array) $request->input('state'), $request->input('title'), Auth::id());

                return [$saved, $notice];
            });
        } catch (ValidationException $e) {
            return response()->json(['errors' => collect($e->errors())->flatten()->all()], 422);
        }

        if ($notice) {
            session()->flash('success', $notice);
        }

        return response()->json(['redirect' => route('proposals.show', $saved)]);
    }

    public function update(Request $request, Proposal $proposal): JsonResponse
    {
        $v = Validator::make($request->all(), ['title' => 'nullable|string|max:160', 'state' => 'required|array']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()->all()], 422);
        }

        return $this->persist($proposal, $proposal->opportunity, $request);
    }

    public function send(Proposal $proposal): RedirectResponse
    {
        $result = $this->service->send($proposal);

        return redirect()->route('proposals.show', $proposal)->with('success', $result === 'sent'
            ? 'Proposta marcada como enviada. Esta versão agora está congelada.'
            : 'O desconto precisa de liberação. A proposta ficou aguardando um administrador.');
    }

    public function release(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_unless($proposal->status === 'pending_release', 422);
        $this->service->release($proposal, Auth::id(), $request->input('note'));

        return redirect()->route('proposals.show', $proposal)->with('success', 'Desconto liberado. A proposta já pode ser enviada.');
    }

    public function newVersion(Proposal $proposal): RedirectResponse
    {
        $copy = $this->service->newVersion($proposal, Auth::id());

        return redirect()->route('proposals.edit', $copy)->with('success', "Versão {$copy->version} criada a partir da v{$proposal->version}.");
    }

    public function accept(Proposal $proposal): RedirectResponse
    {
        $this->service->accept($proposal);
        $opp = $proposal->opportunity;

        // Próximo passo é fechar o negócio — abre o "Fechar Negócio" da oportunidade
        return redirect()->route('opportunities.show', [$opp, 'ganhar' => $opp->isOpen() ? 1 : null])
            ->with('success', 'Proposta aceita. Agora é só fechar o negócio.');
    }

    public function reject(Proposal $proposal): RedirectResponse
    {
        $this->service->reject($proposal);

        return redirect()->route('proposals.show', $proposal)->with('success', 'Proposta marcada como recusada.');
    }

    private function persist(Proposal $proposal, Opportunity $opportunity, Request $request): JsonResponse
    {
        try {
            $saved = $this->service->save($proposal, $opportunity, (array) $request->input('state'), $request->input('title'), Auth::id());
        } catch (ValidationException $e) {
            return response()->json(['errors' => collect($e->errors())->flatten()->all()], 422);
        }

        return response()->json(['redirect' => route('proposals.show', $saved)]);
    }

    private function builderData(Proposal $proposal, ?Opportunity $opportunity): array
    {
        $catalog = CatalogItem::active()->with('components:id,name')->ordered()->get();

        $packages = $catalog->filter->isPackage()->map(fn (CatalogItem $p) => $this->service->packageInput($p) + [
            'description' => $p->description,
            'line'        => $p->line,
            'value_status'=> $p->value_status,
        ])->values();

        $items = $catalog->reject->isPackage()->map(fn (CatalogItem $i) => [
            'id'           => $i->id,
            'name'         => $i->name,
            'line'         => $i->line,
            'kind'         => $i->kind,
            'price'        => (float) ($i->kind === 'monthly' ? $i->monthly_price : $i->one_time_price),
            'starting'     => $i->price_is_starting,
            'description'  => $i->description,
            'value_status' => $i->value_status,
            'rules'        => $i->rules ?? (object) [],
        ])->values();

        // Item de proposta antiga que saiu do catálogo continua aparecendo pra quem edita
        foreach ($proposal->items ?? [] as $pi) {
            if ($pi->catalog_item_id && !$items->contains('id', $pi->catalog_item_id) && ($ci = CatalogItem::find($pi->catalog_item_id))) {
                $items->push(['id' => $ci->id, 'name' => $ci->name, 'line' => $ci->line, 'kind' => $ci->kind,
                    'price' => (float) ($ci->kind === 'monthly' ? $ci->monthly_price : $ci->one_time_price), 'starting' => $ci->price_is_starting,
                    'description' => $ci->description, 'value_status' => $ci->value_status, 'rules' => $ci->rules ?? (object) []]);
            }
        }

        $state = [
            'package_id'   => $proposal->package_id,
            'lines'        => ($proposal->items ?? collect())->map(fn ($i) => ['catalog_item_id' => $i->catalog_item_id, 'quantity' => $i->quantity, 'unit_price' => (float) $i->unit_price])->filter(fn ($l) => $l['catalog_item_id'])->values(),
            'ad_budget'    => $proposal->ad_budget !== null ? (float) $proposal->ad_budget : null,
            'discount'     => (float) ($proposal->discount_percent ?? 0),
            'installments' => $proposal->installments ?? 1,
            'group'        => [
                'on'       => (bool) $proposal->group_on,
                'name'     => $proposal->group_name ?? '',
                'mode'     => $proposal->group_mode ?? 'sum',
                'monthly'  => (float) ($proposal->group_monthly ?? 0),
                'one_time' => (float) ($proposal->group_one_time ?? 0),
            ],
        ];

        $opportunities = $opportunity ? collect() : Opportunity::with('contact:id,name,company_name')
            ->whereNotIn('stage', ['ganho', 'perdido'])->latest()->get(['id', 'title', 'contact_id'])
            ->map(fn ($o) => ['id' => $o->id, 'label' => $o->title . ($o->contact ? ' · ' . $o->contact->name : '')])->values();
        $contacts = $opportunity ? collect() : Contact::orderBy('name')->get(['id', 'name', 'company_name'])
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name . ($c->company_name ? ' · ' . $c->company_name : ''), 'company' => $c->company_name ?: $c->name])->values();
        $preselectContact = $opportunity ? null : request('contato');

        return compact('proposal', 'opportunity', 'opportunities', 'contacts', 'preselectContact', 'packages', 'items', 'state');
    }
}
