<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro · Nonna Agência Digital</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:400,600,700,800|ibm-plex-mono:400,500&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Syne', sans-serif; min-height: 100vh; }
        .pub-input {
            width: 100%; padding: 10px 14px; font-size: 13px; font-family: 'Syne', sans-serif;
            background: var(--s2); border: 1px solid var(--border2); color: var(--text); outline: none;
            transition: border-color .15s;
        }
        .pub-input:focus { border-color: var(--purple); }
        .pub-input::placeholder { color: var(--muted); }
        .pub-mono { font-family: 'IBM Plex Mono', monospace; }
        .pub-select {
            width: 100%; padding: 10px 14px; font-size: 13px; font-family: 'Syne', sans-serif;
            background: var(--s2); border: 1px solid var(--border2); color: var(--muted2); outline: none; cursor: pointer;
            transition: border-color .15s;
        }
        .pub-select:focus { border-color: var(--purple); }
        .pub-label { font-family: 'IBM Plex Mono', monospace; font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 7px; }
        .req { color: var(--orange); }
        .field-error { font-size: 11px; color: var(--red); margin-top: 4px; }
        .block-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: baseline; gap: 12px; }
        .block-num { font-family: 'IBM Plex Mono', monospace; font-size: 9px; letter-spacing: .18em; color: var(--muted); }
        .block-title { font-size: 14px; font-weight: 700; color: var(--text); }
        .block-body { padding: 24px; display: grid; gap: 18px; }
        .col-2 { grid-template-columns: 1fr 1fr; }
        @media (max-width: 600px) { .col-2 { grid-template-columns: 1fr; } }
        .span-2 { grid-column: 1 / -1; }
    </style>
