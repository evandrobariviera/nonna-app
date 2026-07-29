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

        // "sometimes" em tudo: esse endpoint é compartilhado pelo formulário
        // completo de edição da conta e pelo formulário separado de "Metas de
        // Performance" (só target_cost_per_result/target_roas) — sem isso, um
        // campo ausente do formulário de Metas seria validado como nulo e
        // apagaria o valor salvo dos outros campos.
        $data = $request->validate([
            'platform'        => 'sometimes|required|string|max:50',
            'platform_custom' => 'sometimes|required_if:platform,outros|nullable|string|max:100',
            'account_id'      => 'sometimes|required|string|max:100',
            'account_name'    => 'sometimes|nullable|string|max:150',
            'sheet_tab_name'  => 'sometimes|nullable|string|max:150',
            'status'          => 'sometimes|required|in:ativo,pausado,suspenso',
            'notes'           => 'sometimes|nullable|string',
            'payment_method'  => 'sometimes|nullable|in:pix,cartao,boleto',
            'balance'         => 'sometimes|nullable|numeric|min:0',
            'target_cost_per_result' => 'sometimes|nullable|numeric|min:0',
            'target_roas'            => 'sometimes|nullable|numeric|min:0',
        ]);

        // budget_automation_enabled é um checkbox — some do request quando
        // desmarcado, então só reagimos a ele quando o formulário completo
        // de edição foi de fato submetido (sinalizado pela presença de
        // "platform", que o formulário de Metas não envia).
        if ($request->has('platform')) {
            $data['budget_automation_enabled'] = $request->boolean('budget_automation_enabled');
        }

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
