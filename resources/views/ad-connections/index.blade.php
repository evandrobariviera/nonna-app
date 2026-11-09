<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Campanhas</h1>
        </div>
    </x-slot>

    @php
        $utmLabels = ['ok' => ['UTM no padrão', 'badge-green'], 'diferente' => ['UTM diferente', 'badge-orange'], 'faltando' => ['Sem UTM', 'badge-red']];
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <form method="GET" action="{{ route('ad-connections.index') }}">
                <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                    <option value="">Todos os clientes</option>
                    <option value="meus" @selected($mine)>Só os clientes em que sou responsável</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->displayName() }}</option>
                    @endforeach
                </select>
            </form>
            <form method="POST" action="{{ route('ad-connections.refresh') }}" class="flex items-center gap-3">
                @csrf
                <span class="text-xs" style="color:var(--muted)">{{ $lastCheck ? 'Última verificação: ' . \Illuminate\Support\Carbon::parse($lastCheck)->format('d/m H:i') : 'Ainda não verificado' }} · todo dia às 5h50</span>
                <button type="submit" class="btn btn-sm"><x-icon name="refresh-cw" size="13" />Verificar agora</button>
            </form>
        </div>

        <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(170px, 1fr))">
            @foreach([['ok', 'Tudo certo'], ['atencao', 'Falta algo'], ['sem_acesso', 'Sem acesso']] as [$key, $label])
                <div class="card px-4 py-3">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
                    <p class="text-xl font-black mt-1" style="color:{{ $key === 'ok' ? 'var(--green)' : ($key === 'atencao' ? 'var(--orange)' : 'var(--red)') }}">{{ $counts[$key] ?? 0 }}</p>
                    <p class="text-xs mt-0.5" style="color:var(--muted)">contas de anúncio</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            {{-- Contas por cliente --}}
            <section class="card p-5 min-w-0">
                <h2 class="card-title">Contas por cliente <small class="font-normal text-xs" style="color:var(--muted)">o que falta para o App subir anúncio e para a medição funcionar</small></h2>
                <div class="flex flex-col mt-3">
                    @forelse($groups as $accounts)
                        <div class="py-3" style="{{ !$loop->first ? 'border-top:1px solid var(--border)' : '' }}">
                            <p class="font-semibold text-sm mb-2" style="color:var(--text)">{{ $accounts->first()->client?->displayName() }}</p>
                            <div class="flex flex-col gap-2">
                                @foreach($accounts as $account)
                                    @php $conn = $connections->get($account->id); $isMeta = $account->platform === 'meta_ads'; @endphp
                                    <div class="rounded px-3 py-2.5" style="background:var(--s2)">
                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <span class="font-semibold" style="color:var(--text)">{{ $isMeta ? 'Meta' : 'Google' }}</span>
                                            <span class="text-xs" style="color:var(--muted)">{{ $account->account_id }}</span>
                                            @if($conn)
                                                <span class="badge badge-{{ $conn->statusColor() }}">{{ $conn->statusLabel() }}</span>
                                                @if($conn->can_edit === false && $conn->status !== 'sem_acesso')
                                                    <span class="badge badge-red">Só leitura</span>
                                                @endif
                                                @if($conn->utm_status && isset($utmLabels[$conn->utm_status]))
                                                    <span class="badge {{ $utmLabels[$conn->utm_status][1] }}">{{ $utmLabels[$conn->utm_status][0] }}</span>
                                                @endif
                                            @else
                                                <span class="badge badge-muted">Ainda não verificada</span>
                                            @endif
                                        </div>

                                        @if($conn && $isMeta && $conn->status !== 'sem_acesso')
                                            <p class="text-xs mt-1.5" style="color:var(--muted2)">
                                                Página: <b style="color:var(--text)">{{ $conn->page_name ?? 'nenhuma' }}</b>
                                                · Instagram: <b style="color:var(--text)">{{ $conn->instagram_username ? '@' . $conn->instagram_username : 'nenhum' }}</b>
                                                @if($conn->ads_checked) · {{ $conn->ads_checked }} anúncios ativos conferidos @endif
                                            </p>
                                        @endif

                                        @if($conn && $conn->issues)
                                            <ul class="mt-1.5 flex flex-col gap-0.5">
                                                @foreach($conn->issues as $issue)
                                                    <li class="text-xs flex gap-1.5" style="color:{{ $conn->status === 'sem_acesso' ? 'var(--red)' : 'var(--orange)' }}"><span>•</span><span>{{ $issue }}</span></li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if($conn && !$isMeta && in_array($conn->utm_status, ['faltando', 'diferente'], true))
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                @if($conn->utm_current)
                                                    <code class="text-[11px] px-2 py-1 rounded break-all" style="background:var(--s1); color:var(--muted2)">{{ $conn->utm_current }}</code>
                                                @endif
                                                <form method="POST" action="{{ route('ad-connections.google-utm', $account) }}"
                                                      @submit.prevent="if (await $store.confirmDialog.ask('Aplicar a UTM padrão na conta Google {{ $account->account_id }}? Vale para todas as campanhas dela, inclusive as que já estão no ar.')) $el.submit()">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-xs">Aplicar UTM padrão</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm" style="color:var(--muted)">Nenhuma conta Meta ou Google neste filtro.</p>
                    @endforelse
                </div>
            </section>

            <div class="flex flex-col gap-5 min-w-0">
                {{-- Conexões da Nonna --}}
                <section class="card p-5">
                    <h2 class="card-title">Conexões da Nonna</h2>
                    <div class="flex flex-col gap-2 mt-3 text-sm">
                        <div class="rounded px-3 py-2" style="background:var(--s2)">
                            <div class="flex items-center justify-between gap-2"><b>Meta</b>
                                <span class="badge {{ $integrations['meta']['connected'] ? 'badge-green' : 'badge-red' }}">{{ $integrations['meta']['connected'] ? 'Conectada' : 'Desconectada' }}</span></div>
                            <p class="text-xs mt-1" style="color:var(--muted2)">
                                {{ $integrations['meta']['can_create'] ? 'Pode criar e editar anúncios (permissão ads_management).' : 'Sem permissão para criar anúncios.' }}
                            </p>
                        </div>
                        <div class="rounded px-3 py-2" style="background:var(--s2)">
                            <div class="flex items-center justify-between gap-2"><b>Google Ads</b>
                                <span class="badge {{ $integrations['google']['connected'] ? 'badge-green' : 'badge-red' }}">{{ $integrations['google']['connected'] ? 'Conectada' : 'Desconectada' }}</span></div>
                            <p class="text-xs mt-1" style="color:var(--muted2)">Pela conta gerente (MCC) da Nonna.</p>
                        </div>
                        <div class="rounded px-3 py-2" style="background:var(--s2)">
                            <b>Vídeo para o Google</b>
                            <p class="text-xs mt-1" style="color:var(--muted2)">O Google só aceita vídeo que está no YouTube. A equipe sobe como "não listado" e cola o link.</p>
                        </div>
                    </div>
                </section>

                {{-- Padrões --}}
                <section class="card p-5">
                    <h2 class="card-title">UTM padrão da Nonna</h2>
                    <p class="text-xs mt-1 mb-2" style="color:var(--muted2)">É o padrão que o time já usa. A plataforma preenche os campos entre chaves sozinha.</p>
                    <p class="text-xs font-semibold mt-2" style="color:var(--text)">Meta (no anúncio, campo "Parâmetros de URL")</p>
                    <code class="block text-[11px] px-2 py-1.5 rounded break-all mt-1" style="background:var(--s2); color:var(--muted2)">{{ $metaUtm }}</code>
                    <p class="text-xs font-semibold mt-3" style="color:var(--text)">Google (sufixo de URL da conta)</p>
                    <code class="block text-[11px] px-2 py-1.5 rounded break-all mt-1" style="background:var(--s2); color:var(--muted2)">{{ $googleUtm }}</code>
                </section>

                {{-- Gerador --}}
                <section class="card p-5" x-data="{
                        url: '', where: 'bio', campaign: '',
                        map: { bio: ['instagram', 'bio'], whatsapp: ['whatsapp', 'mensagem'], status: ['whatsapp', 'status'], email: ['email', 'email'], qrcode: ['impresso', 'qrcode'], google_meu_negocio: ['google', 'perfil_empresa'] },
                        slug(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); },
                        get out() {
                            const base = this.url.trim(); if (!base) return '';
                            const [src, med] = this.map[this.where];
                            const camp = this.slug(this.campaign) || 'organico';
                            return base + (base.includes('?') ? '&' : '?') + 'utm_source=' + src + '&utm_medium=' + med + '&utm_campaign=' + camp;
                        } }">
                    <h2 class="card-title">Gerador de UTM <small class="font-normal text-xs" style="color:var(--muted)">para links fora dos anúncios</small></h2>
                    <div class="flex flex-col gap-2 mt-3">
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Página de destino
                            <input type="url" x-model="url" placeholder="https://site-do-cliente.com.br/pagina" class="text-sm w-full">
                        </label>
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Onde o link vai aparecer
                            <select x-model="where" class="text-sm w-full">
                                <option value="bio">Bio do Instagram</option>
                                <option value="whatsapp">Mensagem de WhatsApp</option>
                                <option value="status">Status do WhatsApp</option>
                                <option value="email">E-mail</option>
                                <option value="qrcode">QR code impresso</option>
                                <option value="google_meu_negocio">Perfil da Empresa no Google</option>
                            </select>
                        </label>
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Nome da ação (opcional)
                            <input type="text" x-model="campaign" placeholder="Ex.: Black November" class="text-sm w-full">
                        </label>
                        <code class="text-[11px] px-2 py-1.5 rounded break-all" style="background:var(--s2); color:var(--purple)" x-show="out" x-text="out"></code>
                        <button type="button" class="btn btn-sm self-start" x-show="out" @click="navigator.clipboard.writeText(out); $el.textContent = 'Copiado!'; setTimeout(() => $el.textContent = 'Copiar link', 1500)">Copiar link</button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
