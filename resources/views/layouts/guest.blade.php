<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Nonna OS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:700,800,900&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="background: var(--bg)">

    <div class="min-h-screen flex">

        {{-- ── Painel esquerdo — branding ── --}}
        <div class="hidden lg:flex lg:w-1/2 flex-col relative overflow-hidden flex-shrink-0"
             style="background: #6A5ACD">

            {{-- Elementos decorativos --}}
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                {{-- Círculo laranja no canto inferior --}}
                <div class="absolute -bottom-40 -right-40 w-[480px] h-[480px] rounded-full opacity-20"
                     style="background: radial-gradient(circle, #FF8C00, transparent 65%)"></div>
                {{-- Clarão superior --}}
                <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full opacity-10"
                     style="background: radial-gradient(circle, #ffffff, transparent 65%)"></div>
                {{-- "N" decorativo de fundo --}}
                <div class="absolute -right-16 top-1/2 -translate-y-1/2 select-none pointer-events-none"
                     style="font-family: 'Syne', sans-serif; font-size: 520px; font-weight: 900;
                            color: rgba(255,255,255,0.04); line-height: 1; letter-spacing: -0.05em">
                    N
                </div>
            </div>

            {{-- Conteúdo --}}
            <div class="relative flex flex-col h-full px-12 py-14">

                {{-- Logo / Wordmark --}}
                <div class="flex flex-col gap-1">
                    @isset($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="{{ $brandName ?? 'Nonna' }}"
                             class="h-10 w-auto object-contain object-left brightness-0 invert">
                    @else
                        <span style="font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 900;
                                     color: #fff; letter-spacing: -0.02em; line-height: 1">
                            {{ $brandName ?? 'NONNA' }}
                        </span>
                        <span style="color: rgba(255,255,255,0.45); font-size: 12px; font-weight: 500;
                                     letter-spacing: 0.08em; text-transform: uppercase">
                            Agência Digital
                        </span>
                    @endisset
                </div>

                {{-- Hero --}}
                <div class="flex-1 flex flex-col justify-center">
                    <p class="text-xs font-bold uppercase tracking-widest mb-5"
                       style="color: #FF8C00; letter-spacing: 0.14em">
                        Sistema Operacional
                    </p>
                    <h1 class="font-black leading-tight mb-6"
                        style="font-family: 'Syne', sans-serif; font-size: clamp(2rem, 3vw, 3rem);
                               color: #fff; letter-spacing: -0.02em">
                        Planejamento<br>que vira<br>
                        <span style="color: rgba(255,255,255,0.45)">execução.</span>
                    </h1>
                    <p class="text-sm leading-relaxed max-w-[280px]"
                       style="color: rgba(255,255,255,0.40)">
                        Diagnósticos, macroplanejamentos, projetos e tarefas — integrados ao ClickUp via n8n.
                    </p>
                </div>

                {{-- Linha decorativa + rodapé --}}
                <div>
                    <div class="mb-4 w-8 h-0.5" style="background: rgba(255,255,255,0.2)"></div>
                    <p class="text-xs" style="color: rgba(255,255,255,0.25)">
                        &copy; {{ date('Y') }} Nonna Agência Digital
                    </p>
                </div>

            </div>
        </div>

        {{-- ── Painel direito — formulário ── --}}
        <div class="flex-1 flex flex-col justify-center items-center px-6 py-12"
             style="background: var(--bg)">

            {{-- Logo mobile --}}
            <div class="lg:hidden mb-8 text-center">
                <span style="font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 900;
                             color: var(--purple); letter-spacing: -0.02em">
                    NONNA
                </span>
                <p class="text-xs mt-1" style="color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em">
                    Agência Digital
                </p>
            </div>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>

        </div>

    </div>

</body>
</html>
