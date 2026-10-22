<!DOCTYPE html>
<html lang="pt-BR" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">

    <title>{{ config('app.name', 'Nonna Agência Digital — Do posicionamento à conversão!') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden">
    {{-- Sessão do link de aprovação (login mágico): esconde atalhos pra telas que pedem senha. --}}
    @php $_magic = app(\App\Services\PortalMagicAccess::class)->active(); @endphp

    @if(app()->bound('portal.preview'))
    {{-- "Ver como o cliente vê" (ApprovalPreviewController): equipe olhando a página
         do Portal do cliente. Sem lateral do Portal — só a faixa e o conteúdo. --}}
    <div class="flex h-screen flex-col">
        <header class="flex flex-shrink-0 items-center justify-between gap-3 px-6 py-3 flex-wrap"
                style="background:var(--purple); color:#fff">
            <span class="text-sm font-semibold">
                Pré-visualização — é assim que <strong>{{ app('portal.preview')['client']->company_name }}</strong> vê a Central de Aprovações
            </span>
            <a href="{{ route('approvals.index') }}" class="text-xs font-bold px-3 py-1.5" style="background:rgba(255,255,255,.15); color:#fff; border-radius:6px">
                ← Voltar pra nossa Central
            </a>
        </header>
        <main class="portal-page flex-1 overflow-y-auto p-6" style="background:var(--bg)">
            {{ $slot }}
        </main>
    </div>
    @else

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
            <div class="flex h-[64px] items-center justify-between px-5">
                <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
                     alt="Nonna" class="h-7 w-auto object-contain object-left">
                <span class="badge tag-purple">Portal do Cliente</span>
                <button @click="sidebarOpen = false" style="color:var(--muted)">
                    <x-icon name="x" size="20" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                @include('layouts.portal-sidebar-links')
            </nav>
            @unless($_magic)
            <div class="border-t px-5 py-3" style="border-color:var(--border2)">
                <a href="{{ route('portal.account') }}" class="text-xs font-semibold" style="color: var(--muted)">Minha Conta</a>
            </div>
            @endunless
        </div>
    </div>

    {{-- ── LAYOUT PRINCIPAL ── --}}
    <div class="flex h-screen">

        {{-- ── SIDEBAR DESKTOP ── --}}
        {{-- Mesmo menu do App (redesign Mirbal, etapa 2): 264px, logo na altura do topo; Minha
             Conta e Sair foram pro menu da pessoa no topo. --}}
        <aside class="hidden md:flex md:w-[264px] md:flex-col sidebar-nav flex-shrink-0">
            {{-- Logo --}}
            <a href="{{ $_magic ? route('portal.approvals.index') : route('portal.dashboard') }}"
               class="flex h-[70px] items-center px-6 flex-shrink-0 transition-opacity hover:opacity-80">
                <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
                     alt="Nonna" class="h-8 w-auto max-w-full object-contain object-left"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <span class="text-lg font-black grad-text hidden">NONNA</span>
            </a>

            {{-- Cliente logado --}}
            <div class="mx-4 mb-2 px-3 py-2.5 card-inset">
                @if(($portalClients ?? collect())->count() > 1)
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center justify-between w-full text-left" style="min-width:0">
                            <p class="text-xs font-bold truncate" style="color:var(--text)">{{ $currentClient->company_name }}</p>
                            <x-icon name="chevron-right" size="14" class="flex-shrink-0" style="color:var(--muted); transition:transform .15s" x-bind:class="open ? '-rotate-90' : 'rotate-90'" />
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition x-cloak
                             class="absolute left-0 right-0 mt-2 rounded-lg z-10 py-1"
                             style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.12)">
                            @foreach($portalClients as $switchClient)
                                <form method="POST" action="{{ route('portal.client-context.switch', $switchClient) }}">
                                    @csrf
                                    <button type="submit" class="dd-hover w-full text-left px-3 py-2 text-xs font-semibold transition-colors"
                                            style="color:{{ $switchClient->id === $currentClient->id ? 'var(--purple)' : 'var(--text)' }}">
                                        {{ $switchClient->company_name }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-xs font-bold truncate" style="color:var(--text)">{{ $currentClient->company_name }}</p>
                @endif
            </div>

            {{-- Links --}}
            <nav class="flex-1 overflow-y-auto overflow-x-hidden pb-4">
                @include('layouts.portal-sidebar-links')
            </nav>
        </aside>

        {{-- ── CONTEÚDO PRINCIPAL ── --}}
        <div class="flex flex-1 flex-col min-w-0">

            {{-- TOPBAR — "flutuante" como no App: mesma margem do conteúdo, cantos só embaixo --}}
            <header class="topbar flex h-[64px] md:h-[70px] flex-shrink-0 items-center gap-1.5 md:gap-3 px-3 md:px-4 mx-3 md:mx-6">
                {{-- Botão mobile --}}
                <button type="button" class="md:hidden topbar-btn" @click="sidebarOpen = true">
                    <x-icon name="menu" size="20" />
                </button>

                <span class="badge tag-purple hidden sm:inline-flex">Portal do Cliente</span>

                <div class="flex-1"></div>

                @unless($_magic)
                <a href="{{ route('portal.tickets.create') }}" class="btn btn-primary btn-sm flex items-center gap-1.5 flex-shrink-0">
                    <x-icon name="ticket" size="14" />
                    <span class="hidden sm:inline">Novo Ticket</span>
                </a>
                @endunless

                {{-- Pessoa logada: nome + empresa + inicial; Minha Conta e Sair moram aqui --}}
                <div class="relative pl-1 md:pl-2" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex items-center gap-2.5 rounded-full transition-opacity hover:opacity-80">
                        <span class="hidden lg:flex flex-col items-end leading-tight">
                            <span class="text-sm font-medium" style="color:var(--text)">{{ $portalContact->name }}</span>
                            <span class="text-xs" style="color:var(--muted)">{{ $currentClient->company_name }}</span>
                        </span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold flex-shrink-0"
                              style="background:rgba(100, 59, 142,.12); color:var(--purple)">
                            {{ mb_strtoupper(mb_substr($portalContact->name, 0, 1)) }}
                        </span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute right-0 mt-2 z-30 w-56 py-1.5"
                         style="background:var(--s1); border:1px solid var(--border2); border-radius:var(--radius); box-shadow:0 8px 24px rgba(0,0,0,.10)">
                        <div class="px-3.5 py-2" style="border-bottom:1px solid var(--border2)">
                            <p class="text-sm font-medium truncate" style="color:var(--text)">{{ $portalContact->name }}</p>
                            <p class="text-xs truncate" style="color:var(--muted)">{{ $currentClient->company_name }}</p>
                        </div>
                        @unless($_magic)
                        <a href="{{ route('portal.account') }}" class="dd-item">
                            <x-icon name="user" size="15" /> Minha Conta
                        </a>
                        @endunless
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="dd-item">
                                <x-icon name="log-out" size="15" /> Sair
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- PÁGINA --}}
            <main class="portal-page flex-1 overflow-y-auto px-3 pt-4 pb-8 md:px-6 md:pt-5" style="background:var(--bg)">
                {{-- Caminho (casa › página). As páginas do Portal já trazem o próprio título
                     grande, então aqui vai só o caminho — e nada na página inicial. --}}
                @if(!empty($title) && !request()->routeIs('portal.dashboard'))
                    <div class="mb-3">
                        <nav class="page-crumbs" aria-label="Caminho">
                            <a href="{{ $_magic ? route('portal.approvals.index') : route('portal.dashboard') }}" class="inline-flex" title="Início" style="color:var(--purple)">
                                <x-icon name="house" size="15" />
                            </a>
                            <x-icon name="chevron-right" size="13" />
                            <span class="truncate" style="max-width:320px">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $title))), 60) }}</span>
                        </nav>
                    </div>
                @endif

                @if(session('success'))
                    <div class="mb-6 px-4 py-3 text-sm font-semibold"
                         style="background: rgba(5,150,105,.08); color: var(--green); border: 1px solid rgba(5,150,105,.2); border-radius:var(--radius)">
                        {{ session('success') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @endif

    <x-confirm-dialog-modal />

</body>
</html>
