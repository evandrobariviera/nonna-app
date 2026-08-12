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
        ->whereNull('sprint_id')
        ->count();

    $_campaignInsightsCount = \App\Models\CampaignInsight::whereIn('status', ['novo', 'lido'])->count();

    $_orcamentosAlertCount = \App\Models\ClientAdAccount::whereIn('platform', \App\Models\ClientAdAccount::billingPlatforms())
        ->where('budget_status', 'aguardando_pagto')
        ->count();

    $_financialOverdueCount = \App\Models\FinancialTransaction::where('status', 'previsto')
        ->where('due_date', '<', today())
        ->count();
@endphp

{{-- ══ CRM ══ --}}
<div class="nav-group-label">CRM</div>

{{-- Comercial (só Oportunidades — Contratos mora em Financeiro) --}}
<a href="{{ route('opportunities.index') }}"
   class="nav-group-trigger {{ request()->routeIs('opportunities.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('opportunities.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="receipt" size="16" class="flex-shrink-0" />
        Oportunidades
    </span>
</a>

{{-- Cadastros --}}
<div x-data="{ open: {{ request()->routeIs('clients.*') || request()->routeIs('contacts.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <x-icon name="database" size="16" class="flex-shrink-0" />
            Cadastros
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('contacts.index') }}" class="nav-sub-item {{ request()->routeIs('contacts.*') ? 'active' : '' }}">Contatos</a>
        <a href="{{ route('clients.index') }}" class="nav-sub-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">Clientes</a>
    </div>
</div>

{{-- ══ FINANCEIRO ══ --}}
<div class="nav-group-label" style="margin-top:8px">Financeiro</div>

<div x-data="{ open: {{ request()->routeIs('contracts.*') || request()->routeIs('clients.contracts.*') || request()->routeIs('financial-transactions.*') || request()->routeIs('financial-categories.*') || request()->routeIs('financial-dashboard.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <x-icon name="circle-dollar-sign" size="16" class="flex-shrink-0" />
            Financeiro
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('contracts.index') }}" class="nav-sub-item {{ request()->routeIs('contracts.*') || request()->routeIs('clients.contracts.*') ? 'active' : '' }}">Contratos</a>
        <a href="{{ route('financial-transactions.index') }}" class="nav-sub-item {{ request()->routeIs('financial-transactions.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Lançamentos</span>
                @if($_financialOverdueCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('financial-transactions.*') ? 'rgba(100, 59, 142,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('financial-transactions.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('financial-transactions.*') ? 'rgba(100, 59, 142,.25)' : 'var(--border2)' }}">
                        {{ $_financialOverdueCount }}
                    </span>
                @endif
            </span>
        </a>
        <a href="{{ route('financial-categories.index') }}" class="nav-sub-item {{ request()->routeIs('financial-categories.*') ? 'active' : '' }}">Categorias</a>
        <a href="{{ route('financial-dashboard.index') }}" class="nav-sub-item {{ request()->routeIs('financial-dashboard.*') ? 'active' : '' }}">Dashboard</a>
    </div>
</div>

{{-- ══ OPERACIONAL ══ --}}
<div class="nav-group-label" style="margin-top:8px">Operacional</div>

{{-- Atendimento --}}
<div x-data="{ open: {{ request()->routeIs('meetings.*') || request()->routeIs('tickets.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <x-icon name="message-circle" size="16" class="flex-shrink-0" />
            Atendimento
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('meetings.index') }}" class="nav-sub-item {{ request()->routeIs('meetings.*') ? 'active' : '' }}">Agenda</a>
        <a href="{{ route('tickets.index') }}" class="nav-sub-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Chamados (Tickets)</span>
                @if($_ticketsCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('tickets.*') ? 'rgba(100, 59, 142,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('tickets.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('tickets.*') ? 'rgba(100, 59, 142,.25)' : 'var(--border2)' }}">
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
            <x-icon name="users" size="16" class="flex-shrink-0" />
            Gestão de Clientes
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="#" class="nav-sub-item">Entrada (Onboarding)</a>
        <a href="#" class="nav-sub-item">Sucesso do Cliente (CS)</a>
        <a href="#" class="nav-sub-item">Saída (Offboarding)</a>
    </div>
</div>

{{-- Fluxo --}}
<div x-data="{ open: {{ request()->routeIs('macroplans.*') || request()->routeIs('projects.*') || request()->routeIs('fila.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <x-icon name="folder-kanban" size="16" class="flex-shrink-0" />
            Fluxo
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('macroplans.index') }}"
           class="nav-sub-item {{ request()->routeIs('macroplans.*') && !request()->routeIs('projects.*') ? 'active' : '' }}">
            Planejamentos (Roadmaps)
        </a>
        <a href="{{ route('projects.dashboard') }}"
           class="nav-sub-item {{ request()->routeIs('projects.*') ? 'active' : '' }}">
            Projetos & Campanhas
        </a>
        <a href="{{ route('fila.index') }}"
           class="nav-sub-item {{ request()->routeIs('fila.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Filas (Backlog)</span>
                @if($_filaCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('fila.*') ? 'rgba(100, 59, 142,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('fila.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('fila.*') ? 'rgba(100, 59, 142,.25)' : 'var(--border2)' }}">
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
            <x-icon name="zap" size="16" class="flex-shrink-0" />
            Sprints
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
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
@php
    $_approvalPending = \App\Models\TaskApprovalRound::where('status', 'pending')
        ->orWhere(fn ($q) => $q->where('status', 'changes_requested')->whereNull('handled_at'))
        ->count();
