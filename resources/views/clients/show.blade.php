<x-app-layout>
    <x-slot name="header">{{ $client->company_name }}</x-slot>

    {{-- BREADCRUMB + AÇÕES --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            @if($client->logoUrl())
                <img src="{{ $client->logoUrl() }}" alt="{{ $client->company_name }}"
                     class="h-6 w-6 rounded-md object-cover flex-shrink-0" style="border:1px solid var(--border2)">
            @endif
            <a href="{{ route('clients.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Clientes</a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">{{ $client->company_name }}</span>
        </div>
        <div class="flex items-center gap-2">
            @php $color = $client->statusColor() @endphp
            <span class="badge badge-{{ $color }}">{{ $client->statusLabel() }}</span>
            <a href="{{ route('clients.edit', $client) }}" class="btn btn-ghost btn-sm">
                Editar
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @if(session('token_generated'))
        <div class="mb-5 p-4" style="background:rgba(106,90,205,.08); border:1px solid rgba(106,90,205,.3)">
            <p class="stat-label mb-2">Link de cadastro gerado</p>
            <div class="flex items-center gap-3">
                <code class="flex-1 text-xs px-3 py-2 truncate"
                      style="background:var(--s1); border:1px solid var(--border2); color:var(--purple); font-family:'IBM Plex Mono',monospace">
                    {{ session('token_generated') }}
                </code>
                <button onclick="navigator.clipboard.writeText('{{ session('token_generated') }}')"
                    class="px-3 py-2 text-xs font-bold"
                    style="border:1px solid var(--border2); color:var(--muted2)">
                    Copiar
                </button>
            </div>
        </div>
    @endif

    {{-- TABS --}}
    <div x-data="{ tab: '{{ request('tab', 'geral') }}' }">

        <div class="tab-bar">
            <button class="tab-btn" :class="{ active: tab === 'geral' }" @click="tab = 'geral'">
                Geral
            </button>
            <button class="tab-btn" :class="{ active: tab === 'contatos' }" @click="tab = 'contatos'">
                Contatos
            </button>
            <button class="tab-btn" :class="{ active: tab === 'senhas' }" @click="tab = 'senhas'">
                Senhas
                @if($client->credentials->count())
                    <span class="tab-count">{{ $client->credentials->count() }}</span>
                @endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'links' }" @click="tab = 'links'">
                Links
                @if($client->links->count())
                    <span class="tab-count">{{ $client->links->count() }}</span>
                @endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'contas' }" @click="tab = 'contas'">
                Contas de Anúncios
            </button>
            <button class="tab-btn" :class="{ active: tab === 'contratos' }" @click="tab = 'contratos'">
                Contratos
                @if($client->contracts->count())
                    <span class="tab-count">{{ $client->contracts->count() }}</span>
                @endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'dossies' }" @click="tab = 'dossies'">
                Dossiê de Marca
            </button>
            <button class="tab-btn" :class="{ active: tab === 'reunioes' }" @click="tab = 'reunioes'">
                Reuniões
            </button>
            <button class="tab-btn" :class="{ active: tab === 'briefing' }" @click="tab = 'briefing'">
                Briefing
            </button>
            <button class="tab-btn" :class="{ active: tab === 'planejamentos' }" @click="tab = 'planejamentos'">
                Planejamentos
            </button>
            <button class="tab-btn" :class="{ active: tab === 'atendimento' }" @click="tab = 'atendimento'">
                Atendimento
                @if($client->integrations->count())
                    <span class="tab-count">{{ $client->integrations->count() }}</span>
                @endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'portal' }" @click="tab = 'portal'">
                Portal
            </button>
        </div>

        {{-- TAB: GERAL --}}
        <div x-show="tab === 'geral'" x-cloak>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                {{-- COLUNA PRINCIPAL --}}
                <div class="lg:col-span-2 flex flex-col gap-4">

                    {{-- DADOS DA EMPRESA --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Dados da Empresa</h3>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <p class="stat-label mb-1">Razão Social</p>
                                <p class="text-sm font-bold" style="color:var(--text)">{{ $client->company_name }}</p>
                            </div>
                            @if($client->tax_id)
                            <div>
                                <p class="stat-label mb-1">CPF / CNPJ</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->tax_id }}</p>
                            </div>
                            @endif
                            @if($client->contact_email)
                            <div>
                                <p class="stat-label mb-1">E-mail de Contato</p>
                                <a href="mailto:{{ $client->contact_email }}" class="text-sm font-mono transition-colors" style="color:var(--purple)"
                                   onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--purple)'">
                                    {{ $client->contact_email }}
                                </a>
                            </div>
                            @endif
                            @if($client->contact_phone)
                            <div>
                                <p class="stat-label mb-1">Telefone / WhatsApp</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->contact_phone }}</p>
                            </div>
                            @endif
                            @if($client->website)
                            <div>
                                <p class="stat-label mb-1">Website</p>
                                <a href="{{ $client->website }}" target="_blank"
                                   class="text-sm transition-colors" style="color:var(--purple)"
                                   onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--purple)'">
                                    {{ parse_url($client->website, PHP_URL_HOST) ?? $client->website }}
                                </a>
                            </div>
                            @endif
                            @if($client->segment)
                            <div>
                                <p class="stat-label mb-1">Segmento</p>
                                <p class="text-sm" style="color:var(--text)">{{ $client->segment }}</p>
                            </div>
                            @endif
                            @if($client->monthly_ad_budget)
                            <div>
                                <p class="stat-label mb-1">Verba em Mídia</p>
                                <p class="text-sm" style="color:var(--text)">{{ $client->monthly_ad_budget }}</p>
                            </div>
                            @endif
                            @if($client->zip_code)
                            <div>
                                <p class="stat-label mb-1">CEP</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->zip_code }}</p>
                            </div>
                            @endif
                            @if($client->address)
                            <div class="col-span-2">
                                <p class="stat-label mb-1">Endereço</p>
                                <p class="text-sm" style="color:var(--text)">{{ $client->address }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- RESPONSÁVEL --}}
                    @if($client->responsible_name)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Dados do Responsável</h3>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <p class="stat-label mb-1">Nome Completo</p>
                                <p class="text-sm font-bold" style="color:var(--text)">{{ $client->responsible_name }}</p>
                            </div>
                            @if($client->responsible_birthdate)
                            <div>
                                <p class="stat-label mb-1">Data de Nascimento</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->responsible_birthdate->format('d/m/Y') }}</p>
                            </div>
                            @endif
                            @if($client->responsible_marital_status)
                            <div>
                                <p class="stat-label mb-1">Estado Civil</p>
                                <p class="text-sm" style="color:var(--text)">{{ App\Models\Client::$maritalStatuses[$client->responsible_marital_status] ?? $client->responsible_marital_status }}</p>
                            </div>
                            @endif
                            @if($client->responsible_rg)
                            <div>
                                <p class="stat-label mb-1">RG</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->responsible_rg }}</p>
                            </div>
                            @endif
                            @if($client->responsible_cpf)
                            <div>
                                <p class="stat-label mb-1">CPF</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->responsible_cpf }}</p>
                            </div>
                            @endif
                            @if($client->responsible_address)
                            <div class="col-span-2">
                                <p class="stat-label mb-1">Endereço do Responsável</p>
                                <p class="text-sm" style="color:var(--text)">{{ $client->responsible_address }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- COBRANÇA --}}
                    @if($client->payment_method || $client->billing_email || $client->billing_notes)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Dados Financeiros</h3>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-4">
                            @if($client->payment_method)
                            <div>
                                <p class="stat-label mb-1">Forma de Pagamento</p>
                                <p class="text-sm" style="color:var(--text)">{{ App\Models\Client::$paymentMethods[$client->payment_method] ?? $client->payment_method }}</p>
                            </div>
                            @endif
                            @if($client->billing_day)
                            <div>
                                <p class="stat-label mb-1">Dia de Cobrança</p>
                                <p class="text-sm" style="color:var(--text)">Dia {{ $client->billing_day }}</p>
                            </div>
                            @endif
                            @if($client->billing_email)
                            <div>
                                <p class="stat-label mb-1">E-mail de Cobrança</p>
                                <a href="mailto:{{ $client->billing_email }}" class="text-sm font-mono transition-colors" style="color:var(--purple)"
                                   onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--purple)'">
                                    {{ $client->billing_email }}
                                </a>
                            </div>
                            @endif
                            @if($client->billing_whatsapp)
                            <div>
                                <p class="stat-label mb-1">WhatsApp de Cobrança</p>
                                <p class="text-sm font-mono" style="color:var(--muted2)">{{ $client->billing_whatsapp }}</p>
                            </div>
                            @endif
                            @if($client->billing_notes)
                            <div class="col-span-2">
                                <p class="stat-label mb-1">Observações Financeiras</p>
                                <p class="text-sm" style="color:var(--muted2); line-height:1.7; white-space:pre-wrap">{{ $client->billing_notes }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- NOTAS INTERNAS --}}
                    @if($client->notes)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Notas Internas</h3>
                        </div>
                        <div class="p-5">
                            <p class="text-sm" style="color:var(--muted2); line-height:1.7; white-space:pre-wrap">{{ $client->notes }}</p>
                        </div>
                    </div>
                    @endif

                </div>

                {{-- COLUNA LATERAL --}}
                <div class="flex flex-col gap-4">

                    {{-- SERVIÇOS --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Serviços Contratados</h3>
                        </div>
                        <div class="p-5">
                            @if($client->contracted_services)
                                <div class="flex flex-col gap-1.5">
                                    @foreach($client->contracted_services as $svc)
                                        <div class="flex items-center gap-2">
                                            <span style="color:var(--green); font-size:10px">✓</span>
                                            <span class="text-xs font-semibold" style="color:var(--muted2)">
                                                {{ App\Models\Client::$services[$svc] ?? $svc }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs" style="color:var(--muted)">Nenhum serviço cadastrado.</p>
                            @endif
                        </div>
                    </div>

                    {{-- LINK DE ONBOARDING --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Link de Cadastro</h3>
                        </div>
                        <div class="p-5">
                            @if($client->registration_completed_at)
                                <span class="badge badge-green mb-3 block w-fit">Cadastro concluído</span>
                                <p class="text-xs" style="color:var(--muted)">
                                    Concluído em {{ $client->registration_completed_at->format('d/m/Y') }}
                                </p>
                            @elseif($client->registration_token)
                                <span class="badge badge-orange mb-3 block w-fit">Aguardando cliente</span>
                                <code class="block text-xs px-2 py-1.5 mb-3 truncate"
                                      style="background:var(--s1); border:1px solid var(--border); color:var(--muted2); font-family:'IBM Plex Mono',monospace">
                                    /cadastro/{{ Str::limit($client->registration_token, 16) }}...
                                </code>
                                <button onclick="navigator.clipboard.writeText('{{ route('clients.register', $client->registration_token) }}')"
                                    class="w-full py-2 text-xs font-bold"
                                    style="border:1px solid var(--border2); color:var(--muted2)">
                                    Copiar link
                                </button>
                            @else
                                <p class="text-xs mb-4" style="color:var(--muted)">
                                    Gere um link único para enviar ao cliente preencher seus dados.
                                </p>
                                <form method="POST" action="{{ route('clients.generate-token', $client) }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full py-2 text-xs font-bold text-white"
                                        style="background:var(--grad)">
                                        Gerar Link de Cadastro
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- METADADOS --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">Metadados</h3>
                        </div>
                        <div class="p-5 flex flex-col gap-3">
                            <div>
                                <p class="stat-label mb-1">ID interno</p>
                                <p class="text-xs truncate" style="color:var(--muted); font-family:'IBM Plex Mono',monospace">{{ $client->id }}</p>
                            </div>
                            @if($client->clickup_task_id)
                            <div>
                                <p class="stat-label mb-1">ClickUp Task ID</p>
                                <p class="text-xs" style="color:var(--muted); font-family:'IBM Plex Mono',monospace">{{ $client->clickup_task_id }}</p>
                            </div>
                            @endif
                            <div>
                                <p class="stat-label mb-1">Criado em</p>
                                <p class="text-xs" style="color:var(--muted)">{{ $client->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- TAB: SENHAS --}}
        <div x-show="tab === 'senhas'" x-cloak x-data="{ showPasswords: {}, addForm: false, editId: null }">

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Credenciais de Acesso
                </h3>
                <button @click="addForm = !addForm"
                        class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background: var(--purple);">
                    + Adicionar
                </button>
            </div>

            {{-- Formulário de nova credencial --}}
            <div x-show="addForm" x-cloak class="card p-5 mb-6">
                <h4 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Nova Credencial</h4>
                <form method="POST" action="{{ route('clients.credentials.store', $client) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div x-data="{ platform: '' }">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Plataforma <span class="text-[var(--orange)]">*</span>
                            </label>
                            <select name="platform" x-model="platform" required
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\ClientCredential::$platforms as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div x-show="platform === 'outros'" class="mt-2">
                                <input type="text" name="platform_custom"
                                       placeholder="Qual plataforma?"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                URL de acesso
                            </label>
                            <input type="url" name="access_url" placeholder="https://..."
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Usuário</label>
                            <input type="text" name="username"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Senha</label>
                            <input type="text" name="password" autocomplete="off"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] font-mono">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                            <input type="text" name="notes" placeholder="Ex: conta da empresa, não confundir com pessoal..."
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background: var(--purple);">
                            Salvar
                        </button>
                        <button type="button" @click="addForm = false"
                                class="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            {{-- Lista de credenciais --}}
            @if($client->credentials->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">🔐</div>
                    <div class="tab-placeholder-title">Nenhuma credencial cadastrada</div>
                    <div class="tab-placeholder-desc">Adicione as senhas e acessos do cliente para centralizar tudo aqui.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Plataforma</th>
                                <th>URL</th>
                                <th>Usuário</th>
                                <th>Senha</th>
                                <th>Obs</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->credentials as $cred)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">
                                        {{ $cred->platformLabel() }}
                                    </td>
                                    <td class="text-sm">
                                        @if($cred->access_url)
                                            <a href="{{ $cred->access_url }}" target="_blank"
                                               class="text-[var(--purple)] hover:underline font-mono text-xs truncate block max-w-[180px]">
                                                {{ parse_url($cred->access_url, PHP_URL_HOST) }}
                                            </a>
                                        @else
                                            <span class="text-[var(--muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-sm text-[var(--muted2)]">
                                        {{ $cred->username ?: '—' }}
                                    </td>
                                    <td x-data="{ show: false }">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm"
                                                  :style="show ? 'color:var(--text)' : 'color:var(--muted)'">
                                                <span x-show="!show">••••••••</span>
                                                <span x-show="show" x-cloak>{{ $cred->password ?: '—' }}</span>
                                            </span>
                                            <button @click="show = !show"
                                                    class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">
                                                <span x-show="!show">Ver</span>
                                                <span x-show="show" x-cloak>Ocultar</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-xs text-[var(--muted)] max-w-[150px] truncate">
                                        {{ $cred->notes ?: '—' }}
                                    </td>
                                    <td class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button"
                                                    @click="editId = editId === '{{ $cred->id }}' ? null : '{{ $cred->id }}'"
                                                    class="btn btn-ghost btn-xs">
                                                <span x-text="editId === '{{ $cred->id }}' ? 'Cancelar' : 'Editar'"></span>
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('clients.credentials.destroy', [$client, $cred]) }}"
                                                  @submit.prevent="if (await $store.confirmDialog.ask('Remover esta credencial?')) $el.submit()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs">
                                                    Remover
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Formulário de edição --}}
                                <tr x-show="editId === '{{ $cred->id }}'" x-cloak>
                                    <td colspan="6" style="background: var(--s2)">
                                        <form method="POST" action="{{ route('clients.credentials.update', [$client, $cred]) }}" class="p-4 space-y-4">
                                            @csrf @method('PATCH')
                                            <div class="grid grid-cols-2 gap-4">
                                                <div x-data="{ platform: '{{ $cred->platform }}' }">
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                                        Plataforma <span class="text-[var(--orange)]">*</span>
                                                    </label>
                                                    <select name="platform" x-model="platform" required
                                                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                        @foreach(\App\Models\ClientCredential::$platforms as $key => $label)
                                                            <option value="{{ $key }}" {{ $cred->platform === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div x-show="platform === 'outros'" class="mt-2">
                                                        <input type="text" name="platform_custom" value="{{ $cred->platform_custom }}"
                                                               placeholder="Qual plataforma?"
                                                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                                        URL de acesso
                                                    </label>
                                                    <input type="url" name="access_url" value="{{ $cred->access_url }}" placeholder="https://..."
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Usuário</label>
                                                    <input type="text" name="username" value="{{ $cred->username }}"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Senha</label>
                                                    <input type="text" name="password" value="{{ $cred->password }}" autocomplete="off"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] font-mono">
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                                                    <input type="text" name="notes" value="{{ $cred->notes }}" placeholder="Ex: conta da empresa, não confundir com pessoal..."
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                            </div>
                                            <div class="flex gap-3">
                                                <button type="submit"
                                                        class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                                        style="background: var(--purple);">
                                                    Salvar
                                                </button>
                                                <button type="button" @click="editId = null" class="btn btn-ghost btn-sm">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB: LINKS --}}
        <div x-show="tab === 'links'" x-cloak x-data="{ addForm: false }">

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Links Importantes
                </h3>
                <button @click="addForm = !addForm"
                        class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background: var(--purple);">
                    + Adicionar
                </button>
            </div>

            {{-- Formulário de novo link --}}
            <div x-show="addForm" x-cloak class="card p-5 mb-6">
                <h4 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Novo Link</h4>
                <form method="POST" action="{{ route('clients.links.store', $client) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div x-data="{ type: '' }">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Tipo <span class="text-[var(--orange)]">*</span>
                            </label>
                            <select name="type" x-model="type" required
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\ClientLink::$types as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div x-show="type === 'outros'" class="mt-2">
                                <input type="text" name="type_custom"
                                       placeholder="Qual tipo de link?"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                URL <span class="text-[var(--orange)]">*</span>
                            </label>
                            <input type="url" name="url" placeholder="https://..." required
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                            <input type="text" name="notes" placeholder="Ex: pasta de criativos do cliente..."
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background: var(--purple);">
                            Salvar
                        </button>
                        <button type="button" @click="addForm = false"
                                class="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            {{-- Lista de links --}}
            @if($client->links->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">🔗</div>
                    <div class="tab-placeholder-title">Nenhum link cadastrado</div>
                    <div class="tab-placeholder-desc">Adicione o Drive, contratos e demais links importantes do cliente para centralizar tudo aqui.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Link</th>
                                <th>Obs</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->links as $link)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">
                                        {{ $link->typeLabel() }}
                                    </td>
                                    <td class="text-sm">
                                        <a href="{{ $link->url }}" target="_blank" rel="noopener"
                                           class="text-[var(--purple)] hover:underline font-mono text-xs truncate block max-w-[220px]">
                                            {{ $link->url }}
                                        </a>
                                    </td>
                                    <td class="text-xs text-[var(--muted)] max-w-[150px] truncate">
                                        {{ $link->notes ?: '—' }}
                                    </td>
                                    <td class="text-right">
                                        <form method="POST"
                                              action="{{ route('clients.links.destroy', [$client, $link]) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Remover este link?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                Remover
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB: CONTATOS --}}
        <div x-show="tab === 'contatos'" x-cloak
             x-data="{
                mode: null, editId: null,
                subsModal: false, subsContactId: null, subsContactName: '', subsForm: {},
                openSubs(contactId, name, current) {
                    this.subsContactId = contactId;
                    this.subsContactName = name;
                    this.subsForm = current;
                    this.subsModal = true;
                },
                closeSubs() { this.subsModal = false; this.subsContactId = null; }
             }">

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Contatos Vinculados
                    @if($client->contacts->count())
                        <span class="ml-2 tab-count">{{ $client->contacts->count() }}</span>
                    @endif
                </h3>
                <div class="flex gap-2">
                    <button @click="mode = mode === 'link' ? null : 'link'"
                            :style="mode === 'link' ? 'border-color:var(--purple); color:var(--purple)' : ''"
                            class="px-3 py-1.5 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                            style="border:1px solid var(--border2); color:var(--muted2)">
                        Vincular Existente
                    </button>
                    <button @click="mode = mode === 'new' ? null : 'new'"
                            :style="mode === 'new' ? '' : ''"
                            class="px-3 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background:var(--purple)">
                        + Novo Contato
                    </button>
                </div>
            </div>

            {{-- FORM: Vincular contato existente --}}
            <div x-show="mode === 'link'" x-cloak class="card p-5 mb-5">
                <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Vincular Contato do CRM</p>
                @if($availableContacts->isEmpty())
                    <p class="text-sm" style="color:var(--muted)">
                        Todos os contatos cadastrados já estão vinculados a este cliente,
                        ou não há contatos no CRM ainda.
                        <a href="{{ route('contacts.create') }}" style="color:var(--purple)" class="underline">Cadastrar novo contato</a>.
                    </p>
                @else
                    <form method="POST" action="{{ route('clients.contacts.link', $client) }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                    Contato <span style="color:var(--orange)">*</span>
                                </label>
                                <select name="contact_id" required
                                        class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                    <option value="">Selecione um contato...</option>
                                    @foreach($availableContacts as $c)
                                        <option value="{{ $c->id }}">
                                            {{ $c->name }}
                                            @if($c->job_title) · {{ $c->job_title }} @endif
                                            @if($c->company_name && $c->company_name !== $client->company_name)
                                                ({{ $c->company_name }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                    Papel neste cliente
                                </label>
                                <select name="role"
                                        class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                    <option value="">Sem papel definido</option>
                                    @foreach(\App\Http\Controllers\ClientContactController::$roles as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_primary" value="1"
                                   class="w-4 h-4 accent-[var(--purple)]">
                            <span class="text-sm" style="color:var(--muted2)">Contato principal deste cliente</span>
                        </label>
                        <div class="flex gap-3">
                            <button type="submit" class="btn btn-primary btn-sm">
                                Vincular
                            </button>
                            <button type="button" @click="mode = null" class="btn btn-ghost btn-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- FORM: Criar novo contato e vincular --}}
            <div x-show="mode === 'new'" x-cloak class="card p-5 mb-5">
                <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Novo Contato — Já vinculado a este cliente</p>
                <form method="POST" action="{{ route('clients.contacts.store-and-link', $client) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Nome Completo <span style="color:var(--orange)">*</span>
                            </label>
                            <input type="text" name="name" required placeholder="Nome do contato"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Cargo / Função
                            </label>
                            <input type="text" name="job_title" placeholder="Ex: Sócia-fundadora, Gestor"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">E-mail</label>
                            <input type="email" name="email" placeholder="email@exemplo.com"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Telefone / WhatsApp</label>
                            <input type="text" name="whatsapp" placeholder="(41) 99999-9999"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Papel neste cliente
                            </label>
                            <select name="role"
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">Sem papel definido</option>
                                @foreach(\App\Http\Controllers\ClientContactController::$roles as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_primary" value="1"
                                       class="w-4 h-4 accent-[var(--purple)]">
                                <span class="text-sm" style="color:var(--muted2)">Contato principal</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                            Criar e Vincular
                        </button>
                        <button type="button" @click="mode = null"
                                class="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            {{-- LISTA de contatos vinculados --}}
            @if($client->contacts->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">👥</div>
                    <div class="tab-placeholder-title">Nenhum contato vinculado</div>
                    <div class="tab-placeholder-desc">Vincule contatos existentes do CRM ou cadastre um novo já vinculado.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Cargo</th>
                                <th>Papel no Cliente</th>
                                <th>Contato</th>
                                <th>Comunicações</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->contacts as $contact)
                                @php
                                    $contactSubs = ($subscriptionsByContact[$contact->id] ?? null)?->subscriptions ?? collect();
                                    $subsForRow = collect(\App\Models\ClientContactSubscription::$types)->keys()
                                        ->mapWithKeys(fn ($type) => [$type => $contactSubs->firstWhere('type', $type)?->channels ?? []]);
                                    $activeSubs = $subsForRow->filter(fn ($channels) => !empty($channels));
                                @endphp
                                <tr>
                                    {{-- Linha de exibição --}}
                                    <template x-if="editId !== '{{ $contact->id }}'">
                                        <td>
                                            <div class="flex items-center gap-2">
                                                @if($contact->pivot->is_primary)
                                                    <span class="text-xs font-bold px-1.5 py-0.5"
                                                          style="background:rgba(106,90,205,.15); color:var(--purple); border:1px solid rgba(106,90,205,.3)">
                                                        Principal
                                                    </span>
                                                @endif
                                                <a href="{{ route('contacts.show', $contact) }}"
                                                   class="text-sm font-semibold hover:underline"
                                                   style="color:var(--text)">
                                                    {{ $contact->name }}
                                                </a>
                                            </div>
                                        </td>
                                    </template>
                                    <template x-if="editId === '{{ $contact->id }}'">
                                        <td class="font-semibold text-sm" style="color:var(--text)">{{ $contact->name }}</td>
                                    </template>

                                    <td class="text-xs" style="color:var(--muted2)">
                                        {{ $contact->job_title ?: '—' }}
                                    </td>

                                    {{-- Papel: exibição ou edição --}}
                                    <template x-if="editId !== '{{ $contact->id }}'">
                                        <td class="text-xs" style="color:var(--muted2)">
                                            @php
                                                $roles = \App\Http\Controllers\ClientContactController::$roles;
                                                $roleKey = $contact->pivot->role;
                                            @endphp
                                            {{ $roles[$roleKey] ?? ($roleKey ?: '—') }}
                                        </td>
                                    </template>
                                    <template x-if="editId === '{{ $contact->id }}'">
                                        <td>
                                            <form id="edit-contact-{{ $contact->id }}" method="POST"
                                                  action="{{ route('clients.contacts.update-pivot', [$client, $contact]) }}">
                                                @csrf @method('PATCH')
                                                <div class="flex items-center gap-2">
                                                    <select name="role" form="edit-contact-{{ $contact->id }}"
                                                            class="bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                                        <option value="">—</option>
                                                        @foreach(\App\Http\Controllers\ClientContactController::$roles as $key => $rl)
                                                            <option value="{{ $key }}" {{ $contact->pivot->role === $key ? 'selected' : '' }}>
                                                                {{ $rl }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <label class="flex items-center gap-1 text-xs" style="color:var(--muted2); white-space:nowrap">
                                                        <input type="checkbox" name="is_primary" value="1"
                                                               form="edit-contact-{{ $contact->id }}"
                                                               {{ $contact->pivot->is_primary ? 'checked' : '' }}
                                                               class="w-3.5 h-3.5 accent-[var(--purple)]">
                                                        Principal
                                                    </label>
                                                </div>
                                            </form>
                                        </td>
                                    </template>

                                    <td class="text-xs font-mono" style="color:var(--muted)">
                                        @if($contact->whatsapp)
                                            {{ $contact->whatsapp }}
                                        @elseif($contact->email)
                                            {{ $contact->email }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td>
                                        @if($activeSubs->isEmpty())
                                            <button type="button"
                                                    @click="openSubs('{{ $contact->id }}', '{{ addslashes($contact->name) }}', {{ json_encode($subsForRow) }})"
                                                    class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">
                                                + Configurar
                                            </button>
                                        @else
                                            <button type="button"
                                                    @click="openSubs('{{ $contact->id }}', '{{ addslashes($contact->name) }}', {{ json_encode($subsForRow) }})"
                                                    class="flex flex-wrap gap-1">
                                                @foreach($activeSubs as $type => $channels)
                                                    <span class="text-xs font-semibold px-1.5 py-0.5"
                                                          style="background:rgba(106,90,205,.1); color:var(--purple); border:1px solid rgba(106,90,205,.25)">
                                                        {{ \App\Models\ClientContactSubscription::$types[$type] }}
                                                        <span style="color:var(--muted2)">{{ implode('+', array_map(fn($c) => $c === 'whatsapp' ? '📱' : '✉', $channels)) }}</span>
                                                    </span>
                                                @endforeach
                                            </button>
                                        @endif
                                    </td>

                                    <td class="text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <template x-if="editId !== '{{ $contact->id }}'">
                                                <button type="button" @click="editId = '{{ $contact->id }}'" class="btn btn-ghost btn-xs">
                                                    Editar papel
                                                </button>
                                            </template>
                                            <template x-if="editId === '{{ $contact->id }}'">
                                                <div class="flex gap-2">
                                                    <button type="submit" form="edit-contact-{{ $contact->id }}" class="btn btn-primary btn-xs">
                                                        Salvar
                                                    </button>
                                                    <button type="button" @click="editId = null" class="btn btn-ghost btn-xs">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </template>
                                            <form method="POST"
                                                  action="{{ route('clients.contacts.unlink', [$client, $contact]) }}"
                                                  @submit.prevent="if (await $store.confirmDialog.ask('Desvincular {{ addslashes($contact->name) }} deste cliente?')) $el.submit()">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs">
                                                    Desvincular
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Modal: gerenciar comunicações do contato --}}
            <div x-show="subsModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <div class="absolute inset-0 bg-black/60" @click="closeSubs()"></div>
                <div class="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                     style="background:var(--s1); border:1px solid var(--border)"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">

                    <h3 class="text-base font-bold mb-1" style="color:var(--text)">Comunicações</h3>
                    <p class="text-xs mb-5" style="color:var(--muted)" x-text="subsContactName"></p>

                    <template x-if="subsContactId">
                        <form :action="'{{ url('clientes/' . $client->id . '/contatos') }}/' + subsContactId + '/comunicacoes'"
                              method="POST" class="space-y-4">
                            @csrf
                            @method('PUT')

                            @foreach(\App\Models\ClientContactSubscription::$types as $typeKey => $typeLabel)
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text)">{{ $typeLabel }}</label>
                                    <div class="flex items-center gap-4">
                                        @foreach(\App\Models\ClientContactSubscription::$channels as $chKey => $chLabel)
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="checkbox" value="{{ $chKey }}"
                                                       name="subscriptions[{{ $typeKey }}][]"
                                                       x-model="subsForm.{{ $typeKey }}">
                                                <span class="text-xs" style="color:var(--muted2)">{{ $chLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="closeSubs()" class="btn btn-ghost btn-sm">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Salvar
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        </div>

        {{-- TAB: CONTAS DE ANÚNCIOS --}}
        <div x-show="tab === 'contas'" x-cloak x-data="{ addForm: false, editId: null, budgetForm: false, budgetHistory: false, billingOpenId: null, metasOpenId: null }">

            {{-- ORÇAMENTO DE ANÚNCIOS --}}
            @php
                $currentBudget = $client->currentAdBudget();
                $monthSpend = $client->currentMonthAdSpend();
                $budgetPct = $currentBudget && (float) $currentBudget->monthly_budget > 0
                    ? round(($monthSpend / (float) $currentBudget->monthly_budget) * 100, 1)
                    : null;
                $budgetColor = $budgetPct === null ? 'muted' : ($budgetPct > 110 ? 'red' : ($budgetPct >= 90 ? 'orange' : 'green'));
            @endphp
            <div class="card p-5 mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                        Orçamento de Anúncios (mês vigente)
                    </h3>
                    <div class="flex items-center gap-3">
                        @if($client->adBudgets->count() > 1)
                            <button @click="budgetHistory = !budgetHistory"
                                    class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                    onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                                Histórico ({{ $client->adBudgets->count() }})
                            </button>
                        @endif
                        <button @click="budgetForm = !budgetForm"
                                class="text-xs font-bold font-mono uppercase tracking-widest"
                                style="color:var(--purple)">
                            {{ $currentBudget ? 'Ajustar orçamento' : '+ Definir orçamento' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Combinado / mês</p>
                        <p class="text-xl font-black" style="color:var(--text)">
                            {{ $currentBudget ? 'R$ ' . number_format($currentBudget->monthly_budget, 2, ',', '.') : '—' }}
                        </p>
                        @if($currentBudget)
                            <p class="text-xs mt-0.5" style="color:var(--muted)">desde {{ $currentBudget->start_date->format('d/m/Y') }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Gasto no mês</p>
                        <p class="text-xl font-black" style="color:var(--text)">
                            R$ {{ number_format($monthSpend, 2, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">% utilizado</p>
                        @if($budgetPct !== null)
                            <p class="text-xl font-black" style="color:var(--{{ $budgetColor }})">{{ $budgetPct }}%</p>
                        @else
                            <p class="text-xl font-black" style="color:var(--muted)">—</p>
                        @endif
                    </div>
                </div>

                {{-- Formulário de novo orçamento --}}
                <div x-show="budgetForm" x-cloak class="mt-4 pt-4" style="border-top:1px solid var(--border2)">
                    <form method="POST" action="{{ route('clients.ad-budgets.store', $client) }}" class="grid grid-cols-3 gap-4 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Valor mensal (R$) <span class="text-[var(--orange)]">*</span>
                            </label>
                            <input type="number" step="0.01" min="0" name="monthly_budget" required
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 font-mono focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Vigente a partir de <span class="text-[var(--orange)]">*</span>
                            </label>
                            <input type="date" name="start_date" required value="{{ now()->toDateString() }}"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                Salvar
                            </button>
                            <button type="button" @click="budgetForm = false" class="btn btn-ghost btn-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Histórico --}}
                @if($client->adBudgets->count() > 1)
                    <div x-show="budgetHistory" x-cloak class="mt-4 pt-4" style="border-top:1px solid var(--border2)">
                        <div class="flex flex-col gap-1.5">
                            @foreach($client->adBudgets as $budget)
                                <div class="flex items-center justify-between px-3 py-2 text-xs" style="background:var(--s2)">
                                    <span style="color:var(--text)">
                                        R$ {{ number_format($budget->monthly_budget, 2, ',', '.') }}
                                        <span style="color:var(--muted)">desde {{ $budget->start_date->format('d/m/Y') }}</span>
                                    </span>
                                    <div class="flex items-center gap-3">
                                        <span style="color:var(--muted)">{{ $budget->createdBy?->name }}</span>
                                        <form method="POST" action="{{ route('clients.ad-budgets.destroy', [$client, $budget]) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Remover este registro de orçamento?')) $el.submit()">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">✕</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Contas de Anúncios
                </h3>
                <button @click="addForm = !addForm"
                        class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background: var(--purple);">
                    + Adicionar
                </button>
            </div>

            {{-- Formulário de nova conta --}}
            <div x-show="addForm" x-cloak class="card p-5 mb-6">
                <h4 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Nova Conta de Anúncios</h4>
                <form method="POST" action="{{ route('clients.ad-accounts.store', $client) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4" x-data="{ platform: '' }">
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Plataforma <span class="text-[var(--orange)]">*</span>
                            </label>
                            <select name="platform" x-model="platform" required
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\ClientAdAccount::$platforms as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div x-show="platform === 'outros'" class="mt-2">
                                <input type="text" name="platform_custom"
                                       placeholder="Qual plataforma?"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Status <span class="text-[var(--orange)]">*</span>
                            </label>
                            <select name="status" required
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                @foreach(\App\Models\ClientAdAccount::$statuses as $key => $s)
                                    <option value="{{ $key }}" {{ $key === 'ativo' ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                ID da Conta <span class="text-[var(--orange)]">*</span>
                            </label>
                            <input type="text" name="account_id" required
                                   placeholder="Ex: act_123456789"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 font-mono focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Nome da Conta
                            </label>
                            <input type="text" name="account_name"
                                   placeholder="Ex: Conta Principal"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Aba da Planilha de Relatório
                            </label>
                            <input type="text" name="sheet_tab_name"
                                   placeholder="Ex: nome da aba no Google Sheets do Adveronix pra esta conta"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                            <input type="text" name="notes"
                                   placeholder="Ex: conta de remarketing, separada da principal..."
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background: var(--purple);">
                            Salvar
                        </button>
                        <button type="button" @click="addForm = false"
                                class="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            {{-- Lista de contas --}}
            @if($client->adAccounts->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">📡</div>
                    <div class="tab-placeholder-title">Nenhuma conta cadastrada</div>
                    <div class="tab-placeholder-desc">Adicione os IDs de Meta Ads, Google Ads e outras plataformas para centralizar aqui.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Plataforma</th>
                                <th>ID da Conta</th>
                                <th>Nome</th>
                                <th>Aba Planilha</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th>Saldo</th>
                                <th>Automação</th>
                                <th>Obs</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->adAccounts as $account)
                                <tr>
                                    {{-- Linha de exibição --}}
                                    <template x-if="editId !== '{{ $account->id }}'">
                                        <td class="font-semibold text-[var(--text)]">
                                            {{ $account->platformLabel() }}
                                        </td>
                                    </template>
                                    <template x-if="editId === '{{ $account->id }}'">
                                        <td>
                                            <select name="platform" form="edit-account-{{ $account->id }}"
                                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                                @foreach(\App\Models\ClientAdAccount::$platforms as $key => $label)
                                                    <option value="{{ $key }}" {{ $account->platform === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </template>

                                    <template x-if="editId !== '{{ $account->id }}'">
                                        <td class="font-mono text-sm text-[var(--purple)]">{{ $account->account_id }}</td>
                                    </template>
                                    <template x-if="editId === '{{ $account->id }}'">
                                        <td>
                                            <input type="text" name="account_id" form="edit-account-{{ $account->id }}"
                                                   value="{{ $account->account_id }}"
                                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs font-mono text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                        </td>
                                    </template>

                                    <template x-if="editId !== '{{ $account->id }}'">
                                        <td class="text-sm text-[var(--muted2)]">{{ $account->account_name ?: '—' }}</td>
                                    </template>
                                    <template x-if="editId === '{{ $account->id }}'">
                                        <td>
                                            <input type="text" name="account_name" form="edit-account-{{ $account->id }}"
                                                   value="{{ $account->account_name }}"
                                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                        </td>
                                    </template>

                                    <template x-if="editId !== '{{ $account->id }}'">
                                        <td class="text-sm text-[var(--muted2)]">{{ $account->sheet_tab_name ?: '—' }}</td>
                                    </template>
                                    <template x-if="editId === '{{ $account->id }}'">
                                        <td>
                                            <input type="text" name="sheet_tab_name" form="edit-account-{{ $account->id }}"
                                                   value="{{ $account->sheet_tab_name }}"
                                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                        </td>
                                    </template>

                                    <template x-if="editId !== '{{ $account->id }}'">
                                        <td>
                                            <span class="badge badge-{{ $account->statusColor() }}">{{ $account->statusLabel() }}</span>
                                        </td>
                                    </template>
                                    <template x-if="editId === '{{ $account->id }}'">
                                        <td>
                                            <select name="status" form="edit-account-{{ $account->id }}"
                                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                                @foreach(\App\Models\ClientAdAccount::$statuses as $key => $s)
                                                    <option value="{{ $key }}" {{ $account->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </template>

                                    <template x-if="editId !== '{{ $account->id }}'">
                                        <td class="text-xs text-[var(--muted2)]">{{ $account->paymentMethodLabel() }}</td>
                                    </template>
                                    <template x-if="editId === '{{ $account->id }}'">
                                        <td>
                                            <select name="payment_method" form="edit-account-{{ $account->id }}"
                                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                                <option value="">—</option>
                                                @foreach(\App\Models\ClientAdAccount::$paymentMethods as $key => $label)
                                                    <option value="{{ $key }}" {{ $account->payment_method === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </template>

                                    @if($account->hasBillingTracking())
                                        <template x-if="editId !== '{{ $account->id }}'">
                                            <td class="text-xs">
                                                <span style="color:var(--text)">
                                                    {{ $account->balance !== null ? 'R$ ' . number_format((float) $account->balance, 2, ',', '.') : '—' }}
                                                </span>
                                                @if($account->balanceSourceLabel())
                                                    <span class="badge badge-blue" style="white-space:nowrap">{{ $account->balanceSourceLabel() }}</span>
                                                @endif
                                                <div class="mt-0.5">
                                                    <span class="badge badge-{{ $account->balanceStatusColor() }}" style="white-space:nowrap">
                                                        {{ $account->balanceStatusLabel() }}
                                                    </span>
                                                </div>
                                            </td>
                                        </template>
                                        <template x-if="editId === '{{ $account->id }}'">
                                            <td>
                                                <input type="number" step="0.01" min="0" name="balance" form="edit-account-{{ $account->id }}"
                                                       value="{{ $account->balance }}"
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs font-mono text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                            </td>
                                        </template>

                                        <template x-if="editId !== '{{ $account->id }}'">
                                            <td>
                                                <span class="badge badge-{{ $account->budget_automation_enabled ? 'green' : 'muted' }}">
                                                    {{ $account->budget_automation_enabled ? 'Sim' : 'Não' }}
                                                </span>
                                            </td>
                                        </template>
                                        <template x-if="editId === '{{ $account->id }}'">
                                            <td>
                                                <label class="flex items-center gap-2 text-xs" style="color:var(--muted2)">
                                                    <input type="checkbox" name="budget_automation_enabled" value="1" form="edit-account-{{ $account->id }}"
                                                           {{ $account->budget_automation_enabled ? 'checked' : '' }}>
                                                    Avisar saldo baixo
                                                </label>
                                            </td>
                                        </template>
                                    @else
                                        <td class="text-xs" style="color:var(--muted)">—</td>
                                        <td class="text-xs" style="color:var(--muted)">—</td>
                                    @endif

                                    <td class="text-xs text-[var(--muted)] max-w-[150px] truncate">
                                        {{ $account->notes ?: '—' }}
                                    </td>

                                    <td class="text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            @if($account->hasBillingTracking())
                                                <button type="button" @click="billingOpenId = billingOpenId === '{{ $account->id }}' ? null : '{{ $account->id }}'"
                                                        class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">
                                                    Boletos ({{ $account->billingDocuments->count() }})
                                                </button>
                                            @endif
                                            <button type="button" @click="metasOpenId = metasOpenId === '{{ $account->id }}' ? null : '{{ $account->id }}'"
                                                    class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">
                                                Metas
                                            </button>
                                            <template x-if="editId !== '{{ $account->id }}'">
                                                <button type="button" @click="editId = '{{ $account->id }}'" class="btn btn-ghost btn-xs">
                                                    Editar
                                                </button>
                                            </template>
                                            <template x-if="editId === '{{ $account->id }}'">
                                                <div class="flex gap-2">
                                                    <button type="submit" form="edit-account-{{ $account->id }}" class="btn btn-primary btn-xs">
                                                        Salvar
                                                    </button>
                                                    <button type="button" @click="editId = null" class="btn btn-ghost btn-xs">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </template>
                                            <form method="POST"
                                                  action="{{ route('clients.ad-accounts.destroy', [$client, $account]) }}"
                                                  @submit.prevent="if (await $store.confirmDialog.ask('Remover esta conta?')) $el.submit()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs">
                                                    Remover
                                                </button>
                                            </form>
                                        </div>
                                        {{-- Form oculto de edição --}}
                                        <form id="edit-account-{{ $account->id }}" method="POST"
                                              action="{{ route('clients.ad-accounts.update', [$client, $account]) }}"
                                              class="hidden">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                    </td>
                                </tr>
                                <tr x-show="metasOpenId === '{{ $account->id }}'" x-cloak>
                                    <td colspan="10" style="background:var(--s2)">
                                        <div class="p-4">
                                            <h4 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">
                                                Metas de Performance — {{ $account->platformLabel() }} · {{ $account->account_id }}
                                            </h4>
                                            <p class="text-xs mb-3" style="color:var(--muted)">
                                                Padrão herdado por toda campanha desta conta, salvo quando a campanha define sua própria meta. Deixe em branco pra não avaliar esse critério.
                                            </p>
                                            <form method="POST" action="{{ route('clients.ad-accounts.update', [$client, $account]) }}"
                                                  class="grid grid-cols-3 gap-3 items-end">
                                                @csrf
                                                @method('PATCH')
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Meta de custo/resultado (R$)</label>
                                                    <input type="number" step="0.01" min="0" name="target_cost_per_result"
                                                           value="{{ $account->target_cost_per_result }}"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs font-mono text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Meta de ROAS (x)</label>
                                                    <input type="number" step="0.1" min="0" name="target_roas"
                                                           value="{{ $account->target_roas }}"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs font-mono text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">Salvar Metas</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @if($account->hasBillingTracking())
                                    <tr x-show="billingOpenId === '{{ $account->id }}'" x-cloak>
                                        <td colspan="10" style="background:var(--s2)">
                                            <div class="p-4" x-data="{ docType: 'boleto' }">
                                                <h4 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">
                                                    Enviar Boleto/PIX — {{ $account->platformLabel() }} · {{ $account->account_id }}
                                                </h4>
                                                <form method="POST" enctype="multipart/form-data"
                                                      action="{{ route('clients.ad-accounts.billing.store', [$client, $account]) }}"
                                                      class="grid grid-cols-4 gap-3 items-end mb-4">
                                                    @csrf
                                                    <div>
                                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Tipo</label>
                                                        <select name="type" x-model="docType"
                                                                class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                            @foreach(\App\Models\ClientAdBillingDocument::$types as $key => $label)
                                                                <option value="{{ $key }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Valor (R$)</label>
                                                        <input type="number" step="0.01" min="0" name="amount"
                                                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs font-mono text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Vencimento</label>
                                                        <input type="date" name="due_date"
                                                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                    </div>
                                                    <div x-show="docType === 'boleto'">
                                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Arquivo (PDF)</label>
                                                        <input type="file" name="file"
                                                               class="w-full text-xs text-[var(--text)]">
                                                    </div>
                                                    <div x-show="docType === 'pix'" class="col-span-2">
                                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Código PIX (copia e cola)</label>
                                                        <textarea name="pix_code" rows="1"
                                                                  class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs font-mono text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]"></textarea>
                                                    </div>
                                                    <div class="col-span-4">
                                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                                                        <input type="text" name="notes"
                                                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                                    </div>
                                                    <div>
                                                        <button type="submit"
                                                                class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                                                style="background: var(--purple);">
                                                            Enviar e notificar cliente
                                                        </button>
                                                    </div>
                                                </form>

                                                @if($account->billingDocuments->isNotEmpty())
                                                    <div class="flex flex-col gap-1.5">
                                                        @foreach($account->billingDocuments as $doc)
                                                            <div class="flex items-center justify-between px-3 py-2 text-xs" style="background:var(--s3)">
                                                                <span style="color:var(--text)">
                                                                    {{ $doc->typeLabel() }}
                                                                    @if($doc->amount) — R$ {{ number_format((float) $doc->amount, 2, ',', '.') }} @endif
                                                                    @if($doc->due_date) <span style="color:var(--muted)">vence {{ $doc->due_date->format('d/m/Y') }}</span> @endif
                                                                    <span style="color:var(--muted)">· enviado {{ $doc->created_at->format('d/m/Y') }}</span>
                                                                    @if($doc->notified_at)
                                                                        <span class="badge badge-green" style="white-space:nowrap">cliente notificado</span>
                                                                    @endif
                                                                </span>
                                                                <div class="flex items-center gap-3">
                                                                    @if($doc->hasFile())
                                                                        <a href="{{ $doc->url() }}" target="_blank" style="color:var(--purple)">{{ $doc->icon() }} {{ $doc->filename }}</a>
                                                                    @endif
                                                                    <form method="POST"
                                                                          action="{{ route('clients.ad-accounts.billing.destroy', [$client, $account, $doc]) }}"
                                                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover este documento?')) $el.submit()">
                                                                        @csrf @method('DELETE')
                                                                        <button type="submit" class="btn btn-danger btn-xs">✕</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-xs" style="color:var(--muted)">Nenhum boleto/PIX enviado ainda.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB: CONTRATOS --}}
        <div x-show="tab === 'contratos'" x-cloak>

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Contratos
                </h3>
                <form method="POST" action="{{ route('clients.contracts.store', $client) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background: var(--purple);">
                        + Novo Contrato
                    </button>
                </form>
            </div>

            @if($client->contracts->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">📃</div>
                    <div class="tab-placeholder-title">Nenhum contrato cadastrado</div>
                    <div class="tab-placeholder-desc">Clique em "Novo Contrato" para registrar vigência, valor e anexos do contrato deste cliente.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Status</th>
                                <th>Vigência</th>
                                <th>Fee</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->contracts as $contract)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">{{ $contract->title }}</td>
                                    <td>
                                        <span class="badge badge-{{ $contract->statusColor() }}">{{ $contract->statusLabel() }}</span>
                                    </td>
                                    <td class="text-xs text-[var(--muted)]">{{ $contract->periodLabel() }}</td>
                                    <td class="text-xs font-mono text-[var(--muted2)]">
                                        {{ $contract->fee_value ? 'R$ ' . number_format($contract->fee_value, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('clients.contracts.show', [$client, $contract]) }}"
                                           class="text-xs font-mono hover:underline"
                                           style="color:var(--purple)">
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB: DOSSIÊ DE MARCA --}}
        <div x-show="tab === 'dossies'" x-cloak>

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Dossiê de Marca
                </h3>
                <form method="POST" action="{{ route('clients.dossiers.store', $client) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background: var(--purple);">
                        + Novo Dossiê
                    </button>
                </form>
            </div>

            @if($client->dossiers->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">📋</div>
                    <div class="tab-placeholder-title">Nenhum dossiê criado</div>
                    <div class="tab-placeholder-desc">Clique em "Novo Dossiê" para iniciar o processo de Etapa 01 — Dossiê de Marca.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th style="width:50px">Vers.</th>
                                <th>Título</th>
                                <th>Fase</th>
                                <th>Kickoff</th>
                                <th>Criado em</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->dossiers as $dossier)
                                <tr>
                                    <td class="font-mono text-xs text-[var(--muted)]">v{{ $dossier->version }}</td>
                                    <td class="font-semibold text-[var(--text)]">{{ $dossier->title }}</td>
                                    <td>
                                        <span class="text-xs font-mono px-2 py-0.5"
                                              style="background:{{ $dossier->faseColor() }}22; color:{{ $dossier->faseColor() }}; border:1px solid {{ $dossier->faseColor() }}44">
                                            {{ $dossier->faseLabel() }}
                                        </span>
                                    </td>
                                    <td class="text-xs text-[var(--muted)]">
                                        {{ $dossier->data_kickoff?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="text-xs text-[var(--muted)]">
                                        {{ $dossier->created_at->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('clients.dossiers.show', [$client, $dossier]) }}"
                                           class="text-xs font-mono hover:underline"
                                           style="color:var(--purple)">
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB: REUNIÕES --}}
        <div x-show="tab === 'reunioes'" x-cloak>
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                    {{ $client->meetings->count() }} reunião{{ $client->meetings->count() !== 1 ? 'ões' : '' }}
                </p>
                <a href="{{ route('meetings.create', ['client_id' => $client->id]) }}"
                   class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                   style="background:var(--purple)">
                    + Nova Reunião
                </a>
            </div>

            @if($client->meetings->isEmpty())
                <div class="px-6 py-12 text-center" style="color:var(--muted)">
                    <p class="text-sm mb-2">Nenhuma reunião registrada ainda.</p>
                    <a href="{{ route('meetings.create', ['client_id' => $client->id]) }}"
                       class="text-sm font-semibold" style="color:var(--purple)">
                        Agendar primeira reunião →
                    </a>
                </div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($client->meetings as $meeting)
                        <div class="card px-5 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-bold text-sm" style="color:var(--text)">{{ $meeting->title }}</span>
                                        <span class="badge badge-{{ $meeting->statusColor() }}">{{ $meeting->statusLabel() }}</span>
                                        @if($meeting->hasAta())
                                            <span class="badge badge-purple">📝 Ata registrada</span>
                                        @endif
                                    </div>
                                    <p class="text-xs font-mono mb-2" style="color:var(--muted)">
                                        {{ $meeting->typeLabel() }} · {{ $meeting->scheduled_at->format('d/m/Y \à\s H:i') }}
                                    </p>
                                    @if($meeting->hasAta())
                                        <p class="text-xs" style="color:var(--muted2)">{{ \Illuminate\Support\Str::limit($meeting->ata, 140) }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('meetings.show', $meeting) }}" class="btn btn-ghost btn-xs flex-shrink-0">
                                    Ver →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: BRIEFING --}}
        <div x-show="tab === 'briefing'" x-cloak x-data="{ editingBriefing: false }">
            <div class="card card-body-lg">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Briefing do Cliente</p>
                    <button type="button" @click="editingBriefing = !editingBriefing" class="text-xs font-semibold" style="color:var(--purple)">
                        <span x-text="editingBriefing ? 'Cancelar' : (@js((bool) $client->briefing) ? 'Editar' : '+ Adicionar')"></span>
                    </button>
                </div>
                <p class="text-xs mb-4" style="color:var(--muted2)">Panorama corrido do cliente — quem é, posicionamento, síntese estratégica. Base de conhecimento pra equipe toda, evolui com o tempo.</p>

                <div x-show="!editingBriefing">
                    @if($client->briefing)
                        <div class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.75">{{ $client->briefing }}</div>
                    @else
                        <p class="text-sm" style="color:var(--muted)">Nenhum briefing registrado ainda.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('clients.update-briefing', $client) }}" x-show="editingBriefing" x-cloak>
                    @csrf @method('PATCH')
                    <textarea name="briefing" rows="10"
                        placeholder="Quem é o cliente, posicionamento, personas, concorrentes, síntese estratégica..."
                        class="w-full px-4 py-3 text-sm focus:outline-none resize-none leading-relaxed"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $client->briefing }}</textarea>
                    <button type="submit" class="mt-3 px-4 py-2 text-sm font-semibold text-white" style="background:var(--purple)">
                        Salvar Briefing
                    </button>
                </form>
            </div>
        </div>

        {{-- TAB: PLANEJAMENTOS --}}
        <div x-show="tab === 'planejamentos'" x-cloak>
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                    {{ $client->macroplans->count() }} macroplanejamento{{ $client->macroplans->count() !== 1 ? 's' : '' }}
                </p>
                <a href="{{ route('macroplans.create', ['client_id' => $client->id]) }}"
                   class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                   style="background:var(--purple)">
                    + Novo Planejamento
                </a>
            </div>

            @if($client->macroplans->isEmpty())
                <div class="px-6 py-12 text-center" style="color:var(--muted)">
                    <p class="text-sm mb-2">Nenhum macroplanejamento criado ainda.</p>
                    <a href="{{ route('macroplans.create', ['client_id' => $client->id]) }}"
                       class="text-sm font-semibold" style="color:var(--purple)">
                        Criar primeiro ciclo →
                    </a>
                </div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($client->macroplans as $plan)
                        <div class="card px-5 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-bold text-sm" style="color:var(--text)">{{ $plan->title }}</span>
                                        <span class="badge badge-{{ $plan->statusColor() }}">{{ $plan->statusLabel() }}</span>
                                        @if($plan->isLaunched())
                                            <span class="badge badge-green">ClickUp</span>
                                        @endif
                                    </div>
                                    <p class="text-xs font-mono mb-2" style="color:var(--muted)">{{ $plan->periodLabel() }}</p>
                                    @if($plan->projects->count() > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($plan->projects as $proj)
                                                <span class="badge">{{ $proj->title }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs" style="color:var(--muted)">Sem projetos</span>
                                    @endif
                                </div>
                                <a href="{{ route('macroplans.edit', $plan) }}" class="btn btn-ghost btn-xs flex-shrink-0">
                                    Editar →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: ATENDIMENTO --}}
        <div x-show="tab === 'atendimento'" x-cloak x-data="{ addForm: false, editId: null }">

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Números de Atendimento
                </h3>
                <button @click="addForm = !addForm"
                        class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background: var(--purple);">
                    + Adicionar
                </button>
            </div>

            <p class="text-xs text-[var(--muted2)] mb-4 max-w-2xl">
                Números de WhatsApp (via uazapi) alocados a este cliente para o Atendimento Assistido.
                Só mensagens de números cadastrados aqui são aceitas pelo webhook de ingestão.
            </p>

            {{-- Formulário de novo número --}}
            <div x-show="addForm" x-cloak class="card p-5 mb-6">
                <h4 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Novo Número de Atendimento</h4>
                <form method="POST" action="{{ route('clients.integrations.store', $client) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Origem <span class="text-[var(--orange)]">*</span>
                            </label>
                            <select name="provider" required
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="uazapi">WhatsApp (uazapi)</option>
                                <option value="partner_crm" disabled>CRM Parceiro (em breve)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Nome <span class="text-[var(--orange)]">*</span>
                            </label>
                            <input type="text" name="label" required placeholder="Ex: Atendimento Fratte"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Token da instância (uazapi) <span class="text-[var(--orange)]">*</span>
                            </label>
                            <input type="text" name="external_id" required autocomplete="off"
                                   placeholder="Instance Token (painel uazapi → Dados da instância)"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Servidor uazapi (Base URL)
                            </label>
                            <input type="text" name="base_url" placeholder="https://nonna.uazapi.com"
                                   value="https://nonna.uazapi.com"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] font-mono">
                            <p class="text-xs mt-1" style="color:var(--muted)">Necessário pra baixar áudio/imagem recebidos.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Cadência do diagnóstico
                            </label>
                            <select name="diagnostic_frequency_days"
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="7">A cada 7 dias</option>
                                <option value="15">A cada 15 dias</option>
                                <option value="30" selected>A cada 30 dias</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Agente de IA
                            </label>
                            <select name="ai_agent_id"
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">Sem agente definido ainda</option>
                                @foreach($aiAgents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Ticket médio (R$)
                            </label>
                            <input type="number" name="avg_ticket_value" min="0" step="0.01" placeholder="Ex: 1200.00"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            <p class="text-xs mt-1" style="color:var(--muted)">Base pra estimar o valor de vendas perdidas nos gaps do diagnóstico.</p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Status</label>
                            <select name="status"
                                    class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                @foreach(\App\Models\ClientIntegration::$statuses as $key => $meta)
                                    <option value="{{ $key }}" {{ $key === 'connected' ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background: var(--purple);">
                            Salvar
                        </button>
                        <button type="button" @click="addForm = false"
                                class="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            {{-- Lista de números --}}
            @if($client->integrations->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon">📱</div>
                    <div class="tab-placeholder-title">Nenhum número de atendimento cadastrado</div>
                    <div class="tab-placeholder-desc">Conecte um número de WhatsApp (uazapi) para começar a alimentar o Atendimento Assistido deste cliente.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Origem</th>
                                <th>Agente de IA</th>
                                <th>Cadência</th>
                                <th>Status</th>
                                <th>Última sincronização</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($client->integrations as $integration)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">{{ $integration->label }}</td>
                                    <td class="text-sm text-[var(--muted2)]">{{ $integration->providerLabel() }}</td>
                                    <td class="text-sm text-[var(--muted2)]">
                                        {{ $aiAgents->firstWhere('id', $integration->settings['ai_agent_id'] ?? null)?->name ?? '—' }}
                                    </td>
                                    <td class="text-sm font-mono text-[var(--muted2)]">
                                        {{ $integration->diagnosticFrequencyDays() }} dias
                                    </td>
                                    <td class="text-xs font-mono uppercase" style="color: var(--{{ $integration->status === 'connected' ? 'green' : ($integration->status === 'error' ? 'red' : 'muted') }})">
                                        {{ $integration->statusLabel() }}
                                    </td>
                                    <td class="text-xs font-mono text-[var(--muted)]">
                                        {{ $integration->last_synced_at?->diffForHumans() ?? 'nunca' }}
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button type="button" @click="editId = editId === '{{ $integration->id }}' ? null : '{{ $integration->id }}'"
                                                class="btn btn-ghost btn-xs mr-3">
                                            <span x-text="editId === '{{ $integration->id }}' ? 'Fechar' : 'Editar'"></span>
                                        </button>
                                        <form method="POST" class="inline"
                                              action="{{ route('clients.integrations.destroy', [$client, $integration]) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Remover este número de atendimento? Conversas e diagnósticos já gerados não são apagados.')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                Remover
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <tr x-show="editId === '{{ $integration->id }}'" x-cloak>
                                    <td colspan="7" style="background:var(--s2)">
                                        <form method="POST" action="{{ route('clients.integrations.update', [$client, $integration]) }}" class="space-y-4 p-4">
                                            @csrf
                                            @method('PATCH')
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Origem</label>
                                                    <select name="provider" required
                                                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                        <option value="uazapi" {{ $integration->provider === 'uazapi' ? 'selected' : '' }}>WhatsApp (uazapi)</option>
                                                        <option value="partner_crm" disabled>CRM Parceiro (em breve)</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Nome</label>
                                                    <input type="text" name="label" required value="{{ $integration->label }}"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Token da instância (uazapi)</label>
                                                    <input type="text" name="external_id" autocomplete="off"
                                                           placeholder="Deixe em branco para manter o token atual"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] font-mono">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Servidor uazapi (Base URL)</label>
                                                    <input type="text" name="base_url" placeholder="https://nonna.uazapi.com"
                                                           value="{{ $integration->baseUrl() }}"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] font-mono">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Cadência do diagnóstico</label>
                                                    <select name="diagnostic_frequency_days"
                                                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                        <option value="7" {{ $integration->diagnosticFrequencyDays() === 7 ? 'selected' : '' }}>A cada 7 dias</option>
                                                        <option value="15" {{ $integration->diagnosticFrequencyDays() === 15 ? 'selected' : '' }}>A cada 15 dias</option>
                                                        <option value="30" {{ $integration->diagnosticFrequencyDays() === 30 ? 'selected' : '' }}>A cada 30 dias</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Agente de IA</label>
                                                    <select name="ai_agent_id"
                                                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                        <option value="">Sem agente definido ainda</option>
                                                        @foreach($aiAgents as $agent)
                                                            <option value="{{ $agent->id }}" {{ ($integration->settings['ai_agent_id'] ?? null) === $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Ticket médio (R$)</label>
                                                    <input type="number" name="avg_ticket_value" min="0" step="0.01" value="{{ $integration->avgTicketValue() }}"
                                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Status</label>
                                                    <select name="status"
                                                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                                        @foreach(\App\Models\ClientIntegration::$statuses as $key => $meta)
                                                            <option value="{{ $key }}" {{ $integration->status === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="flex gap-3">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Salvar alterações
                                                </button>
                                                <button type="button" @click="editId = null" class="btn btn-ghost btn-sm">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ══ TAB PORTAL ══ --}}
        @php
            $portalContacts = $client->contacts()->wherePivot('portal_access_enabled', true)->orderBy('name')->get();
            $availableContacts = $client->contacts()->wherePivot('portal_access_enabled', false)->orderBy('name')->get();
        @endphp
        <div x-show="tab === 'portal'" x-cloak>
            <div class="max-w-lg">
                <h2 class="text-sm font-bold mb-1" style="color: var(--text)">Acesso ao Portal do Cliente</h2>
                <p class="text-xs mb-5" style="color: var(--muted)">
                    Habilite o acesso pra um Contato já vinculado a este cliente — o login usa o e-mail do próprio contato.
                </p>

                @if($portalContacts->isNotEmpty())
                    <div class="space-y-3 mb-6">
                        @foreach($portalContacts as $portalContact)
                            <div class="card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--text)">{{ $portalContact->name }}</p>
                                        <p class="text-xs" style="color: var(--muted)">{{ $portalContact->email }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('portal.login') }}" target="_blank"
                                           class="text-xs font-semibold px-3 py-2 rounded-lg"
                                           style="background: rgba(106,90,205,.1); color: var(--purple)">
                                            Ver portal →
                                        </a>
                                        <form method="POST" action="{{ route('clients.portal-access.destroy', [$client, $portalContact]) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Remover acesso de {{ addslashes($portalContact->name) }}?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                Remover
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Novo acesso --}}
                @if($availableContacts->isNotEmpty())
                    <form method="POST" action="{{ route('clients.portal-access.store', $client) }}"
                          class="card p-5 space-y-4">
                        @csrf
                        <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--muted)">Novo acesso</p>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                                Contato
                            </label>
                            <select name="contact_id" required
                                    class="w-full rounded-lg border px-3 py-2 text-sm"
                                    style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($availableContacts as $availableContact)
                                    <option value="{{ $availableContact->id }}" @selected(old('contact_id') === $availableContact->id)>
                                        {{ $availableContact->name }}{{ $availableContact->email ? " ({$availableContact->email})" : ' — sem e-mail cadastrado' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('contact_id')
                                <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                                Senha inicial
                            </label>
                            <input type="text" name="password"
                                   placeholder="Mínimo 8 caracteres — deixe em branco se o contato já tem senha (de outro cliente)"
                                   class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                   style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                            @error('password')
                                <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold">
                            Habilitar Acesso
                        </button>
                    </form>
                @else
                    <div class="card p-5 text-center">
                        <p class="text-sm" style="color: var(--muted)">
                            Nenhum contato disponível pra habilitar acesso.
                        </p>
                        <p class="text-xs mt-1" style="color: var(--muted)">
                            Cadastre e vincule um contato na aba Contatos primeiro.
                        </p>
                    </div>
                @endif
            </div>
        </div>

    </div>{{-- /x-data tabs --}}

</x-app-layout>
