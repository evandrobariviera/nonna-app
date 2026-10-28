{{-- Gráfico de funil da aba Resultados: investido → chegaram → avaliados →
     bons → vendas, com o custo de cada etapa e, embaixo, como a IA avaliou
     os leads. Roxo único, mais forte perto da venda. Espera $t com spend,
     leads, evaluated, good, weak, no_profile, sold, sold_value. --}}
@php
    $money = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');
    $spend = (float) ($t['spend'] ?? 0);
    $per = fn ($n) => $spend > 0 && $n > 0 ? $money($spend / $n) : null;

    $steps = [
        ['label' => 'Leads que chegaram', 'value' => $t['leads'],     'of' => null,               'cost' => $per($t['leads']) ? $per($t['leads']) . ' por lead' : null],
        ['label' => 'Avaliados pela IA',  'value' => $t['evaluated'], 'of' => 'dos que chegaram', 'cost' => null],
        ['label' => 'Leads bons',         'value' => $t['good'],      'of' => 'dos avaliados',    'cost' => $per($t['good']) ? $per($t['good']) . ' por lead bom' : null],
        ['label' => 'Vendas',             'value' => $t['sold'],      'of' => 'dos leads bons',   'cost' => $t['sold'] ? $money($t['sold_value']) . ' vendidos' : 'nenhuma marcada'],
    ];

    // Largura proporcional aos que chegaram, com um mínimo para o número caber.
    $top = max(1, $t['leads']);
    $widths = array_map(fn ($s) => max(26, $s['value'] / $top * 100), $steps);
    $shades = [42, 62, 82, 100]; // % de roxo — mais forte em direção à venda

    $pending = max(0, $t['leads'] - $t['evaluated']);
    $split = array_filter([
        ['Bons', $t['good'], 'var(--green)'],
        ['Fracos', $t['weak'] ?? 0, 'var(--orange)'],
        ['Sem perfil', $t['no_profile'] ?? 0, 'var(--red)'],
        ['Aguardando a IA', $pending, 'var(--border)'],
    ], fn ($s) => $s[1] > 0);
@endphp

<div class="card px-5 py-5 mb-5">
    <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Do anúncio à venda</p>

    <div class="flex flex-col" style="gap:3px" role="list" aria-label="Funil do lead à venda">
        @foreach($steps as $i => $s)
            @php
                $w = $widths[$i];
                $next = $widths[$i + 1] ?? $w * 0.82;
                // Trapézio centralizado: topo = esta etapa, base = a próxima.
                $points = sprintf('%.2f,0 %.2f,0 %.2f,10 %.2f,10', 50 - $w / 2, 50 + $w / 2, 50 + $next / 2, 50 - $next / 2);
                $prev = $i > 0 ? $steps[$i - 1]['value'] : null;
                $pct = $prev ? round($s['value'] / $prev * 100) : null;
                $dark = $i >= 2;
            @endphp
            <div class="grid items-center gap-3" role="listitem"
                 style="grid-template-columns:minmax(84px, 140px) 1fr minmax(96px, 170px)"
                 title="{{ $s['label'] }}: {{ $s['value'] }}{{ $pct !== null ? " ({$pct}% {$s['of']})" : '' }}">
                <span class="text-sm text-right leading-tight" style="color:var(--muted2)">{{ $s['label'] }}</span>
                <div class="relative" style="height:54px">
                    <svg viewBox="0 0 100 10" preserveAspectRatio="none" class="absolute inset-0 w-full h-full" aria-hidden="true">
                        <polygon points="{{ $points }}" style="fill:color-mix(in srgb, var(--purple) {{ $shades[$i] }}%, var(--bg))"/>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-lg font-black"
                          style="color:{{ $dark ? '#fff' : 'var(--text)' }}">{{ number_format($s['value'], 0, ',', '.') }}</span>
                </div>
                <div class="leading-tight">
                    @if($pct !== null)
                        <div class="text-sm font-bold" style="color:var(--text)">{{ $prev > 0 ? $pct . '%' : '—' }} <span class="font-normal text-xs" style="color:var(--muted)">{{ $s['of'] }}</span></div>
                    @endif
                    @if($s['cost'])
                        <div class="text-xs mt-0.5" style="color:var(--muted)">{{ $s['cost'] }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($t['leads'] > 0)
        <div class="mt-5 pt-4" style="border-top:1px solid var(--border)">
            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Como a IA avaliou os {{ $t['leads'] }} leads</p>
            <div class="flex h-3 w-full overflow-hidden rounded" style="gap:2px" role="img"
                 aria-label="{{ collect($split)->map(fn ($s) => $s[0] . ': ' . $s[1])->implode(', ') }}">
                @foreach($split as [$label, $n, $color])
                    <div style="flex:{{ $n }} 1 0; background:{{ $color }}" title="{{ $label }}: {{ $n }} ({{ round($n / $t['leads'] * 100) }}%)"></div>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2">
                @foreach($split as [$label, $n, $color])
                    <span class="inline-flex items-center gap-1.5 text-xs" style="color:var(--muted2)">
                        <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:{{ $color }}"></span>
                        {{ $label }} <strong style="color:var(--text)">{{ $n }}</strong>
                        <span style="color:var(--muted)">({{ round($n / $t['leads'] * 100) }}%)</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