@endphp
<a href="{{ route('approvals.index') }}"
   class="nav-group-trigger {{ request()->routeIs('approvals.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('approvals.*') ? 'color:var(--orange);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="circle-check" size="16" class="flex-shrink-0" />
        Aprovações
    </span>
    @if($_approvalPending > 0)
        <span class="text-xs px-1.5 py-px rounded-full font-semibold"
              style="background:rgba(238, 121, 25,.15); color:var(--orange); border:1px solid rgba(238, 121, 25,.3)">
            {{ $_approvalPending }}
        </span>
    @endif
</a>

{{-- Rotina --}}
<div x-data="{ open: {{ request()->routeIs('campaigns.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <x-icon name="refresh-cw" size="16" class="flex-shrink-0" />
            Rotina
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
    </button>
    <div x-show="open" x-transition style="display:none">
        <a href="{{ route('campaigns.index') }}" class="nav-sub-item {{ request()->routeIs('campaigns.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Campanhas</span>
                @if($_campaignInsightsCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('campaigns.*') ? 'rgba(100, 59, 142,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('campaigns.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('campaigns.*') ? 'rgba(100, 59, 142,.25)' : 'var(--border2)' }}">
                        {{ $_campaignInsightsCount }}
                    </span>
                @endif
            </span>
        </a>
        <a href="{{ route('orcamentos.index') }}" class="nav-sub-item {{ request()->routeIs('orcamentos.*') ? 'active' : '' }}">
            <span class="flex items-center justify-between w-full">
                <span>Orçamentos</span>
                @if($_orcamentosAlertCount > 0)
                    <span class="text-xs px-1.5 py-px rounded-full font-semibold"
                          style="background:{{ request()->routeIs('orcamentos.*') ? 'rgba(100, 59, 142,.15)' : 'var(--s3)' }};
                                 color:{{ request()->routeIs('orcamentos.*') ? 'var(--purple)' : 'var(--muted)' }};
                                 border:1px solid {{ request()->routeIs('orcamentos.*') ? 'rgba(100, 59, 142,.25)' : 'var(--border2)' }}">
                        {{ $_orcamentosAlertCount }}
                    </span>
                @endif
            </span>
        </a>
        <a href="#" class="nav-sub-item">Prestação de Contas</a>
        <a href="#" class="nav-sub-item">Suporte e Manutenção</a>
    </div>
</div>

{{-- ══ INTELIGÊNCIA IA ══ --}}
<div class="nav-group-label" style="margin-top:8px">Inteligência IA</div>

<a href="{{ route('productivity.index') }}"
   class="nav-group-trigger {{ request()->routeIs('productivity.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('productivity.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="chart-no-axes-column-increasing" size="16" class="flex-shrink-0" />
        Produtividade
    </span>
</a>

<div x-data="{ open: {{ request()->routeIs('ai.*') || request()->routeIs('automations.*') || request()->routeIs('service-diagnostics.*') ? 'true' : 'false' }} }">
    <button @click="open = !open" class="nav-group-trigger" :class="open ? 'open' : ''">
        <span class="flex items-center gap-3">
            <x-icon name="sparkles" size="16" class="flex-shrink-0" />
            Agentes & IA
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
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
        <a href="{{ route('service-diagnostics.index') }}"
           class="nav-sub-item {{ request()->routeIs('service-diagnostics.*') ? 'active' : '' }}">
            Atendimento Assistido
        </a>
        <a href="{{ route('automations.index') }}"
           class="nav-sub-item {{ request()->routeIs('automations.*') ? 'active' : '' }}">
            Automações
        </a>
    </div>
</div>

{{-- Sugestões de melhoria (checklist da equipe) --}}
<a href="{{ route('feature-suggestions.index') }}"
   class="nav-group-trigger {{ request()->routeIs('feature-suggestions.*') ? 'open' : '' }}"
   style="{{ request()->routeIs('feature-suggestions.*') ? 'color:var(--purple);' : '' }}">
    <span class="flex items-center gap-3">
        <x-icon name="lightbulb" size="16" class="flex-shrink-0" />
        Sugestões
    </span>
</a>

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
            <x-icon name="layout-dashboard" size="16" class="flex-shrink-0" />
            Meus Dashboards
        </span>
        <x-icon name="chevron-right" size="14" class="transition-transform duration-200" :class="open ? 'rotate-90' : ''" />
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
        <x-icon name="settings" size="16" class="flex-shrink-0" />
        Configurações
    </span>
</a>
@endif
