{{-- Evolução semana a semana (PerformanceDashboard::weekly): investido e leads
     em dois gráficos separados (escalas diferentes não dividem eixo). A semana
     atual ainda está incompleta e aparece mais clara. Espera $weekly. --}}
@php
    $maxSpend = max(1, max(array_column($weekly, 'spend')));
    $maxLeads = max(1, max(array_column($weekly, 'leads')));
    $short = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
@endphp

<div class="grid gap-4 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(300px, 1fr))">
    @foreach([
        ['Investido por semana', 'spend', $maxSpend, null],
        ['Leads por semana', 'leads', $maxLeads, 'good'],
    ] as [$title, $key, $max, $inner])
        <div class="card px-5 py-4">
            <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $title }}</p>
                @if($inner)
                    <div class="flex items-center gap-3 text-xs" style="color:var(--muted2)">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:color-mix(in srgb, var(--purple) 45%, var(--bg))"></span>Leads</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:var(--green)"></span>Bons</span>
                    </div>
                @endif
            </div>
            <div class="flex items-end gap-2" style="height:150px" role="img"
                 aria-label="{{ $title }}: {{ collect($weekly)->map(fn ($w) => $w['week']->format('d/m') . ' ' . $w[$key])->implode(', ') }}">
                @foreach($weekly as $w)
                    @php
                        $v = $w[$key];
                        $h = $v > 0 ? max(3, $v / $max * 100) : 0;
                        $tip = 'Semana de ' . $w['week']->format('d/m') . ($w['current'] ? ' (até hoje)' : '') . ': '
                            . ($key === 'spend' ? 'R$ ' . number_format($v, 2, ',', '.') : $v . ' leads, ' . $w['good'] . ' bons, ' . $w['sold'] . ' vendas');
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full min-w-0" title="{{ $tip }}">
                        <span class="text-xs mb-1 whitespace-nowrap" style="color:var(--muted2)">{{ $v > 0 ? ($key === 'spend' ? $short($v) : $v) : '' }}</span>
                        <div class="w-full rounded-t flex flex-col justify-end overflow-hidden"
                             style="height:{{ $h }}%; max-width:44px; background:{{ $key === 'spend' ? 'var(--purple)' : 'color-mix(in srgb, var(--purple) 45%, var(--bg))' }}; opacity:{{ $w['current'] ? .55 : 1 }}">
                            @if($inner && $v > 0 && $w[$inner] > 0)
                                <div style="height:{{ $w[$inner] / $v * 100 }}%; background:var(--green); border-top:2px solid var(--bg)"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-2 mt-1.5" style="border-top:1px solid var(--border)">
                @foreach($weekly as $w)
                    <span class="flex-1 text-center text-xs pt-1 min-w-0" style="color:var(--muted)">{{ $w['current'] ? 'atual' : $w['week']->format('d/m') }}</span>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
