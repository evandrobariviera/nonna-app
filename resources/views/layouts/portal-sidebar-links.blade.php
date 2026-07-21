@php
    $_portalClient = auth()->user()->client;

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
@endphp

{{-- ══ ATENDIMENTO ══ --}}
<div class="nav-group-label">Atendimento</div>

<a href="{{ route('portal.meetings.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.meetings.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.meetings.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
        </svg>
        Reuniões
    </span>
</a>

<a href="{{ route('portal.tickets.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.tickets.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.tickets.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 10.5h7.5m-7.5 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
        </svg>
        <span class="flex items-center justify-between w-full">
            <span>Chamados</span>
            @if($_portalTicketsCount > 0)
                <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                      style="background:{{ request()->routeIs('portal.tickets.*') ? 'rgba(106,90,205,.15)' : 'var(--s3)' }};
                             color:{{ request()->routeIs('portal.tickets.*') ? 'var(--purple)' : 'var(--muted)' }};
                             border:1px solid {{ request()->routeIs('portal.tickets.*') ? 'rgba(106,90,205,.25)' : 'var(--border2)' }}">
                    {{ $_portalTicketsCount }}
                </span>
            @endif
        </span>
    </span>
</a>

{{-- ══ INTELIGÊNCIA ══ --}}
<div class="nav-group-label" style="margin-top:8px">Inteligência</div>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
        </svg>
        Atendimento
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
        </svg>
        Campanhas
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

{{-- ══ ESTRATÉGICO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Estratégico</div>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75" />
        </svg>
        Dossiê de Marca
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

<a href="{{ route('portal.projects.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.projects.index') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.projects.index') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
        </svg>
        Roadmaps
    </span>
</a>

<a href="{{ $_portalActivePlan ? route('portal.projects.show', $_portalActivePlan) : route('portal.projects.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.projects.show') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.projects.show') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" />
        </svg>
        Projetos e Campanhas
    </span>
</a>

{{-- ══ OPERACIONAL ══ --}}
<div class="nav-group-label" style="margin-top:8px">Operacional</div>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H7.497m5.007 0a7.454 7.454 0 01-.982-3.172M7.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35" />
        </svg>
        Produção
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

<a href="{{ route('portal.approvals.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.approvals.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.approvals.*') ? 'color:var(--orange);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="flex items-center justify-between w-full">
            <span>Aprovações</span>
            @if($_portalApprovalsCount > 0)
                <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                      style="background:rgba(255,140,0,.15); color:var(--orange); border:1px solid rgba(255,140,0,.3)">
                    {{ $_portalApprovalsCount }}
                </span>
            @endif
        </span>
    </span>
</a>

{{-- ══ DESEMPENHO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Desempenho</div>

<a href="{{ route('portal.campaigns.index') }}"
   class="nav-group-trigger {{ request()->routeIs('portal.campaigns.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('portal.campaigns.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
        </svg>
        Campanhas de Tráfego
    </span>
</a>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
        </svg>
        Relatório Analítico
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

{{-- ══ FISCAL E FINANCEIRO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Fiscal e Financeiro</div>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Notas Fiscais
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>

<span class="nav-group-trigger" style="opacity:.45; cursor:not-allowed">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
        </svg>
        Boletos
    </span>
    <span class="badge badge-muted" style="font-size:9px; padding:1px 6px; white-space:nowrap; flex-shrink:0">Em breve</span>
</span>
