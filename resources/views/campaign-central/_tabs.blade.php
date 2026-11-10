{{-- Barra da Central de Campanhas — a prancheta do gestor de tráfego. Fica no
     topo de Hoje, Campanhas, Verbas (antiga Orçamentos) e Otimizações. O
     cliente escolhido fica guardado na sessão (RememberCampaignClient) e vale em
     todas as abas — por isso os links das abas não levam client_id. --}}
@php
    $_ccClient = ($_ccSaved = \App\Http\Middleware\RememberCampaignClient::current()) ? ['client_id' => $_ccSaved] : [];
    $_ccTabs = [
        ['Hoje',        'campaign-central.today',         'sun',                request()->routeIs('campaign-central.today')],
        ['Campanhas',   'campaigns.index',                'target',             request()->routeIs('campaigns.*')],
        ['Criativos',   'ad-creations.index',             'image',              request()->routeIs('ad-creations.*')],
        ['Verbas',      'orcamentos.index',               'wallet',             request()->routeIs('orcamentos.*')],
        ['Prestação de contas', 'billing-statements.index', 'receipt',          request()->routeIs('billing-statements.*')],
        ['Otimizações', 'campaign-central.optimizations', 'sliders-horizontal', request()->routeIs('campaign-central.optimizations')],
        ['Contas e conexões', 'ad-connections.index',     'plug',               request()->routeIs('ad-connections.*')],
    ];
@endphp

<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
            Central de Campanhas
            <span class="normal-case tracking-normal font-sans" style="color:var(--muted)">· os números de resultado ficam na
                <a href="{{ route('performance.index', $_ccClient) }}" class="underline" style="color:var(--purple)">Central de Performance</a></span>
        </p>
        @unless(request()->routeIs('campaign-creations.*'))
            <a href="{{ route('campaign-creations.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="14" />Nova campanha</a>
        @endunless
    </div>
    <nav class="flex gap-1 overflow-x-auto pb-px" style="border-bottom:1px solid var(--border)" aria-label="Central de Campanhas">
        @foreach($_ccTabs as [$label, $route, $icon, $active])
            <a href="{{ route($route) }}"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold whitespace-nowrap -mb-px transition-colors"
               style="border-bottom:2px solid {{ $active ? 'var(--purple)' : 'transparent' }}; color:{{ $active ? 'var(--purple)' : 'var(--muted2)' }}"
               @if($active) aria-current="page" @endif>
                <x-icon :name="$icon" size="15" />
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
