{{-- Editor do documento HTML (planejamento / campanha / projeto) — DocumentEditController.
     O HTML abre num iframe srcdoc com sandbox SEM allow-scripts: os scripts do documento não
     rodam (nada de JS de fora tocando a sessão), mas a página consegue marcar os textos como
     editáveis e ler o resultado. O painel "Dados do documento" mexe no bloco escondido
     "nonna-dados" — que o servidor regrava e usa pra atualizar datas/verba no App. --}}
@php
    $kindLabel = $kind === 'plan' ? 'planejamento' : ($owner->type === 'campanha' ? 'campanha' : 'projeto');
    $isCampaign = $kind === 'project' && $owner->type === 'campanha';
@endphp
<x-app-layout>
    <x-slot name="header">Editar documento — {{ $owner->title }}</x-slot>

    <div x-data="docEditor(@js(['data' => $data, 'saveUrl' => $saveUrl, 'kind' => $kind]))" @beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">

    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <div class="min-w-0">
            <a href="{{ $backUrl }}" class="text-xs font-semibold flex items-center gap-1 mb-1" style="color:var(--muted)">
                <x-icon name="arrow-left" size="12" /> Voltar {{ $kind === 'plan' ? 'ao planejamento' : 'para a ' . ($isCampaign ? 'campanha' : 'página do projeto') }}
            </a>
            <h1 class="text-lg font-bold truncate" style="color:var(--text)">{{ $owner->title }}</h1>
            <p class="text-xs" style="color:var(--muted)">
                Clique em qualquer texto do documento para corrigir. Enter quebra a linha; colar entra como texto simples.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs" style="color:var(--orange)" x-show="dirty" x-cloak>Alterações não salvas</span>
            <a href="{{ $backUrl }}" class="btn btn-ghost btn-sm">Cancelar</a>
            <button type="button" class="btn btn-primary btn-sm flex items-center gap-1" @click="save()" :disabled="saving">
                <x-icon name="save" size="14" /> <span x-text="saving ? 'Salvando…' : 'Salvar nova versão'"></span>
            </button>
        </div>
    </div>

    <p class="text-sm mb-3 px-4 py-2 rounded-lg" style="background:var(--red-bg, rgba(220,38,38,.08)); color:var(--red)" x-show="error" x-cloak x-text="error"></p>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_360px] items-start">
        <div class="card overflow-hidden min-w-0">
            <iframe x-ref="frame" srcdoc="{{ $html }}" sandbox="allow-same-origin" title="Documento em edição"
                    @load="prepare()"
                    style="width:100%; height:calc(100vh - 190px); min-height:560px; border:0; background:#fff; display:block"></iframe>
        </div>

        <div class="space-y-4 lg:sticky lg:top-4">
            {{-- Sugestões de troca no texto (aparecem ao mudar uma data/verba no painel) --}}
            <template x-for="(s, i) in suggestions" :key="s.id">
                <div class="card p-4" style="border-left:3px solid var(--orange)">
                    <p class="text-sm" style="color:var(--text)">
                        <b x-text="s.from"></b> aparece <b x-text="s.count + '×'"></b> no texto do documento.
                    </p>
                    <p class="text-xs mt-1" style="color:var(--muted)">Quer trocar por <b x-text="s.to"></b> em todos esses lugares?</p>
                    <div class="flex gap-2 mt-3">
                        <button type="button" class="btn btn-primary btn-xs flex items-center gap-1" @click="applySuggestion(i)">
                            <x-icon name="replace" size="12" /> Trocar no texto
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs" @click="suggestions.splice(i, 1)">Deixar como está</button>
                    </div>
                </div>
            </template>

            <div class="card p-4">
                <h2 class="card-title mb-1">Dados do documento</h2>
                @if($data === null)
                    <p class="text-xs" style="color:var(--muted)">
                        Este documento não tem o bloco de dados que o App lê — dá para corrigir o texto, mas as datas do App
                        não mudam por aqui. Ajuste as datas direto na página {{ $kind === 'plan' ? 'do planejamento' : 'da ' . $kindLabel }}.
                    </p>
                @else
                    <p class="text-xs mb-3" style="color:var(--muted)">
                        Mudou aqui, muda também no App ao salvar. Se a data antiga aparecer no texto, o App oferece trocar.
                    </p>
                    @php
                        // Datas que já estão diferentes entre App e documento (alguém mudou direto no App).
                        $fields = $kind === 'plan'
                            ? ['periodo_inicio' => ['period_start', 'Início do período'], 'periodo_fim' => ['period_end', 'Fim do período']]
                            : array_filter(['inicio' => ['start_date', $isCampaign ? 'Vai ao ar' : 'Início'], 'fim' => ['end_date', 'Fim'],
                                            'entrega_pecas' => $isCampaign ? ['pieces_due_date', 'Entrega das peças'] : null]);
                        $diverged = collect($fields)->filter(fn ($f, $k) => $owner->{$f[0]} && ($data[$k] ?? null) && $owner->{$f[0]}->toDateString() !== $data[$k])
                            ->map(fn ($f) => $f[1] . ': ' . $owner->{$f[0]}->format('d/m/Y'));
                    @endphp
                    @if($diverged->isNotEmpty())
                        <p class="text-xs mb-3 px-2 py-1.5 rounded" style="background:var(--s2); color:var(--text)">
                            No App está diferente — {{ $diverged->implode(' · ') }}. Salvar sem mexer nesses campos mantém o App como está.
                        </p>
                    @endif

                    @if($kind === 'plan')
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="block text-xs font-semibold mb-1" style="color:var(--muted)">Início do período</span>
                                <input type="date" class="field w-full px-3 py-2 text-sm" :value="data.periodo_inicio" @change="setDate(['periodo_inicio'], $event.target.value)">
                            </label>
                            <label class="block">
                                <span class="block text-xs font-semibold mb-1" style="color:var(--muted)">Fim do período</span>
                                <input type="date" class="field w-full px-3 py-2 text-sm" :value="data.periodo_fim" @change="setDate(['periodo_fim'], $event.target.value)">
                            </label>
                        </div>
                        <label class="block mt-2">
                            <span class="block text-xs font-semibold mb-1" style="color:var(--muted)">Verba mensal de mídia (R$)</span>
                            <input type="text" inputmode="decimal" class="field w-full px-3 py-2 text-sm" :value="money(data.verba_mensal)" @change="setVerba($event.target.value); $event.target.value = money(data.verba_mensal)">
                        </label>

                        <template x-if="(data.itens || []).length">
                            <div class="mt-4">
                                <p class="text-xs font-semibold mb-1" style="color:var(--muted)">Campanhas e projetos</p>
                                <div class="space-y-2">
                                    <template x-for="(item, i) in data.itens" :key="i">
                                        <div class="card-inset p-2">
                                            <p class="text-xs font-semibold mb-1" style="color:var(--text)" x-text="item.nome"></p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="date" class="field w-full px-2 py-1 text-xs" :value="item.inicio" @change="setDate(['itens', i, 'inicio'], $event.target.value)" title="Início">
                                                <input type="date" class="field w-full px-2 py-1 text-xs" :value="item.fim" @change="setDate(['itens', i, 'fim'], $event.target.value)" title="Fim">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <p class="text-xs mt-2" style="color:var(--muted)">O nome liga cada linha à campanha/projeto do App — por isso não muda aqui.</p>
                            </div>
                        </template>
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="block text-xs font-semibold mb-1" style="color:var(--muted)">{{ $isCampaign ? 'Vai ao ar' : 'Início' }}</span>
                                <input type="date" class="field w-full px-3 py-2 text-sm" :value="data.inicio" @change="setDate(['inicio'], $event.target.value)">
                            </label>
                            <label class="block">
                                <span class="block text-xs font-semibold mb-1" style="color:var(--muted)">Fim</span>
                                <input type="date" class="field w-full px-3 py-2 text-sm" :value="data.fim" @change="setDate(['fim'], $event.target.value)">
                            </label>
                        </div>
                        @if($isCampaign)
                            <label class="block mt-2">
                                <span class="block text-xs font-semibold mb-1" style="color:var(--muted)">Entrega das peças</span>
                                <input type="date" class="field w-full px-3 py-2 text-sm" :value="data.entrega_pecas" @change="setDate(['entrega_pecas'], $event.target.value)">
                            </label>
                        @endif

                        <template x-if="(data.pecas || []).length">
                            <div class="mt-4">
                                <p class="text-xs font-semibold mb-1" style="color:var(--muted)">Peças</p>
                                <div class="space-y-1">
                                    <template x-for="(peca, i) in data.pecas" :key="i">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs flex-1 min-w-0 truncate" style="color:var(--text)" :title="peca.nome" x-text="peca.nome + (peca.quantidade ? ' (' + peca.quantidade + ')' : '')"></span>
                                            <input type="date" class="field px-2 py-1 text-xs" style="width:150px" :value="peca.data" @change="setDate(['pecas', i, 'data'], $event.target.value)">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    @endif
                @endif
            </div>

            <div class="card p-4">
                <h2 class="card-title mb-2 flex items-center gap-1">Versões</h2>
                <p class="text-xs mb-2" style="color:var(--muted)">Cada salvar guarda uma versão nova; dá para voltar a qualquer uma.</p>
                <div class="space-y-1">
                    @foreach($versions as $version)
                        <div class="flex items-center gap-2 py-1" style="border-bottom:1px solid var(--border2)">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs truncate" style="color:var(--text)" title="{{ $version->filename }}">{{ $version->filename }}</p>
                                <p class="text-xs" style="color:var(--muted)">{{ $version->created_at->format('d/m/Y H:i') }} · {{ $version->uploadedBy?->name ?? '—' }}</p>
                            </div>
                            @if($version->id === $attachment->id)
                                <span class="tag-green text-xs">atual</span>
                            @else
                                <a href="{{ $version->url() }}" target="_blank" rel="noopener" class="btn-icon" title="Ver esta versão"><x-icon name="external-link" size="12" /></a>
                                <form method="POST" action="{{ $kind === 'plan' ? route('macroplans.document.restore', [$owner, $version]) : route('projects.document.restore', [$owner, $version]) }}"
                                      @submit.prevent="if (await $store.confirmDialog.ask('Voltar para a versão de {{ $version->created_at->format('d/m/Y H:i') }}? As datas do App voltam junto. A versão atual continua guardada.')) { dirty = false; $el.submit() }">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-xs flex items-center gap-1" title="Restaurar"><x-icon name="rotate-ccw" size="12" /> Restaurar</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    </div>

    @push('scripts')
    <script>
    window.docEditor = function (config) {
        const EDIT_STYLE_ID = 'nonna-edit-style';
        const SKIP = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'SVG', 'IFRAME']);
        const brDate = iso => { const [y, m, d] = iso.split('-'); return `${d}/${m}/${y}`; };
        const escapeRe = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const fmtBRL = (v, decimals) => Number(v).toLocaleString('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

        return {
            data: config.data ? JSON.parse(JSON.stringify(config.data)) : null,
            dirty: false,
            saving: false,
            error: '',
            suggestions: [],
            seq: 0,

            get doc() { return this.$refs.frame.contentDocument; },

            // O srcdoc pode terminar de carregar antes do Alpine ligar o @load.
            init() {
                const doc = this.doc;
                if (doc && doc.URL === 'about:srcdoc' && doc.readyState === 'complete') this.prepare();
            },

            // Marca como editável o elemento mais alto que tem texto próprio (p, td, h2, .label…):
            // o que estiver dentro dele (b, span) edita junto.
            prepare() {
                const doc = this.doc;
                if (!doc || !doc.body || doc.getElementById(EDIT_STYLE_ID)) return;
                const style = doc.createElement('style');
                style.id = EDIT_STYLE_ID;
                style.textContent = '[data-nonna-edit]{cursor:text;border-radius:2px}' +
                    '[data-nonna-edit]:hover{outline:1px dashed rgba(100,59,142,.6);outline-offset:2px}' +
                    '[data-nonna-edit]:focus{outline:2px solid #643B8E;outline-offset:2px;background:rgba(100,59,142,.06)}';
                doc.head.appendChild(style);

                const walk = el => {
                    for (const child of el.children) {
                        if (SKIP.has(child.tagName.toUpperCase())) continue;
                        const ownText = [...child.childNodes].some(n => n.nodeType === 3 && n.textContent.trim());
                        if (ownText) {
                            child.setAttribute('contenteditable', 'true');
                            child.setAttribute('data-nonna-edit', '');
                        } else {
                            walk(child);
                        }
                    }
                };
                walk(doc.body);

                doc.addEventListener('input', () => this.dirty = true);
                doc.addEventListener('click', e => { if (e.target.closest('a')) e.preventDefault(); });
                doc.addEventListener('keydown', e => {
                    if (e.key === 'Enter' && e.target.closest('[data-nonna-edit]')) {
                        e.preventDefault();
                        doc.execCommand('insertLineBreak');
                    }
                });
                doc.addEventListener('paste', e => {
                    if (!e.target.closest || !e.target.closest('[data-nonna-edit]')) return;
                    e.preventDefault();
                    doc.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
                });
            },

            // Nós de texto do documento (fora de script/style).
            textNodes() {
                const doc = this.doc;
                const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, {
                    acceptNode: n => n.parentElement && n.parentElement.closest('script,style,noscript,template') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT,
                });
                const nodes = [];
                while (walker.nextNode()) nodes.push(walker.currentNode);
                return nodes;
            },

            count(re) {
                return this.textNodes().reduce((total, n) => total + (n.textContent.match(re) || []).length, 0);
            },

            // Uma sugestão por forma encontrada (12/03/2026 e 12/03 sozinho — as expressões não se
            // sobrepõem: a curta ignora a que tem ano).
            suggest(candidates) {
                for (const [from, to, re] of candidates) {
                    if (from === to) continue;
                    const count = this.count(re);
                    if (count) {
                        this.suggestions = this.suggestions.filter(s => s.from !== from);
                        this.suggestions.push({ id: ++this.seq, from, to, re, count });
                    }
                }
            },

            applySuggestion(i) {
                const s = this.suggestions[i];
                this.textNodes().forEach(n => {
                    const replaced = n.textContent.replace(s.re, s.to);
                    if (replaced !== n.textContent) n.textContent = replaced;
                });
                this.suggestions.splice(i, 1);
                this.dirty = true;
            },

            setDate(path, value) {
                let target = this.data;
                for (const key of path.slice(0, -1)) target = target[key];
                const last = path[path.length - 1];
                const old = target[last];
                target[last] = value || null;
                this.dirty = true;
                if (!old || !value || old === value || !/^\d{4}-\d{2}-\d{2}$/.test(old)) return;

                const [oy, om, od] = old.split('-'), [ny, nm, nd] = value.split('-');
                this.suggest([
                    [brDate(old), brDate(value), new RegExp(escapeRe(brDate(old)), 'g')],
                    [`${od}/${om}`, `${nd}/${nm}`, new RegExp(`(?<![\\d/])${od}/${om}(?![\\d/])`, 'g')],
                ]);
            },

            money(v) {
                return v === null || v === undefined || v === '' ? '' : fmtBRL(v, Number(v) % 1 ? 2 : 0);
            },

            setVerba(raw) {
                let clean = String(raw).replace(/[^0-9.,]/g, '');
                if (clean.includes(',') && clean.includes('.')) clean = clean.replace(/\./g, '').replace(',', '.');
                else if (clean.includes(',')) clean = clean.replace(',', '.');
                else if (/^\d{1,3}(\.\d{3})+$/.test(clean)) clean = clean.replace(/\./g, '');
                const value = clean === '' ? null : Number(clean);
                if (value !== null && isNaN(value)) return;

                const old = this.data.verba_mensal;
                this.data.verba_mensal = value;
                this.dirty = true;
                if (old === null || old === undefined || value === null || Number(old) === value) return;

                const candidates = [2, 0].map(dec => {
                    const from = fmtBRL(old, dec);
                    return [from, fmtBRL(value, dec), new RegExp(`(?<![\\d.,])${escapeRe(from)}(?![\\d]|,\\d)`, 'g')];
                });
                this.suggest(candidates);
            },

            // HTML final: sem as marcas de edição. O bloco de dados o servidor regrava.
            serialize() {
                const clone = this.doc.documentElement.cloneNode(true);
                clone.querySelector('#' + EDIT_STYLE_ID)?.remove();
                clone.querySelectorAll('[data-nonna-edit]').forEach(el => {
                    el.removeAttribute('contenteditable');
                    el.removeAttribute('data-nonna-edit');
                });
                return (this.doc.doctype ? '<!DOCTYPE html>\n' : '') + clone.outerHTML;
            },

            async save() {
                this.saving = true;
                this.error = '';
                try {
                    const res = await fetch(config.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ html: this.serialize(), data: this.data }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json.message || 'Não deu para salvar (erro ' + res.status + '). Tente de novo.');
                    this.dirty = false;
                    window.location = json.redirect;
                } catch (e) {
                    this.error = e.message;
                    this.saving = false;
                }
            },
        };
    };
    </script>
    @endpush
</x-app-layout>
