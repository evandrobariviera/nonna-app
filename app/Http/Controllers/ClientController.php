<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('company_name', 'ilike', '%' . $request->q . '%')
                  ->orWhere('tax_id', 'ilike', '%' . $request->q . '%')
                  ->orWhere('contact_email', 'ilike', '%' . $request->q . '%')
                  ->orWhere('responsible_name', 'ilike', '%' . $request->q . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $clients = $query->orderBy('company_name')->paginate(25)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.create', ['client' => new Client()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name'            => 'required|string|max:255',
            'tax_id'                  => 'nullable|string|max:20',
            'website'                 => 'nullable|url|max:255',
            'segment'                 => 'nullable|string|max:255',
            'status'                  => 'required|in:lead,active,inactive',
            'monthly_ad_budget'       => 'nullable|string|max:100',
            'contracted_services'     => 'nullable|array',
            'contracted_services.*'   => 'string',
            'contact_email'           => 'nullable|email|max:255',
            'contact_phone'           => 'nullable|string|max:30',
            'address'                 => 'nullable|string',
            'zip_code'                => 'nullable|string|max:10',
            'responsible_name'        => 'nullable|string|max:255',
            'responsible_birthdate'   => 'nullable|date',
            'responsible_rg'          => 'nullable|string|max:30',
            'responsible_cpf'         => 'nullable|string|max:20',
            'responsible_address'     => 'nullable|string',
            'responsible_marital_status' => 'nullable|string|max:30',
            'payment_method'          => 'nullable|in:pix,cartao,boleto',
            'billing_day'             => 'nullable|integer|in:10,15,20',
            'billing_email'           => 'nullable|email|max:255',
            'billing_whatsapp'        => 'nullable|string|max:30',
            'billing_notes'           => 'nullable|string',
            'notes'                   => 'nullable|string',
        ]);

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente criado com sucesso.');
    }

    public function show(Client $client)
    {
        $client->load(['credentials', 'adAccounts', 'dossiers', 'contacts', 'macroplans.projects']);

        // Contatos disponíveis para vincular (excluindo os já vinculados)
        $linkedIds = $client->contacts->pluck('id');
        $availableContacts = \App\Models\Contact::whereNotIn('id', $linkedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'company_name']);

        return view('clients.show', compact('client', 'availableContacts'));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'company_name'            => 'required|string|max:255',
            'tax_id'                  => 'nullable|string|max:20',
            'website'                 => 'nullable|url|max:255',
            'segment'                 => 'nullable|string|max:255',
            'status'                  => 'required|in:lead,active,inactive',
            'monthly_ad_budget'       => 'nullable|string|max:100',
            'contracted_services'     => 'nullable|array',
            'contracted_services.*'   => 'string',
            'contact_email'           => 'nullable|email|max:255',
            'contact_phone'           => 'nullable|string|max:30',
            'address'                 => 'nullable|string',
            'zip_code'                => 'nullable|string|max:10',
            'responsible_name'        => 'nullable|string|max:255',
            'responsible_birthdate'   => 'nullable|date',
            'responsible_rg'          => 'nullable|string|max:30',
            'responsible_cpf'         => 'nullable|string|max:20',
            'responsible_address'     => 'nullable|string',
            'responsible_marital_status' => 'nullable|string|max:30',
            'payment_method'          => 'nullable|in:pix,cartao,boleto',
            'billing_day'             => 'nullable|integer|in:10,15,20',
            'billing_email'           => 'nullable|email|max:255',
            'billing_whatsapp'        => 'nullable|string|max:30',
            'billing_notes'           => 'nullable|string',
            'notes'                   => 'nullable|string',
            'clickup_task_id'         => 'nullable|string|max:50',
        ]);

        $client->update($data);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    public function generateToken(Client $client)
    {
        $token = $client->generateRegistrationToken();

        return back()->with('token_generated', route('clients.register', $token));
    }
}
