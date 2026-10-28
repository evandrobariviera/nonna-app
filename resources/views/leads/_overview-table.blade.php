{{-- Uma linha por cliente (LeadFunnel::overview) — aba Resultados da Central
     de Leads e dashboard da Central de Performance. Espera $overview e
     $clientUrl (fn (Client) => url da linha). --}}
@php
    $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
@endphp

<div class="card overflow-hidden">
<div class="overflow-x-auto">
    <table class="nonna-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th style="text-align:right">Investido</th>
                <th style="text-align:right" title="Leads cuja campanha de origem é conhecida">Leads (com campanha)</th>
                <th style="text-align:right">Bons</th>
                <th style="text-align:right">Custo por lead bom</th>
                <th style="text-align:right">Vendas</th>
                <th style="text-align:right">Cada R$ 1 voltou</th>
            </tr>
        </thead>
        <tbody>
            @forelse($overview as $r)
                <tr>
                    <td>
                        <a href="{{ $clientUrl($r['client']) }}"
                           class="font-semibold hover:underline" style="color:var(--text); font-size:13px">{{ $r['client']->displayName() }}</a>
                        @unless($r['has_rule'])
                            <div class="text-xs" style="color:var(--muted)">sem régua de lead — IA não avalia</div>
                        @endunless
                    </td>
                    <td style="white-space:nowrap; text-align:right">{{ $r['spend'] > 0 ? $money($r['spend']) : '—' }}</td>
                    <td style="white-space:nowrap; text-align:right">
                        {{ $r['leads'] ?: '—' }}
                        @if($r['leads'])<span class="text-xs" style="color:var(--muted)">({{ $r['attributed'] }})</span>@endif
                    </td>
                    <td style="white-space:nowrap; text-align:right">{{ $r['evaluated'] ? $r['good'] : '—' }}</td>
                    <td style="white-space:nowrap; text-align:right; font-weight:600">{{ $money($r['cost_per_good']) }}</td>
                    <td style="white-space:nowrap; text-align:right">
                        {{ $r['sold'] ?: '—' }}
                        @if($r['sold'])<div class="text-xs" style="color:var(--muted)">{{ $money($r['sold_value']) }}</div>@endif
                    </td>
                    <td style="white-space:nowrap; text-align:right">{{ $money($r['return']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-8" style="color:var(--muted)">Nenhum investimento nem lead neste período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>
