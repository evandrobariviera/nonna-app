<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAdAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientAdAccountController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'platform'        => 'required|string|max:50',
            'platform_custom' => 'required_if:platform,outros|nullable|string|max:100',
            'account_id'      => 'required|string|max:100',
            'account_name'    => 'nullable|string|max:150',
            'status'          => 'required|in:ativo,pausado,suspenso',
            'notes'           => 'nullable|string',
        ]);

        $data['client_id']  = $client->id;
        $data['created_by'] = Auth::id();

        ClientAdAccount::create($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'contas'])
            ->with('success', 'Conta de anúncios adicionada.');
    }

    public function update(Request $request, Client $client, ClientAdAccount $adAccount)
    {
        abort_unless($adAccount->client_id === $client->id, 403);

        $data = $request->validate([
            'platform'        => 'required|string|max:50',
            'platform_custom' => 'required_if:platform,outros|nullable|string|max:100',
            'account_id'      => 'required|string|max:100',
            'account_name'    => 'nullable|string|max:150',
            'status'          => 'required|in:ativo,pausado,suspenso',
            'notes'           => 'nullable|string',
        ]);

        $adAccount->update($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'contas'])
            ->with('success', 'Conta de anúncios atualizada.');
    }

    public function destroy(Client $client, ClientAdAccount $adAccount)
    {
        abort_unless($adAccount->client_id === $client->id, 403);

        $adAccount->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'contas'])
            ->with('success', 'Conta de anúncios removida.');
    }
}
