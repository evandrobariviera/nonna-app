<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAdBudget;
use Illuminate\Http\Request;

class ClientAdBudgetController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'monthly_budget' => 'required|numeric|min:0',
            'start_date'     => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();
        $client->adBudgets()->create($data);

        return back()->with('success', 'Orçamento de anúncios atualizado.');
    }

    public function destroy(Client $client, ClientAdBudget $adBudget)
    {
        abort_unless($adBudget->client_id === $client->id, 403);

        $adBudget->delete();

        return back()->with('success', 'Registro de orçamento removido.');
    }
}
