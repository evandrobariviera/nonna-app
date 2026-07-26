<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinancialDashboardController extends Controller
{
    public static array $periods = [
        '3'  => 'Últimos 3 meses',
        '6'  => 'Últimos 6 meses',
        '12' => 'Últimos 12 meses',
    ];

    public static array $futurePeriods = [
        '6'  => 'Próximos 6 meses',
        '12' => 'Próximos 12 meses',
        '24' => 'Próximos 24 meses',
    ];

    public function index(Request $request): View
    {
        $period = $request->get('period', '6');
        if (!array_key_exists($period, self::$periods)) {
            $period = '6';
        }
        $months = (int) $period;

        $currentMonth = $this->periodStats(now()->startOfMonth(), now()->endOfMonth());
        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonth = $this->periodStats($previousMonthStart, $previousMonthStart->copy()->endOfMonth());

        $saldoDelta = $previousMonth['saldo_previsto'] != 0
            ? round(($currentMonth['saldo_previsto'] - $previousMonth['saldo_previsto']) / abs($previousMonth['saldo_previsto']) * 100)
            : null;

        $monthlyTrend = $this->monthlyTrend($months);

        $periodStart = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $categoryBreakdown = $this->categoryBreakdown($periodStart, now()->endOfMonth());

        $overdue = FinancialTransaction::where('status', 'previsto')
            ->where('due_date', '<', today())
            ->with(['client', 'contract', 'category'])
            ->orderBy('due_date')
            ->get();

        $future = $request->get('future', '12');
        if (!array_key_exists($future, self::$futurePeriods)) {
            $future = '12';
        }
        $futureProjection = $this->futureProjection((int) $future);

        return view('financial.dashboard.index', [
            'period'            => $period,
            'currentMonth'      => $currentMonth,
            'previousMonth'     => $previousMonth,
            'saldoDelta'        => $saldoDelta,
            'monthlyTrend'      => $monthlyTrend,
            'despesasPorCategoria' => $categoryBreakdown['despesa'],
            'receitasPorCategoria' => $categoryBreakdown['receita'],
            'overdue'           => $overdue,
            'future'            => $future,
            'futureProjection'  => $futureProjection,
        ]);
    }

    // Simula (sem gravar nada) a receita de contratos ativos pros meses
    // futuros que ainda não têm lançamento real — o comando
    // financial:generate-contract-transactions só materializa 1 mês por
    // vez, então pra uma visão de 12+ meses pra frente precisamos calcular
    // o que ele geraria, sem esperar o tempo passar. Despesa futura não é
    // simulada (não existe conceito de despesa recorrente cadastrada nesse
    // sistema) — só soma o que já foi lançado de verdade (ex.: via
    // recorrência manual da Fase 2).
    private function futureProjection(int $months): Collection
    {
        $activeContracts = Contract::where('status', 'ativo')
            ->whereNotNull('fee_value')
            ->whereNotNull('billing_day')
            ->whereIn('fee_type', ['mensal', 'anual'])
            ->get();

        $result = collect();
        $cumulative = 0;

        for ($i = 1; $i <= $months; $i++) {
            $monthStart = now()->addMonthsNoOverflow($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $realEntrada = (float) FinancialTransaction::where('type', 'entrada')
                ->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount');
            $realSaida = (float) FinancialTransaction::where('type', 'saida')
                ->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount');

            $projetada = $activeContracts->sum(function (Contract $contract) use ($monthStart, $monthEnd) {
                $anchor = $contract->fee_type === 'anual' ? ($contract->start_date ?? $contract->signed_at) : null;
                if ($contract->fee_type === 'anual' && (!$anchor || $anchor->month !== $monthStart->month)) {
                    return 0;
                }

                $hasReal = FinancialTransaction::where('contract_id', $contract->id)
                    ->whereBetween('due_date', [$monthStart, $monthEnd])
                    ->exists();

                return $hasReal ? 0 : (float) $contract->fee_value;
            });

            $entradaTotal = $realEntrada + $projetada;
            $cumulative += $entradaTotal - $realSaida;

            $result->push((object) [
                'label'      => $monthStart->translatedFormat('M/y'),
                'entrada'    => $entradaTotal,
                'saida'      => $realSaida,
                'cumulative' => $cumulative,
            ]);
        }

        return $result;
    }

    private function periodStats($start, $end): array
    {
        $transactions = FinancialTransaction::whereBetween('due_date', [$start, $end])->get();
        $entradas = $transactions->where('type', 'entrada');
        $saidas = $transactions->where('type', 'saida');

        return [
            'entradas_previstas' => (float) $entradas->sum('amount'),
            'saidas_previstas'   => (float) $saidas->sum('amount'),
            'saldo_previsto'     => (float) $entradas->sum('amount') - (float) $saidas->sum('amount'),
            'realizado'          => (float) $entradas->where('status', 'pago')->sum('amount')
                                     - (float) $saidas->where('status', 'pago')->sum('amount'),
        ];
    }

    private function monthlyTrend(int $months): Collection
    {
        $result = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonthsNoOverflow($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $stats = $this->periodStats($monthStart, $monthEnd);

            $result->push((object) [
                'label'   => $monthStart->translatedFormat('M/y'),
                'entrada' => $stats['entradas_previstas'],
                'saida'   => $stats['saidas_previstas'],
            ]);
        }

        return $result;
    }

    private function categoryBreakdown($start, $end): array
    {
        $transactions = FinancialTransaction::whereBetween('due_date', [$start, $end])
            ->with('category')
            ->get();

        $build = fn (string $type) => $transactions->where('type', $type)
            ->filter(fn (FinancialTransaction $t) => $t->category !== null)
            ->groupBy('category_id')
            ->map(fn ($group) => (object) [
                'category' => $group->first()->category,
                'total'    => (float) $group->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(8);

        return [
            'despesa' => $build('saida'),
            'receita' => $build('entrada'),
        ];
    }
}
