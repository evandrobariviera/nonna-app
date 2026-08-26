@php
    $_portalClient = $currentClient;

    $_portalTicketsCount = \App\Models\Task::where('is_ticket', true)
        ->where('client_id', $_portalClient->id)
        ->whereNotIn('status', ['concluido', 'cancelado'])
        ->count();

    $_portalApprovalsCount = \App\Models\TaskApprovalRound::whereHas('task', fn ($q) => $q->where('client_id', $_portalClient->id))
        ->where('status', 'pending')
        ->count();

    $_portalActivePlan = \App\Models\MacroPlan::where('client_id', $_portalClient->id)
        ->where('status', 'em_execucao')
        ->orderByDesc('period_start')
        ->first();

    $_leadsModuleActive = $_portalClient->moduleStatus('central_leads') === 'ativo';
@endphp

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

{{-- ══ INTELIGÊNCIA ══ --}}
<div class="nav-group-label" style="margin-top:8px">Inteligência</div>

<a href="{{ route('portal.service-diagnostics.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.service-diagnostics.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.service-diagnostics.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="sparkles" size="16" class="flex-shrink-0" />
        Atendimento Assistido
    </span>
</a>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <x-icon name="sparkles" size="16" class="flex-shrink-0" />
        Campanhas
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

{{-- ══ ESTRATÉGICO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Estratégico</div>

<a href="{{ route('portal.leads.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.leads.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.leads.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="megaphone" size="16" class="flex-shrink-0" />
        <span class="flex items-center justify-between w-full">
            <span>Central de Leads</span>
            @unless($_leadsModuleActive)
                <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Contratar</span>
            @endunless
        </span>
    </span>
</a>

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
            <span>Aprovações</span>
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

{{-- ══ DESEMPENHO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Desempenho</div>

<a href="{{ route('portal.campaigns.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.campaigns.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.campaigns.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="chart-no-axes-column-increasing" size="16" class="flex-shrink-0" />
        Campanhas de Tráfego
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
