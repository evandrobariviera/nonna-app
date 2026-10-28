{{-- Gráfico de funil da aba Resultados: chegaram → avaliados → bons → vendas.
     Uma cor só (roxo), largura proporcional aos leads que chegaram; o texto
     fica fora da cor. Espera $t com leads, evaluated, good, sold, sold_value. --}}
@php
    $steps = [
        ['Leads que chegaram', $t['leads'], null],
        ['Avaliados pela IA', $t['evaluated'], 'dos que chegaram'],
        ['Leads bons', $t['good'], 'dos avaliados'],
        ['Vendas', $t['sold'], 'dos leads bons'],
    ];
    $top = max(1, $t['leads']);
@endphp

<div class="card px-5 py-4 mb-5">
    <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Do lead à venda</p>
    <div class="flex flex-col gap-2" role="list">
        @foreach($steps as $i => [$label, $value, $ofWhat])
            @php
                $prev = $i > 0 ? $steps[$i - 1][1] : null;
                $pct = $prev ? round($value / $prev * 100) : null;
                $width = $value > 0 ? max(2, $value / $top * 100) : 0;
                $tip = $label . ': ' . $value . ($pct !== null ? " ({$pct}% {$ofWhat})" : '')
                    . ($i === 3 && $value ? ' · R$ ' . number_format($t['sold_value'], 2, ',', '.') : '');
            @endphp
            <div class="grid items-center gap-3" style="grid-template-columns:minmax(100px, 150px) 1fr minmax(70px, auto)" role="listitem" title="{{ $tip }}">
                <span class="text-sm" style="color:var(--text)">{{ $label }}</span>
                <div class="h-7 min-w-0">
                    <div class="h-7 rounded-r" style="width:{{ $width }}%; background:var(--purple); opacity:{{ 1 - $i * 0.18 }}"></div>
                </div>
                <div class="text-right leading-tight">
                    <span class="text-sm font-bold" style="color:var(--text)">{{ $value }}</span>
                    @if($pct !== null && $prev > 0)
                        <div class="text-xs whitespace-nowrap" style="color:var(--muted)">{{ $pct }}% {{ $ofWhat }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
