<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Nonna Agência Digital — Do posicionamento à conversão!') }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .mono { font-family:Arial,'Segoe UI',Tahoma,sans-serif; }
    </style>
</head>
<body>

    <header style="background:rgba(12,12,18,.95); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); height:56px; display:flex; align-items:center; padding:0 20px;">
        <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
             alt="Nonna" style="height:20px"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <span style="font-weight:800; font-size:15px; display:none">nonna</span>
    </header>

    <main style="flex:1; display:flex; align-items:center; justify-content:center; padding:40px 16px">
        <div style="max-width:480px; width:100%; text-align:center">
            <div style="margin-bottom:16px; display:flex; justify-content:center; color:var(--muted)"><x-icon name="clock" size="44" /></div>
            <h1 style="font-size:22px; font-weight:800; margin:0 0 10px">
                {{ $setupToken->used_at ? 'Senha já definida' : 'Link Expirado' }}
            </h1>
            <p style="font-size:14px; color:var(--muted); line-height:1.7; margin:0">
                @if($setupToken->used_at)
                    Você já criou sua senha de acesso ao Portal. Acesse normalmente com seu e-mail e senha.
                @else
                    Este link pra criar a senha do Portal expirou. Entre em contato com o time da Nonna pra receber um novo link.
                @endif
            </p>
            @if($setupToken->used_at)
                <a href="{{ route('portal.login') }}"
                   style="display:inline-block; margin-top:20px; padding:10px 20px; font-size:13px; font-weight:700; background:var(--purple); color:#fff; text-decoration:none;">
                    Ir para o login
                </a>
            @endif
        </div>
    </main>

</body>
</html>
