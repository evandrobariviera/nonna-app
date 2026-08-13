<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{
          sidebarOpen: false,
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
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

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
<body class="h-screen overflow-hidden">

    {{-- ── MOBILE DRAWER ── --}}
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

    {{-- ── LAYOUT PRINCIPAL ── --}}
    <div class="flex h-screen">

        {{-- ── SIDEBAR DESKTOP ── --}}
        <aside class="hidden md:flex md:w-64 md:flex-col sidebar-nav flex-shrink-0">
            {{-- Logo --}}
            <a href="{{ route('dashboard') }}" class="flex h-16 items-center px-6 border-b transition-opacity hover:opacity-80" style="border-color:var(--border); flex-shrink:0">
                @if(isset($currentOrg) && $currentOrg->logoUrl())
                    <img src="{{ $currentOrg->logoUrl() }}" alt="{{ $currentOrg->name }}" class="h-7 w-auto"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <span class="text-base font-black grad-text hidden">{{ $currentOrg->name }}</span>
                @else
                    <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
                         alt="{{ config('app.name') }}" class="h-7 w-auto"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <span class="text-base font-black grad-text hidden">{{ config('app.name') }}</span>
                @endif
            </a>

            {{-- Usuário logado --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b" style="border-color:var(--border)">
                <x-user-avatar :user="Auth::user()" size="8" />
                <div class="min-w-0">
                    <p class="text-xs font-bold truncate" style="color:var(--text)">{{ Auth::user()->name }}</p>
                    <p class="text-xs truncate" style="color:var(--muted); font-size:11px">{{ Auth::user()->email }}</p>
                </div>
            </div>

            {{-- Links --}}
            <nav class="flex-1 overflow-y-auto py-3">
                @include('layouts.sidebar-links')
            </nav>

            {{-- Footer --}}
            <div class="border-t px-5 py-3 flex items-center justify-between" style="border-color:var(--border2)">
                <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-xs">
                    Perfil
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-xs">
                        Sair
                    </button>
                </form>
            </div>
        </aside>

        {{-- ── CONTEÚDO PRINCIPAL ── --}}
        <div class="flex flex-1 flex-col min-w-0">

            {{-- TOPBAR --}}
            <header class="topbar flex h-16 flex-shrink-0 items-center gap-4 px-6">
                {{-- Botão mobile --}}
                <button type="button" class="md:hidden -ml-1 p-1.5" style="color:var(--muted)" @click="sidebarOpen = true">
                    <x-icon name="menu" size="20" />
                </button>

                {{-- Início --}}
                <a href="{{ route('dashboard') }}"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors flex-shrink-0"
                    style="color:var(--muted)"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                    title="Início">
                    <x-icon name="house" size="16" />
                </a>

                {{-- Título dinâmico --}}
                <div class="flex-1">
                    @isset($header)
                        <div>{{ $header }}</div>
                    @endisset
                </div>

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
                         @keydown.slash.window.prevent="if (!open && document.activeElement === document.body) { open = true; $nextTick(() => $refs.globalSearchInput.focus()) }">
                        <button @click="open = !open; if (open) $nextTick(() => $refs.globalSearchInput.focus())"
                            class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors flex-shrink-0"
                            style="color:var(--muted)"
                            onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                            onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                            title="Buscar tarefa/ticket">
                            <x-icon name="search" size="16" />
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-1 z-30"
                             style="width:360px; background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.15)">
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
                                    class="w-full px-3 py-2 text-sm focus:outline-none"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
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
                                    <a :href="r.url" class="block px-4 py-2.5 transition-colors"
                                       style="border-bottom:1px solid var(--border2)"
                                       onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
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
                <button @click="toggleTheme()"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                    style="color:var(--muted)"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                    :title="darkMode ? 'Modo claro' : 'Modo escuro'">
                    {{-- Lua (modo claro → clicar para escurecer) --}}
                    <x-icon name="moon" size="16" x-show="!darkMode" />
                    {{-- Sol (modo escuro → clicar para clarear) --}}
                    <x-icon name="sun" size="16" x-show="darkMode" x-cloak />
                </button>

                {{-- Aviso de navegador (Notification API + som) — só liga se o usuário pedir,
                     Chrome exige gesto explícito pra conceder a permissão. --}}
                @auth
                    <button type="button"
                        @click="$store.browserNotify.enabled ? $store.browserNotify.disable() : $store.browserNotify.enable()"
                        class="relative flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                        :style="$store.browserNotify.enabled ? 'color:var(--purple)' : 'color:var(--muted)'"
                        onmouseover="this.style.background='var(--s3)'"
                        onmouseout="this.style.background=''"
                        :title="$store.browserNotify.enabled ? 'Avisos do navegador ativados — clique pra desligar' : 'Ativar avisos do navegador (popup + som)'">
                        <span x-text="$store.browserNotify.enabled ? '🔔' : '🔕'"></span>
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
                        <button @click="open = !open"
                            class="relative flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                            style="color:var(--muted)"
                            onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                            onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                            title="Notificações">
                            <x-icon name="bell" size="16" />
                            @if($unreadNotifications->count() > 0)
                                <span class="absolute -top-0.5 -right-0.5 flex items-center justify-center rounded-full text-white"
                                      style="background:var(--orange); font-size:9px; min-width:15px; height:15px; padding:0 3px; line-height:15px">
                                    {{ $unreadNotifications->count() }}
                                </span>
                            @endif
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-1 z-30"
                             style="width:340px; background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.15)">
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
                                        <div class="flex items-center gap-3 mt-0.5">
                                            <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="lido">
                                                <button type="submit" class="btn btn-ghost btn-xs">
                                                    Marcar como lido
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="resolvido">
                                                <button type="submit" class="btn btn-success btn-xs">
                                                    Resolver
                                                </button>
                                            </form>
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

                {{-- Badge ambiente --}}
                <span class="badge badge-purple">
                    Nonna OS
                </span>
            </header>

            {{-- PÁGINA --}}
            <main class="app-page flex-1 overflow-y-auto p-6" style="background:var(--bg)">
                {{ $slot }}
            </main>
        </div>
    </div>

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

    <x-confirm-dialog-modal />

    @stack('scripts')
</body>
</html>
