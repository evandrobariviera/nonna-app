<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = $this->filteredClients($request);

        return view('clients.index', compact('clients'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário digita/filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $clients = $this->filteredClients($request);

        return view('clients._results', compact('clients'));
    }

    private function filteredClients(Request $request)
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

        return $query->orderBy('company_name')->paginate(25)->withQueryString();
    }

    public function create()
    {
        return view('clients.create', ['client' => new Client()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name'            => 'required|string|max:255',
            'logo'                    => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
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

        $logo = $request->file('logo');
        unset($data['logo']);

        $client = Client::create($data);

        if ($logo) {
            $disk = config('filesystems.default', 'r2');
            $path = $logo->store("clients/{$client->id}/logo", $disk);
            $client->update(['logo_path' => $path, 'logo_disk' => $disk]);
        }

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente criado com sucesso.');
    }

    public function show(Client $client)
    {
        $client->load(['credentials', 'adAccounts', 'dossiers', 'contacts', 'macroplans.projects', 'contracts', 'adBudgets.createdBy', 'integrations']);

        $aiAgents = \App\Models\AiAgent::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Contatos disponíveis para vincular (excluindo os já vinculados)
        $linkedIds = $client->contacts->pluck('id');
        $availableContacts = \App\Models\Contact::whereNotIn('id', $linkedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'company_name']);

        // Assinaturas de comunicação (aprovação, financeiro, feedback, cobrança)
        // por contato vinculado — buscado à parte porque não dá pra eager-load
        // uma relação do pivot (ClientContact->subscriptions) via $client->contacts.
        $subscriptionsByContact = \App\Models\ClientContact::where('client_id', $client->id)
            ->with('subscriptions')
            ->get()
            ->keyBy('contact_id');

        return view('clients.show', compact('client', 'availableContacts', 'subscriptionsByContact', 'aiAgents'));
    }

    /**
     * Preview leve para o painel lateral (canvas) — não a página completa.
     */
    public function preview(Client $client)
    {
        $client->loadCount(['adAccounts', 'macroplans']);
        $client->load(['credentials', 'links', 'contacts', 'dossiers']);

        $recentMacroplans = $client->macroplans()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'status']);

        $contactRoles = \App\Http\Controllers\ClientContactController::$roles;

        return view('clients._preview', compact('client', 'recentMacroplans', 'contactRoles'));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'company_name'            => 'required|string|max:255',
            'logo'                    => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
            'remove_logo'             => 'nullable|boolean',
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

        $logo = $request->file('logo');
        $removeLogo = $request->boolean('remove_logo');
        unset($data['logo'], $data['remove_logo']);

        if ($logo) {
            if ($client->logo_path) {
                Storage::disk($client->logo_disk)->delete($client->logo_path);
            }
            $disk = config('filesystems.default', 'r2');
            $data['logo_path'] = $logo->store("clients/{$client->id}/logo", $disk);
            $data['logo_disk'] = $disk;
        } elseif ($removeLogo && $client->logo_path) {
            Storage::disk($client->logo_disk)->delete($client->logo_path);
            $data['logo_path'] = null;
            $data['logo_disk'] = null;
        }

        $client->update($data);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    // Panorama corrido do cliente — evolui com o tempo, editável a qualquer
    // momento (não é derivado de outra tela, é a síntese que a equipe mantém).
    public function updateBriefing(Request $request, Client $client)
    {
        $client->update(['briefing' => $request->validate([
            'briefing' => 'nullable|string',
        ])['briefing']]);

        return redirect()->back()->with('success', 'Briefing atualizado.');
    }

    public function generateToken(Client $client)
    {
        $token = $client->generateRegistrationToken();

        return back()->with('token_generated', route('clients.register', $token));
    }

    // Edição rápida de status direto na listagem, sem abrir o form completo
    // de edição do cliente (mesmo padrão de MeetingController::updateStatus()).
    public function updateStatus(Request $request, Client $client)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Client::$statuses)),
        ]);

        $client->update($data);

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    // Delete de verdade — exceção à regra geral de "nunca deletar cliente,
    // usar status inactive" (ver CLAUDE.md), só permitida quando o cliente
    // não tem NENHUMA associação real (ver Client::blockingAssociations()).
    // Existe pra limpar duplicatas cadastradas por engano, nunca pra remover
    // um cliente com histórico de verdade — pra esse caso, status inactive
    // continua sendo o caminho certo.
    public function destroy(Client $client)
    {
        $blocking = $client->blockingAssociations();

        if (!empty($blocking)) {
            $reasons = collect($blocking)->map(fn ($count, $label) => "{$count} {$label}")->implode(', ');
            return back()->with('error', "Não é possível excluir — este cliente tem: {$reasons}. Use o status \"Inativo\" em vez de excluir.");
        }

        if ($client->logo_path) {
            Storage::disk($client->logo_disk)->delete($client->logo_path);
        }

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Cliente excluído.');
    }
}
