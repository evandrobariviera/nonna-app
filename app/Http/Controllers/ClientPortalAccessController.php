<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientPortalAccessController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        if (User::where('client_id', $client->id)->exists()) {
            return back()->with('error', 'Este cliente já tem acesso ao portal.');
        }

        $data = $request->validate([
            'portal_email'    => ['required', 'email', 'unique:users,email'],
            'portal_password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name'      => $client->responsible_name ?? $client->company_name,
            'email'     => $data['portal_email'],
            'password'  => $data['portal_password'],
            'client_id' => $client->id,
        ]);

        return back()->with('success', 'Acesso ao portal criado com sucesso.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        User::where('client_id', $client->id)->delete();

        return back()->with('success', 'Acesso ao portal removido.');
    }
}
