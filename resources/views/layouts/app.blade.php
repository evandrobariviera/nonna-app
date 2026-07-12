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

    <title>{{ config('app.name', 'Nonna OS') }}</title>

    {{-- Flash prevention: aplica dark antes do Alpine inicializar --}}
    <script>if(localStorage.getItem('nonna-theme')==='dark')document.documentElement.classList.add('dark')</script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700|poppins:500,600,700|syne:700,800|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

    {{-- Branding dinâmico do tenant --}}
    @isset($currentOrg)
    @php
        $brandPrimary   = $currentOrg->brandColor('primary_color',   '#6A5ACD');
        $brandSecondary = $currentOrg->brandColor('secondary_color',  '#FF8C00');
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

                {{-- Início --}}
                <a href="{{ route('dashboard') }}"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors flex-shrink-0"
                    style="color:var(--muted)"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                    title="Início">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9.5 12 3l9 6.5" />
                        <path d="M5 8.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V8.5" />
                    </svg>
                </a>

                {{-- Título dinâmico --}}
                <div class="flex-1">
                    @isset($header)
                        <div>{{ $header }}</div>
                    @endisset
                </div>

                {{-- Toggle tema --}}
                <button @click="toggleTheme()"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                    style="color:var(--muted)"
                    onmouseover="this.style.background='var(--s3)'; this.style.color='var(--text)'"
                    onmouseout="this.style.background=''; this.style.color='var(--muted)'"
                    :title="darkMode ? 'Modo claro' : 'Modo escuro'">
                    {{-- Lua (modo claro → clicar para escurecer) --}}
                    <svg x-show="!darkMode" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    {{-- Sol (modo escuro → clicar para clarear) --}}
                    <svg x-show="darkMode" x-cloak xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                </button>

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

    @stack('scripts')
</body>
</html>
