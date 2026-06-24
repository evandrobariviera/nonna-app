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

    <style>
        .login-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: row;
        }
        .login-left {
            display: none;
        }
        .login-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            background: var(--bg);
        }
        @media (min-width: 1024px) {
            .login-left {
                display: flex;
                flex-direction: column;
                width: 50%;
                flex-shrink: 0;
                position: relative;
                overflow: hidden;
                background: #6A5ACD;
            }
            .login-mobile-logo {
                display: none;
            }
        }
    </style>
</head>
<body class="antialiased" style="margin:0; background: var(--bg)">

    <div class="login-wrap">

        {{-- ── Painel esquerdo — branding ── --}}
        <div class="login-left">

            {{-- Elementos decorativos --}}
            <div style="position:absolute; inset:0; pointer-events:none; overflow:hidden">
                <div style="position:absolute; bottom:-160px; right:-160px; width:500px; height:500px;
                            border-radius:50%; opacity:.18;
                            background: radial-gradient(circle, #FF8C00, transparent 65%)"></div>
                <div style="position:absolute; top:-120px; left:-120px; width:380px; height:380px;
                            border-radius:50%; opacity:.10;
                            background: radial-gradient(circle, #ffffff, transparent 65%)"></div>
                <div style="position:absolute; right:-40px; top:50%; transform:translateY(-50%);
                            font-family:'Syne',sans-serif; font-size:520px; font-weight:900;
                            color:rgba(255,255,255,.05); line-height:1; letter-spacing:-.05em;
                            user-select:none; pointer-events:none">N</div>
            </div>

            {{-- Conteúdo --}}
            <div style="position:relative; display:flex; flex-direction:column; height:100%;
                        padding: 56px 48px">

                {{-- Wordmark --}}
                <div>
                    @isset($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="{{ $brandName ?? 'Nonna' }}"
                             style="height:40px; width:auto; object-fit:contain; filter:brightness(0) invert(1)">
                    @else
                        <div style="font-family:'Syne',sans-serif; font-size:28px; font-weight:900;
                                    color:#fff; letter-spacing:-.02em; line-height:1">
                            {{ $brandName ?? 'NONNA' }}
                        </div>
                        <div style="color:rgba(255,255,255,.4); font-size:11px; font-weight:600;
                                    letter-spacing:.10em; text-transform:uppercase; margin-top:4px">
                            Agência Digital
                        </div>
                    @endisset
                </div>

                {{-- Hero --}}
                <div style="flex:1; display:flex; flex-direction:column; justify-content:center">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase;
                                letter-spacing:.14em; color:#FF8C00; margin-bottom:20px">
                        Sistema Operacional
                    </div>
                    <h1 style="font-family:'Syne',sans-serif; font-size:clamp(2rem,2.6vw,3rem);
                               font-weight:900; color:#fff; letter-spacing:-.02em;
                               line-height:1.15; margin:0 0 20px">
                        Planejamento<br>que vira<br>
                        <span style="color:rgba(255,255,255,.4)">execução.</span>
                    </h1>
                    <p style="color:rgba(255,255,255,.38); font-size:13px; line-height:1.7;
                               max-width:280px; margin:0">
                        Diagnósticos, macroplanejamentos, projetos e tarefas — integrados ao ClickUp via n8n.
                    </p>
                </div>

                {{-- Rodapé --}}
                <div>
                    <div style="width:32px; height:2px; background:rgba(255,255,255,.2); margin-bottom:16px"></div>
                    <p style="font-size:11px; color:rgba(255,255,255,.22); margin:0">
                        &copy; {{ date('Y') }} Nonna Agência Digital
                    </p>
                </div>

            </div>
        </div>

        {{-- ── Painel direito — formulário ── --}}
        <div class="login-right">

            {{-- Logo mobile --}}
            <div class="login-mobile-logo" style="margin-bottom:32px; text-align:center">
                <div style="font-family:'Syne',sans-serif; font-size:24px; font-weight:900;
                            color:var(--purple); letter-spacing:-.02em">
                    NONNA
                </div>
                <div style="font-size:10px; color:var(--muted); text-transform:uppercase;
                            letter-spacing:.10em; margin-top:4px">
                    Agência Digital
                </div>
            </div>

            <div style="width:100%; max-width:360px">
                {{ $slot }}
            </div>

        </div>

    </div>

</body>
</html>
