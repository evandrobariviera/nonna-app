<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Nonna OS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:400,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden" x-data="{ sidebarOpen: false }">

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
                <span class="text-sm font-bold grad-text">nonna OS</span>
                <button @click="sidebarOpen = false" style="color:var(--muted)">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
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
            <div class="flex h-16 items-center px-6 border-b" style="border-color:var(--border); flex-shrink:0">
                <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
                     alt="Nonna OS" class="h-7 w-auto"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <span class="text-base font-black grad-text hidden">nonna OS</span>
            </div>

            {{-- Usuário logado --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b" style="border-color:var(--border)">
                <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-black text-white flex-shrink-0"
                     style="background: var(--grad)">
                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                </div>
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
                <a href="{{ route('profile.edit') }}"
                   class="text-xs font-semibold transition-colors"
                   style="color:var(--muted)"
                   onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                    Perfil
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-xs font-semibold transition-colors"
                            style="color:var(--muted); background:none; border:none; cursor:pointer"
                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
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

                {{-- Título dinâmico --}}
                <div class="flex-1">
                    @isset($header)
                        <div>{{ $header }}</div>
                    @endisset
                </div>

                {{-- Badge ambiente --}}
                <span class="badge badge-purple">
                    Nonna OS
                </span>
            </header>

            {{-- PÁGINA --}}
            <main class="flex-1 overflow-y-auto p-6" style="background:var(--bg)">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
