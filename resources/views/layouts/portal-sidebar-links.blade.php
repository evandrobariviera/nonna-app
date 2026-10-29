@php
    $_portalClient = $currentClient;

    $_portalTicketsCount = \App\Models\Task::where('is_ticket', true)
        ->where('client_id', $_portalClient->id)
        ->whereNotIn('status', ['concluido', 'cancelado'])
        ->count();

    // Mesmo critério dos cards da Central (sem "Aguardando Envio" nem aviso).
    $_portalApprovalsCount = app(\App\Services\ProjectApprovalPageService::class)->pendingCount($_portalClient);

    $_portalActivePlan = \App\Models\MacroPlan::where('client_id', $_portalClient->id)
        ->where('status', 'em_execucao')
        ->orderByDesc('period_start')
        ->first();
@endphp

@if(app(\App\Services\PortalMagicAccess::class)->active())
{{-- Entrou pelo link de aprovação (login mágico): só a Central. O resto do
     Portal pede senha (EnsurePortalAccess). --}}
<a href="{{ route('portal.approvals.index') }}"
   class="nav-group-trigger open" style="color:var(--orange);">
    <span class="flex items-center gap-3">
        <x-icon name="circle-check" size="16" class="flex-shrink-0" />
        <span class="flex items-center justify-between w-full">
            <span>Central de Aprovações</span>
            @if($_portalApprovalsCount > 0)
                <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                      style="background:rgba(238, 121, 25,.15); color:var(--orange); border:1px solid rgba(238, 121, 25,.3)">
                    {{ $_portalApprovalsCount }}
                </span>
            @endif
        </span>
    </span>
</a>
<p class="px-5 mt-4 text-xs" style="color:var(--muted); line-height:1.5">
    Você entrou pelo link de aprovação.
    <a href="{{ route('portal.dashboard') }}" style="color:var(--purple); font-weight:600">Entrar com senha</a>
    para ver o restante do Portal.
</p>
@else
{{-- ══ ATENDIMENTO ══ --}}
<div class="nav-group-label">Atendimento</div>

<a href="{{ route('portal.meetings.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.meetings.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.meetings.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="message-circle" size="16" class="flex-shrink-0" />
        Reuniões
    </span>
</a>

<a href="{{ route('portal.tickets.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.tickets.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.tickets.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="receipt" size="16" class="flex-shrink-0" />
        <span class="flex items-center justify-between w-full">
            <span>Chamados</span>
            @if($_portalTicketsCount > 0)
                <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                      style="background:{{ request()->routeIs('portal.tickets.*') ? 'rgba(100, 59, 142,.15)' : 'var(--s3)' }};
                             color:{{ request()->routeIs('portal.tickets.*') ? 'var(--purple)' : 'var(--muted)' }};
                             border:1px solid {{ request()->routeIs('portal.tickets.*') ? 'rgba(100, 59, 142,.25)' : 'var(--border2)' }}">
                    {{ $_portalTicketsCount }}
                </span>
            @endif
        </span>
    </span>
</a>

{{-- ══ ESTRATÉGICO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Estratégico</div>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <x-icon name="file-text" size="16" class="flex-shrink-0" />
        Dossiê de Marca
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

<a href="{{ route('portal.projects.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.projects.index') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.projects.index') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="calendar-range" size="16" class="flex-shrink-0" />
        Roadmaps
    </span>
</a>

<a href="{{ $_portalActivePlan ? route('portal.projects.show', $_portalActivePlan) : route('portal.projects.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.projects.show') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.projects.show') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="folder-kanban" size="16" class="flex-shrink-0" />
        Projetos e Campanhas
    </span>
</a>

{{-- ══ OPERACIONAL ══ --}}
<div class="nav-group-label" style="margin-top:8px">Operacional</div>

<a href="{{ route('portal.production.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.production.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.production.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="check-square-2" size="16" class="flex-shrink-0" />
        Produção
    </span>
</a>

<a href="{{ route('portal.approvals.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.approvals.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.approvals.*') ? 'color:var(--orange);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="circle-check" size="16" class="flex-shrink-0" />
        <span class="flex items-center justify-between w-full">
            <span>Central de Aprovações</span>
            @if($_portalApprovalsCount > 0)
                <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                      style="background:rgba(238, 121, 25,.15); color:var(--orange); border:1px solid rgba(238, 121, 25,.3)">
                    {{ $_portalApprovalsCount }}
                </span>
            @endif
        </span>
    </span>
</a>

<a href="{{ route('portal.materials.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.materials.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.materials.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="download" size="16" class="flex-shrink-0" />
        Materiais Aprovados
    </span>
</a>

{{-- ══ PERFORMANCE ══ --}}
<div class="nav-group-label" style="margin-top:8px">Performance</div>

@php $_perfActive = request()->routeIs('portal.performance.*') || request()->routeIs('portal.leads.*') || request()->routeIs('portal.campaigns.*') || request()->routeIs('portal.service-diagnostics.*'); @endphp
<a href="{{ route('portal.performance.index') }}"
   class="nav-group-trigger {{ $_perfActive ? 'open' : '' }}"
   style="{{ $_perfActive ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="trending-up" size="16" class="flex-shrink-0" />
        Central de Performance
    </span>
</a>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <x-icon name="file-chart-column" size="16" class="flex-shrink-0" />
        Relatório Analítico
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

{{-- ══ FISCAL E FINANCEIRO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Fiscal e Financeiro</div>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <x-icon name="file-text" size="16" class="flex-shrink-0" />
        Notas Fiscais
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

<a href="{{ route('portal.boletos.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.boletos.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.boletos.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="receipt" size="16" class="flex-shrink-0" />
        Boletos
    </span>
</a>
@endif
