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
    <link href="https://fonts.bunny.net/css?family=syne:400,600,700,800|ibm-plex-mono:400,500&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Syne', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .mono { font-family: 'IBM Plex Mono', monospace; }
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

            @if(!$approvalToken->isPending())
                <div style="font-size:48px; margin-bottom:16px">✅</div>
                <h1 style="font-size:22px; font-weight:800; margin:0 0 10px">Avaliação já enviada</h1>
                <p style="font-size:14px; color:var(--muted); line-height:1.7; margin:0">
                    Você já registrou sua avaliação para este material. Obrigado!
                </p>
            @else
                <div style="font-size:48px; margin-bottom:16px">⏱</div>
                <h1 style="font-size:22px; font-weight:800; margin:0 0 10px">Link Expirado</h1>
                <p style="font-size:14px; color:var(--muted); line-height:1.7; margin:0">
                    Este link de aprovação expirou. Entre em contato com o time da Nonna para receber um novo link.
                </p>
            @endif

            <p class="mono" style="font-size:10px; color:var(--muted); margin-top:24px">
                Você pode fechar esta janela.
            </p>

        </div>
    </main>

</body>
</html>
