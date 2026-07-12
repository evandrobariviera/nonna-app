<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientContactSubscription;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientContactController extends Controller
{
    public static array $roles = [
        'socio'         => 'Sócio / Fundador',
        'gestor'        => 'Gestor / CEO',
        'financeiro'    => 'Financeiro',
        'ponto_contato' => 'Ponto de Contato',
        'marketing'     => 'Marketing',
        'operacional'   => 'Operacional',
        'outros'        => 'Outros',
    ];

    // Vincula um contato existente ao cliente
    public function link(Request $request, Client $client)
    {
        $data = $request->validate([
            'contact_id' => 'required|uuid|exists:contacts,id',
            'role'       => 'nullable|string|max:50',
            'is_primary' => 'boolean',
        ]);

        // Evita duplicata
        if ($client->contacts()->where('contact_id', $data['contact_id'])->exists()) {
            return redirect()->route('clients.show', [$client, 'tab' => 'contatos'])
                ->with('error', 'Este contato já está vinculado a este cliente.');
        }

        if (!empty($data['is_primary'])) {
            \DB::table('client_contacts')
                ->where('client_id', $client->id)
                ->update(['is_primary' => false]);
        }

        $client->contacts()->attach($data['contact_id'], [
            'role'       => $data['role'] ?? null,
            'is_primary' => !empty($data['is_primary']),
        ]);

        return redirect()->route('clients.show', [$client, 'tab' => 'contatos'])
            ->with('success', 'Contato vinculado com sucesso.');
    }

    // Cria novo contato no CRM e já vincula ao cliente
    public function storeAndLink(Request $request, Client $client)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'nullable|string|max:30',
            'whatsapp'   => 'nullable|string|max:30',
            'job_title'  => 'nullable|string|max:100',
            'role'       => 'nullable|string|max:50',
            'is_primary' => 'boolean',
        ]);

        $contact = Contact::create([
            'name'         => $data['name'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'whatsapp'     => $data['whatsapp'] ?? null,
            'job_title'    => $data['job_title'] ?? null,
            'company_name' => $client->company_name,
            'source'       => 'outros',
            'status'       => 'ativo',
            'created_by'   => Auth::id(),
        ]);

        if (!empty($data['is_primary'])) {
            \DB::table('client_contacts')
                ->where('client_id', $client->id)
                ->update(['is_primary' => false]);
        }

        $client->contacts()->attach($contact->id, [
            'role'       => $data['role'] ?? null,
            'is_primary' => !empty($data['is_primary']),
        ]);

        return redirect()->route('clients.show', [$client, 'tab' => 'contatos'])
            ->with('success', 'Contato criado e vinculado com sucesso.');
    }

    // Atualiza role e is_primary na junction
    public function updatePivot(Request $request, Client $client, Contact $contact)
    {
        $data = $request->validate([
            'role'       => 'nullable|string|max:50',
            'is_primary' => 'boolean',
        ]);

        if (!empty($data['is_primary'])) {
            \DB::table('client_contacts')
                ->where('client_id', $client->id)
                ->update(['is_primary' => false]);
        }

        $client->contacts()->updateExistingPivot($contact->id, [
            'role'       => $data['role'] ?? null,
            'is_primary' => !empty($data['is_primary']),
        ]);

        return redirect()->route('clients.show', [$client, 'tab' => 'contatos'])
            ->with('success', 'Vínculo atualizado.');
    }

    // Atualiza quais comunicações (aprovação, financeiro, feedback, cobrança)
    // esse contato recebe e por quais canais — substitui tudo de uma vez
    // (mais simples que diff, e a escala é pequena: no máximo 4 tipos x 2 canais).
    public function updateSubscriptions(Request $request, Client $client, Contact $contact)
    {
        $data = $request->validate([
            'subscriptions'      => 'nullable|array',
            'subscriptions.*'    => 'array',
            'subscriptions.*.*'  => 'in:' . implode(',', array_keys(ClientContactSubscription::$channels)),
        ]);

        $clientContact = ClientContact::where('client_id', $client->id)
            ->where('contact_id', $contact->id)
            ->firstOrFail();

        $clientContact->subscriptions()->delete();

        foreach ($data['subscriptions'] ?? [] as $type => $channels) {
            if (!array_key_exists($type, ClientContactSubscription::$types) || empty($channels)) {
                continue;
            }
            $clientContact->subscriptions()->create([
                'type'     => $type,
                'channels' => array_values($channels),
            ]);
        }

        return redirect()->route('clients.show', [$client, 'tab' => 'contatos'])
            ->with('success', 'Comunicações atualizadas.');
    }

    // Remove o vínculo (não exclui o contato do CRM)
    public function unlink(Client $client, Contact $contact)
    {
        $client->contacts()->detach($contact->id);

        return redirect()->route('clients.show', [$client, 'tab' => 'contatos'])
            ->with('success', 'Contato desvinculado.');
    }
}
