{{-- Barra da Central de Campanhas — a prancheta do gestor de tráfego. Fica no
     topo de Hoje, Campanhas, Verbas (antiga Orçamentos) e Otimizações. O
     cliente escolhido (client_id) vai junto de uma aba para a outra. --}}
@php
    $_ccClient = request('client_id') ? ['client_id' => request('client_id')] : [];
    $_ccTabs = [
        ['Hoje',        'campaign-central.today',         'sun',                request()->routeIs('campaign-central.today')],
        ['Campanhas',   'campaigns.index',                'target',             request()->routeIs('campaigns.*')],
        ['Verbas',      'orcamentos.index',               'wallet',             request()->routeIs('orcamentos.*')],
        ['Prestação de contas', 'billing-statements.index', 'receipt',          request()->routeIs('billing-statements.*')],
        ['Otimizações', 'campaign-central.optimizations', 'sliders-horizontal', request()->routeIs('campaign-central.optimizations')],
        ['Contas e conexões', 'ad-connections.index',     'plug',               request()->routeIs('ad-connections.*')],
    ];
@endphp

<div class="mb-6">
    <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
        Central de Campanhas
        <span class="normal-case tracking-normal font-sans" style="color:var(--muted)">· os números de resultado ficam na
            <a href="{{ route('performance.index', $_ccClient) }}" class="underline" style="color:var(--purple)">Central de Performance</a></span>
    </p>
    <nav class="flex gap-1 overflow-x-auto pb-px" style="border-bottom:1px solid var(--border)" aria-label="Central de Campanhas">
        @foreach($_ccTabs as [$label, $route, $icon, $active])
            <a href="{{ route($route, $route === 'orcamentos.index' ? [] : $_ccClient) }}"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold whitespace-nowrap -mb-px transition-colors"
               style="border-bottom:2px solid {{ $active ? 'var(--purple)' : 'transparent' }}; color:{{ $active ? 'var(--purple)' : 'var(--muted2)' }}"
               @if($active) aria-current="page" @endif>
                <x-icon :name="$icon" size="15" />
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
