<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('vencidos')) {
            $query->whereDate('end_date', '<', now())
                ->whereNotIn('status', ['encerrado', 'cancelado']);
        }

        $contracts = $query->orderByDesc('start_date')->get();

        $activeContracts = Contract::whereNotIn('status', ['encerrado', 'cancelado'])->get();

        $stats = [
            'total_ativos'      => $activeContracts->count(),
            'fee_mensal_total'  => $activeContracts->where('fee_type', 'mensal')->sum('fee_value'),
            'valor_total'       => $activeContracts->sum(fn (Contract $c) => (float) $c->fee_value + $c->lineItemsTotal()),
            'vencidos'          => Contract::whereDate('end_date', '<', now())
                                        ->whereNotIn('status', ['encerrado', 'cancelado'])
                                        ->count(),
        ];

        return view('contracts.index', compact('contracts', 'stats'));
    }

    public function store(Request $request, Client $client)
    {
        $contract = $client->contracts()->create([
            'title'      => 'Novo Contrato',
            'status'     => 'rascunho',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('clients.contracts.show', [$client, $contract])
            ->with('success', 'Contrato criado.');
    }

    public function show(Client $client, Contract $contract)
    {
        $contract->load(['attachments.uploadedBy']);

        return view('contracts.show', compact('client', 'contract'));
    }

    public function update(Request $request, Client $client, Contract $contract)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'status'              => 'required|in:' . implode(',', array_keys(Contract::$statuses)),
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'signed_at'           => 'nullable|date',
            'fee_value'           => 'nullable|numeric|min:0',
            'fee_type'            => 'nullable|in:' . implode(',', array_keys(Contract::$feeTypes)),
            'payment_method'      => 'nullable|in:pix,cartao,boleto',
            'billing_day'         => 'nullable|integer|in:10,15,20',
            'renewal_type'        => 'nullable|in:' . implode(',', array_keys(Contract::$renewalTypes)),
            'notice_period_days'  => 'nullable|integer|min:0',
            'notes'               => 'nullable|string',
            'line_items'          => 'nullable|string',
        ]);

        if (isset($data['line_items'])) {
            $decoded = json_decode($data['line_items'], true);
            $data['line_items'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $contract->update($data);

        return back()->with('success', 'Contrato atualizado.');
    }

    public function destroy(Client $client, Contract $contract)
    {
        $contract->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'contratos'])
            ->with('success', 'Contrato removido.');
    }

    // Edição rápida de status direto na listagem, sem abrir o form completo
    // de edição do contrato (mesmo padrão de MeetingController::updateStatus()).
    public function updateStatus(Request $request, Client $client, Contract $contract)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Contract::$statuses)),
        ]);

        $contract->update($data);

        return redirect()->back()->with('success', 'Status atualizado.');
    }
}
