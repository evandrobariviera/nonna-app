<?php

namespace App\Http\Controllers;

use App\Models\ClientAdAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrcamentoController extends Controller
{
    // Ordem de exibição dos grupos — mesma ordem do "Active" no ClickUp,
    // da situação que pede mais atenção pra menos.
    public static array $groupOrder = ['aguardando_pagto', 'adicao_necessaria', 'standby', 'creditos_ativos'];

    public function index(): View
    {
        $accounts = ClientAdAccount::whereIn('platform', ClientAdAccount::billingPlatforms())
            ->with(['client', 'responsibleUser'])
            ->withCount('billingDocuments')
            ->get()
            ->sortBy(fn (ClientAdAccount $account) => $account->client?->company_name);

        $grouped = $accounts->groupBy('budget_status');
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('orcamentos.index', compact('grouped', 'users'));
    }

    public function updateStatus(Request $request, ClientAdAccount $adAccount): RedirectResponse
    {
        $data = $request->validate([
            'budget_status'       => 'required|in:' . implode(',', array_keys(ClientAdAccount::$budgetStatuses)),
            'responsible_user_id' => 'nullable|exists:users,id',
        ]);

        $data['budget_automation_enabled'] = $request->boolean('budget_automation_enabled');

        $adAccount->update($data);

        return back()->with('success', 'Orçamento atualizado.');
    }
}
