<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientCredentialController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'platform'        => 'required|string|max:50',
            'platform_custom' => 'required_if:platform,outros|nullable|string|max:100',
            'access_url'      => 'nullable|url|max:500',
            'username'        => 'nullable|string|max:255',
            'password'        => 'nullable|string|max:500',
            'notes'           => 'nullable|string',
        ]);

        $data['client_id']  = $client->id;
        $data['created_by'] = Auth::id();

        ClientCredential::create($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'senhas'])
            ->with('success', 'Credencial adicionada.');
    }

    public function destroy(Client $client, ClientCredential $credential)
    {
        abort_unless($credential->client_id === $client->id, 403);

        $credential->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'senhas'])
            ->with('success', 'Credencial removida.');
    }

    public function update(Request $request, Client $client, ClientCredential $credential)
    {
        abort_unless($credential->client_id === $client->id, 403);

        $data = $request->validate([
            'platform'        => 'required|string|max:50',
            'platform_custom' => 'required_if:platform,outros|nullable|string|max:100',
            'access_url'      => 'nullable|url|max:500',
            'username'        => 'nullable|string|max:255',
            'password'        => 'nullable|string|max:500',
            'notes'           => 'nullable|string',
        ]);

        // Se a senha vier vazia, mantém a atual
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $credential->update($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'senhas'])
            ->with('success', 'Credencial atualizada.');
    }
}
