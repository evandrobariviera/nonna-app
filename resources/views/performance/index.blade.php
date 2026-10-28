<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Performance</h1>
        </div>
    </x-slot>

    @php
        $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
        $t = $client ? $funnel['total'] : \App\Services\Leads\LeadFunnel::overviewTotals($overview);
        $spendAll = $client ? $t['spend'] : array_sum(array_column($overview, 'spend'));
    @endphp

    <div class="py-8 px-6">
        @include('performance._tabs')

        <form method="GET" action="{{ route('performance.index') }}" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="client_id" onchange="this.form.submit()" class="filter-select">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $client?->id === $c->id ? 'selected' : '' }}>{{ $c->displayName() }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1">
                <label class="text-xs" style="color:var(--muted)">de</label>
                <input type="date" name="date_from" value="{{ $from->toDateString() }}" onchange="this.form.submit()" class="filter-select" style="padding:6px 8px">
                <label class="text-xs" style="color:var(--muted)">até</label>
                <input type="date" name="date_to" value="{{ $to->toDateString() }}" onchange="this.form.submit()" class="filter-select" style="padding:6px 8px">
            </div>
            @if(request()->hasAny(['client_id', 'date_from', 'date_to']))
                <a href="{{ route('performance.index') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
            @endif
        </form>

        <p class="text-sm mb-4" style="color:var(--muted)">
            {{ $client ? $client->displayName() : 'Todos os clientes' }} · de {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}
        </p>

        {{-- Números do período --}}
        <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr))">
            @foreach([
                ['Investido em anúncios', $money($spendAll), $client && ($funnel['total']['platform_results'] ?? 0) ? number_format($funnel['total']['platform_results'], 0, ',', '.') . ' resultados na plataforma' : null, route('campaigns.index', array_filter(['client_id' => $client?->id]))],
                ['Conversas no WhatsApp', $conversations['total'], $conversations['total'] ? $conversations['from_ad'] . ' vieram de anúncio' : 'nenhuma no período', route('service-diagnostics.index', array_filter(['client_id' => $client?->id]))],
                ['Leads', $t['leads'], $t['leads'] && $t['spend'] > 0 ? $money($t['spend'] / $t['leads']) . ' por lead' : null, route('leads.index', array_filter(['client_id' => $client?->id]))],
                ['Leads bons', $t['good'], $t['good'] && $t['spend'] > 0 ? $money($t['spend'] / $t['good']) . ' por lead bom' : ($t['evaluated'] ? null : 'nenhum avaliado ainda'), route('leads.index', array_filter(['client_id' => $client?->id, 'qualidade' => 'bom']))],
                ['Vendas', $t['sold'], $t['sold'] ? $money($t['sold_value']) : 'nenhuma marcada', route('leads.index', array_filter(['client_id' => $client?->id, 'stage' => 'ganho']))],
                ['Cada R$ 1 investido voltou', $t['sold_value'] > 0 && $t['spend'] > 0 ? $money($t['sold_value'] / $t['spend']) : '—', $client && ($funnel['total']['return_estimated'] ?? null) ? 'estimado: ' . $money($funnel['total']['return_estimated']) : null, route('leads.index', array_filter(['view' => 'resultados', 'client_id' => $client?->id]))],
            ] as [$label, $value, $hint, $url])
                <a href="{{ $url }}" class="card px-4 py-3 block transition-colors hover:border-[var(--purple)]">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
                    <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $value }}</p>
                    @if($hint)<p class="text-xs mt-0.5" style="color:var(--muted)">{{ $hint }}</p>@endif
                </a>
            @endforeach
        </div>

        <div class="grid gap-5 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(340px, 1fr))">
            {{-- Funil --}}
            <div>
                @include('leads._funnel-chart', ['t' => $t])
            </div>

            {{-- Insights em aberto + atendimento --}}
            <div class="flex flex-col gap-5">
                <div class="card px-5 py-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Insights em aberto</p>
                        <a href="{{ route('performance.insights', array_filter(['client_id' => $client?->id])) }}" class="text-xs font-semibold hover:underline" style="color:var(--purple)">
                            Ver {{ $insightsCount > 0 ? 'todos os ' . $insightsCount : 'insights' }} →
                        </a>
                    </div>
                    @forelse($insights as $insight)
                        <a href="{{ $insight->ad_campaign_id ? route('campaigns.show', $insight->ad_campaign_id) : route('performance.insights', ['client_id' => $insight->client_id]) }}"
                           class="flex items-start gap-3 py-2 hover:opacity-80" style="border-top:1px solid var(--border)">
                            <span class="badge badge-{{ $insight->severityColor() }} flex-shrink-0 mt-0.5">{{ $insight->severityLabel() }}</span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold" style="color:var(--text)">{{ $insight->title }}</span>
                                <span class="block text-xs" style="color:var(--muted)">
                                    {{ $insight->kindLabel() }}@unless($client) · {{ $insight->client?->displayName() }}@endunless · {{ $insight->generated_at->format('d/m') }}
                                </span>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm py-2" style="color:var(--muted)">Nenhum insight em aberto. Os alertas e as análises da IA aparecem aqui quando as campanhas chegam no prazo de otimização.</p>
                    @endforelse
                </div>

                @if($client)
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Atendimento</p>
                        @if($diagnostic)
                            <div class="flex items-baseline gap-3 flex-wrap">
                                @if($diagnostic->service_score !== null)
                                    <span class="text-2xl font-black" style="color:var(--text)">{{ $diagnostic->service_score }}<span class="text-sm font-normal" style="color:var(--muted)">/100</span></span>
                                @endif
                                <span class="text-sm" style="color:var(--muted2)">
                                    nota do último diagnóstico ({{ $diagnostic->period_start->format('d/m') }} a {{ $diagnostic->period_end->format('d/m/Y') }})
                                </span>
                            </div>
                            <p class="text-xs mt-1" style="color:var(--muted)">
                                {{ $diagnostic->total_conversations }} conversas analisadas
                                @if($diagnostic->avg_first_response_minutes !== null) · 1ª resposta em {{ $diagnostic->avg_first_response_minutes }} min, em média @endif
                            </p>
                            <a href="{{ route('service-diagnostics.show', [$diagnostic->client_integration_id, $diagnostic]) }}" class="inline-block text-xs font-semibold mt-2 hover:underline" style="color:var(--purple)">Abrir diagnóstico →</a>
                        @else
                            <p class="text-sm" style="color:var(--muted)">Ainda não há diagnóstico de atendimento para este cliente.</p>
                            <a href="{{ route('service-diagnostics.index', ['client_id' => $client->id]) }}" class="inline-block text-xs font-semibold mt-2 hover:underline" style="color:var(--purple)">Ir para Atendimento →</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @include('performance._weekly', ['weekly' => $weekly])

        @unless($client)
            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Por cliente</p>
            @include('leads._overview-table', [
                'overview'  => $overview,
                'clientUrl' => fn ($c) => route('performance.index', array_merge(request()->only(['date_from', 'date_to']), ['client_id' => $c->id])),
            ])
        @endunless
    </div>
</x-app-layout>
