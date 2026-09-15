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
        .label-sm { font-family:Arial,'Segoe UI',Tahoma,sans-serif; font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 6px; }
        .field-input { width: 100%; padding: 10px 12px; font-size: 13px; font-family: 'Inter', sans-serif; background: var(--s2); border: 1px solid var(--border2); color: var(--text); outline: none; box-sizing: border-box; }
        .field-input:focus { border-color: var(--purple); }
        .submit-btn { width: 100%; padding: 16px; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 800; background: var(--purple); color: #fff; border: none; cursor: pointer; letter-spacing: .04em; }
        .submit-btn:hover { opacity: .88; }
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
        <div style="max-width:400px; width:100%">
            <h1 style="font-size:20px; font-weight:800; margin:0 0 8px; text-align:center">Criar senha do Portal</h1>
            <p style="font-size:13px; color:var(--muted); line-height:1.7; margin:0 0 24px; text-align:center">
                Oi, {{ $setupToken->contact->name }}! Crie a senha de acesso ao Portal da {{ $setupToken->client->displayName() }}
                (login: {{ $setupToken->contact->email }}).
            </p>

            @if($errors->any())
                <div style="margin-bottom:20px; padding:12px 16px; font-size:13px; background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.25)">
                    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('portal.password-setup.submit', $setupToken->token) }}">
                @csrf
                <div style="margin-bottom:14px">
                    <span class="label-sm">Nova senha</span>
                    <input type="password" name="password" class="field-input" minlength="8" required autofocus>
                </div>
                <div style="margin-bottom:24px">
                    <span class="label-sm">Confirmar senha</span>
                    <input type="password" name="password_confirmation" class="field-input" minlength="8" required>
                </div>
                <button type="submit" class="submit-btn">Criar senha e liberar acesso</button>
            </form>

            <p class="mono" style="font-size:10px; color:var(--muted); margin-top:20px; text-align:center">
                Mínimo de 8 caracteres.
            </p>
        </div>
    </main>

</body>
</html>
