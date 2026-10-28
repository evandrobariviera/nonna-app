{{-- Barra da Central de Performance — no topo do dashboard e das telas que
     viraram abas (Leads, Campanhas, Insights, Atendimento). O cliente
     escolhido (client_id) vai junto de uma aba para a outra. --}}
@php
    $_perfClient = request('client_id') && request('client_id') !== 'sem_cliente' ? ['client_id' => request('client_id')] : [];
    $_perfInsights = once(fn () => \App\Models\CampaignInsight::whereIn('status', ['novo', 'lido'])
        ->when($_perfClient, fn ($q) => $q->where('client_id', $_perfClient['client_id']))->count());
    $_perfTabs = [
        ['Dashboard',   'performance.index',         'layout-dashboard', request()->routeIs('performance.index')],
        ['Leads',       'leads.index',               'megaphone',        request()->routeIs('leads.*')],
        ['Campanhas',   'campaigns.index',           'target',           request()->routeIs('campaigns.*')],
        ['Insights',    'performance.insights',      'sparkles',         request()->routeIs('performance.insights')],
        ['Atendimento', 'service-diagnostics.index', 'headphones',       request()->routeIs('service-diagnostics.*')],
    ];
@endphp

<div class="mb-6">
    <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Central de Performance</p>
    <nav class="flex gap-1 overflow-x-auto pb-px" style="border-bottom:1px solid var(--border)" aria-label="Central de Performance">
        @foreach($_perfTabs as [$label, $route, $icon, $active])
            <a href="{{ route($route, $_perfClient) }}"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold whitespace-nowrap -mb-px transition-colors"
               style="border-bottom:2px solid {{ $active ? 'var(--purple)' : 'transparent' }}; color:{{ $active ? 'var(--purple)' : 'var(--muted2)' }}"
               @if($active) aria-current="page" @endif>
                <x-icon :name="$icon" size="15" />
                {{ $label }}
                @if($label === 'Insights' && $_perfInsights > 0)
                    <span class="text-xs px-1.5 py-px rounded-full" style="background:var(--s3); color:var(--muted); border:1px solid var(--border2)">{{ $_perfInsights }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
