<x-app-layout>
    <x-slot name="header">{{ $client->company_name }}</x-slot>

    {{-- BREADCRUMB + AÇÕES --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('clients.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Clientes</a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">{{ $client->company_name }}</span>
        </div>
        <div class="flex items-center gap-2">
            @php $color = $client->statusColor() @endphp
            <span class="badge badge-{{ $color }}">{{ $client->statusLabel() }}</span>
            <a href="{{ route('clients.edit', $client) }}"
               class="px-4 py-2 text-xs font-bold transition-colors"
               style="border:1px solid var(--border2); color:var(--muted2)"
               onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
               onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
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
            <button class="tab-btn" :class="{ active: tab === 'contas' }" @click="tab = 'contas'">
                Contas de Anúncios
            </button>
            <button class="tab-btn" :class="{ active: tab === 'diagnosticos' }" @click="tab = 'diagnosticos'">
                Diagnósticos
            </button>
            <button class="tab-btn" :class="{ active: tab === 'atas' }" @click="tab = 'atas'">
                Atas
            </button>
            <button class="tab-btn" :class="{ active: tab === 'briefing' }" @click="tab = 'briefing'">
                Briefing
            </button>
            <button class="tab-btn" :class="{ active: tab === 'planejamentos' }" @click="tab = 'planejamentos'">
                Planejamentos
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
                                class="px-4 py-2 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] transition-colors">
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
                                        <form method="POST"
                                              action="{{ route('clients.credentials.destroy', [$client, $cred]) }}"
                                              onsubmit="return confirm('Remover esta credencial?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-xs font-mono text-[var(--muted)] hover:text-[var(--red)] transition-colors">
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
        <div x-show="tab === 'contatos'" x-cloak>
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon">👥</div>
                <p class="tab-placeholder-title">Contatos</p>
                <p class="tab-placeholder-desc">Cadastro de pessoas físicas vinculadas a este cliente — decisores, gestores, financeiro. Em breve.</p>
            </div>
        </div>

        {{-- TAB: CONTAS DE ANÚNCIOS --}}
        <div x-show="tab === 'contas'" x-cloak>
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon">📡</div>
                <p class="tab-placeholder-title">Contas de Anúncios</p>
                <p class="tab-placeholder-desc">IDs de contas Meta Ads, Google Ads e outras plataformas. Consultadas pelo n8n para sincronização de métricas. Em breve.</p>
            </div>
        </div>

        {{-- TAB: DIAGNÓSTICOS --}}
        <div x-show="tab === 'diagnosticos'" x-cloak>
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon">🔎</div>
                <p class="tab-placeholder-title">Diagnósticos Estratégicos</p>
                <p class="tab-placeholder-desc">Imersão completa: briefing de negócio, cenário de marketing, auditoria de comunicação, análise de concorrência, personas e síntese estratégica. Em breve.</p>
            </div>
        </div>

        {{-- TAB: ATAS --}}
        <div x-show="tab === 'atas'" x-cloak>
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon">📝</div>
                <p class="tab-placeholder-title">Atas de Reuniões</p>
                <p class="tab-placeholder-desc">Histórico de reuniões do cliente. Cole o texto corrido e a IA estrutura a ata automaticamente. Em breve.</p>
            </div>
        </div>

        {{-- TAB: BRIEFING --}}
        <div x-show="tab === 'briefing'" x-cloak>
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon">💡</div>
                <p class="tab-placeholder-title">Briefing do Cliente</p>
                <p class="tab-placeholder-desc">Panorama consolidado: quem é o cliente, seu posicionamento, personas, concorrentes e síntese estratégica — base de conhecimento para toda a equipe. Em breve.</p>
            </div>
        </div>

        {{-- TAB: PLANEJAMENTOS --}}
        <div x-show="tab === 'planejamentos'" x-cloak>
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon">🗺️</div>
                <p class="tab-placeholder-title">Macroplanejamentos</p>
                <p class="tab-placeholder-desc">Ciclos estratégicos de 60–90 dias → Projetos multidisciplinares → Tarefas lançadas no ClickUp. Em breve.</p>
            </div>
        </div>

    </div>{{-- /x-data tabs --}}

</x-app-layout>
