<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Portal' }} — {{ config('app.name', 'Nonna OS') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="background: var(--bg); color: var(--text)">

    <header style="background: var(--s1); border-bottom: 1px solid var(--border2)">
        <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <span class="grad-text text-lg font-black"
                      style="background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text">
                    NONNA
                </span>
                <nav class="flex items-center gap-1">
                    @php
                        $navLinks = [
                            ['route' => 'portal.dashboard',       'label' => 'Início',       'match' => 'portal.dashboard'],
                            ['route' => 'portal.projects.index',  'label' => 'Projetos',     'match' => 'portal.projects.*'],
                            ['route' => 'portal.tickets.index',   'label' => 'Chamados',     'match' => 'portal.tickets.*'],
                            ['route' => 'portal.meetings.index',  'label' => 'Reuniões',     'match' => 'portal.meetings.*'],
                            ['route' => 'portal.approvals.index', 'label' => 'Aprovações',   'match' => 'portal.approvals.*'],
                            ['route' => 'portal.campaigns.index', 'label' => 'Campanhas',    'match' => 'portal.campaigns.*'],
                            ['route' => 'portal.account',         'label' => 'Minha Conta',  'match' => 'portal.account'],
                        ];
                    @endphp
                    @foreach($navLinks as $link)
                        @php $active = request()->routeIs($link['match']); @endphp
                        <a href="{{ route($link['route']) }}"
                           class="px-3 py-1.5 rounded text-xs font-semibold transition-colors"
                           style="color: {{ $active ? 'var(--purple)' : 'var(--muted)' }};
                                  background: {{ $active ? 'rgba(106,90,205,.08)' : 'transparent' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs font-semibold" style="color: var(--muted)">
                    {{ auth()->user()->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs font-semibold transition-colors"
                            style="color: var(--muted)"
                            onmouseover="this.style.color='var(--red)'"
                            onmouseout="this.style.color='var(--muted)'">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-8">
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
