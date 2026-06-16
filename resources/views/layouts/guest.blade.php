<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Nonna OS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @isset($brandPrimary)
    <style>
        :root {
            --purple: {{ $brandPrimary }};
            --orange: {{ $brandSecondary ?? '#FF8C00' }};
            --grad: linear-gradient(135deg, {{ $brandPrimary }}, {{ $brandSecondary ?? '#FF8C00' }});
        }
    </style>
    @endisset
</head>
<body class="antialiased" style="background:var(--bg)">

    <div class="min-h-screen flex">

        {{-- ── Painel esquerdo — branding ── --}}
        <div class="hidden lg:flex lg:w-[420px] xl:w-[480px] flex-col relative overflow-hidden flex-shrink-0"
             style="background: linear-gradient(160deg, #1c1c2e 0%, #2d2b4e 60%, #1c1c2e 100%)">

            {{-- Glow de fundo --}}
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full opacity-20"
                     style="background: radial-gradient(circle, var(--purple), transparent 70%)"></div>
                <div class="absolute -bottom-32 -right-16 w-80 h-80 rounded-full opacity-15"
                     style="background: radial-gradient(circle, var(--orange), transparent 70%)"></div>
            </div>

            {{-- Conteúdo --}}
            <div class="relative flex flex-col h-full px-10 py-12">

                {{-- Logo --}}
                <div class="flex items-center gap-3">
                    @isset($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="{{ $brandName ?? 'Logo' }}"
                             class="h-8 w-auto object-contain">
                    @else
                        <span class="grad-text text-2xl font-black tracking-tight"
                              style="background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text">
                            {{ $brandName ?? config('app.name', 'NONNA') }}
                        </span>
                    @endisset
                </div>

                {{-- Hero --}}
                <div class="flex-1 flex flex-col justify-center">
                    <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color: var(--orange)">
                        Sistema Operacional
                    </p>
                    <h1 class="text-4xl xl:text-5xl font-black leading-tight mb-6" style="color: #fff">
                        Planejamento<br>que vira<br>
                        <span style="background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text">
                            execução.
                        </span>
                    </h1>
                    <p class="text-sm leading-relaxed max-w-xs" style="color: rgba(255,255,255,0.45)">
                        Diagnósticos, macroplanejamentos, projetos e tarefas — tudo em um lugar, integrado ao ClickUp via n8n.
                    </p>
                </div>

                {{-- Rodapé --}}
                <p class="text-xs" style="color: rgba(255,255,255,0.2)">
                    &copy; {{ date('Y') }} {{ $brandName ?? 'Nonna Agência Digital' }}
                </p>
            </div>
        </div>

        {{-- ── Painel direito — formulário ── --}}
        <div class="flex-1 flex flex-col justify-center items-center px-6 py-12" style="background: var(--bg)">

            {{-- Logo mobile --}}
            <div class="lg:hidden mb-8">
                <span class="grad-text text-3xl font-black tracking-tight"
                      style="background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text">
                    {{ $brandName ?? config('app.name', 'NONNA') }}
                </span>
            </div>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>

    </div>

</body>
</html>
