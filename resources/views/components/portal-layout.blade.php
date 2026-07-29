<!DOCTYPE html>
<html lang="pt-BR" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Portal' }} — {{ config('app.name', 'Nonna OS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700|syne:700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                <span class="text-sm font-bold grad-text">Portal do Cliente</span>
                <button @click="sidebarOpen = false" style="color:var(--muted)">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                @include('layouts.portal-sidebar-links')
            </nav>
            <div class="border-t px-5 py-3" style="border-color:var(--border2)">
                <a href="{{ route('portal.account') }}" class="text-xs font-semibold" style="color: var(--muted)">Minha Conta</a>
            </div>
        </div>
    </div>

    {{-- ── LAYOUT PRINCIPAL ── --}}
    <div class="flex h-screen">

        {{-- ── SIDEBAR DESKTOP ── --}}
        <aside class="hidden md:flex md:w-64 md:flex-col sidebar-nav flex-shrink-0">
            {{-- Logo --}}
            <div class="flex h-16 items-center px-6 border-b" style="border-color:var(--border); flex-shrink:0">
                <span class="text-lg font-black grad-text">NONNA</span>
            </div>

            {{-- Cliente logado --}}
            <div class="px-5 py-4 border-b" style="border-color:var(--border)">
                @if(($portalClients ?? collect())->count() > 1)
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center justify-between w-full text-left" style="min-width:0">
                            <p class="text-xs font-bold truncate" style="color:var(--text)">{{ $currentClient->company_name }}</p>
                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color:var(--muted)">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" :class="open ? '-rotate-90' : 'rotate-90'" style="transition:transform .15s"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition x-cloak
                             class="absolute left-0 right-0 mt-2 rounded-lg z-10 py-1"
                             style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.12)">
                            @foreach($portalClients as $switchClient)
                                <form method="POST" action="{{ route('portal.client-context.switch', $switchClient) }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-3 py-2 text-xs font-semibold transition-colors"
                                            style="color:{{ $switchClient->id === $currentClient->id ? 'var(--purple)' : 'var(--text)' }}"
                                            onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background=''">
                                        {{ $switchClient->company_name }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-xs font-bold truncate" style="color:var(--text)">{{ $currentClient->company_name }}</p>
                @endif
                <p class="text-xs truncate mt-0.5" style="color:var(--muted); font-size:11px">{{ $portalContact->name }}</p>
            </div>

            {{-- Links --}}
            <nav class="flex-1 overflow-y-auto py-3">
                @include('layouts.portal-sidebar-links')
            </nav>

            {{-- Footer --}}
            <div class="border-t px-5 py-3 flex items-center justify-between" style="border-color:var(--border2)">
                <a href="{{ route('portal.account') }}"
                   class="text-xs font-semibold transition-colors {{ request()->routeIs('portal.account') ? '' : '' }}"
                   style="color: {{ request()->routeIs('portal.account') ? 'var(--purple)' : 'var(--muted)' }}"
                   onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='{{ request()->routeIs('portal.account') ? 'var(--purple)' : 'var(--muted)' }}'">
                    Minha Conta
                </a>
                <form method="POST" action="{{ route('portal.logout') }}">
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
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>

                {{-- Início --}}
                <a href="{{ route('portal.dashboard') }}"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors flex-shrink-0"
                    style="color:{{ request()->routeIs('portal.dashboard') ? 'var(--purple)' : 'var(--muted)' }}"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='{{ request()->routeIs('portal.dashboard') ? 'var(--purple)' : 'var(--muted)' }}'"
                    title="Início">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9.5 12 3l9 6.5" />
                        <path d="M5 8.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V8.5" />
                    </svg>
                </a>

                {{-- Título dinâmico --}}
                <div class="flex-1">
                    <h1 class="text-sm font-bold" style="color:var(--text)">{{ $title ?? '' }}</h1>
                </div>

                <span class="badge badge-purple">Portal do Cliente</span>
            </header>

            {{-- PÁGINA --}}
            <main class="portal-page flex-1 overflow-y-auto p-6" style="background:var(--bg)">
                @if(session('success'))
                    <div class="mb-6 rounded-lg px-4 py-3 text-sm font-semibold"
                         style="background: rgba(5,150,105,.08); color: var(--green); border: 1px solid rgba(5,150,105,.2)">
                        {{ session('success') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

</body>
</html>
