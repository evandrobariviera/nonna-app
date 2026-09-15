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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; }
        .mono { font-family:Arial,'Segoe UI',Tahoma,sans-serif; }
        .label-sm { font-family:Arial,'Segoe UI',Tahoma,sans-serif; font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 6px; }
        .field-input { width: 100%; padding: 9px 12px; font-size: 13px; font-family: 'Inter', sans-serif; background: var(--s2); border: 1px solid var(--border2); color: var(--text); outline: none; box-sizing: border-box; }
        .field-input:focus { border-color: var(--purple); }
        .entry-card { background: var(--s1); border: 1px solid var(--border); padding: 16px; margin-bottom: 16px; position: relative; }
        .remove-btn { position: absolute; top: 10px; right: 10px; font-size: 12px; color: var(--muted); background: none; border: none; cursor: pointer; }
        .add-btn { width: 100%; padding: 12px; font-size: 13px; font-weight: 700; background: transparent; border: 1px dashed var(--border2); color: var(--muted2); cursor: pointer; }
        .submit-btn { width: 100%; padding: 16px; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 800; background: var(--purple); color: #fff; border: none; cursor: pointer; letter-spacing: .04em; }
        .submit-btn:not(:disabled):hover { opacity: .88; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>

    <header style="background:rgba(12,12,18,.95); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); height:56px; display:flex; align-items:center; padding:0 20px;">
        <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
             alt="Nonna" style="height:20px"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <span style="font-weight:800; font-size:15px; display:none">nonna</span>
    </header>

    <main style="padding:40px 16px">
        <div style="max-width:560px; margin:0 auto">

            <h1 style="font-size:20px; font-weight:800; margin:0 0 8px">
                Acessos — {{ $credentialRequest->client->displayName() }}
            </h1>
            <p style="font-size:13px; color:var(--muted); line-height:1.7; margin:0 0 8px">
                Oi, {{ $credentialRequest->contact->name }}! Pra gente avançar com o trabalho, precisamos que você nos
                informe os acessos das plataformas do seu negócio (site, redes sociais, ferramentas, etc). Fique à
                vontade pra preencher aos poucos — dá pra voltar neste mesmo link depois pra enviar mais.
            </p>
            <p class="mono" style="font-size:10px; color:var(--muted); margin:0 0 28px">
                Link válido até {{ $credentialRequest->expires_at->format('d/m/Y') }}.
            </p>

            @if(session('success'))
                <div style="margin-bottom:20px; padding:12px 16px; font-size:13px; background:rgba(34,197,94,.12); color:#22c55e; border:1px solid rgba(34,197,94,.25)">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div style="margin-bottom:20px; padding:12px 16px; font-size:13px; background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.25)">
                    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('credential-request.submit', $credentialRequest->token) }}"
                  x-data="{
                    entries: [{ platform: '', platform_custom: '', access_url: '', username: '', password: '', notes: '' }],
                    addEntry() { this.entries.push({ platform: '', platform_custom: '', access_url: '', username: '', password: '', notes: '' }); },
                    removeEntry(i) { this.entries.splice(i, 1); },
                  }">
                @csrf

                <template x-for="(entry, i) in entries" :key="i">
                    <div class="entry-card">
                        <button type="button" x-show="entries.length > 1" @click="removeEntry(i)" class="remove-btn">✕ remover</button>

                        <div style="margin-bottom:12px">
                            <span class="label-sm">Plataforma *</span>
                            <select class="field-input" :name="'entries['+i+'][platform]'" x-model="entry.platform">
                                <option value="">Selecione...</option>
                                @foreach($platforms as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="entry.platform === 'outros'" x-cloak style="margin-bottom:12px">
                            <span class="label-sm">Qual plataforma?</span>
                            <input type="text" class="field-input" :name="'entries['+i+'][platform_custom]'" x-model="entry.platform_custom">
                        </div>

                        <div style="margin-bottom:12px">
                            <span class="label-sm">Link de acesso</span>
                            <input type="url" class="field-input" placeholder="https://..." :name="'entries['+i+'][access_url]'" x-model="entry.access_url">
                        </div>

                        <div style="display:flex; gap:10px; margin-bottom:12px">
                            <div style="flex:1">
                                <span class="label-sm">Usuário / E-mail</span>
                                <input type="text" class="field-input" :name="'entries['+i+'][username]'" x-model="entry.username">
                            </div>
                            <div style="flex:1">
                                <span class="label-sm">Senha</span>
                                <input type="text" class="field-input" autocomplete="off" :name="'entries['+i+'][password]'" x-model="entry.password">
                            </div>
                        </div>

                        <div>
                            <span class="label-sm">Observações</span>
                            <input type="text" class="field-input" placeholder="ex: quem mais tem acesso, dica de segurança..." :name="'entries['+i+'][notes]'" x-model="entry.notes">
                        </div>
                    </div>
                </template>

                <button type="button" @click="addEntry()" class="add-btn" style="margin-bottom:24px">+ Adicionar outra plataforma</button>

                <button type="submit" class="submit-btn">Enviar</button>
            </form>

            <p class="mono" style="font-size:10px; color:var(--muted); margin-top:24px; text-align:center">
                Essas informações são criptografadas e usadas só pelo time da Nonna.
            </p>
        </div>
    </main>

</body>
</html>
