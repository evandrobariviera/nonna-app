<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">← Campanha</a>
            <h1 class="text-2xl font-black text-[var(--text)]">Anúncio de Pesquisa · {{ $campaign->adAccount?->client?->displayName() }}</h1>
        </div>
    </x-slot>

    @php
        $blocked = $connection && ($connection->status === 'sem_acesso' || $connection->can_edit === false);
        $oldHeadlines = old('headlines', []);
        $oldDescriptions = old('descriptions', []);
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm mb-4" style="color:var(--muted2)">Campanha <b style="color:var(--text)">{{ $campaign->name }}</b>. O anúncio entra num grupo que já existe, com o mesmo endereço de destino dos anúncios dele, e nasce <b>pausado</b>. A UTM vem do sufixo da conta (Contas e conexões).</p>

        @if($blocked)
            <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(220,38,38,.06); color:var(--red)">O App não tem acesso de edição a esta conta do Google. <a href="{{ route('ad-connections.index', ['client_id' => $campaign->adAccount?->client_id]) }}" class="underline">Ver em Contas e conexões</a></div>
        @endif

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
             x-data="googleSearchAd({
                 groupsUrl: @js(route('google-search-ads.groups', $campaign)),
                 suggestUrl: @js(route('google-search-ads.suggest', $campaign)),
                 groupId: @js(old('ad_group_id')),
                 headlines: @js(array_pad($oldHeadlines, 15, '')),
                 descriptions: @js(array_pad($oldDescriptions, 4, '')),
                 path1: @js(old('path1', '')), path2: @js(old('path2', '')), finalUrl: @js(old('final_url', '')),
                 aiSuggested: @js((bool) old('ai_suggested')),
             })" x-init="loadGroups()">

            <form method="POST" action="{{ route('google-search-ads.store', $campaign) }}" class="card p-5 flex flex-col gap-4 min-w-0"
                  @submit.prevent="if (problems().length) return; if (await $store.confirmDialog.ask('Criar o anúncio PAUSADO no Google? Ele só começa a rodar quando alguém clicar em Ativar.')) { saving = true; $el.submit() }">
                @csrf
                <input type="hidden" name="ad_group_id" :value="groupId">
                <input type="hidden" name="ai_suggested" :value="aiSuggested ? 1 : 0">

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">1. Grupo de anúncios</p>
                    <p class="text-sm" x-show="loadingGroups" style="color:var(--muted)">Buscando os grupos no Google…</p>
                    <p class="text-sm" x-show="groupsError" x-cloak style="color:var(--red)" x-text="groupsError"></p>
                    <select x-show="!loadingGroups && groups.length" x-cloak x-model="groupId" @change="pickGroup()" class="text-sm w-full min-w-0 max-w-full" aria-label="Grupo de anúncios">
                        <option value="">Escolha…</option>
                        <template x-for="g in groups" :key="g.id">
                            <option :value="g.id" x-text="g.name + (g.status !== 'ENABLED' ? ' (pausado)' : '') + ' · ' + g.ads.length + ' anúncio(s)'" :selected="g.id === groupId"></option>
                        </template>
                    </select>
                    <template x-if="group">
                        <div class="mt-2 text-xs" style="color:var(--muted2)">
                            <p x-show="group.keywords.length"><b>Palavras-chave mais buscadas:</b> <span x-text="group.keywords.slice(0, 10).join(', ')"></span></p>
                            <template x-for="(a, i) in group.ads.slice(0, 2)" :key="i">
                                <p class="mt-1">Anúncio atual <span x-text="a.status === 'ENABLED' ? '(no ar)' : '(pausado)'"></span> · força <b x-text="strength(a.strength)"></b>: <span x-text="a.headlines.slice(0, 4).join(' | ')"></span></p>
                            </template>
                        </div>
                    </template>
                </div>

                <div x-show="groupId" x-cloak class="rounded px-3 py-3" style="background:color-mix(in srgb, var(--purple) 6%, transparent); border:1px solid color-mix(in srgb, var(--purple) 20%, transparent)">
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--purple)">2. Textos com a IA (opcional)</p>
                    <textarea x-model="pedido" rows="2" placeholder="Orientação extra (ex.: destacar o atendimento aos sábados; falar de cirurgia de joelho)" class="field w-full px-3 py-2 text-sm mb-2" style="resize:vertical"></textarea>
                    <button type="button" class="btn btn-sm" :disabled="suggesting || {{ $blocked ? 'true' : 'false' }}" @click="suggest()">
                        <x-icon name="sparkles" size="13" /><span x-text="suggesting ? 'Escrevendo… (até 1 minuto)' : 'Sugerir títulos e descrições'"></span>
                    </button>
                    <p class="text-xs mt-1.5" x-show="suggestError" style="color:var(--red)" x-text="suggestError"></p>
                    <p class="text-xs mt-1.5" x-show="note" style="color:var(--muted2)" x-text="'IA: ' + note"></p>
                </div>

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">3. Títulos <span style="color:var(--muted)">(3 a 15, até 30 caracteres; o Google combina os melhores)</span></p>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        <template x-for="(h, i) in headlines" :key="'h' + i">
                            <label class="flex items-center gap-2 min-w-0">
                                <input type="text" name="headlines[]" x-model="headlines[i]" maxlength="40" class="field text-sm px-2.5 py-1.5 w-full min-w-0" :placeholder="'Título ' + (i + 1)">
                                <span class="text-[11px] tabular-nums w-7 text-right" :style="'color:' + (headlines[i].length > 30 ? 'var(--red)' : 'var(--muted)')" x-text="30 - headlines[i].length"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">4. Descrições <span style="color:var(--muted)">(2 a 4, até 90 caracteres)</span></p>
                    <div class="flex flex-col gap-1.5">
                        <template x-for="(d, i) in descriptions" :key="'d' + i">
                            <label class="flex items-center gap-2 min-w-0">
                                <input type="text" name="descriptions[]" x-model="descriptions[i]" maxlength="120" class="field text-sm px-2.5 py-1.5 w-full min-w-0" :placeholder="'Descrição ' + (i + 1)">
                                <span class="text-[11px] tabular-nums w-7 text-right" :style="'color:' + (descriptions[i].length > 90 ? 'var(--red)' : 'var(--muted)')" x-text="90 - descriptions[i].length"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Endereço de destino
                        <input type="url" name="final_url" x-model="finalUrl" required class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Caminho 1
                        <input type="text" name="path1" x-model="path1" maxlength="15" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Caminho 2
                        <input type="text" name="path2" x-model="path2" maxlength="15" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                    </label>
                </div>

                <ul x-show="groupId && problems().length" x-cloak class="text-xs flex flex-col gap-0.5" style="color:var(--orange)">
                    <template x-for="p in problems()" :key="p"><li x-text="'• ' + p"></li></template>
                </ul>

                <button type="submit" class="btn btn-primary self-start" :disabled="saving || !groupId || problems().length > 0 || {{ $blocked ? 'true' : 'false' }}">
                    <x-icon name="upload" size="14" /><span x-text="saving ? 'Criando…' : 'Criar anúncio pausado'"></span>
                </button>
            </form>

            <div class="flex flex-col gap-5 min-w-0">
                <section class="card p-5">
                    <h2 class="card-title">Como pode aparecer</h2>
                    <p class="text-xs mt-1 mb-3" style="color:var(--muted)">Simulação: o Google escolhe e combina os títulos e descrições.</p>
                    <div class="rounded px-4 py-3" style="background:var(--s1); border:1px solid var(--border)">
                        <p class="text-xs" style="color:var(--muted2)"><b style="color:var(--text)">Patrocinado</b> · <span x-text="displayUrl()"></span></p>
                        <p class="text-base mt-0.5 leading-snug" style="color:var(--purple)" x-text="filled(headlines).slice(0, 3).join(' | ') || 'Seus títulos aparecem aqui'"></p>
                        <p class="text-sm mt-1 leading-snug" style="color:var(--muted2)" x-text="filled(descriptions).slice(0, 2).join(' ') || 'Suas descrições aparecem aqui.'"></p>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="card-title">Anúncios criados nesta campanha</h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($creations as $c)
                            <div class="rounded px-3 py-2 text-sm" style="background:var(--s2)">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="badge badge-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span>
                                    <span class="text-xs" style="color:var(--muted)">{{ $c->created_at->format('d/m H:i') }}{{ $c->createdBy ? ' · ' . \Illuminate\Support\Str::before($c->createdBy->name, ' ') : '' }}</span>
                                </div>
                                <p class="text-xs mt-1" style="color:var(--muted2)">Grupo {{ $c->ad_group_name }} · {{ count($c->headlines) }} títulos{{ $c->ai_suggested ? ' · textos da IA' : '' }}</p>
                                <p class="text-xs" style="color:var(--text)">{{ implode(' | ', array_slice($c->headlines, 0, 3)) }}</p>
                                @if($c->error)<p class="text-xs" style="color:var(--red)">{{ $c->error }}</p>@endif
                                @if($c->status === 'pausado')
                                    <form method="POST" action="{{ route('google-search-ads.activate', $c) }}" class="mt-2"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Ativar o anúncio? Ele passa a rodar (se o grupo e a campanha estiverem ativos) depois da revisão do Google.')) $el.submit()">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-xs"><x-icon name="play" size="12" />Ativar</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm" style="color:var(--muted)">Nenhum ainda.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function googleSearchAd(cfg) {
            return {
                ...cfg, groups: [], loadingGroups: true, groupsError: '', pedido: '', suggesting: false, suggestError: '', note: '', saving: false,
                get group() { return this.groups.find(g => g.id === this.groupId) || null; },
                async loadGroups() {
                    const res = await fetch(this.groupsUrl, { headers: { Accept: 'application/json' } }).catch(() => null);
                    const data = res ? await res.json().catch(() => ({})) : {};
                    this.loadingGroups = false;
                    if (!res || !res.ok) { this.groupsError = data.message || 'Não deu para buscar os grupos no Google.'; return; }
                    this.groups = data.groups || [];
                    if (!this.groups.length) this.groupsError = 'Esta campanha não tem grupos de anúncios.';
                    if (this.groupId && !this.finalUrl) this.pickGroup();
                },
                pickGroup() {
                    const g = this.group; if (!g) return;
                    if (!this.finalUrl) this.finalUrl = g.final_url || '';
                    if (!this.path1) this.path1 = g.path1 || '';
                    if (!this.path2) this.path2 = g.path2 || '';
                },
                async suggest() {
                    this.suggesting = true; this.suggestError = ''; this.note = '';
                    const res = await fetch(this.suggestUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ ad_group_id: this.groupId, pedido: this.pedido }),
                    }).catch(() => null);
                    const data = res ? await res.json().catch(() => ({})) : {};
                    this.suggesting = false;
                    if (!res || !res.ok) { this.suggestError = data.message || 'A IA não respondeu.'; return; }
                    const pad = (list, n) => [...list, ...Array(n).fill('')].slice(0, n);
                    this.headlines = pad(data.headlines || [], 15);
                    this.descriptions = pad(data.descriptions || [], 4);
                    if (data.path1) this.path1 = data.path1;
                    if (data.path2) this.path2 = data.path2;
                    this.note = data.note || '';
                    this.aiSuggested = true;
                },
                filled(list) { return list.map(t => (t || '').trim()).filter(Boolean); },
                strength(s) { return ({ EXCELLENT: 'excelente', GOOD: 'boa', AVERAGE: 'média', POOR: 'fraca', PENDING: 'em análise' })[s] || '—'; },
                displayUrl() {
                    let host = '';
                    try { host = new URL(this.finalUrl).hostname.replace(/^www\./, ''); } catch (e) { host = 'site-do-cliente.com.br'; }
                    return [host, this.path1, this.path1 && this.path2].filter(Boolean).join(' › ');
                },
                problems() {
                    const h = this.filled(this.headlines), d = this.filled(this.descriptions), p = [];
                    if (h.length < 3) p.push('Faltam títulos: mínimo 3 (o ideal é 15).');
                    if (d.length < 2) p.push('Faltam descrições: mínimo 2 (o ideal é 4).');
                    h.forEach((t, i) => { if (t.length > 30) p.push('Um título passa de 30 caracteres: "' + t + '"'); if (t.includes('!')) p.push('Título com "!" não é aceito: "' + t + '"'); });
                    d.forEach(t => { if (t.length > 90) p.push('Uma descrição passa de 90 caracteres.'); });
                    if (new Set(h.map(t => t.toLowerCase())).size !== h.length) p.push('Há títulos repetidos.');
                    if (!this.finalUrl) p.push('Falta o endereço de destino.');
                    return p;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
