<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SuperAdmin' }} — {{ config('app.name', 'Nonna OS') }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="background: var(--bg); color: var(--text)">

    <header style="background: #1c1c2e; border-bottom: 1px solid rgba(255,255,255,.06)">
        <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <span class="grad-text text-lg font-black"
                      style="background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text">
                    NONNA
                </span>
                <span class="text-xs font-bold uppercase tracking-widest px-2 py-1 rounded"
                      style="background: rgba(220,38,38,.15); color: #f87171; border: 1px solid rgba(220,38,38,.2)">
                    SuperAdmin
                </span>
                <nav class="flex items-center gap-1">
                    <a href="{{ route('superadmin.dashboard') }}"
                       class="px-3 py-1.5 rounded text-xs font-semibold"
                       style="color: {{ request()->routeIs('superadmin.dashboard') ? '#fff' : 'rgba(255,255,255,.45)' }};
                              background: {{ request()->routeIs('superadmin.dashboard') ? 'rgba(255,255,255,.08)' : 'transparent' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('superadmin.organizations.index') }}"
                       class="px-3 py-1.5 rounded text-xs font-semibold"
                       style="color: {{ request()->routeIs('superadmin.organizations.*') ? '#fff' : 'rgba(255,255,255,.45)' }};
                              background: {{ request()->routeIs('superadmin.organizations.*') ? 'rgba(255,255,255,.08)' : 'transparent' }}">
                        Organizações
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}"
                   class="text-xs font-semibold"
                   style="color: rgba(255,255,255,.4)"
                   onmouseover="this.style.color='rgba(255,255,255,.8)'"
                   onmouseout="this.style.color='rgba(255,255,255,.4)'">
                    ← Voltar ao App
                </a>
                <span class="text-xs" style="color: rgba(255,255,255,.3)">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
        @if(session('success'))
            <div class="mb-6 rounded-lg px-4 py-3 text-sm font-semibold"
                 style="background: rgba(5,150,105,.08); color: var(--green); border: 1px solid rgba(5,150,105,.2)">
                {{ session('success') }}
            </div>
        @endif

        {{ $slot }}
    </main>

</body>
</html>
