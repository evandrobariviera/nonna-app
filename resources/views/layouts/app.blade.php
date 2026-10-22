<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{
          sidebarOpen: false,
          {{-- Menu recolhido só com ícones (desktop), lembrado no navegador --}}
          sidebarCollapsed: localStorage.getItem('nonna-sidebar') === 'collapsed',
          toggleSidebar() {
              this.sidebarCollapsed = !this.sidebarCollapsed;
              localStorage.setItem('nonna-sidebar', this.sidebarCollapsed ? 'collapsed' : 'open');
              this.navTip.show = false;
          },
          {{-- Nome do item ao passar o mouse com o menu recolhido (fixo na tela: o menu corta o que vaza) --}}
          navTip: { show: false, text: '', top: 0, left: 0 },
          showNavTip(e) {
              const el = this.sidebarCollapsed && e.target.closest('.nav-group-trigger');
              if (!el) { this.navTip.show = false; return; }
              const r = el.getBoundingClientRect();
              this.navTip = { show: true, text: el.querySelector('span')?.textContent.trim() || '', top: r.top + r.height / 2, left: r.right + 10 };
          },
          darkMode: localStorage.getItem('nonna-theme') === 'dark',
          toggleTheme() {
              document.documentElement.classList.add('theme-transition');
              this.darkMode = !this.darkMode;
              localStorage.setItem('nonna-theme', this.darkMode ? 'dark' : 'light');
              setTimeout(() => document.documentElement.classList.remove('theme-transition'), 250);
          }
      }"
      :class="{ dark: darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">

    <title>{{ config('app.name', 'Nonna Agência Digital — Do posicionamento à conversão!') }}</title>

    {{-- Flash prevention: aplica dark antes do Alpine inicializar --}}
    <script>if(localStorage.getItem('nonna-theme')==='dark')document.documentElement.classList.add('dark')</script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

    {{-- Branding dinâmico do tenant --}}
    @isset($currentOrg)
    @php
        $brandPrimary   = $currentOrg->brandColor('primary_color',   '#643B8E');
        $brandSecondary = $currentOrg->brandColor('secondary_color',  '#EE7919');
    @endphp
    <style>
        :root {
            --purple: {{ $brandPrimary }};
            --orange: {{ $brandSecondary }};
        }
    </style>
    @endisset
</head>
@php
    // Modo enxuto pra quando a página é aberta dentro do popup de tarefa (ver
    // task-popup.js): sem sidebar nem topbar, só o conteúdo — evita mostrar o app
    // inteiro aninhado dentro de si mesmo num iframe pequeno.
    $embed = request()->boolean('embed');
