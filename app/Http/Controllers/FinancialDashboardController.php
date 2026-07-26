<?php

namespace App\Http\Controllers;

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

        return view('financial.dashboard.index', [
            'period'            => $period,
            'currentMonth'      => $currentMonth,
            'previousMonth'     => $previousMonth,
            'saldoDelta'        => $saldoDelta,
            'monthlyTrend'      => $monthlyTrend,
            'despesasPorCategoria' => $categoryBreakdown['despesa'],
            'receitasPorCategoria' => $categoryBreakdown['receita'],
            'overdue'           => $overdue,
        ]);
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
