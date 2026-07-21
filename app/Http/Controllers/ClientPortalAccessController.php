<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientPortalAccessController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        $data = $request->validate([
            'contact_id' => [
                'required', 'uuid',
                Rule::exists('client_contacts', 'contact_id')->where('client_id', $client->id),
            ],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $contact = Contact::findOrFail($data['contact_id']);

        if (!$contact->email) {
            return back()->withErrors(['contact_id' => 'Este contato não tem e-mail cadastrado — adicione um e-mail antes de habilitar o acesso.']);
        }

        $clash = Contact::where('organization_id', $contact->organization_id)
            ->where('id', '!=', $contact->id)
            ->whereRaw('lower(email) = ?', [Str::lower($contact->email)])
            ->where('portal_access_enabled', true)
            ->exists();

        if ($clash) {
            return back()->withErrors(['contact_id' => 'Já existe outro acesso de portal ativo com este e-mail.']);
        }

        $contact->update([
            'password'               => $data['password'],
            'portal_access_enabled'  => true,
        ]);

        return back()->with('success', 'Acesso ao portal habilitado.');
    }

    public function destroy(Client $client, Contact $portalContact): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_unless($client->contacts()->where('contact_id', $portalContact->id)->exists(), 403);

        $portalContact->update(['portal_access_enabled' => false]);

        return back()->with('success', 'Acesso removido.');
    }
}
