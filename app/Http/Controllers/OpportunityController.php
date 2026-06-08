<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $stages = Opportunity::$stages;

        $opportunities = Opportunity::with('contact')
            ->whereNotIn('stage', ['ganho', 'perdido'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('stage');

        $closed = Opportunity::with('contact', 'client')
            ->whereIn('stage', ['ganho', 'perdido'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return view('opportunities.index', compact('stages', 'opportunities', 'closed'));
    }

    public function create(Request $request)
    {
        $contact = $request->contact_id
            ? Contact::find($request->contact_id)
            : null;

        $contacts = Contact::orderBy('name')->get();

        return view('opportunities.create', compact('contact', 'contacts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_id'         => 'required|exists:contacts,id',
            'title'              => 'required|string|max:255',
            'services_interest'  => 'nullable|array',
            'proposed_fee'       => 'nullable|numeric|min:0',
            'proposed_ad_budget' => 'nullable|numeric|min:0',
            'proposal_url'       => 'nullable|url|max:500',
            'contract_months'    => 'nullable|integer|min:1|max:60',
            'notes'              => 'nullable|string',
        ]);

        $data['stage']      = 'novo_lead';
        $data['created_by'] = Auth::id();

        $opportunity = Opportunity::create($data);

        // Update contact status
        Contact::find($data['contact_id'])->update(['status' => 'em_negociacao']);

        return redirect()->route('opportunities.index')
            ->with('success', 'Oportunidade criada.');
    }

    public function show(Opportunity $opportunity)
    {
        $opportunity->load('contact', 'client');
        return view('opportunities.show', compact('opportunity'));
    }

    public function updateStage(Request $request, Opportunity $opportunity)
    {
        $request->validate([
            'stage' => 'required|in:' . implode(',', array_keys(Opportunity::$stages)),
        ]);

        $opportunity->update(['stage' => $request->stage]);

        // Update contact status to match opportunity stage
        $statusMap = [
            'ganho'   => 'ganho',
            'perdido' => 'perdido',
        ];
        if (isset($statusMap[$request->stage])) {
            $opportunity->contact->update(['status' => $statusMap[$request->stage]]);
        }

        return response()->json(['ok' => true]);
    }

    public function win(Request $request, Opportunity $opportunity)
    {
        $data = $request->validate([
            'company_name'       => 'required|string|max:255',
            'contracted_services'=> 'nullable|array',
            'monthly_ad_budget'  => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
        ]);

        DB::transaction(function () use ($opportunity, $data, $request) {
            // Create client
            $client = Client::create([
                'company_name'        => $data['company_name'],
                'contracted_services' => $data['contracted_services'] ?? [],
                'monthly_ad_budget'   => $data['monthly_ad_budget'],
                'notes'               => $data['notes'],
                'status'              => 'active',
                'contact_email'       => $opportunity->contact->email,
                'contact_phone'       => $opportunity->contact->phone,
                'responsible_name'    => $opportunity->contact->name,
            ]);

            // Link contact to client
            $client->contacts()->attach($opportunity->contact_id, [
                'role'       => $opportunity->contact->job_title ?? 'Responsável',
                'is_primary' => true,
            ]);

            // Update opportunity
            $opportunity->update([
                'stage'     => 'ganho',
                'client_id' => $client->id,
                'won_at'    => now(),
            ]);

            // Update contact status
            $opportunity->contact->update(['status' => 'ganho']);
        });

        return redirect()->route('clients.show', $opportunity->fresh()->client)
            ->with('success', 'Negócio fechado! Cliente criado com sucesso.');
    }

    public function lose(Request $request, Opportunity $opportunity)
    {
        $request->validate([
            'lost_reason' => 'nullable|string|max:500',
        ]);

        $opportunity->update([
            'stage'       => 'perdido',
            'lost_reason' => $request->lost_reason,
            'lost_at'     => now(),
        ]);

        $opportunity->contact->update(['status' => 'perdido']);

        return redirect()->route('opportunities.index')
            ->with('success', 'Oportunidade encerrada como perdida.');
    }

    public function edit(Opportunity $opportunity)
    {
        $contacts = Contact::orderBy('name')->get();
        return view('opportunities.edit', compact('opportunity', 'contacts'));
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'services_interest'  => 'nullable|array',
            'proposed_fee'       => 'nullable|numeric|min:0',
            'proposed_ad_budget' => 'nullable|numeric|min:0',
            'proposal_url'       => 'nullable|url|max:500',
            'contract_months'    => 'nullable|integer|min:1|max:60',
            'notes'              => 'nullable|string',
            'assigned_to'        => 'nullable|exists:users,id',
        ]);

        $opportunity->update($data);

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Oportunidade atualizada.');
    }
}
