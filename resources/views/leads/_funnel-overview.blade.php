{{-- Aba Resultados sem cliente escolhido: uma linha por cliente
     (LeadFunnel::overview). Só no app interno. --}}
@php
    $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
@endphp

<p class="text-sm mb-4" style="color:var(--muted)">
    De {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}. Escolha um cliente para ver campanha por campanha.
</p>

{{-- Custos do funil só com o investimento de quem tem lead na Central —
     cliente que investe mas não usa a Central inflaria o custo por lead. --}}
@php($withLeads = array_filter($overview, fn ($r) => $r['leads'] > 0))
@include('leads._funnel-chart', ['t' => collect(['spend', 'leads', 'evaluated', 'good', 'weak', 'no_profile', 'sold', 'sold_value'])
    ->mapWithKeys(fn ($k) => [$k => array_sum(array_column($withLeads, $k))])->all()])

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
                        <a href="{{ route('leads.index', array_merge(request()->only(['date_from', 'date_to']), ['view' => 'resultados', 'client_id' => $r['client']->id])) }}"
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
