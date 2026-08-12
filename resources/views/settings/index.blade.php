<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">Configurações da Organização</h1>
    </x-slot>

    <div x-data="{ tab: '{{ session('tab', 'geral') }}' }">

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-4 rounded-lg px-4 py-3 text-sm font-semibold"
                 style="background:rgba(34,197,94,.1); color:var(--green); border:1px solid rgba(34,197,94,.2)">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-lg px-4 py-3 text-sm font-semibold"
                 style="background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.2)">
                <p class="mb-1">Não foi possível salvar — corrija e tente novamente:</p>
                <ul class="list-disc pl-4 font-normal">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Tabs --}}
        <div class="flex gap-1 mb-6 border-b" style="border-color:var(--border)">
            @foreach(['geral' => 'Geral', 'integracoes' => 'Integrações', 'equipe' => 'Equipe', 'setores' => 'Setores', 'papeis' => 'Papéis Funcionais', 'mensagens' => 'Mensagens Padrão', 'api' => 'API & Tokens'] as $key => $label)
                <button @click="tab = '{{ $key }}'"
                        class="tab-btn px-4 py-2.5 text-sm font-semibold transition-colors"
                        :class="tab === '{{ $key }}'
                            ? 'border-b-2 -mb-px'
                            : 'opacity-60 hover:opacity-100'"
                        :style="tab === '{{ $key }}'
                            ? 'color:var(--purple); border-color:var(--purple)'
                            : 'color:var(--muted)'">
                    {{ $label }}
                    @if($key === 'integracoes')
                        <span class="ml-1 text-xs px-1.5 py-px rounded-full"
                              style="background:var(--s3); color:var(--muted)">
                            {{ $integrations->count() }}
                        </span>
                    @elseif($key === 'equipe')
                        <span class="ml-1 text-xs px-1.5 py-px rounded-full"
                              style="background:var(--s3); color:var(--muted)">
                            {{ $members->count() }}
                        </span>
                    @elseif($key === 'setores')
                        <span class="ml-1 text-xs px-1.5 py-px rounded-full"
                              style="background:var(--s3); color:var(--muted)">
                            {{ $sectors->count() }}
                        </span>
                    @elseif($key === 'papeis')
                        <span class="ml-1 text-xs px-1.5 py-px rounded-full"
                              style="background:var(--s3); color:var(--muted)">
                            {{ $functionalRoles->count() }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ══ TAB GERAL ══ --}}
        <div x-show="tab === 'geral'" x-cloak>
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-5 max-w-2xl">
                @csrf
                @method('PATCH')

                {{-- Identidade --}}
                <div class="card p-6">
                    <h2 class="text-sm font-bold mb-4" style="color:var(--text)">Identidade</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                Nome da Organização
                            </label>
                            <input type="text" name="name"
                                   value="{{ old('name', $org->name) }}"
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)"
                                   required>
                            @error('name')
                                <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                Slug (identificador na URL)
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="text" value="{{ $org->slug }}" disabled
                                       class="flex-1 rounded-lg border px-3 py-2 text-sm opacity-50 cursor-not-allowed"
                                       style="background:var(--s3); border-color:var(--border2); color:var(--muted)">
                                <span class="text-xs" style="color:var(--muted)">
                                    {{ $org->slug }}.{{ config('app.domain') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Plano</label>
                                <span class="badge badge-purple text-xs">
                                    {{ \App\Models\Organization::$plans[$org->plan] ?? $org->plan }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Status</label>
                                <span class="badge {{ $org->status === 'active' ? 'badge-green' : 'badge-muted' }} text-xs">
                                    {{ \App\Models\Organization::$statuses[$org->status] ?? $org->status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Branding --}}
                <div class="card p-6">
                    <h2 class="text-sm font-bold mb-1" style="color:var(--text)">Branding</h2>
                    <p class="text-xs mb-4" style="color:var(--muted)">
                        Aparência aplicada a todos os usuários desta organização.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                URL do Logotipo
                            </label>
                            <input type="url" name="logo_url"
                                   value="{{ old('logo_url', $org->logoUrl()) }}"
                                   placeholder="https://..."
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                            @error('logo_url')
                                <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                    Cor Principal
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="primary_color"
                                           value="{{ old('primary_color', $org->brandColor('primary_color')) }}"
                                           class="h-9 w-14 rounded cursor-pointer border p-0.5"
                                           style="border-color:var(--border2); background:var(--s2)">
                                    <input type="text" x-ref="primaryColorText"
                                           value="{{ old('primary_color', $org->brandColor('primary_color')) }}"
                                           class="flex-1 rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)"
                                           readonly>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                    Cor Secundária
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="secondary_color"
                                           value="{{ old('secondary_color', $org->brandColor('secondary_color')) }}"
                                           class="h-9 w-14 rounded cursor-pointer border p-0.5"
                                           style="border-color:var(--border2); background:var(--s2)">
                                    <input type="text"
                                           value="{{ old('secondary_color', $org->brandColor('secondary_color')) }}"
                                           class="flex-1 rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)"
                                           readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-6">
                    <h2 class="text-sm font-bold mb-1" style="color:var(--text)">Inteligência de Campanhas</h2>
                    <p class="text-xs mb-4" style="color:var(--muted)">
                        Agente de IA usado para gerar a narrativa dos insights automáticos de campanha (orçamento, CPA, ROAS). Sem um agente configurado, os insights são criados só com os números, sem recomendação em texto.
                    </p>

                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                            Agente de IA
                        </label>
                        <select name="campaign_insights_agent_id"
                                class="w-full rounded-lg border px-3 py-2 text-sm"
                                style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                            <option value="">Nenhum (sem narrativa de IA)</option>
                            @foreach($aiAgents as $agent)
                                <option value="{{ $agent->id }}"
                                    {{ old('campaign_insights_agent_id', data_get($org->settings, 'campaign_insights.agent_id')) === $agent->id ? 'selected' : '' }}>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('campaign_insights_agent_id')
                            <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="btn-primary px-5 py-2 text-sm rounded-lg font-semibold">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>

        {{-- ══ TAB INTEGRAÇÕES ══ --}}
        <div x-show="tab === 'integracoes'" x-cloak
             x-data="{
                modal: false,
                editing: null,
                emptyCredentials: { access_token: '', refresh_token: '', developer_token: '', customer_id: '', login_customer_id: '', client_id: '', client_secret: '', webhook_url: '' },
                form: { provider: '', label: '', external_id: '', status: 'pending', credentials: {} },
                open(integration) {
                    if (integration) {
                        this.editing = integration;
                        this.form = {
                            provider:    integration.provider,
                            label:       integration.label,
                            external_id: integration.external_id ?? '',
                            status:      integration.status,
                            credentials: { ...this.emptyCredentials },
                        };
                    } else {
                        this.editing = null;
                        this.form = { provider: '', label: '', external_id: '', status: 'pending', credentials: { ...this.emptyCredentials } };
                    }
                    this.modal = true;
                },
                close() { this.modal = false; this.editing = null; }
             }">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-bold" style="color:var(--text)">Plataformas e Ferramentas</h2>
                    <p class="text-xs mt-0.5" style="color:var(--muted)">
                        Conexões da organização com plataformas externas.
                    </p>
                </div>
                <button @click="open(null)"
                        class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold flex items-center gap-2">
                    <x-icon name="plus" size="16" />
                    Nova Integração
                </button>
            </div>

            @if($integrations->isEmpty())
                <div class="card p-10 text-center">
                    <x-icon name="link-2-off" size="40" stroke="1" class="mx-auto mb-3" style="color:var(--border2)" />
                    <p class="text-sm font-semibold" style="color:var(--muted)">Nenhuma integração cadastrada</p>
                    <p class="text-xs mt-1" style="color:var(--muted)">
                        Adicione as contas da agência — Meta BM, Google MCC, ClickUp, n8n e mais.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($integrations as $integration)
                        @php
                            $providerInfo = \App\Models\OrganizationIntegration::$providers[$integration->provider] ?? null;
                            $statusInfo   = \App\Models\OrganizationIntegration::$statuses[$integration->status]  ?? null;
                            $statusColors = [
                                'connected'    => ['dot' => 'var(--green)',  'text' => 'var(--green)'],
                                'pending'      => ['dot' => 'var(--muted)',  'text' => 'var(--muted)'],
                                'disconnected' => ['dot' => 'var(--orange)', 'text' => 'var(--orange)'],
                                'error'        => ['dot' => 'var(--red)',    'text' => 'var(--red)'],
                            ];
                            $colors = $statusColors[$integration->status] ?? $statusColors['pending'];
                        @endphp
                        <div class="card p-5 flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--muted)">
                                        {{ $providerInfo['label'] ?? $integration->provider }}
                                    </p>
                                    <p class="text-sm font-semibold mt-0.5 truncate" style="color:var(--text)">
                                        {{ $integration->label }}
                                    </p>
                                </div>
                                <span class="flex items-center gap-1.5 text-xs font-semibold flex-shrink-0">
                                    <span class="h-1.5 w-1.5 rounded-full flex-shrink-0"
                                          style="background:{{ $colors['dot'] }}"></span>
                                    <span style="color:{{ $colors['text'] }}">
                                        {{ $statusInfo['label'] ?? $integration->status }}
                                    </span>
                                </span>
                            </div>

                            @if($integration->external_id)
                                <div class="rounded-md px-3 py-2 text-xs font-mono truncate"
                                     style="background:var(--s3); color:var(--muted)">
                                    ID: {{ $integration->external_id }}
                                </div>
                            @endif

                            <p class="text-xs flex items-center gap-1.5" style="color:{{ $integration->hasCredentials() ? 'var(--green)' : 'var(--muted)' }}">
                                <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:currentColor"></span>
                                {{ $integration->hasCredentials() ? 'Credenciais configuradas' : 'Sem credenciais salvas' }}
                            </p>

                            @if($integration->provider === 'google')
                                <a href="{{ route('settings.integrations.google.connect', $integration) }}"
                                   class="block text-center text-xs font-semibold py-1.5 rounded-lg"
                                   style="background:rgba(100, 59, 142,.1); color:var(--purple)">
                                    Conectar com Google Ads
                                </a>
                            @endif

                            <div class="flex items-center gap-2 pt-1 border-t" style="border-color:var(--border2)">
                                <button type="button" @click="open({{ $integration->toJson() }})" class="btn btn-ghost btn-xs">
                                    Editar
                                </button>
                                {{-- credentials nunca vem no toJson() (hidden no model) - segredo nunca chega ao browser --}}
                                <form method="POST"
                                      action="{{ route('settings.integrations.destroy', $integration) }}"
                                      @submit.prevent="if (await $store.confirmDialog.ask('Remover esta integração?')) $el.submit()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        Remover
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Modal Nova / Editar Integração --}}
            <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <div class="absolute inset-0 bg-black/60" @click="close()"></div>
                <div class="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                     style="background:var(--s1); border:1px solid var(--border)"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">

                    <h3 class="text-base font-bold mb-5" style="color:var(--text)"
                        x-text="editing ? 'Editar Integração' : 'Nova Integração'"></h3>

                    <form :action="editing
                              ? '{{ url('configuracoes/integracoes') }}/' + editing.id
                              : '{{ route('settings.integrations.store') }}'"
                          method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_method" value="PATCH" :disabled="!editing">

                        {{-- Provider --}}
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                Plataforma / Ferramenta
                            </label>
                            <select name="provider" x-model="form.provider" required
                                    class="w-full rounded-lg border px-3 py-2 text-sm"
                                    style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                <option value="">Selecione...</option>
                                @php
                                    $grouped = collect(\App\Models\OrganizationIntegration::$providers)
                                        ->groupBy(fn($p) => $p['category'], true);
                                    $categoryLabels = [
                                        'ads'          => 'Mídia Paga',
                                        'automation'   => 'Automação',
                                        'communication'=> 'Comunicação',
                                        'analytics'    => 'Analytics',
                                    ];
                                @endphp
                                @foreach($grouped as $category => $providers)
                                    <optgroup label="{{ $categoryLabels[$category] ?? $category }}">
                                        @foreach($providers as $key => $info)
                                            <option value="{{ $key }}">{{ $info['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        {{-- Label --}}
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                Nome / Rótulo
                                <span class="font-normal">(ex: BM Principal, Google MCC Nonna)</span>
                            </label>
                            <input type="text" name="label" x-model="form.label" required
                                   placeholder="Ex: BM Principal"
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        {{-- External ID --}}
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                ID da conta na plataforma
                                <span class="font-normal">(BM ID, MCC ID, Team ID...)</span>
                            </label>
                            <input type="text" name="external_id" x-model="form.external_id"
                                   placeholder="Ex: 123456789"
                                   class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                Status da conexão
                            </label>
                            <select name="status" x-model="form.status" required
                                    class="w-full rounded-lg border px-3 py-2 text-sm"
                                    style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                @foreach(\App\Models\OrganizationIntegration::$statuses as $key => $info)
                                    <option value="{{ $key }}">{{ $info['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Credenciais --}}
                        <div class="pt-2 border-t" style="border-color:var(--border2)">
                            <p class="text-xs font-semibold mb-1.5" style="color:var(--muted)">Credenciais</p>
                            <p class="text-xs mb-3" style="color:var(--muted)" x-show="editing">
                                Deixe em branco para manter o valor já salvo. Os campos nunca são pré-preenchidos por segurança.
                            </p>

                            {{-- Meta / TikTok / LinkedIn / Pinterest: token único --}}
                            <div x-show="['meta','tiktok','linkedin','pinterest'].includes(form.provider)" class="mb-3">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Access Token</label>
                                <input type="password" name="credentials[access_token]" x-model="form.credentials.access_token"
                                       placeholder="{{ '' }}" autocomplete="off"
                                       class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                       style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                            </div>

                            {{-- Google Ads --}}
                            <div x-show="form.provider === 'google'" class="space-y-3 mb-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Developer Token</label>
                                    <input type="password" name="credentials[developer_token]" x-model="form.credentials.developer_token" autocomplete="off"
                                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Customer ID</label>
                                    <p class="text-xs mb-1.5" style="color:var(--muted)">
                                        Só preencha se a agência acessa o Google Ads <strong>direto, sem conta MCC</strong>. Se usa MCC (o caso mais comum), deixe em branco — a conta de cada cliente já vem do cadastro de "Contas de Anúncios".
                                    </p>
                                    <input type="text" name="credentials[customer_id]" x-model="form.credentials.customer_id"
                                           placeholder="Ex: 123-456-7890"
                                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Login Customer ID (MCC)</label>
                                    <p class="text-xs mb-1.5" style="color:var(--muted)">
                                        O ID da conta MCC (gerenciadora) da agência. Usado em toda chamada, pra acessar a conta de cada cliente "em nome" dela.
                                    </p>
                                    <input type="text" name="credentials[login_customer_id]" x-model="form.credentials.login_customer_id"
                                           placeholder="Ex: 123-456-7890"
                                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">OAuth Client ID</label>
                                    <input type="text" name="credentials[client_id]" x-model="form.credentials.client_id"
                                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">OAuth Client Secret</label>
                                    <input type="password" name="credentials[client_secret]" x-model="form.credentials.client_secret" autocomplete="off"
                                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                </div>
                                <p class="text-xs" style="color:var(--muted)">
                                    Depois de salvar com Client ID/Secret preenchidos, use o botão "Conectar com Google Ads" no card da integração — o App cuida de gerar e renovar o token de acesso sozinho.
                                </p>
                            </div>

                            {{-- n8n: URL do webhook (não é OAuth) --}}
                            <div x-show="form.provider === 'n8n'" class="mb-3">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">URL do Webhook</label>
                                <p class="text-xs mb-1.5" style="color:var(--muted)">
                                    URL do node "Webhook" do fluxo no n8n que recebe as notificações padrão (ver Configurações > Mensagens Padrão). Status precisa estar "Conectado" pra disparar.
                                </p>
                                <input type="text" name="credentials[webhook_url]" x-model="form.credentials.webhook_url" autocomplete="off"
                                       placeholder="https://seu-n8n.com/webhook/..."
                                       class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                       style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                            </div>

                            {{-- Demais providers: token genérico --}}
                            <div x-show="form.provider && !['meta','tiktok','linkedin','pinterest','google','n8n'].includes(form.provider)" class="mb-3">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Access Token / API Key</label>
                                <input type="password" name="credentials[access_token]" x-model="form.credentials.access_token" autocomplete="off"
                                       class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                                       style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors"
                                    style="color:var(--muted); background:var(--s3)">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold"
                                    x-text="editing ? 'Salvar Alterações' : 'Adicionar'">
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══ TAB EQUIPE ══ --}}
        <div x-show="tab === 'equipe'" x-cloak
             x-data="{
                modal: false,
                editing: null,
                form: { name: '', email: '', password: '', role: 'member', function_roles: [], avatar_url: null, remove_avatar: false },
                open(member) {
                    if (member) {
                        this.editing = member;
                        this.form = { name: member.name, email: member.email, password: '', role: member.role, function_roles: member.function_roles || [], avatar_url: member.avatar_url || null, remove_avatar: false };
                    } else {
                        this.editing = null;
                        this.form = { name: '', email: '', password: '', role: 'member', function_roles: [], avatar_url: null, remove_avatar: false };
                    }
                    this.modal = true;
                },
                close() { this.modal = false; this.editing = null; }
             }">

            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-bold" style="color:var(--text)">Membros da Organização</h2>
                    <p class="text-xs mt-0.5" style="color:var(--muted)">
                        Usuários com acesso a esta organização.
                    </p>
                </div>
                <button @click="open(null)"
                        class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold flex items-center gap-2">
                    <x-icon name="plus" size="16" />
                    Novo Membro
                </button>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border); background:var(--s3)">
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Usuário</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">E-mail</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Papel</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            @php
                                $role      = $member->pivot->role;
                                $isOwner   = $role === 'owner';
                                $isAdmin   = $role === 'admin';
                                $roleLabel = \App\Models\OrganizationUser::$roles[$role] ?? $role;
                                $isSelf    = $member->id === auth()->id();
                                $isOrgOwner = $org->owner_user_id === $member->id;
                            @endphp
                            <tr style="border-bottom:1px solid var(--border2)">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <x-user-avatar :user="$member" size="7" />
                                        <div>
                                            <span class="font-semibold" style="color:var(--text)">{{ $member->name }}</span>
                                            @if($isSelf)
                                                <span class="text-xs ml-1" style="color:var(--muted)">(você)</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-xs" style="color:var(--muted)">{{ $member->email }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="text-xs font-semibold px-2 py-1 rounded-full"
                                          style="background:{{ $isOwner ? 'rgba(100, 59, 142,.15)' : ($isAdmin ? 'rgba(238, 121, 25,.1)' : 'var(--s3)') }};
                                                 color:{{ $isOwner ? 'var(--purple)' : ($isAdmin ? 'var(--orange)' : 'var(--muted)') }};
                                                 border:1px solid {{ $isOwner ? 'rgba(100, 59, 142,.2)' : ($isAdmin ? 'rgba(238, 121, 25,.2)' : 'var(--border2)') }}">
                                        {{ $roleLabel }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if(!$isOrgOwner)
                                        <div class="flex items-center gap-2 justify-end">
                                            <button type="button"
                                                    @click="open({{ json_encode(['id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'role' => $role, 'function_roles' => $member->functionalRoles->pluck('key')->all(), 'avatar_url' => $member->avatarUrl()]) }})"
                                                    class="btn btn-ghost btn-xs">
                                                Editar
                                            </button>
                                            @if(!$isSelf)
                                                <form method="POST"
                                                      action="{{ route('settings.members.destroy', $member) }}"
                                                      @submit.prevent="if (await $store.confirmDialog.ask('Remover {{ addslashes($member->name) }} da organização?')) $el.submit()">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-xs">
                                                        Remover
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Usuários sem organização — normalmente sobra de registro público de
                 teste (/register): existem em `users` mas não aparecem em nenhuma
                 lista do sistema porque nunca foram vinculados a uma organização. --}}
            @if($orphanUsers->isNotEmpty())
                <div class="mt-8">
                    <div class="mb-3">
                        <h2 class="text-sm font-bold" style="color:var(--text)">
                            Usuários sem Organização
                            <span class="ml-1 text-xs px-1.5 py-px rounded-full" style="background:var(--s3); color:var(--muted)">{{ $orphanUsers->count() }}</span>
                        </h2>
                        <p class="text-xs mt-0.5" style="color:var(--muted)">
                            Cadastrados no sistema (geralmente via tela pública de registro/teste) mas nunca vinculados a esta organização — não aparecem em nenhuma outra tela.
                        </p>
                    </div>

                    <div class="card overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border); background:var(--s3)">
                                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Usuário</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">E-mail</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Cadastrado em</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orphanUsers as $orphan)
                                    <tr style="border-bottom:1px solid var(--border2)">
                                        <td class="px-5 py-3.5">
                                            <span class="font-semibold" style="color:var(--text)">{{ $orphan->name }}</span>
                                        </td>
                                        <td class="px-5 py-3.5 text-xs" style="color:var(--muted)">{{ $orphan->email }}</td>
                                        <td class="px-5 py-3.5 text-xs font-mono" style="color:var(--muted)">{{ $orphan->created_at->format('d/m/Y') }}</td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-2 justify-end">
                                                <form method="POST" action="{{ route('settings.orphan-users.attach', $orphan) }}"
                                                      class="flex items-center gap-1.5">
                                                    @csrf
                                                    <select name="role" class="text-xs rounded-lg border px-2 py-1.5"
                                                            style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                                        @foreach(\App\Models\OrganizationUser::$roles as $key => $label)
                                                            @continue($key === 'owner')
                                                            <option value="{{ $key }}" {{ $key === 'member' ? 'selected' : '' }}>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-xs">Adicionar à Equipe</button>
                                                </form>
                                                <form method="POST" action="{{ route('settings.orphan-users.destroy', $orphan) }}"
                                                      @submit.prevent="if (await $store.confirmDialog.ask('Excluir permanentemente {{ addslashes($orphan->name) }} ({{ addslashes($orphan->email) }})? Essa ação não pode ser desfeita.')) $el.submit()">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-xs">Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Modal Novo / Editar Membro --}}
            <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <div class="absolute inset-0 bg-black/60" @click="close()"></div>
                <div class="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                     style="background:var(--s1); border:1px solid var(--border)"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">

                    <h3 class="text-base font-bold mb-5" style="color:var(--text)"
                        x-text="editing ? 'Editar Membro' : 'Novo Membro'"></h3>

                    <form :action="editing
                              ? '{{ url('configuracoes/equipe') }}/' + editing.id
                              : '{{ route('settings.members.store') }}'"
                          method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_method" value="PATCH" :disabled="!editing">

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Foto de Perfil</label>
                            <div class="flex items-center gap-3">
                                <img x-show="form.avatar_url" :src="form.avatar_url" x-cloak
                                     class="h-10 w-10 rounded-full object-cover flex-shrink-0">
                                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                                       class="flex-1 text-xs" style="color:var(--muted)">
                            </div>
                            <label x-show="editing && form.avatar_url" x-cloak class="flex items-center gap-2 mt-2 cursor-pointer">
                                <input type="checkbox" name="remove_avatar" value="1" x-model="form.remove_avatar">
                                <span class="text-xs" style="color:var(--muted)">Remover foto atual</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Nome</label>
                            <input type="text" name="name" x-model="form.name" required
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">E-mail</label>
                            <input type="email" name="email" x-model="form.email" required
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">
                                Senha
                                <span x-show="editing" class="font-normal">(deixe em branco para não alterar)</span>
                            </label>
                            <input type="password" name="password" x-model="form.password"
                                   :required="!editing"
                                   placeholder="Mínimo 8 caracteres"
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Papel (permissão)</label>
                            <select name="role" x-model="form.role" required
                                    class="w-full rounded-lg border px-3 py-2 text-sm"
                                    style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                                <option value="admin">Administrador</option>
                                <option value="manager">Gestor</option>
                                <option value="member">Membro</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-2" style="color:var(--muted)">Papéis funcionais (dashboards)</label>
                            <div style="display:flex; flex-wrap:wrap; gap:6px">
                                @foreach($functionalRoles as $fr)
                                    <label style="cursor:pointer; position:relative">
                                        <input type="checkbox"
                                               name="function_roles[]"
                                               value="{{ $fr->key }}"
                                               x-model="form.function_roles"
                                               style="position:absolute; opacity:0; width:0; height:0; pointer-events:none">
                                        <span :style="form.function_roles.includes('{{ $fr->key }}')
                                                ? 'background:rgba(100, 59, 142,.12); border-color:rgba(100, 59, 142,.4); color:var(--purple);'
                                                : 'background:var(--s3); border-color:var(--border2); color:var(--muted);'"
                                              style="display:inline-block; padding:4px 12px; border-radius:100px; font-size:11px; font-weight:600; border:1px solid; transition:all .12s; user-select:none">
                                            {{ $fr->name }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 text-sm font-semibold rounded-lg"
                                    style="color:var(--muted); background:var(--s3)">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold"
                                    x-text="editing ? 'Salvar Alterações' : 'Criar Membro'">
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══ TAB SETORES ══ --}}
        <div x-show="tab === 'setores'" x-cloak
             x-data="{
                modal: false,
                editing: null,
                form: { name: '', user_ids: [] },
                open(sector) {
                    if (sector) {
                        this.editing = sector;
                        this.form = { name: sector.name, user_ids: sector.user_ids || [] };
                    } else {
                        this.editing = null;
                        this.form = { name: '', user_ids: [] };
                    }
                    this.modal = true;
                },
                close() { this.modal = false; this.editing = null; }
             }">

            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-bold" style="color:var(--text)">Setores</h2>
                    <p class="text-xs mt-0.5 max-w-lg" style="color:var(--muted)">
                        Grupos usados pra rotear notificação interna (ex: "quando uma tarefa com destino X for
                        concluída, avisa o setor Y" — configurado na tela de Automações). Não tem relação com os
                        papéis funcionais da aba Equipe.
                    </p>
                </div>
                <button @click="open(null)"
                        class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold flex items-center gap-2">
                    <x-icon name="plus" size="16" />
                    Novo Setor
                </button>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border); background:var(--s3)">
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Setor</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Membros</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sectors as $sector)
                            <tr style="border-bottom:1px solid var(--border2)">
                                <td class="px-5 py-3.5 font-semibold" style="color:var(--text)">{{ $sector->name }}</td>
                                <td class="px-5 py-3.5 text-xs" style="color:var(--muted)">
                                    {{ $sector->users->pluck('name')->implode(', ') ?: '— nenhum membro alocado —' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2 justify-end">
                                        <button type="button"
                                                @click="open({{ json_encode(['id' => $sector->id, 'name' => $sector->name, 'user_ids' => $sector->users->pluck('id')->map(fn ($id) => (string) $id)->all()]) }})"
                                                class="btn btn-ghost btn-xs">
                                            Editar
                                        </button>
                                        <form method="POST"
                                              action="{{ route('settings.sectors.destroy', $sector) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Remover o setor {{ addslashes($sector->name) }}?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                Remover
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-xs" style="color:var(--muted)">
                                    Nenhum setor cadastrado ainda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Modal Novo / Editar Setor --}}
            <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <div class="absolute inset-0 bg-black/60" @click="close()"></div>
                <div class="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                     style="background:var(--s1); border:1px solid var(--border)"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">

                    <h3 class="text-base font-bold mb-5" style="color:var(--text)"
                        x-text="editing ? 'Editar Setor' : 'Novo Setor'"></h3>

                    <form :action="editing
                              ? '{{ url('configuracoes/setores') }}/' + editing.id
                              : '{{ route('settings.sectors.store') }}'"
                          method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_method" value="PATCH" :disabled="!editing">

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Nome do Setor</label>
                            <input type="text" name="name" x-model="form.name" required
                                   placeholder="Ex: Tráfego"
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-2" style="color:var(--muted)">Membros deste setor</label>
                            <div style="display:flex; flex-wrap:wrap; gap:6px">
                                @foreach($members as $member)
                                    <label style="cursor:pointer; position:relative">
                                        <input type="checkbox"
                                               name="user_ids[]"
                                               value="{{ $member->id }}"
                                               x-model="form.user_ids"
                                               style="position:absolute; opacity:0; width:0; height:0; pointer-events:none">
                                        <span :style="form.user_ids.includes('{{ $member->id }}')
                                                ? 'background:rgba(100, 59, 142,.12); border-color:rgba(100, 59, 142,.4); color:var(--purple);'
                                                : 'background:var(--s3); border-color:var(--border2); color:var(--muted);'"
                                              style="display:inline-block; padding:4px 12px; border-radius:100px; font-size:11px; font-weight:600; border:1px solid; transition:all .12s; user-select:none">
                                            {{ $member->name }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 text-sm font-semibold rounded-lg"
                                    style="color:var(--muted); background:var(--s3)">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold"
                                    x-text="editing ? 'Salvar Alterações' : 'Criar Setor'">
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══ TAB PAPÉIS FUNCIONAIS ══ --}}
        <div x-show="tab === 'papeis'" x-cloak
             x-data="{
                modal: false,
                editing: null,
                form: { name: '' },
                open(role) {
                    if (role) {
                        this.editing = role;
                        this.form = { name: role.name };
                    } else {
                        this.editing = null;
                        this.form = { name: '' };
                    }
                    this.modal = true;
                },
                close() { this.modal = false; this.editing = null; }
             }">

            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-bold" style="color:var(--text)">Papéis Funcionais</h2>
                    <p class="text-xs mt-0.5 max-w-lg" style="color:var(--muted)">
                        O que cada pessoa faz na agência (ex: Tráfego, Design) — controla o que aparece pra ela no
                        Dashboard e pode ser usado como destinatário de notificação na tela de Automações. Não tem
                        relação com os Setores.
                    </p>
                </div>
                <button @click="open(null)"
                        class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold flex items-center gap-2">
                    <x-icon name="plus" size="16" />
                    Novo Papel
                </button>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border); background:var(--s3)">
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Papel</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Pessoas</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($functionalRoles as $fr)
                            <tr style="border-bottom:1px solid var(--border2)">
                                <td class="px-5 py-3.5 font-semibold" style="color:var(--text)">
                                    {{ $fr->name }}
                                    @if($fr->is_protected)
                                        <span class="ml-1 text-xs" style="color:var(--muted)" title="Usado por uma seção do sistema — não pode ser removido">🔒</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-xs" style="color:var(--muted)">
                                    {{ $fr->users_count }} {{ $fr->users_count === 1 ? 'pessoa' : 'pessoas' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2 justify-end">
                                        <button type="button"
                                                @click="open({{ json_encode(['id' => $fr->id, 'name' => $fr->name]) }})"
                                                class="btn btn-ghost btn-xs">
                                            Editar
                                        </button>
                                        @if(!$fr->is_protected)
                                            <form method="POST"
                                                  action="{{ route('settings.functional-roles.destroy', $fr) }}"
                                                  @submit.prevent="if (await $store.confirmDialog.ask('Remover o papel {{ addslashes($fr->name) }}?')) $el.submit()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs">
                                                    Remover
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-xs" style="color:var(--muted)">
                                    Nenhum papel funcional cadastrado ainda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Modal Novo / Editar Papel --}}
            <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <div class="absolute inset-0 bg-black/60" @click="close()"></div>
                <div class="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                     style="background:var(--s1); border:1px solid var(--border)"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">

                    <h3 class="text-base font-bold mb-5" style="color:var(--text)"
                        x-text="editing ? 'Editar Papel' : 'Novo Papel'"></h3>

                    <form :action="editing
                              ? '{{ url('configuracoes/papeis') }}/' + editing.id
                              : '{{ route('settings.functional-roles.store') }}'"
                          method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_method" value="PATCH" :disabled="!editing">

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Nome do Papel</label>
                            <input type="text" name="name" x-model="form.name" required
                                   placeholder="Ex: Growth"
                                   class="w-full rounded-lg border px-3 py-2 text-sm"
                                   style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 text-sm font-semibold rounded-lg"
                                    style="color:var(--muted); background:var(--s3)">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold"
                                    x-text="editing ? 'Salvar Alterações' : 'Criar Papel'">
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══ TAB MENSAGENS PADRÃO ══ --}}
        <div x-show="tab === 'mensagens'" x-cloak>
            <div class="mb-5">
                <h2 class="text-sm font-bold" style="color:var(--text)">Mensagens Padrão</h2>
                <p class="text-xs mt-0.5 max-w-lg" style="color:var(--muted)">
                    Texto padrão por gatilho e canal, usado em toda a organização. Um cliente só recebe uma dessas
                    mensagens se algum contato dele estiver assinado naquele tipo (aba Portal/Contatos da ficha do
                    cliente). O disparo automático ainda não está ligado — por enquanto isso só cadastra o texto.
                </p>
            </div>

            <form method="POST" action="{{ route('settings.notification-templates.update') }}" class="space-y-4">
                @csrf

                @foreach(\App\Models\NotificationTemplate::$types as $type => $typeLabel)
                    @php
                        $hints = \App\Models\NotificationTemplate::$variableHints[$type] ?? [];
                    @endphp
                    <div class="card p-5" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between text-left">
                            <span class="text-sm font-bold" style="color:var(--text)">{{ $typeLabel }}</span>
                            <x-icon name="chevron-right" size="16" class="flex-shrink-0 transition-transform" :class="open ? 'rotate-90' : ''" style="color:var(--muted)" />
                        </button>

                        <div x-show="open" x-transition class="mt-4 space-y-4">
                            @if(!empty($hints))
                                <p class="text-xs" style="color:var(--muted)">
                                    Variáveis disponíveis:
                                    @foreach($hints as $hint)
                                        <code class="px-1 py-0.5 rounded" style="background:var(--s3); color:var(--purple)">{{ $hint }}</code>
                                    @endforeach
                                </p>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach(\App\Models\NotificationTemplate::$channels as $channel => $channelLabel)
                                    @php $existing = $notificationTemplates[$type][$channel] ?? null; @endphp
                                    <div class="rounded-lg p-3" style="background:var(--s2); border:1px solid var(--border2)">
                                        <p class="text-xs font-bold uppercase tracking-wide mb-2" style="color:var(--muted)">{{ $channelLabel }}</p>

                                        @if($channel === 'email')
                                            <input type="text"
                                                   name="templates[{{ $type }}][{{ $channel }}][subject]"
                                                   value="{{ old("templates.{$type}.{$channel}.subject", $existing?->subject) }}"
                                                   placeholder="Assunto do e-mail"
                                                   class="w-full rounded-lg border px-3 py-2 text-sm mb-2"
                                                   style="background:var(--s1); border-color:var(--border2); color:var(--text)">
                                        @endif

                                        <textarea name="templates[{{ $type }}][{{ $channel }}][body]"
                                                  rows="4"
                                                  placeholder="Texto padrão..."
                                                  class="w-full rounded-lg border px-3 py-2 text-sm resize-none"
                                                  style="background:var(--s1); border-color:var(--border2); color:var(--text)">{{ old("templates.{$type}.{$channel}.body", $existing?->body) }}</textarea>

                                        <div class="flex justify-end mt-2">
                                            <button type="submit" form="test-{{ $type }}-{{ $channel }}"
                                                    class="text-xs font-semibold transition-colors"
                                                    style="color:var(--purple)"
                                                    onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
                                                Enviar teste →
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold">
                    Salvar Mensagens
                </button>
            </form>

            {{-- Um form isolado por gatilho+canal pra "Enviar teste" (associado ao
                 botão via atributo form="", já que HTML não permite form aninhado
                 dentro do form grande de salvar acima). Testa sempre o texto já
                 salvo — se você editou e ainda não salvou, salve primeiro. --}}
            @foreach(\App\Models\NotificationTemplate::$types as $type => $typeLabel)
                @foreach(\App\Models\NotificationTemplate::$channels as $channel => $channelLabel)
                    <form id="test-{{ $type }}-{{ $channel }}" method="POST"
                          action="{{ route('settings.notification-templates.test', [$type, $channel]) }}" class="hidden">
                        @csrf
                    </form>
                @endforeach
            @endforeach
        </div>

        {{-- ══ TAB API & TOKENS ══ --}}
        <div x-show="tab === 'api'" x-cloak>

            {{-- Token recém-gerado --}}
            @if(session('new_token'))
                <div class="mb-5 rounded-xl p-4 border"
                     style="background:rgba(34,197,94,.05); border-color:rgba(34,197,94,.3)">
                    <p class="text-xs font-bold mb-2" style="color:var(--green)">
                        Token gerado — copie agora, não será exibido novamente
                    </p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-xs font-mono px-3 py-2 rounded-lg overflow-x-auto"
                              style="background:var(--s3); color:var(--text)">
                            {{ session('new_token') }}
                        </code>
                        <button onclick="navigator.clipboard.writeText('{{ session('new_token') }}')"
                                class="flex-shrink-0 px-3 py-2 text-xs font-semibold rounded-lg transition-colors"
                                style="background:rgba(34,197,94,.15); color:var(--green)">
                            Copiar
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-sm font-bold" style="color:var(--text)">Tokens de API</h2>
                    <p class="text-xs mt-0.5 max-w-lg" style="color:var(--muted)">
                        Tokens usados pelo n8n para autenticar chamadas à API interna.
                        Cada token identifica esta organização — guarde com segurança.
                    </p>
                </div>
                <form method="POST" action="{{ route('settings.tokens.create') }}"
                      class="flex items-center gap-2 flex-shrink-0"
                      x-data="{ name: '' }">
                    @csrf
                    <input type="text" name="token_name" x-model="name"
                           placeholder="Nome do token (ex: n8n-prod)"
                           required maxlength="80"
                           class="rounded-lg border px-3 py-2 text-sm w-52"
                           style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                    <button type="submit" :disabled="!name"
                            class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold whitespace-nowrap">
                        Gerar Token
                    </button>
                </form>
            </div>

            {{-- Instruções de uso --}}
            <div class="mb-5 rounded-xl p-4 border" style="background:var(--s3); border-color:var(--border2)">
                <p class="text-xs font-bold mb-2" style="color:var(--muted)">Como usar no n8n</p>
                <div class="space-y-1">
                    <p class="text-xs font-mono" style="color:var(--muted)">
                        Header: <span style="color:var(--text)">Authorization: Bearer {token}</span>
                    </p>
                    <p class="text-xs font-mono" style="color:var(--muted)">
                        Base URL: <span style="color:var(--text)">{{ config('app.url') }}/api</span>
                    </p>
                </div>
                <div class="mt-3 pt-3 border-t space-y-1" style="border-color:var(--border2)">
                    <p class="text-xs font-semibold mb-1" style="color:var(--muted)">Endpoints disponíveis</p>
                    <p class="text-xs font-mono" style="color:var(--muted)">GET  <span style="color:var(--text)">/api/ad-accounts</span> — contas de anúncios ativas</p>
                    <p class="text-xs font-mono" style="color:var(--muted)">POST <span style="color:var(--text)">/api/sync/campaigns</span> — sincroniza estrutura de campanhas</p>
                    <p class="text-xs font-mono" style="color:var(--muted)">POST <span style="color:var(--text)">/api/sync/snapshots</span> — grava métricas diárias</p>
                </div>
            </div>

            {{-- Lista de tokens --}}
            @if($apiTokens->isEmpty())
                <div class="card p-8 text-center">
                    <p class="text-sm font-semibold" style="color:var(--muted)">Nenhum token gerado ainda</p>
                    <p class="text-xs mt-1" style="color:var(--muted)">Gere um token para conectar o n8n a esta organização.</p>
                </div>
            @else
                <div class="card overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border); background:var(--s3)">
                                <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Nome</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Criado em</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold" style="color:var(--muted)">Último uso</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($apiTokens as $token)
                                <tr style="border-bottom:1px solid var(--border2)">
                                    <td class="px-5 py-3.5">
                                        <span class="font-semibold text-xs" style="color:var(--text)">{{ $token->name }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs" style="color:var(--muted)">
                                        {{ $token->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs" style="color:var(--muted)">
                                        {{ $token->last_used_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <form method="POST"
                                              action="{{ route('settings.tokens.delete', $token->id) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Revogar token {{ addslashes($token->name) }}?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                Revogar
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

    </div>

    @push('scripts')
    <script>
        // Sincroniza input de cor com o text readonly ao lado
        document.querySelectorAll('input[type="color"]').forEach(picker => {
            const textInput = picker.nextElementSibling;
            picker.addEventListener('input', () => { if (textInput) textInput.value = picker.value; });
        });
    </script>
    @endpush
</x-app-layout>
