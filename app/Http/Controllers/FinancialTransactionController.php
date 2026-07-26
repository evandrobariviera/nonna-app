<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            $month = now()->format('Y-m');
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $transactions = FinancialTransaction::with(['category', 'client', 'contract'])
            ->whereBetween('due_date', [$start, $end])
            ->orderBy('due_date')
            ->get();

        $entradas = $transactions->where('type', 'entrada');
        $saidas = $transactions->where('type', 'saida');

        $stats = [
            'entradas_previstas' => $entradas->sum('amount'),
            'saidas_previstas'   => $saidas->sum('amount'),
            'saldo_previsto'     => $entradas->sum('amount') - $saidas->sum('amount'),
            'realizado'          => $entradas->where('status', 'pago')->sum('amount')
                                     - $saidas->where('status', 'pago')->sum('amount'),
        ];

        $categories = FinancialCategory::active()->orderBy('name')->get()->groupBy('type');
        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);
        $contracts = Contract::whereNotIn('status', ['encerrado', 'cancelado'])
            ->with('client')
            ->get(['id', 'title', 'client_id']);

        return view('financial.transactions.index', [
            'transactions' => $transactions,
            'stats'        => $stats,
            'categories'   => $categories,
            'clients'      => $clients,
            'contracts'    => $contracts,
            'month'        => $month,
            'monthStart'   => $start,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'           => 'required|in:' . implode(',', array_keys(FinancialTransaction::$types)),
            'category_id'    => 'nullable|exists:financial_categories,id',
            'amount'         => 'required|numeric|min:0.01',
            'due_date'       => 'required|date',
            'payment_method' => 'nullable|in:pix,cartao,boleto',
            'description'    => 'nullable|string|max:255',
            'client_id'      => 'nullable|exists:clients,id',
            'contract_id'    => 'nullable|exists:contracts,id',
        ]);

        $data['created_by'] = auth()->id();

        FinancialTransaction::create($data);

        return back()->with('success', 'Lançamento criado.');
    }

    public function update(Request $request, FinancialTransaction $financialTransaction)
    {
        $data = $request->validate([
            'type'           => 'sometimes|required|in:' . implode(',', array_keys(FinancialTransaction::$types)),
            'category_id'    => 'nullable|exists:financial_categories,id',
            'amount'         => 'sometimes|required|numeric|min:0.01',
            'due_date'       => 'sometimes|required|date',
            'status'         => 'sometimes|required|in:previsto,pago,cancelado',
            'payment_method' => 'nullable|in:pix,cartao,boleto',
            'description'    => 'nullable|string|max:255',
            'client_id'      => 'nullable|exists:clients,id',
            'contract_id'    => 'nullable|exists:contracts,id',
        ]);

        // Dar baixa: marcar como pago preenche a data de pagamento na hora,
        // se ainda não tiver uma. Não mexe em paid_at ao sair de "pago" —
        // evita apagar histórico se alguém reabrir por engano.
        if (($data['status'] ?? null) === 'pago' && !$financialTransaction->paid_at) {
            $data['paid_at'] = now();
        }

        $financialTransaction->update($data);

        return back()->with('success', 'Lançamento atualizado.');
    }

    public function destroy(FinancialTransaction $financialTransaction)
    {
        $financialTransaction->delete();

        return back()->with('success', 'Lançamento removido.');
    }
}