@endphp
<body class="h-screen overflow-hidden">

    {{-- ── MOBILE DRAWER ── --}}
    @unless($embed)
    <div x-show="sidebarOpen" class="fixed inset-0 z-50 flex md:hidden" x-cloak>
        <div class="fixed inset-0 bg-black/60" @click="sidebarOpen = false"
             x-transition:enter="transition-opacity duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <div class="relative z-10 flex w-72 flex-col sidebar-nav"
             x-transition:enter="transition duration-200 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition duration-200 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full">
            <div class="flex h-16 items-center justify-between px-5 border-b" style="border-color:var(--border)">
                <a href="{{ route('dashboard') }}" class="text-sm font-bold grad-text">nonna OS</a>
                <button @click="sidebarOpen = false" style="color:var(--muted)">
                    <x-icon name="x" size="20" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                @include('layouts.sidebar-links')
            </nav>
        </div>
    </div>
    @endunless

    {{-- ── LAYOUT PRINCIPAL ── --}}
    <div class="flex h-screen">

        {{-- ── SIDEBAR DESKTOP ── --}}
        @unless($embed)
        {{-- Menu no padrão Mirbal (redesign etapa 2): 264px, branco, logo na altura do topo,
             sem blocos de usuário/rodapé (foram pro topo). Recolhido = só ícones (76px);
             clicar num grupo com o menu recolhido reabre o menu. --}}
        <aside class="hidden md:flex md:flex-col sidebar-nav flex-shrink-0 transition-[width] duration-200"
               :class="sidebarCollapsed ? 'sidebar-collapsed md:w-[76px]' : 'md:w-[264px]'"
               @click.capture="if (sidebarCollapsed && $event.target.closest('button.nav-group-trigger')) toggleSidebar()"
               @mouseover="showNavTip($event)" @mouseleave="navTip.show = false">
            <div x-show="navTip.show && navTip.text" x-cloak class="nav-tip"
                 :style="`top:${navTip.top}px; left:${navTip.left}px`" x-text="navTip.text"></div>
            {{-- Logo --}}
            <a href="{{ route('dashboard') }}" class="flex h-[70px] items-center transition-opacity hover:opacity-80 flex-shrink-0"
               :class="sidebarCollapsed ? 'justify-center px-2' : 'px-6'">
                @if(isset($currentOrg) && $currentOrg->logoUrl())
                    <img src="{{ $currentOrg->logoUrl() }}" alt="{{ $currentOrg->name }}" class="h-8 w-auto max-w-full object-contain object-left"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <span class="text-base font-black grad-text hidden">{{ $currentOrg->name }}</span>
                @else
                    <img x-show="sidebarCollapsed" x-cloak src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png"
                         alt="{{ config('app.name') }}" class="h-8 w-8">
                    <img x-show="!sidebarCollapsed" src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
                         alt="{{ config('app.name') }}" class="h-8 w-auto max-w-full object-contain object-left"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <span class="text-base font-black grad-text hidden">{{ config('app.name') }}</span>
                @endif
            </a>

            {{-- Links --}}
            <nav class="flex-1 overflow-y-auto overflow-x-hidden pb-4">
                @include('layouts.sidebar-links')
            </nav>
        </aside>
        @endunless

        {{-- ── CONTEÚDO PRINCIPAL ── --}}
        <div class="flex flex-1 flex-col min-w-0">

            {{-- TOPBAR --}}
            @unless($embed)
            {{-- Topo "flutuante" do Mirbal: afastado das laterais na mesma margem do conteúdo,
                 cantos arredondados só embaixo. Título da página saiu daqui e foi pra linha de
                 título dentro do conteúdo (abaixo). --}}
            <header class="topbar flex h-[64px] md:h-[70px] flex-shrink-0 items-center gap-1.5 md:gap-3 px-3 md:px-4 mx-3 md:mx-6">
                {{-- Botão mobile --}}
                <button type="button" class="md:hidden topbar-btn" @click="sidebarOpen = true">
                    <x-icon name="menu" size="20" />
                </button>

                {{-- Recolher/abrir o menu (desktop) --}}
                <button type="button" class="hidden md:inline-flex topbar-btn" @click="toggleSidebar()"
                        :title="sidebarCollapsed ? 'Abrir o menu' : 'Recolher o menu'">
                    <x-icon name="panel-left-close" size="19" x-show="!sidebarCollapsed" />
                    <x-icon name="panel-left-open" size="19" x-show="sidebarCollapsed" x-cloak />
                </button>

                <div class="flex-1"></div>

                {{-- Seletor de MODO (ver App\Support\DashboardModes) — o que a pessoa está
                     fazendo agora; trocar salva como último modo e abre a Dashboard nele.
                     Só aparece pra quem tem mais de um modo. --}}
                @auth
                    @if(count($dashboardModes ?? []) > 1)
                        @php $currentModeMeta = \App\Support\DashboardModes::ALL[$dashboardMode]; @endphp
                        <div class="relative flex-shrink-0" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="btn btn-ghost btn-sm flex items-center gap-1.5"
                                title="Modo: {{ $currentModeMeta['label'] }}">
                                <x-icon :name="$currentModeMeta['icon']" size="14" style="color:var(--purple)" />
                                <span class="hidden sm:inline">{{ $currentModeMeta['label'] }}</span>
                                <x-icon name="chevron-down" size="12" />
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 class="absolute right-0 mt-1 z-30 w-64 max-md:fixed max-md:inset-x-3 max-md:top-16 max-md:w-auto py-1"
                                 style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.15)">
                                <p class="px-3 pt-1.5 pb-1 text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Modo</p>
                                @foreach($dashboardModes as $modeKey)
                                    @php $meta = \App\Support\DashboardModes::ALL[$modeKey]; $isCurrent = $modeKey === $dashboardMode; @endphp
                                    <form method="POST" action="{{ route('dashboard.mode') }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="mode" value="{{ $modeKey }}">
                                        <button type="submit"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors"
                                            style="{{ $isCurrent ? 'background:rgba(100, 59, 142,.10)' : '' }}"
                                            onmouseover="this.style.background='var(--s3)'"
                                            onmouseout="this.style.background='{{ $isCurrent ? 'rgba(100, 59, 142,.10)' : '' }}'">
                                            <x-icon :name="$meta['icon']" size="15" style="color:{{ $isCurrent ? 'var(--purple)' : 'var(--muted)' }}" />
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold" style="color:{{ $isCurrent ? 'var(--purple)' : 'var(--text)' }}">{{ $meta['label'] }}</span>
                                                <span class="block text-xs" style="color:var(--muted)">{{ $meta['hint'] }}</span>
                                            </span>
                                            @if($isCurrent)
                                                <x-icon name="check" size="14" class="ml-auto" style="color:var(--purple)" />
                                            @endif
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endauth

                {{-- Criar rápido — sempre visível, sem precisar navegar até Tickets/Agenda primeiro --}}
                @auth
                    <a href="{{ route('meetings.create') }}"
                        class="btn btn-ghost btn-sm flex items-center gap-1.5 flex-shrink-0">
                        <x-icon name="calendar-plus" size="14" />
                        <span class="hidden sm:inline">Nova Reunião</span>
                    </a>
                    <a href="{{ route('tickets.create') }}"
                        class="btn btn-primary btn-sm flex items-center gap-1.5 flex-shrink-0">
                        <x-icon name="ticket" size="14" />
                        <span class="hidden sm:inline">Novo Ticket</span>
                    </a>
                @endauth

                {{-- Busca global de tarefas/tickets — acha item antigo mesmo em sprint
                     fechada ou já concluído/cancelado (não usa os filtros operacionais
                     de /tarefas). Atalho "/" abre quando nada mais está focado. --}}
                @auth
                    <div class="relative" x-data="{ open:false, q:'', results:[], loading:false, timer:null }"
                         @keydown.slash.window="if (!open && document.activeElement === document.body) { $event.preventDefault(); open = true; $nextTick(() => $refs.globalSearchInput.focus()) }"
                         @nonna-open-search.window="open = true; $nextTick(() => $refs.globalSearchInput.focus())">
                        <button @click="open = !open; if (open) $nextTick(() => $refs.globalSearchInput.focus())"
                            class="topbar-btn" title="Buscar tarefa/ticket">
                            <x-icon name="search" size="18" />
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-1 z-30 w-[360px] max-md:fixed max-md:inset-x-3 max-md:top-16 max-md:w-auto"
                             style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.15)">
                            <div class="p-2" style="border-bottom:1px solid var(--border2)">
                                <input type="text" x-ref="globalSearchInput" x-model="q"
                                    @input="
                                        clearTimeout(timer);
                                        const term = q.trim();
                                        if (term.length < 2) { results = []; loading = false; return; }
                                        loading = true;
                                        timer = setTimeout(() => {
                                            fetch('{{ route('tasks.search-global') }}?q=' + encodeURIComponent(term), {
                                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                            })
                                                .then(r => r.json())
                                                .then(data => { results = data; loading = false; });
                                        }, 300)
                                    "
                                    placeholder="Buscar por título ou ID ClickUp..."
                                    class="field w-full px-3 py-2 text-sm focus:outline-none">
                            </div>
                            <div style="max-height:360px; overflow-y:auto">
                                <template x-if="loading">
                                    <div class="px-4 py-6 text-center">
                                        <p class="text-xs" style="color:var(--muted)">Buscando...</p>
                                    </div>
                                </template>
                                <template x-if="!loading && q.trim().length >= 2 && results.length === 0">
                                    <div class="px-4 py-6 text-center">
                                        <p class="text-xs" style="color:var(--muted)">Nenhuma tarefa encontrada.</p>
                                    </div>
                                </template>
                                <template x-for="r in results" :key="r.id">
                                    <a :href="r.url" class="dd-hover block px-4 py-2.5 transition-colors"
                                       style="border-bottom:1px solid var(--border2)">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-semibold truncate" style="color:var(--text)" x-text="r.title"></span>
                                            <span class="badge flex-shrink-0" :class="'badge-' + r.status_color" x-text="r.status_label"></span>
                                        </div>
                                        <p class="text-xs mt-0.5" style="color:var(--muted)"
                                           x-text="[r.is_ticket ? 'Ticket' : 'Tarefa', r.client, r.sprint_label].filter(Boolean).join(' · ')"></p>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                @endauth

                {{-- Toggle tema --}}
                <button @click="toggleTheme()" class="topbar-btn"
                    :title="darkMode ? 'Modo claro' : 'Modo escuro'">
                    {{-- Lua (modo claro → clicar para escurecer) --}}
                    <x-icon name="moon" size="18" x-show="!darkMode" />
                    {{-- Sol (modo escuro → clicar para clarear) --}}
                    <x-icon name="sun" size="18" x-show="darkMode" x-cloak />
                </button>

                {{-- Aviso de navegador (Notification API + som) — só liga se o usuário pedir,
                     Chrome exige gesto explícito pra conceder a permissão. --}}
                @auth
                    <button type="button"
                        @click="$store.browserNotify.enabled ? $store.browserNotify.disable() : $store.browserNotify.enable()"
                        class="topbar-btn hidden sm:inline-flex"
                        :style="$store.browserNotify.enabled ? 'color:var(--purple)' : ''"
                        :title="$store.browserNotify.enabled ? 'Avisos do navegador ativados — clique pra desligar' : 'Ativar avisos do navegador (popup + som)'">
                        <x-icon name="bell-ring" size="18" x-show="$store.browserNotify.enabled" x-cloak />
                        <x-icon name="bell-off" size="18" x-show="!$store.browserNotify.enabled" />
                    </button>
                @endauth

                {{-- Notificações --}}
                @auth
                    @php
                        $unreadNotifications = \App\Models\InternalNotification::where('user_id', auth()->id())
                            ->where('status', 'novo')
                            ->orderByDesc('generated_at')
                            ->limit(8)
                            ->get();
                    @endphp
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="topbar-btn relative" title="Notificações">
                            <x-icon name="bell" size="18" />
                            @if($unreadNotifications->count() > 0)
                                <span class="absolute top-0.5 right-0.5 flex items-center justify-center rounded-full text-white"
                                      style="background:var(--orange); font-size:9px; min-width:16px; height:16px; padding:0 4px; line-height:16px; border:2px solid var(--s1)">
                                    {{ $unreadNotifications->count() }}
                                </span>
                            @endif
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-1 z-30 w-[340px] max-md:fixed max-md:inset-x-3 max-md:top-16 max-md:w-auto"
                             style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.15)">
                            <div class="px-4 py-3" style="border-bottom:1px solid var(--border2)">
                                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Notificações</p>
                            </div>
                            <div style="max-height:360px; overflow-y:auto">
                                @forelse($unreadNotifications as $n)
                                    <div class="px-4 py-3 flex flex-col gap-1.5" style="border-bottom:1px solid var(--border2)">
                                        <a href="{{ $n->link ?? '#' }}" class="text-sm font-semibold" style="color:var(--text)">{{ $n->title }}</a>
                                        @if($n->body)
                                            <p class="text-xs" style="color:var(--muted2); line-height:1.5">{{ $n->body }}</p>
                                        @endif
                                        {{-- Controle do próprio sino: ✓ resolver · lido · ✕ apagar
                                             (lido/não lido de volta fica em "Ver todas"). --}}
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="resolvido">
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ $n->resolveHint() }}">
                                                    ✓ {{ $n->resolveLabel() }}
                                                </button>
                                            </form>
                                            @if($n->canDiscard())
                                                <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="lido">
                                                    <button type="submit" class="btn btn-ghost btn-xs" title="Tira do sino sem resolver — volta como não lido em Ver todas">
                                                        Marcar como lido
                                                    </button>
                                                </form>
                                            @endif
                                            @if($n->canDiscard())
                                                <form method="POST" action="{{ route('notifications.destroy', $n) }}" class="ml-auto">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs" style="color:var(--muted2)" title="Apagar este aviso">✕</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-4 py-8 text-center">
                                        <p class="text-xs" style="color:var(--muted)">Nenhuma notificação nova.</p>
                                    </div>
                                @endforelse
                            </div>
                            <a href="{{ route('notifications.index') }}"
                               class="block px-4 py-2.5 text-center text-xs font-mono transition-colors"
                               style="color:var(--purple); border-top:1px solid var(--border2)">
                                Ver todas →
                            </a>
                        </div>
                    </div>
                @endauth

                {{-- Pessoa logada (Mirbal: nome + função + foto à direita) — Perfil e Sair moram
                     aqui (antes ficavam no rodapé do menu lateral). --}}
                @auth
                    @php $topbarRole = Auth::user()->functionalRoles()->value('functional_roles.name'); @endphp
                    <div class="relative pl-1 md:pl-2" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="flex items-center gap-2.5 rounded-full transition-opacity hover:opacity-80" title="{{ Auth::user()->email }}">
                            <span class="hidden lg:flex flex-col items-end leading-tight">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ Auth::user()->name }}</span>
                                @if($topbarRole)
                                    <span class="text-xs" style="color:var(--muted)">{{ $topbarRole }}</span>
                                @endif
                            </span>
                            <x-user-avatar :user="Auth::user()" size="9" />
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 z-30 w-56 py-1.5"
                             style="background:var(--s1); border:1px solid var(--border2); border-radius:var(--radius); box-shadow:0 8px 24px rgba(0,0,0,.10)">
                            <div class="px-3.5 py-2" style="border-bottom:1px solid var(--border2)">
                                <p class="text-sm font-medium truncate" style="color:var(--text)">{{ Auth::user()->name }}</p>
                                <p class="text-xs truncate" style="color:var(--muted)">{{ Auth::user()->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm transition-colors"
                               style="color:var(--text)" onmouseover="this.style.background='rgba(100, 59, 142,.08)'; this.style.color='var(--purple)'" onmouseout="this.style.background=''; this.style.color='var(--text)'">
                                <x-icon name="user" size="15" /> Meu perfil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-left transition-colors"
                                        style="color:var(--text)" onmouseover="this.style.background='rgba(100, 59, 142,.08)'; this.style.color='var(--purple)'" onmouseout="this.style.background=''; this.style.color='var(--text)'">
                                    <x-icon name="log-out" size="15" /> Sair
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </header>
            @endunless

            {{-- PÁGINA --}}
            {{-- Margem lateral fixa, igual à do topo; o conteúdo ocupa o resto (decisão do
                 Evandro: "margem fixa onde o conteúdo se adapta"). --}}
            <main class="app-page flex-1 overflow-y-auto {{ $embed ? 'p-4 md:p-6' : 'px-3 pt-4 pb-24 md:px-6 md:pt-5 md:pb-0' }} flex flex-col" style="background:var(--bg)">
                <div class="flex-1">
                    {{-- Linha de título (Mirbal): título da página à esquerda, caminho à direita.
                         Antes o título ficava dentro do topo. --}}
                    @if(isset($header) && !$embed)
                        @php
                            // Cabeçalho com link/botão (Contatos, Campanha...) já traz a própria
                            // navegação — o caminho à direita só aparece pra título de texto simples.
                            $headerIsSimple = !preg_match('/<(a|button|form)\b/i', (string) $header);
                        @endphp
                        <div class="page-title-row">
                            <h2 class="min-w-0 {{ $headerIsSimple ? '' : 'flex-1' }}">{{ $header }}</h2>
                            @if($headerIsSimple)
                            <nav class="page-crumbs hidden sm:flex" aria-label="Caminho">
                                <a href="{{ route('dashboard') }}" class="inline-flex" title="Início" style="color:var(--purple)">
                                    <x-icon name="house" size="15" />
                                </a>
                                <x-icon name="chevron-right" size="13" />
                                <span class="truncate" style="max-width:320px">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $header))), 60) }}</span>
                            </nav>
                            @endif
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                    {{ $slot }}
                </div>

                {{-- Rodapé em card (Mirbal) — só desktop; no celular a barra inferior ocupa o pé --}}
                @unless($embed)
                    <footer class="card hidden md:flex items-center justify-between gap-3 px-5 py-4 mt-6 mb-5 text-sm" style="color:var(--muted)">
                        <span>{{ now()->year }} © Nonna Agência Digital</span>
                        <span>Nonna OS</span>
                    </footer>
                @endunless
            </main>
        </div>
    </div>

    {{-- ── BARRA DE NAVEGAÇÃO INFERIOR (só mobile) ── --}}
    @auth
        @unless($embed)
            @include('layouts.bottom-nav')
        @endunless

        {{-- ── CHAT DA EQUIPE (widget flutuante) — só equipe interna; some no modo embed
             (popup de tarefa) e na própria tela cheia /chat ── --}}
        @unless($embed || request()->routeIs('chat.index') || auth()->user()->isClient())
            @include('chat._widget')
        @endunless
    @endauth

    {{-- ── PAINEL LATERAL (canvas) ── carrega detalhe de Cliente/Projeto sem sair da página atual ── --}}
    <div x-show="$store.sidePanel.visible" x-cloak
         @keydown.escape.window="$store.sidePanel.close()"
         @click.outside="$store.sidePanel.close()"
         class="fixed inset-y-0 right-0 z-40 w-full md:w-1/2 flex flex-col"
         style="background:var(--bg); border-left:1px solid var(--border2); box-shadow:-8px 0 24px rgba(0,0,0,.12)"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition duration-150 ease-in"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        <div class="flex items-center justify-between h-14 px-5 border-b flex-shrink-0" style="border-color:var(--border2)">
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Detalhes</span>
            <div class="flex items-center gap-1.5">
                <a :href="$store.sidePanel.url" target="_blank" rel="noopener"
                   class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                   style="color:var(--muted)"
                   onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                   onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                   title="Abrir página completa em nova aba">
                    <x-icon name="external-link" size="15" />
                </a>
                <button @click="$store.sidePanel.close()"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                    style="color:var(--muted)"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                    title="Fechar (Esc)">
                    <x-icon name="x" size="16" />
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div x-show="$store.sidePanel.loading" class="flex items-center justify-center h-full" style="color:var(--muted)">
                <x-icon name="loader-circle" size="20" class="animate-spin" />
            </div>
            <div x-show="$store.sidePanel.error" x-cloak class="p-6 text-sm" style="color:var(--red)">
                Não foi possível carregar os detalhes.
            </div>
            <div x-show="!$store.sidePanel.loading && !$store.sidePanel.error" x-html="$store.sidePanel.html"></div>
        </div>
    </div>

    {{-- ── POPUP DE TAREFA ── carrega a tela real da tarefa num iframe, por cima da página
         atual — a tela de baixo (Painel de Produção, Sprint, etc.) nunca navega. ── --}}
    <div x-show="$store.taskPopup.visible" x-cloak
         @keydown.escape.window="$store.taskPopup.close()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-8"
         style="background:rgba(0,0,0,.55)">
        <div @click.outside="$store.taskPopup.close()"
             class="relative flex flex-col w-full h-full rounded-lg overflow-hidden shadow-2xl"
             style="max-width:1200px; background:var(--bg)">
            <div class="flex items-center justify-end gap-1.5 h-11 px-3 flex-shrink-0" style="background:var(--s2); border-bottom:1px solid var(--border2)">
                <a :href="$store.taskPopup.url ? $store.taskPopup.url.replace(/[?&]embed=1/, '') : '#'"
                   target="_blank" rel="noopener"
                   class="flex items-center justify-center w-7 h-7 rounded-lg transition-colors"
                   style="color:var(--muted)"
                   onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                   onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                   title="Abrir a tarefa numa aba própria">
                    <x-icon name="external-link" size="14" />
                </a>
                <button @click="$store.taskPopup.close()"
                    class="flex items-center justify-center w-7 h-7 rounded-lg transition-colors"
                    style="color:var(--muted)"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                    title="Fechar (Esc)">
                    <x-icon name="x" size="15" />
                </button>
            </div>
            <iframe x-show="$store.taskPopup.visible" :src="$store.taskPopup.url || 'about:blank'"
                    class="flex-1 w-full" style="border:0"></iframe>
        </div>
    </div>

    <x-confirm-dialog-modal />
    <x-task-delivery-modal />

    @stack('scripts')
</body>
</html>
