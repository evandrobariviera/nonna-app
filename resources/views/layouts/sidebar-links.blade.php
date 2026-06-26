@php
    $_sidebarSprints = \App\Models\Sprint::whereIn('status', ['active', 'planning'])
        ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
        ->orderByDesc('starts_at')
        ->limit(5)
        ->get(['id', 'title', 'status']);

    $_filaCount = \App\Models\Task::whereNull('sprint_id')
        ->whereNotIn('status', ['concluido', 'cancelado'])
        ->count();

    $_approvalPending = \App\Models\TaskApprovalRound::whereIn('status', ['pending', 'changes_requested'])->count();

    $_ticketsCount = \App\Models\Task::where('is_ticket', true)
        ->whereNotIn('status', ['concluido', 'cancelado'])
        ->count();
@endphp

{{-- ══ CRM ══ --}}
<div class="nav-group-label">CRM</div>

{{-- Comercial --}}
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

{{-- Cadastros --}}
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
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('contacts.index') }}" class="nav-sub-item {{ request()->routeIs('contacts.*') ? 'active' : '' }}">Contatos</a>
        <a href="{{ route('clients.index') }}" class="nav-sub-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">Clientes</a>
    </div>
</div>

{{-- ══ OPERACIONAL ══ --}}
<div class="nav-group-label" style="margin-top:8px">Operacional</div>

{{-- Atendimento --}}
<div x-data="{ open: {{ request()->routeIs('meetings.*') || request()->routeIs('tickets.*') ? 'true' : 'false' }} }">
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
        <a href="{{ route('meetings.index') }}" class="nav-sub-item {{ request()->routeIs('meetings.*') ? 'active' : '' }}">Agenda</a>
        <a href="{{ route('tickets.index') }}" class="nav-sub-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Chamados (Tickets)</span>
                @if($_ticketsCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('tickets.*') ? 'rgba(106,90,205,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('tickets.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('tickets.*') ? 'rgba(106,90,205,.25)' : 'var(--border2)' }}">
                        {{ $_ticketsCount }}
                    </span>
                @endif
            </span>
        </a>
    </div>
</div>

{{-- Gestão de Clientes --}}
<div x-data="{ open: false }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            Gestão de Clientes
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="#" class="nav-sub-item">Entrada (Onboarding)</a>
        <a href="#" class="nav-sub-item">Sucesso do Cliente (CS)</a>
        <a href="#" class="nav-sub-item">Saída (Offboarding)</a>
    </div>
</div>

{{-- Fluxo --}}
<div x-data="{ open: {{ request()->routeIs('macroplans.*') || request()->routeIs('projects.*') || request()->routeIs('fila.*') || request()->routeIs('clients.dossiers.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75" />
            </svg>
            Fluxo
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('clients.index') }}"
           class="nav-sub-item {{ request()->routeIs('clients.dossiers.*') ? 'active' : '' }}">
            Dossiê de Marca
        </a>
        <a href="{{ route('macroplans.index') }}"
           class="nav-sub-item {{ request()->routeIs('macroplans.*') && !request()->routeIs('projects.*') ? 'active' : '' }}">
            Planejamentos (Roadmaps)
        </a>
        <a href="{{ route('projects.dashboard') }}"
           class="nav-sub-item {{ request()->routeIs('projects.*') ? 'active' : '' }}">
            Projetos (Projects)
        </a>
        <a href="{{ route('fila.index') }}"
           class="nav-sub-item {{ request()->routeIs('fila.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Filas (Backlog)</span>
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
    </div>
</div>

{{-- Sprints --}}
<div x-data="{ open: {{ request()->routeIs('sprints.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
            </svg>
            Sprints
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('sprints.index') }}"
           class="nav-sub-item {{ request()->routeIs('sprints.index') || request()->routeIs('sprints.create') ? 'active' : '' }}">
            Todas as Sprints
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
    </div>
</div>

{{-- Aprovações --}}
@php $_approvalPending = \App\Models\TaskApprovalRound::whereIn('status',['pending','changes_requested'])->count(); @endphp
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

{{-- Rotina --}}
<div x-data="{ open: false }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Rotina
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="#" class="nav-sub-item">Campanhas</a>
        <a href="#" class="nav-sub-item">Orçamentos</a>
        <a href="#" class="nav-sub-item">Prestação de Contas</a>
        <a href="#" class="nav-sub-item">Suporte e Manutenção</a>
    </div>
</div>

{{-- ══ INTELIGÊNCIA IA ══ --}}
<div class="nav-group-label" style="margin-top:8px">Inteligência IA</div>

<div x-data="{ open: {{ request()->routeIs('ai.*') || request()->routeIs('automations.*') ? 'true' : 'false' }} }">
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
        <a href="{{ route('ai.agents.index') }}"
           class="nav-sub-item {{ request()->routeIs('ai.agents.*') ? 'active' : '' }}">
            Agentes
        </a>
        <a href="{{ route('ai.providers.index') }}"
           class="nav-sub-item {{ request()->routeIs('ai.providers.*') ? 'active' : '' }}">
            Providers & Chaves
        </a>
        <a href="{{ route('ai.usage.index') }}"
           class="nav-sub-item {{ request()->routeIs('ai.usage.*') ? 'active' : '' }}">
            Uso & Custos
        </a>
        <a href="{{ route('automations.index') }}"
           class="nav-sub-item {{ request()->routeIs('automations.*') ? 'active' : '' }}">
            Automações
        </a>
    </div>
</div>

{{-- ══ VISÕES ══ --}}
@php
    $_visionRoles = [
        'direcao_geral'    => 'Direção Geral',
        'direcao_criativa' => 'Direção Criativa',
        'coo'              => 'COO & Operação',
        'gestor_campanhas' => 'Gestor de Campanhas',
        'head_criativa'    => 'Head Criativa & Copy',
        'head_tech'        => 'Head de Tecnologia',
        'designer'         => 'Design',
        'trafego'          => 'Tráfego',
        'dev'              => 'Dev',
    ];
    $_myRoles = ($isOrgAdmin ?? false)
        ? array_keys($_visionRoles)
        : ($userFunctionRoles ?? []);
    $_activeVision = request()->route('role') ?? null;
@endphp
@if(!empty($_myRoles))
<div class="nav-group-label" style="margin-top:8px">Visões</div>

<div x-data="{ open: {{ $_activeVision ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            Meus Dashboards
        </span>
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition style="display:none">
        @foreach($_myRoles as $_role)
            @if(isset($_visionRoles[$_role]))
                <a href="{{ route('visoes.show', $_role) }}"
                   class="nav-sub-item {{ $_activeVision === $_role ? 'active' : '' }}">
                    {{ $_visionRoles[$_role] }}
                </a>
            @endif
        @endforeach
    </div>
</div>
@endif

{{-- ══ ADMINISTRAÇÃO ══ --}}
@if($isOrgAdmin ?? false)
<div class="nav-group-label" style="margin-top:8px">Administração</div>
<a href="{{ route('settings.index') }}"
   class="nav-group-trigger {{ request()->routeIs('settings.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('settings.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Configurações
    </span>
</a>
@endif
