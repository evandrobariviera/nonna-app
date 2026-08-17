<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">

    <title>Portal do Cliente — {{ config('app.name', 'Nonna Agência Digital') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Visual deliberadamente diferente do login interno (layouts/guest.blade.php) —
         painel laranja (não roxo) + selo "PORTAL DO CLIENTE" sempre visível, inclusive
         no mobile, onde o painel lateral nem aparece. Existem pra evitar que um cliente
         confunda essa tela com o login da equipe (mesmo campo de e-mail/senha, tabelas
         de usuário diferentes por baixo — ver EnsurePortalAccess). --}}
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
        .portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            background: rgba(238, 121, 25, .1);
            border: 1px solid rgba(238, 121, 25, .3);
            color: #EE7919;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        @media (min-width: 1024px) {
            .login-left {
                display: flex;
                flex-direction: column;
                width: 50%;
                flex-shrink: 0;
                position: relative;
                overflow: hidden;
                background: #EE7919;
            }
            .login-mobile-badge {
                display: none;
            }
        }
    </style>
</head>
<body class="antialiased" style="margin:0; background: var(--bg)">

    <div class="login-wrap">

        {{-- ── Painel esquerdo — branding do Portal (laranja, não roxo) ── --}}
        <div class="login-left">

            <div style="position:absolute; inset:0; pointer-events:none; overflow:hidden">
                <div style="position:absolute; bottom:-160px; right:-160px; width:500px; height:500px;
                            border-radius:50%; opacity:.15;
                            background: radial-gradient(circle, #643B8E, transparent 65%)"></div>
                <div style="position:absolute; top:-120px; left:-120px; width:380px; height:380px;
                            border-radius:50%; opacity:.10;
                            background: radial-gradient(circle, #ffffff, transparent 65%)"></div>
                <div style="position:absolute; right:-40px; top:50%; transform:translateY(-50%);
                            font-family:'Inter',sans-serif; font-size:520px; font-weight:700;
                            color:rgba(255,255,255,.08); line-height:1; letter-spacing:-.05em;
                            user-select:none; pointer-events:none">P</div>
            </div>

            <div style="position:relative; display:flex; flex-direction:column; height:100%;
                        padding: 56px 48px">

                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:24px; font-weight:700;
                                color:#fff; letter-spacing:-.02em; line-height:1">
                        NONNA
                    </div>
                    <div style="color:rgba(255,255,255,.55); font-size:11px; font-weight:600;
                                letter-spacing:.10em; text-transform:uppercase; margin-top:4px">
                        Portal do Cliente
                    </div>
                </div>

                <div style="flex:1; display:flex; flex-direction:column; justify-content:center">
                    <div style="display:inline-flex; align-items:center; gap:6px; width:fit-content;
                                padding:5px 12px; border-radius:999px; background:rgba(255,255,255,.14);
                                font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
                                text-transform:uppercase; letter-spacing:.1em; color:#fff; margin-bottom:20px">
                        Área exclusiva do cliente
                    </div>
                    <h1 style="font-family:'Inter',sans-serif; font-size:clamp(2rem,2.6vw,3rem);
                               font-weight:700; color:#fff; letter-spacing:-.02em;
                               line-height:1.15; margin:0 0 20px">
                        Acompanhe seus<br>projetos e<br>
                        <span style="color:rgba(255,255,255,.55)">campanhas.</span>
                    </h1>
                    <p style="color:rgba(255,255,255,.55); font-size:13px; line-height:1.7;
                               max-width:280px; margin:0">
                        Aprovações, entregas, resultados de tráfego pago e histórico do seu projeto — tudo num só lugar.
                    </p>
                </div>

                <div>
                    <div style="width:32px; height:2px; background:rgba(255,255,255,.25); margin-bottom:16px"></div>
                    <p style="font-size:11px; color:rgba(255,255,255,.4); margin:0">
                        &copy; {{ date('Y') }} Nonna Agência Digital
                    </p>
                </div>

            </div>
        </div>

        {{-- ── Painel direito — formulário ── --}}
        <div class="login-right">

            {{-- Selo mobile — único indicativo visível de que essa NÃO é a tela de
                 login da equipe, já que o painel esquerdo fica escondido aqui. --}}
            <div class="login-mobile-badge" style="margin-bottom:24px; text-align:center">
                <span class="portal-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Portal do Cliente
                </span>
            </div>

            <div style="width:100%; max-width:360px">
                {{ $slot }}
            </div>

        </div>

    </div>

</body>
</html>
