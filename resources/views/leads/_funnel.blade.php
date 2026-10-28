{{-- Aba Resultados da Central de Leads: do anúncio até a venda, por campanha
     (LeadFunnel::forClient). Compartilhado entre o app interno e o Portal.
     Espera $funnel e $campaignRoute (rota da página da campanha) e, no
     interno, $ruleUrl (ficha do cliente, aba Leads). --}}
@php
    $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    $t = $funnel['total'];
    $ruleUrl = $ruleUrl ?? null;
@endphp

<p class="text-sm mb-4" style="color:var(--muted)">
    De {{ $funnel['from']->format('d/m/Y') }} a {{ $funnel['to']->format('d/m/Y') }} · cada lead conta na data em que chegou.
</p>

<div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr))">
    @foreach([
        ['Investido em anúncios', $money($t['spend']), null],
        ['Leads que chegaram', $t['leads'], $t['cost_per_lead'] ? $money($t['cost_per_lead']) . ' por lead' : null],
        ['Leads bons', $t['good'], $t['evaluated'] ? $t['good_pct'] . '% dos avaliados' . ($t['cost_per_good'] ? ' · ' . $money($t['cost_per_good']) . ' cada' : '') : 'nenhum avaliado ainda'],
        ['Vendas', $t['sold'], $t['sold'] ? $money($t['sold_value']) : 'nenhuma marcada'],
        ['Cada R$ 1 investido voltou', $money($t['return']), $t['return_estimated'] ? 'estimado: ' . $money($t['return_estimated']) : null],
    ] as [$label, $value, $hint])
        <div class="card px-4 py-3">
            <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
            <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $value }}</p>
            @if($hint)<p class="text-xs mt-0.5" style="color:var(--muted)">{{ $hint }}</p>@endif
        </div>
    @endforeach
</div>

@php
    $notices = [];
    if (!$funnel['has_rule']) {
        $notices[] = 'Este cliente ainda não tem régua de lead qualificado — a IA não avalia os leads, então "Leads bons" fica zerado.'
            . ($ruleUrl ? ' <a href="' . e($ruleUrl) . '" class="underline">Criar a régua</a>.' : '');
    } elseif ($t['leads'] > $t['evaluated']) {
        $notices[] = ($t['leads'] - $t['evaluated']) . ' lead(s) ainda sem avaliação — a IA avalia de hora em hora, depois que a conversa fica 2 horas parada.';
    }
    if ($funnel['ticket'] === null) {
        $notices[] = 'Sem ticket médio no cadastro do cliente, não dá para estimar o retorno dos leads bons que ainda não compraram.'
            . ($ruleUrl ? ' <a href="' . e($ruleUrl) . '" class="underline">Preencher</a>.' : '');
    } elseif ($funnel['purchase_rate'] === null) {
        $notices[] = 'O retorno estimado aparece quando houver pelo menos 3 vendas marcadas entre os leads bons (é daí que sai a taxa de compra).';
    } else {
        $notices[] = 'Estimativa: leads bons ainda em aberto × taxa de compra (' . round($funnel['purchase_rate'] * 100) . '% dos leads bons compraram) × ticket médio (' . $money($funnel['ticket']) . ').';
    }
    if ($funnel['unattributed'] && $funnel['unattributed']['leads'] > 0) {
        $notices[] = $funnel['unattributed']['leads'] . ' lead(s) chegaram sem dizer de qual campanha vieram (site ou formulário sem o código do anúncio). Eles entram no total, mas não nas linhas das campanhas.';
    }
@endphp
@if($notices)
    <div class="card px-4 py-3 mb-5 text-sm space-y-1" style="border-left:4px solid var(--orange)">
        @foreach($notices as $n)<p style="color:var(--text)">{!! $n !!}</p>@endforeach
    </div>
@endif

<div class="card overflow-hidden">
<div class="overflow-x-auto">
    <table class="nonna-table">
        <thead>
            <tr>
                <th>Campanha</th>
                <th style="text-align:right">Investido</th>
                <th style="text-align:right" title="O que a plataforma de anúncios conta como resultado (pode ser clique, visualização ou conversa iniciada — nem sempre é lead)">Resultados na plataforma</th>
                <th style="text-align:right">Leads</th>
                <th style="text-align:right">Bons</th>
                <th style="text-align:right">Custo por lead</th>
                <th style="text-align:right">Custo por lead bom</th>
                <th style="text-align:right">Vendas</th>
                <th style="text-align:right">Cada R$ 1 voltou</th>
            </tr>
        </thead>
        <tbody>
            @forelse(array_merge($funnel['rows'], $funnel['unattributed'] ? [$funnel['unattributed']] : []) as $r)
                <tr>
                    <td>
                        @if($r['id'])
                            <a href="{{ route($campaignRoute, $r['id']) }}" class="font-semibold hover:underline" style="color:var(--text); font-size:13px">{{ $r['name'] }}</a>
                        @else
                            <span class="font-semibold" style="color:var(--muted); font-size:13px">{{ $r['name'] }}</span>
                        @endif
                        @if($r['platform'])
                            <div class="text-xs" style="color:var(--muted)">{{ ucfirst($r['platform']) }}{{ $r['status'] && $r['status'] !== 'active' ? ' · pausada' : '' }}</div>
                        @endif
                    </td>
                    <td style="text-align:right">{{ $r['spend'] > 0 ? $money($r['spend']) : '—' }}</td>
                    <td style="text-align:right; color:var(--muted)">{{ $r['platform_results'] ?: '—' }}</td>
                    <td style="text-align:right">{{ $r['leads'] ?: '—' }}</td>
                    <td style="text-align:right">
                        {{ $r['good'] ?: ($r['evaluated'] ? 0 : '—') }}
                        @if($r['good_pct'] !== null)<span class="text-xs" style="color:var(--muted)">({{ $r['good_pct'] }}%)</span>@endif
                    </td>
                    <td style="text-align:right">{{ $money($r['cost_per_lead']) }}</td>
                    <td style="text-align:right; font-weight:600">{{ $money($r['cost_per_good']) }}</td>
                    <td style="text-align:right">
                        {{ $r['sold'] ?: '—' }}
                        @if($r['sold'])<div class="text-xs" style="color:var(--muted)">{{ $money($r['sold_value']) }}</div>@endif
                    </td>
                    <td style="text-align:right">
                        {{ $money($r['return']) }}
                        @if($r['return_estimated'] && $r['return_estimated'] !== $r['return'])
                            <div class="text-xs" style="color:var(--muted)">estimado {{ $money($r['return_estimated']) }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-8" style="color:var(--muted)">Nenhum investimento nem lead neste período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>
