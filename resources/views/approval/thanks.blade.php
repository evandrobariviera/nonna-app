<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avaliação Enviada · Nonna</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:400,600,700,800|ibm-plex-mono:400,500&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Syne', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .mono { font-family: 'IBM Plex Mono', monospace; }
    </style>
</head>
<body>

    <header style="background:rgba(12,12,18,.95); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); height:56px; display:flex; align-items:center; padding:0 20px; position:sticky; top:0; z-index:10">
        <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
             alt="Nonna" style="height:20px"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <span style="font-weight:800; font-size:15px; display:none">nonna</span>
    </header>

    <main style="flex:1; display:flex; align-items:center; justify-content:center; padding:40px 16px">
        <div style="max-width:480px; width:100%; text-align:center">

            @if($approvalToken->status === 'approved')
                <div style="font-size:48px; margin-bottom:16px">✅</div>
                <h1 style="font-size:22px; font-weight:800; margin:0 0 10px">Material Aprovado!</h1>
                <p style="font-size:14px; color:var(--muted); line-height:1.7; margin:0 0 24px">
                    Sua aprovação foi registrada. O time da Nonna vai avançar com a produção.
                </p>
            @else
                <div style="font-size:48px; margin-bottom:16px">📝</div>
                <h1 style="font-size:22px; font-weight:800; margin:0 0 10px">Ajustes Registrados!</h1>
                <p style="font-size:14px; color:var(--muted); line-height:1.7; margin:0 0 24px">
                    Suas sugestões foram enviadas para o time. Em breve você receberá o material revisado.
                </p>
            @endif

            <div style="background:var(--s1); border:1px solid var(--border); padding:16px 20px; text-align:left">
                <p class="mono" style="font-size:9px; letter-spacing:.15em; text-transform:uppercase; color:var(--muted); margin:0 0 4px">Tarefa</p>
                <p style="font-size:13px; font-weight:600; margin:0">{{ $approvalToken->round->task->title }}</p>
            </div>

            <p class="mono" style="font-size:10px; color:var(--muted); margin-top:24px">
                Você já pode fechar esta janela.
            </p>

        </div>
    </main>

</body>
</html>
