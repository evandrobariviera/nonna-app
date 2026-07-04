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

        {{-- Tabs --}}
        <div class="flex gap-1 mb-6 border-b" style="border-color:var(--border)">
            @foreach(['geral' => 'Geral', 'integracoes' => 'Integrações', 'equipe' => 'Equipe', 'api' => 'API & Tokens'] as $key => $label)
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
                form: { provider: '', label: '', external_id: '', status: 'pending' },
                open(integration) {
                    if (integration) {
                        this.editing = integration;
                        this.form = {
                            provider:    integration.provider,
                            label:       integration.label,
                            external_id: integration.external_id ?? '',
                            status:      integration.status,
                        };
                    } else {
                        this.editing = null;
                        this.form = { provider: '', label: '', external_id: '', status: 'pending' };
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
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Nova Integração
                </button>
            </div>

            @if($integrations->isEmpty())
                <div class="card p-10 text-center">
                    <svg class="mx-auto h-10 w-10 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1"
                         stroke="currentColor" style="color:var(--border2)">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                    </svg>
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

                            <div class="flex items-center gap-2 pt-1 border-t" style="border-color:var(--border2)">
                                <button type="button"
                                        @click="open({{ $integration->toJson() }})"
                                        class="text-xs font-semibold transition-colors"
                                        style="color:var(--muted)"
                                        onmouseover="this.style.color='var(--purple)'"
                                        onmouseout="this.style.color='var(--muted)'">
                                    Editar
                                </button>
                                <span style="color:var(--border2)">·</span>
                                <form method="POST"
                                      action="{{ route('settings.integrations.destroy', $integration) }}"
                                      onsubmit="return confirm('Remover esta integração?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold transition-colors"
                                            style="color:var(--muted)"
                                            onmouseover="this.style.color='var(--red)'"
                                            onmouseout="this.style.color='var(--muted)'">
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
                                        ->groupBy(fn($p) => $p['category']);
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
                form: { name: '', email: '', password: '', role: 'member', function_roles: [] },
                open(member) {
                    if (member) {
                        this.editing = member;
                        this.form = { name: member.name, email: member.email, password: '', role: member.role, function_roles: member.function_roles || [] };
                    } else {
                        this.editing = null;
                        this.form = { name: '', email: '', password: '', role: 'member', function_roles: [] };
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
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
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
                                        <div class="h-7 w-7 rounded-full flex items-center justify-center text-xs font-black text-white flex-shrink-0"
                                             style="background:var(--grad)">
                                            {{ strtoupper(substr($member->name, 0, 2)) }}
                                        </div>
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
                                          style="background:{{ $isOwner ? 'rgba(106,90,205,.15)' : ($isAdmin ? 'rgba(255,140,0,.1)' : 'var(--s3)') }};
                                                 color:{{ $isOwner ? 'var(--purple)' : ($isAdmin ? 'var(--orange)' : 'var(--muted)') }};
                                                 border:1px solid {{ $isOwner ? 'rgba(106,90,205,.2)' : ($isAdmin ? 'rgba(255,140,0,.2)' : 'var(--border2)') }}">
                                        {{ $roleLabel }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if(!$isOrgOwner)
                                        <div class="flex items-center gap-3 justify-end">
                                            <button type="button"
                                                    @click="open({{ json_encode(['id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'role' => $role, 'function_roles' => $member->pivot->function_roles ?? []]) }})"
                                                    class="text-xs font-semibold transition-colors"
                                                    style="color:var(--muted)"
                                                    onmouseover="this.style.color='var(--purple)'"
                                                    onmouseout="this.style.color='var(--muted)'">
                                                Editar
                                            </button>
                                            @if(!$isSelf)
                                                <span style="color:var(--border2)">·</span>
                                                <form method="POST"
                                                      action="{{ route('settings.members.destroy', $member) }}"
                                                      onsubmit="return confirm('Remover {{ $member->name }} da organização?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-xs font-semibold transition-colors"
                                                            style="color:var(--muted)"
                                                            onmouseover="this.style.color='var(--red)'"
                                                            onmouseout="this.style.color='var(--muted)'">
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
                          method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_method" value="PATCH" :disabled="!editing">

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
                                @foreach(\App\Models\OrganizationUser::$functionRoles as $frKey => $frLabel)
                                    <label style="cursor:pointer; position:relative">
                                        <input type="checkbox"
                                               name="function_roles[]"
                                               value="{{ $frKey }}"
                                               x-model="form.function_roles"
                                               style="position:absolute; opacity:0; width:0; height:0; pointer-events:none">
                                        <span :style="form.function_roles.includes('{{ $frKey }}')
                                                ? 'background:rgba(106,90,205,.12); border-color:rgba(106,90,205,.4); color:var(--purple);'
                                                : 'background:var(--s3); border-color:var(--border2); color:var(--muted);'"
                                              style="display:inline-block; padding:4px 12px; border-radius:100px; font-size:11px; font-weight:600; border:1px solid; transition:all .12s; user-select:none">
                                            {{ $frLabel }}
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
                                              onsubmit="return confirm('Revogar token {{ $token->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-xs font-semibold transition-colors"
                                                    style="color:var(--muted)"
                                                    onmouseover="this.style.color='var(--red)'"
                                                    onmouseout="this.style.color='var(--muted)'">
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
