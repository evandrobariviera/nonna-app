<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Nonna OS') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-gray-900 dark:text-gray-100" x-data="{ sidebarOpen: false }">
    
    <!-- 📱 MENU LATERAL MOBILE (Slide-over drawer) -->
    <div x-show="sidebarOpen" class="relative z-50 md:hidden" x-description="Mobile menu" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/80" x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
        <div class="fixed inset-0 flex">
            <div class="relative mr-16 flex w-full max-w-xs flex-1" x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
                <!-- Botão para fechar o menu mobile -->
                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                    <button type="button" class="-m-2.5 p-2.5 text-white" @click="sidebarOpen = false">
                        <span class="sr-only">Fechar menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <!-- Conteúdo do Menu Mobile -->
                <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white dark:bg-gray-900 px-6 pb-4">
                    <div class="flex h-16 shrink-0 items-center border-b border-gray-100 dark:border-gray-800">
                        <img class="h-8 w-auto" src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png" alt="Nonna OS">
                    </div>
                    <nav class="flex flex-col flex-1 gap-y-7">
                        <ul role="list" class="flex flex-col flex-1 gap-y-1">
                            @include('layouts.sidebar-links')
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- 🖥️ MENU LATERAL FIXO (Desktop) -->
    <div class="hidden md:fixed md:inset-y-0 md:z-50 md:flex md:w-64 md:flex-col">
        <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-6 pb-4">
            <!-- Logo Nonna -->
            <div class="flex h-20 shrink-0 items-center border-b border-gray-100 dark:border-gray-800">
                <img class="h-9 w-auto" src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png" alt="Nonna OS">
            </div>
            <!-- Links do Menu -->
            <nav class="flex flex-col flex-1">
                <ul role="list" class="flex flex-col flex-1 gap-y-1">
                    @include('layouts.sidebar-links')
                </ul>
            </nav>
        </div>
    </div>

    <!-- 📦 ÁREA DE CONTEÚDO PRINCIPAL (Lado direito) -->
    <div class="md:pl-64 flex flex-col h-full">
        
        <!-- TOP BAR (Cabeçalho superior com Perfil) -->
        <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
            <!-- Botão Hamburguer Mobile -->
            <button type="button" class="-m-2.5 p-2.5 text-gray-700 md:hidden" @click="sidebarOpen = true">
                <span class="sr-only">Abrir menu</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </button>

            <!-- Título da Página dinâmico -->
            <div class="flex-1 text-sm font-semibold leading-6 text-gray-900 dark:text-gray-100">
                @if (isset($header))
                    {{ $header }}
                @endif
            </div>

            <!-- Perfil Dropdown do Breeze -->
            <div class="flex items-center gap-x-4 lg:gap-x-6">
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>
                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1-1-1 011.414 0L10 10.586l3.293-3.293a1-1-1 111.414 1.414l-4 4a1-1-1 01-1.414 0l-4-4a1-1-1 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Perfil') }}
                            </x-dropdown-link>

                            <!-- Formulário de Logout nativo -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Sair') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </header>

        <!-- CONTEÚDO DA PÁGINA (Fluido e Full-Width) -->
        <main class="flex-1 py-8 px-4 sm:px-6 md:px-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>