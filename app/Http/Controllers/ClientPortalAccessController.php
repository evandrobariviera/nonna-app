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

        $data = $request->validate([
            'portal_name'     => ['required', 'string', 'max:150'],
            'portal_email'    => ['required', 'email', 'unique:users,email'],
            'portal_password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name'      => $data['portal_name'],
            'email'     => $data['portal_email'],
            'password'  => $data['portal_password'],
            'client_id' => $client->id,
        ]);

        return back()->with('success', 'Acesso ao portal criado com sucesso.');
    }

    public function destroy(Client $client, User $portalUser): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_if($portalUser->client_id !== $client->id, 403);

        $portalUser->delete();

        return back()->with('success', 'Acesso removido.');
    }
}
