@php
    $_sidebarSprints = \App\Models\Sprint::whereIn('status', ['active', 'planning'])
        ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
        ->orderByDesc('starts_at')
        ->limit(5)
        ->get(['id', 'title', 'status']);
@endphp

{{-- ══ CRM ══ --}}
<div class="nav-group-label">CRM</div>

<div x-data="{ open: {{ request()->routeIs('clients.*') || request()->routeIs('contacts.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75m16.5 0v3.75m-16.5-3.75v3.75" />
            </svg>
            Cadastros
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display:none">
        <a href="{{ route('contacts.index') }}" class="nav-sub-item {{ request()->routeIs('contacts.*') ? 'active' : '' }}">Contatos</a>
        <a href="{{ route('clients.index') }}" class="nav-sub-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">Clientes</a>
        <a href="#" class="nav-sub-item">Contas de Anúncios</a>
    </div>
</div>

<div x-data="{ open: {{ request()->routeIs('opportunities.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
            </svg>
            Comercial
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('opportunities.index') }}" class="nav-sub-item {{ request()->routeIs('opportunities.*') ? 'active' : '' }}">Oportunidades</a>
        <a href="#" class="nav-sub-item">Contratos</a>
    </div>
</div>

{{-- ══ OPERACIONAL ══ --}}
<div class="nav-group-label" style="margin-top:8px">Operacional</div>

{{-- Fluxo de Trabalho --}}
<div x-data="{ open: {{ request()->routeIs('macroplans.*') || request()->routeIs('projects.*') || request()->routeIs('fila.*') || request()->routeIs('sprints.*') || request()->routeIs('tickets.*') || request()->routeIs('tasks.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75" />
            </svg>
            Fluxo de Trabalho
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">

        <a href="{{ route('macroplans.index') }}"
           class="nav-sub-item {{ request()->routeIs('macroplans.*') && !request()->routeIs('projects.*') ? 'active' : '' }}">
            Planejamentos
        </a>

        <a href="{{ route('projects.dashboard') }}"
           class="nav-sub-item {{ request()->routeIs('projects.*') ? 'active' : '' }}">
            Projetos
        </a>

        <a href="{{ route('fila.index') }}"
           class="nav-sub-item {{ request()->routeIs('fila.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Filas</span>
                @php
                    $_filaCount = \App\Models\Task::whereNull('sprint_id')
                        ->whereNotIn('status', ['concluido', 'cancelado'])
                        ->count();
                @endphp
                @if($_filaCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('fila.*') ? 'rgba(106,90,205,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('fila.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('fila.*') ? 'rgba(106,90,205,.25)' : 'var(--border2)' }}">
                        {{ $_filaCount }}
                    </span>
                @endif
            </span>
        </a>

        <a href="{{ route('sprints.index') }}"
           class="nav-sub-item {{ request()->routeIs('sprints.index') || request()->routeIs('sprints.create') ? 'active' : '' }}">
            Sprints
        </a>

        @foreach($_sidebarSprints as $sp)
            <a href="{{ route('sprints.show', $sp) }}"
               class="nav-sprint-item {{ request()->is('sprints/'.$sp->id) ? 'active' : '' }}">
                <span class="h-1.5 w-1.5 rounded-full flex-shrink-0"
                      style="background:{{ $sp->status === 'active' ? 'var(--green)' : 'var(--orange)' }}"></span>
                <span class="truncate flex-1 min-w-0">{{ $sp->title }}</span>
                @if($sp->status === 'active')
                    <span style="color:var(--green); font-size:9px; flex-shrink:0">●</span>
                @endif
            </a>
        @endforeach

        <a href="{{ route('tickets.index') }}"
           class="nav-sub-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
            Tickets
        </a>

    </div>
</div>

{{-- Aprovações --}}
@php
    $_approvalPending = \App\Models\TaskApprovalRound::whereIn('status',['pending','changes_requested'])->count();
@endphp
<a href="{{ route('approvals.index') }}"
   class="nav-group-trigger {{ request()->routeIs('approvals.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('approvals.*') ? 'color:var(--orange);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Aprovações
    </span>
    @if($_approvalPending > 0)
        <span class="text-xs px-1.5 py-px rounded-full font-semibold"
              style="background:rgba(255,140,0,.15); color:var(--orange); border:1px solid rgba(255,140,0,.3)">
            {{ $_approvalPending }}
        </span>
    @endif
</a>

{{-- Atendimento --}}
<div x-data="{ open: {{ request()->routeIs('meetings.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
            </svg>
            Atendimento
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('meetings.index') }}" class="nav-sub-item {{ request()->routeIs('meetings.*') ? 'active' : '' }}">Agenda (Reuniões)</a>
        <a href="#" class="nav-sub-item">Onboarding</a>
        <a href="#" class="nav-sub-item">Offboarding</a>
    </div>
</div>

{{-- ══ TRÁFEGO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Tráfego & Performance</div>

<div x-data="{ open: false }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v5.625c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 013 18.75v-5.625zM16.5 13.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 12 21 12.504 21 13.125v5.625c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-5.625zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v10.125c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z" />
            </svg>
            Campanhas & Ads
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="#" class="nav-sub-item">Campanhas Ativas</a>
        <a href="#" class="nav-sub-item">Diário de Otimizações</a>
        <a href="#" class="nav-sub-item">Orçamentos</a>
        <a href="#" class="nav-sub-item">Prestação de Contas</a>
    </div>
</div>

{{-- ══ INTELIGÊNCIA ══ --}}
<div class="nav-group-label" style="margin-top:8px">Inteligência IA</div>

<div x-data="{ open: false }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
            </svg>
            Agentes & IA
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="#" class="nav-sub-item">Meus Agentes (RAG)</a>
        <a href="#" class="nav-sub-item">Base de Conhecimento</a>
        <a href="#" class="nav-sub-item">Histórico de Conversas</a>
    </div>
</div>
