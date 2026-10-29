{{-- Barra da Central de Performance no Portal — no topo do dashboard e das
     telas que viraram abas. Aba com módulo não contratado leva à vitrine. --}}
@php
    $_pc = app('currentPortalClient');
    $_tabs = [
        ['Dashboard',   null,            'portal.performance.index',            'layout-dashboard', request()->routeIs('portal.performance.index')],
        ['Campanhas',   'trafego',       'portal.campaigns.index',              'target',           request()->routeIs('portal.campaigns.*') || request()->is('portal/performance/conhecer/trafego')],
        ['Leads',       'central_leads', 'portal.leads.index',                  'megaphone',        request()->routeIs('portal.leads.*') || request()->is('portal/performance/conhecer/central_leads')],
        ['Insights',    'analises_ia',   'portal.performance.insights',         'sparkles',         request()->routeIs('portal.performance.insights') || request()->is('portal/performance/conhecer/analises_ia')],
        ['Atendimento', 'atendimento',   'portal.service-diagnostics.index',    'headphones',       request()->routeIs('portal.service-diagnostics.*') || request()->is('portal/performance/conhecer/atendimento')],
    ];
@endphp

<div class="mb-6">
    <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Central de Performance</p>
    <nav class="flex gap-1 overflow-x-auto pb-px" style="border-bottom:1px solid var(--border)" aria-label="Central de Performance">
        @foreach($_tabs as [$label, $module, $route, $icon, $isActive])
            @php $locked = $module && !$_pc->portalModuleActive($module); @endphp
            <a href="{{ $locked ? route('portal.performance.showcase', $module) : route($route) }}"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold whitespace-nowrap -mb-px transition-colors"
               style="border-bottom:2px solid {{ $isActive ? 'var(--purple)' : 'transparent' }}; color:{{ $isActive ? 'var(--purple)' : 'var(--muted2)' }}; {{ $locked && !$isActive ? 'opacity:.6' : '' }}"
               @if($isActive) aria-current="page" @endif>
                <x-icon :name="$icon" size="15" />
                {{ $label }}
                @if($locked)
                    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px">Contratar</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
