{{-- Dashboard da Central de Performance no Portal. Cada bloco só aparece com o
     módulo liberado ($active); os que faltam viram convite para a vitrine.
     Números de leads somados só para quem vê todos os leads ($seesLeads). --}}
@php
    $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    $t = $funnel['total'] ?? null;
    $missing = collect(\App\Models\ClientModule::$modules)->filter(fn ($label, $key) => !$active[$key]);

    $cards = [];
    if ($active['trafego']) {
        $cards[] = ['Investido em anúncios', $money($t['spend']), $t['platform_results'] ? number_format($t['platform_results'], 0, ',', '.') . ' resultados nos anúncios' : null, route('portal.campaigns.index')];
    }
    if ($active['atendimento']) {
        $cards[] = ['Conversas no WhatsApp', $conversations['total'], $conversations['total'] ? $conversations['from_ad'] . ' vieram de anúncio' : 'nenhuma no período', route('portal.service-diagnostics.index')];
    }
    if ($seesLeads) {
        $cards[] = ['Contatos (leads)', $t['leads'], $active['trafego'] && $t['leads'] && $t['spend'] > 0 ? $money($t['spend'] / $t['leads']) . ' por contato' : null, route('portal.leads.index')];
        $cards[] = ['Com perfil de compra', $t['good'], $t['evaluated'] ? $t['good_pct'] . '% dos avaliados' : 'nenhum avaliado ainda', route('portal.leads.index', ['qualidade' => 'bom'])];
        $cards[] = ['Vendas', $t['sold'], $t['sold'] ? $money($t['sold_value']) : 'marque "Comprou" na ficha do lead', route('portal.leads.index', ['view' => 'resultados'])];
        if ($active['trafego']) {
            $cards[] = ['Cada R$ 1 investido voltou', $money($t['return']), $t['return_estimated'] ? 'estimado: ' . $money($t['return_estimated']) : null, route('portal.leads.index', ['view' => 'resultados'])];
        }
    }
@endphp

<x-portal-layout>
    <x-slot name="title">Central de Performance</x-slot>

    @include('portal.performance._tabs')

    <div class="mb-4">
        <h1 class="text-2xl font-black" style="color: var(--text)">Central de Performance</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Tudo o que diz respeito aos resultados de {{ $client->company_name }} na internet, num lugar só.</p>
    </div>

    <form method="GET" action="{{ route('portal.performance.index') }}" class="flex flex-wrap items-end gap-3 mb-5">
        <div class="flex items-center gap-1">
            <label class="text-xs" style="color:var(--muted)">de</label>
            <input type="date" name="date_from" value="{{ $from->toDateString() }}" onchange="this.form.submit()" class="filter-select" style="padding:6px 8px">
            <label class="text-xs" style="color:var(--muted)">até</label>
            <input type="date" name="date_to" value="{{ $to->toDateString() }}" onchange="this.form.submit()" class="filter-select" style="padding:6px 8px">
        </div>
    </form>

    @if($cards)
        <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr))">
            @foreach($cards as [$label, $value, $hint, $url])
                <a href="{{ $url }}" class="card px-4 py-3 block">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
                    <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $value }}</p>
                    @if($hint)<p class="text-xs mt-0.5" style="color:var(--muted)">{{ $hint }}</p>@endif
                </a>
            @endforeach
        </div>
    @endif

    @if($seesLeads || $active['analises_ia'] || $active['atendimento'])
        <div class="grid gap-5 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(320px, 1fr))">
            @if($seesLeads)
                <div>@include('leads._funnel-chart', ['t' => $active['trafego'] ? $t : array_merge($t, ['spend' => 0])])</div>
            @endif

            @if($active['analises_ia'] || $active['atendimento'])
                <div class="flex flex-col gap-5">
                    @if($active['analises_ia'])
                        <div class="card px-5 py-4">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Como está indo</p>
                                <a href="{{ route('portal.performance.insights') }}" class="text-xs font-semibold hover:underline" style="color:var(--purple)">Ver todos →</a>
                            </div>
                            @forelse($insights as $insight)
                                <div class="py-2" style="border-top:1px solid var(--border)">
                                    <p class="text-xs" style="color:var(--muted)">{{ $insight->campaign?->name ?? 'Anúncios' }} · {{ $insight->generated_at->format('d/m') }}</p>
                                    <p class="text-sm mt-0.5 line-clamp-3" style="color:var(--muted2); line-height:1.6" title="{{ $insight->client_summary }}">{{ $insight->client_summary }}</p>
                                </div>
                            @empty
                                <p class="text-sm" style="color:var(--muted)">Ainda não há análises neste período. Elas aparecem aqui a cada ciclo de otimização das campanhas.</p>
                            @endforelse
                        </div>
                    @endif

                    @if($active['atendimento'])
                        <div class="card px-5 py-4">
                            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Atendimento</p>
                            @if($diagnostic)
                                <div class="flex items-baseline gap-3 flex-wrap">
                                    @if($diagnostic->service_score !== null)
                                        <span class="text-2xl font-black" style="color:var(--text)">{{ $diagnostic->service_score }}<span class="text-sm font-normal" style="color:var(--muted)">/100</span></span>
                                    @endif
                                    <span class="text-sm" style="color:var(--muted2)">nota do último diagnóstico ({{ $diagnostic->period_start->format('d/m') }} a {{ $diagnostic->period_end->format('d/m/Y') }})</span>
                                </div>
                                <a href="{{ route('portal.service-diagnostics.show', [$diagnostic->client_integration_id, $diagnostic]) }}" class="inline-block text-xs font-semibold mt-2 hover:underline" style="color:var(--purple)">Abrir diagnóstico →</a>
                            @else
                                <p class="text-sm" style="color:var(--muted)">O primeiro diagnóstico do seu atendimento ainda está sendo preparado pela nossa equipe.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if($weekly && ($active['trafego'] || $seesLeads))
        @include('performance._weekly', ['weekly' => $weekly, 'charts' => array_values(array_filter([$active['trafego'] ? 'spend' : null, $seesLeads ? 'leads' : null]))])
    @endif

    @if($missing->isNotEmpty())
        <div class="card px-5 py-4" style="border-left:4px solid var(--purple)">
            <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Complete sua Central de Performance</p>
            <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr))">
                @foreach($missing as $key => $label)
                    <a href="{{ route('portal.performance.showcase', $key) }}" class="flex items-start gap-3 p-3 rounded-lg" style="background:var(--s2)">
                        <x-icon :name="\App\Models\ClientModule::$showcase[$key]['icon']" size="18" style="color:var(--purple)" class="flex-shrink-0 mt-0.5" />
                        <span>
                            <span class="block text-sm font-semibold" style="color:var(--text)">{{ $label }}</span>
                            <span class="block text-xs" style="color:var(--muted)">{{ \App\Models\ClientModule::$showcase[$key]['benefits'][0][1] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-portal-layout>