</head>
<body>

    {{-- TOPBAR --}}
    <header style="background:rgba(12,12,18,.95); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); height:56px; display:flex; align-items:center; padding:0 28px; position:sticky; top:0; z-index:10">
        <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
             alt="Nonna" style="height:22px"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <span class="grad-text" style="font-weight:800; font-size:15px; display:none">nonna</span>
    </header>

    <main style="max-width:700px; margin:0 auto; padding:48px 24px 80px">

        {{-- HERO --}}
        <div style="margin-bottom:36px">
            <p style="font-family:'IBM Plex Mono',monospace; font-size:9px; letter-spacing:.22em; text-transform:uppercase; color:var(--muted2); margin-bottom:10px">
                Onboarding · Cadastro de Cliente
            </p>
            <h1 style="font-size:clamp(22px,4vw,34px); font-weight:800; line-height:1.15; letter-spacing:-.02em; margin-bottom:12px">
                Bem-vindo à <span class="grad-text">Nonna</span>
            </h1>
            <p style="font-size:14px; color:var(--muted2); line-height:1.7; max-width:520px">
                Preencha as informações abaixo para formalizarmos o seu cadastro.
                Todas as informações são tratadas com total confidencialidade.
            </p>
        </div>

        @if($errors->any())
            <div style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); padding:14px 18px; margin-bottom:24px">
                <p style="font-size:12px; font-weight:700; color:var(--red); margin-bottom:6px">Verifique os campos abaixo:</p>
                <ul style="font-size:12px; color:var(--red); padding-left:16px">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('clients.register.submit', $client->registration_token) }}">
            @csrf

            {{-- BLOCO 01: EMPRESA --}}
            <div class="card" style="margin-bottom:20px">
                <div class="block-header">
                    <span class="block-num">01</span>
                    <span class="block-title">Dados da Empresa</span>
                </div>
                <div class="block-body col-2">

                    <div class="span-2">
                        <label class="pub-label">Razão Social <span class="req">*</span></label>
                        <input type="text" name="company_name" class="pub-input"
                            value="{{ old('company_name', $client->company_name) }}"
                            placeholder="Nome jurídico da empresa">
                        @error('company_name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="pub-label">CPF / CNPJ</label>
                        <input type="text" name="tax_id" class="pub-input pub-mono"
                            value="{{ old('tax_id', $client->tax_id) }}"
                            placeholder="000.000.000-00 ou 00.000.000/0000-00">
                    </div>

                    <div>
                        <label class="pub-label">CEP</label>
                        <input type="text" name="zip_code" class="pub-input pub-mono"
                            value="{{ old('zip_code', $client->zip_code) }}"
                            placeholder="00000-000">
                    </div>

                    <div>
                        <label class="pub-label">E-mail de Contato <span class="req">*</span></label>
                        <input type="email" name="contact_email" class="pub-input pub-mono"
                            value="{{ old('contact_email', $client->contact_email) }}"
                            placeholder="contato@empresa.com">
                        @error('contact_email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="pub-label">Telefone / WhatsApp <span class="req">*</span></label>
                        <input type="text" name="contact_phone" class="pub-input pub-mono"
                            value="{{ old('contact_phone', $client->contact_phone) }}"
                            placeholder="(00) 00000-0000">
                        @error('contact_phone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="span-2">
                        <label class="pub-label">Endereço Completo</label>
                        <input type="text" name="address" class="pub-input"
                            value="{{ old('address', $client->address) }}"
                            placeholder="Rua, número, complemento, bairro, cidade – UF">
                    </div>

                </div>
            </div>

            {{-- BLOCO 02: RESPONSÁVEL --}}
            <div class="card" style="margin-bottom:20px">
                <div class="block-header">
                    <span class="block-num">02</span>
                    <span class="block-title">Dados do Responsável</span>
                </div>
                <div class="block-body col-2">

                    <div class="span-2">
                        <label class="pub-label">Nome Completo <span class="req">*</span></label>
                        <input type="text" name="responsible_name" class="pub-input"
                            value="{{ old('responsible_name', $client->responsible_name) }}"
                            placeholder="Nome completo do responsável">
                        @error('responsible_name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="pub-label">Data de Nascimento</label>
                        <input type="date" name="responsible_birthdate" class="pub-input pub-mono"
                            value="{{ old('responsible_birthdate', $client->responsible_birthdate?->format('Y-m-d')) }}">
                    </div>

                    <div>
                        <label class="pub-label">Estado Civil</label>
                        <select name="responsible_marital_status" class="pub-select">
                            <option value="">Selecione...</option>
                            @foreach(App\Models\Client::$maritalStatuses as $key => $label)
                                <option value="{{ $key }}" {{ old('responsible_marital_status', $client->responsible_marital_status) === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="pub-label">RG</label>
                        <input type="text" name="responsible_rg" class="pub-input pub-mono"
                            value="{{ old('responsible_rg', $client->responsible_rg) }}"
                            placeholder="00.000.000-0">
                    </div>

                    <div>
                        <label class="pub-label">CPF</label>
                        <input type="text" name="responsible_cpf" class="pub-input pub-mono"
                            value="{{ old('responsible_cpf', $client->responsible_cpf) }}"
                            placeholder="000.000.000-00">
                    </div>

                    <div class="span-2">
                        <label class="pub-label">Endereço do Responsável <span style="color:var(--muted); font-weight:400">(se diferente da empresa)</span></label>
                        <input type="text" name="responsible_address" class="pub-input"
                            value="{{ old('responsible_address', $client->responsible_address) }}"
                            placeholder="Rua, número, complemento, bairro, cidade – UF">
                    </div>

                </div>
            </div>

            {{-- BLOCO 03: FINANCEIRO --}}
            <div class="card" style="margin-bottom:32px">
                <div class="block-header">
                    <span class="block-num">03</span>
                    <span class="block-title">Dados Financeiros</span>
                </div>
                <div class="block-body col-2">

                    <div>
                        <label class="pub-label">Forma de Pagamento <span class="req">*</span></label>
                        <select name="payment_method" class="pub-select">
                            <option value="">Selecione...</option>
                            @foreach(App\Models\Client::$paymentMethods as $key => $label)
                                <option value="{{ $key }}" {{ old('payment_method', $client->payment_method) === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('payment_method') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="pub-label">Melhor Data para Cobrança <span class="req">*</span></label>
                        <select name="billing_day" class="pub-select">
                            <option value="">Selecione...</option>
                            @foreach(App\Models\Client::$billingDays as $day)
                                <option value="{{ $day }}" {{ old('billing_day', $client->billing_day) == $day ? 'selected' : '' }}>
                                    Dia {{ $day }}
                                </option>
                            @endforeach
                        </select>
                        @error('billing_day') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="pub-label">E-mail de Cobrança</label>
                        <input type="email" name="billing_email" class="pub-input pub-mono"
                            value="{{ old('billing_email', $client->billing_email) }}"
                            placeholder="financeiro@empresa.com">
                        @error('billing_email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="pub-label">WhatsApp de Cobrança</label>
                        <input type="text" name="billing_whatsapp" class="pub-input pub-mono"
                            value="{{ old('billing_whatsapp', $client->billing_whatsapp) }}"
                            placeholder="(00) 00000-0000">
                    </div>

                    <div class="span-2">
                        <label class="pub-label">Observações</label>
                        <textarea name="billing_notes" rows="3" class="pub-input"
                            style="resize:vertical; line-height:1.6"
                            placeholder="Alguma observação sobre pagamento ou faturamento?">{{ old('billing_notes', $client->billing_notes) }}</textarea>
                    </div>

                </div>
            </div>

            {{-- SUBMIT --}}
            <button type="submit"
                style="width:100%; padding:16px; font-family:'Syne',sans-serif; font-size:14px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#fff; background:var(--grad); border:none; cursor:pointer; transition:opacity .2s"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                Enviar Cadastro →
            </button>

            <p style="text-align:center; font-size:11px; color:var(--muted); margin-top:16px">
                Seus dados são tratados com total confidencialidade pela Nonna Agência Digital.
            </p>
        </form>
    </main>
</body>
</html>
