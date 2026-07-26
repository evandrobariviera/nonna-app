<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Financeiro</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Dashboard</h1>
            </div>
            <div class="flex items-center gap-1.5">
                @foreach(\App\Http\Controllers\FinancialDashboardController::$periods as $key => $label)
                    <a href="{{ route('financial-dashboard.index', ['period' => $key]) }}"
                       class="px-3 py-1.5 text-xs font-semibold transition-colors"
                       style="background:{{ $period === $key ? 'var(--purple)' : 'var(--s3)' }};
                              color:{{ $period === $key ? '#fff' : 'var(--muted)' }};
                              border:1px solid {{ $period === $key ? 'var(--purple)' : 'var(--border2)' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot>

    {{-- ── KPIs ── --}}
    <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(4, 1fr)">
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:{{ $currentMonth['saldo_previsto'] >= 0 ? 'var(--text)' : 'var(--red)' }}">
                R$ {{ number_format($currentMonth['saldo_previsto'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Saldo previsto (mês)</div>
        </div>
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--purple)">
                R$ {{ number_format($currentMonth['realizado'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Realizado até agora</div>
        </div>
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:{{ $saldoDelta === null ? 'var(--text)' : ($saldoDelta >= 0 ? 'var(--green)' : 'var(--red)') }}">
                {{ $saldoDelta !== null ? ($saldoDelta >= 0 ? '+' : '') . $saldoDelta . '%' : '—' }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Saldo vs mês anterior</div>
        </div>
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:{{ $overdue->isEmpty() ? 'var(--text)' : 'var(--red)' }}">
                R$ {{ number_format($overdue->sum('amount'), 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Inadimplência ({{ $overdue->count() }})</div>
        </div>
    </div>

    {{-- ── Fluxo de caixa mensal ── --}}
    <div class="card card-body-lg mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
            Fluxo de Caixa Mensal <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· entradas x saídas</span>
        </p>
        @php $maxTrend = max($monthlyTrend->flatMap(fn($m) => [$m->entrada, $m->saida])->max(), 1); @endphp
        <div class="flex items-end gap-4" style="height:140px">
            @foreach($monthlyTrend as $month)
                <div class="flex-1 flex flex-col items-center justify-end h-full">
                    <div class="flex items-end gap-1 w-full justify-center" style="height:100%">
                        <div class="rounded-t" style="width:40%; height:{{ round($month->entrada / $maxTrend * 100) }}%; min-height:{{ $month->entrada > 0 ? '4px' : '0' }}; background:var(--green)"></div>
                        <div class="rounded-t" style="width:40%; height:{{ round($month->saida / $maxTrend * 100) }}%; min-height:{{ $month->saida > 0 ? '4px' : '0' }}; background:var(--red)"></div>
                    </div>
                    <span class="text-xs mt-1.5" style="color:var(--muted2)">{{ $month->label }}</span>
                </div>
            @endforeach
        </div>
        <div class="flex items-center gap-4 mt-4 text-xs" style="color:var(--muted2)">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:var(--green)"></span> Entradas</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:var(--red)"></span> Saídas</span>
        </div>
    </div>

    {{-- ── Por categoria ── --}}
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Despesas por Categoria</p>
            @if($despesasPorCategoria->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma despesa categorizada no período.</p>
            @else
                @php $maxDespesa = $despesasPorCategoria->max('total'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($despesasPorCategoria as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="badge badge-{{ $row->category->color }}">{{ $row->category->name }}</span>
                                <span class="text-sm font-bold" style="color:var(--text)">R$ {{ number_format($row->total, 2, ',', '.') }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ round($row->total / $maxDespesa * 100) }}%; background:var(--red)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Receitas por Categoria</p>
            @if($receitasPorCategoria->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma receita categorizada no período.</p>
            @else
                @php $maxReceita = $receitasPorCategoria->max('total'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($receitasPorCategoria as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="badge badge-{{ $row->category->color }}">{{ $row->category->name }}</span>
                                <span class="text-sm font-bold" style="color:var(--text)">R$ {{ number_format($row->total, 2, ',', '.') }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ round($row->total / $maxReceita * 100) }}%; background:var(--green)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Inadimplência ── --}}
    <div class="card overflow-x-auto">
        <p class="text-xs font-semibold uppercase tracking-widest px-5 pt-5 mb-1" style="color:var(--muted); letter-spacing:.1em">Inadimplência</p>
        @if($overdue->isEmpty())
            <div class="px-6 py-10 text-center" style="color:var(--muted)">
                <p class="text-sm">Nenhum lançamento previsto em atraso.</p>
            </div>
        @else
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th>Cliente / Contrato</th>
                        <th>Categoria</th>
                        <th>Vencimento</th>
                        <th>Dias em atraso</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($overdue as $transaction)
                        <tr>
                            <td class="text-xs text-[var(--text)]">
                                {{ $transaction->client?->company_name ?? $transaction->contract?->client?->company_name ?? '—' }}
                                @if($transaction->contract)
                                    <span style="color:var(--muted)">· {{ $transaction->contract->title }}</span>
                                @endif
                            </td>
                            <td>
                                @if($transaction->category)
                                    <span class="badge badge-{{ $transaction->category->color }}">{{ $transaction->category->name }}</span>
                                @else
                                    <span style="color:var(--muted)">—</span>
                                @endif
                            </td>
                            <td class="text-xs text-[var(--muted2)]">{{ $transaction->due_date->format('d/m/Y') }}</td>
                            <td class="text-xs font-semibold" style="color:var(--red)">{{ $transaction->due_date->diffInDays(today()) }} dia(s)</td>
                            <td class="text-sm font-mono text-[var(--text)]">R$ {{ number_format($transaction->amount, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
