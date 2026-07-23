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
            'sheet_tab_name'  => 'nullable|string|max:150',
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
            'sheet_tab_name'  => 'nullable|string|max:150',
            'status'          => 'required|in:ativo,pausado,suspenso',
            'notes'           => 'nullable|string',
            'payment_method'  => 'nullable|in:pix,cartao,boleto',
            'balance'         => 'nullable|numeric|min:0',
        ]);

        $data['budget_automation_enabled'] = $request->boolean('budget_automation_enabled');

        // Editar o saldo na mão sempre marca a origem como manual, mesmo que
        // a última sincronização tenha vindo da API — evita que o próximo
        // sync automático sobrescreva silenciosamente uma correção do time.
        if ($request->filled('balance')) {
            $data['balance_source'] = 'manual';
        }

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
