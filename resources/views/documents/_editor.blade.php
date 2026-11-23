{{-- Editor de documento em campos (23/11) — genérico: tudo vem do modelo do tipo
     (App\Services\Documents\DocumentSchema). Cada campo salva sozinho (DocumentEditor);
     a lista "O que falta" e a situação de cada seção são calculadas no servidor.
     Recebe: $document (App\Models\Document); opcional $itemLinks (lista => [item id => [url, rótulo]]). --}}
@php
    $schema  = $document->schema();
    $payload = app(\App\Services\Documents\DocumentEditor::class)->payload($document);
@endphp

<div x-data="fieldDocEditor(@js([
        'sections'  => $schema->sections(),
        'payload'   => $payload,
        'fieldUrl'  => route('documents.field', $document),
        'listUrl'   => route('documents.list', $document),
        'renderUrl' => route('documents.render', $document),
        'itemLinks' => $itemLinks ?? (object) [],
    ]))" class="card overflow-hidden">

    {{-- Cabeçalho --}}
    <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap" style="border-bottom:1px solid var(--border2)">
        <div>
            <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">{{ $schema->title() }} · em campos</p>
            <p class="text-xs" style="color:var(--muted2)">
                <span x-show="saving" x-cloak>Salvando…</span>
                <span x-show="!saving && error" x-cloak style="color:var(--red)" x-text="error"></span>
                <span x-show="!saving && !error">Cada campo salva sozinho<span x-show="savedLabel" x-text="' · salvo às ' + savedLabel"></span></span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex" style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
                <button type="button" class="px-3 py-1.5 text-xs font-semibold" @click="view = 'editar'"
                        :style="view === 'editar' ? 'background:rgba(100,59,142,.12); color:var(--purple)' : 'color:var(--muted)'">Editar</button>
                <button type="button" class="px-3 py-1.5 text-xs font-semibold" @click="showClient()"
                        :style="view === 'cliente' ? 'background:rgba(100,59,142,.12); color:var(--purple)' : 'color:var(--muted)'">Como o cliente vê</button>
            </div>
            <a :href="cfg.renderUrl" target="_blank" rel="noopener" class="btn btn-ghost btn-xs flex items-center gap-1"
               title="Abre a versão do cliente numa aba própria (dá para imprimir ou salvar em PDF por lá)">
                <x-icon name="external-link" size="12" /> Abrir em nova aba
            </a>
        </div>
    </div>

    {{-- Como o cliente vê --}}
    <div x-show="view === 'cliente'" x-cloak>
        <iframe :src="clientSrc" title="Versão do cliente" style="width:100%; height:78vh; border:0; background:#F6F7FB; display:block"></iframe>
    </div>

    {{-- Editar --}}
    <div x-show="view === 'editar'" class="p-5">

        {{-- Situação das seções --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <template x-for="s in cfg.sections" :key="s.key">
                <button type="button" @click="scrollTo('sec-' + s.key)"
                        class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full"
                        style="border:1px solid var(--border2); background:var(--s2); color:var(--text)">
                    <span class="font-mono" style="color:var(--muted)" x-text="s.num || '··'"></span>
                    <span x-text="s.title"></span>
                    <span class="font-semibold px-1.5 rounded-full" style="font-size:10px"
                          :style="statusStyle(state.statuses[s.key])" x-text="statusLabel(state.statuses[s.key])"></span>
                </button>
            </template>
        </div>

        {{-- O que falta --}}
        <div x-show="state.issues.length" class="mb-5 px-4 py-3 text-xs" style="border-radius:8px"
             :style="state.blocking ? 'background:rgba(239,68,68,.06); border:1px solid rgba(239,68,68,.25)' : 'background:rgba(238,121,25,.06); border:1px solid rgba(238,121,25,.25)'">
            <button type="button" class="w-full flex items-center justify-between gap-2 font-semibold" @click="issuesOpen = !issuesOpen"
                    :style="state.blocking ? 'color:var(--red)' : 'color:var(--orange)'">
                <span x-text="issuesTitle()"></span>
                <span x-text="issuesOpen ? 'Esconder' : 'Ver'"></span>
            </button>
            <ul x-show="issuesOpen" x-cloak class="mt-2 space-y-1">
                <template x-for="(i, n) in state.issues" :key="n">
                    <li class="flex gap-2 items-start">
                        <span class="font-semibold flex-shrink-0" :style="i.level === 'erro' ? 'color:var(--red)' : 'color:var(--orange)'"
                              x-text="i.level === 'erro' ? 'Regra' : 'Falta'"></span>
                        <button type="button" class="text-left hover:underline" style="color:var(--text)" x-text="i.text"
                                @click="scrollTo(i.path ? 'f-' + i.path : 'sec-' + i.section)"></button>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Seções --}}
        <template x-for="s in cfg.sections" :key="s.key">
            <section :id="'sec-' + s.key" class="mb-6 pb-6" style="border-bottom:1px solid var(--border2); scroll-margin-top:80px">
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-xs font-mono font-bold" style="color:var(--purple)" x-text="s.num"></span>
                    <h3 class="text-base font-bold" style="color:var(--text)" x-text="s.title"></h3>
                </div>
                <p class="text-xs mb-4" style="color:var(--muted); max-width:80ch" x-text="s.help"></p>

                <div class="grid gap-4" style="grid-template-columns:repeat(2, minmax(0, 1fr))">
                    <template x-for="b in s.blocks" :key="b.key">
                        <div :style="b.type === 'field' && b.half ? '' : 'grid-column:1 / -1'">

                            {{-- Campo simples --}}
                            <template x-if="b.type === 'field'">
                                <div :id="'f-' + b.key" style="scroll-margin-top:90px">
                                    @include('documents._field', ['fieldExpr' => 'b', 'pathExpr' => 'b.key', 'valueExpr' => 'state.content[b.key]', 'itemExpr' => 'null'])
                                </div>
                            </template>

                            {{-- Lista (componente repetível) --}}
                            <template x-if="b.type === 'list'">
                                <div class="space-y-3">
                                    <template x-for="(item, idx) in (state.content[b.key] || [])" :key="item.id">
                                        <div class="p-4" style="border:1px solid var(--border2); border-radius:10px; background:var(--s1)">
                                            <div class="flex items-center gap-2 mb-3 flex-wrap">
                                                <template x-if="b.switch">
                                                    <span class="text-[10px] font-mono font-semibold uppercase px-2 py-0.5 rounded-full"
                                                          :style="item[b.switch] === 'projeto' ? 'background:rgba(100,59,142,.12); color:var(--purple)' : 'background:rgba(238,121,25,.12); color:var(--orange)'"
                                                          x-text="optionLabel(b, item[b.switch])"></span>
                                                </template>
                                                <span class="text-sm font-semibold" style="color:var(--text)"
                                                      x-text="item[b.title_field] || (b.item_label + ' ' + (idx + 1))"></span>
                                                <template x-if="itemOrigin(b.key, item.id)">
                                                    <span class="text-[10px] font-semibold px-1.5 rounded" style="background:rgba(100,59,142,.1); color:var(--purple)" x-text="itemOrigin(b.key, item.id)"></span>
                                                </template>
                                                <template x-if="cfg.itemLinks[b.key] && cfg.itemLinks[b.key][item.id]">
                                                    <a :href="cfg.itemLinks[b.key][item.id][0]" class="text-xs font-semibold hover:underline" style="color:var(--purple)"
                                                       x-text="cfg.itemLinks[b.key][item.id][1]"></a>
                                                </template>
                                                <div class="ml-auto flex items-center gap-1">
                                                    <button type="button" class="btn btn-ghost btn-xs" title="Subir" :disabled="idx === 0" @click="listOp(b.key, 'up', item.id)">↑</button>
                                                    <button type="button" class="btn btn-ghost btn-xs" title="Descer" :disabled="idx === state.content[b.key].length - 1" @click="listOp(b.key, 'down', item.id)">↓</button>
                                                    <div class="relative" x-data="{ menu: false }" @click.outside="menu = false">
                                                        <button type="button" class="btn btn-ghost btn-xs" title="Mais ações" @click="menu = !menu">⋯</button>
                                                        <div x-show="menu" x-cloak class="absolute right-0 mt-1 z-20 py-1 text-xs" style="min-width:180px; background:var(--s1); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12)">
                                                            <button type="button" class="block w-full text-left px-3 py-1.5 hover:bg-black/5" style="color:var(--text)" @click="menu = false; listOp(b.key, 'duplicate', item.id)">Duplicar</button>
                                                            <template x-if="b.switch">
                                                                <template x-for="opt in otherOptions(b, item[b.switch])" :key="opt.value">
                                                                    <button type="button" class="block w-full text-left px-3 py-1.5 hover:bg-black/5" style="color:var(--text)"
                                                                            @click="menu = false; listOp(b.key, 'tipo', item.id, { [b.switch]: opt.value })" x-text="'Trocar para ' + opt.label.toLowerCase()"></button>
                                                                </template>
                                                            </template>
                                                            <button type="button" class="block w-full text-left px-3 py-1.5 hover:bg-black/5" style="color:var(--red)"
                                                                    @click="menu = false; removeItem(b, item)">Remover</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid gap-3" style="grid-template-columns:repeat(2, minmax(0, 1fr))">
                                                <template x-for="f in b.fields.filter(f => f.key !== b.switch && applies(f, item))" :key="f.key">
                                                    <div :id="'f-' + b.key + '.' + item.id + '.' + f.key" :style="(f.half ? '' : 'grid-column:1 / -1') + '; scroll-margin-top:90px'">
                                                        @include('documents._field', ['fieldExpr' => 'f', 'pathExpr' => "b.key + '.' + item.id + '.' + f.key", 'valueExpr' => 'item[f.key]', 'itemExpr' => 'item'])
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="flex gap-2 flex-wrap">
                                        <template x-for="a in b.add" :key="a.label">
                                            <button type="button" class="btn btn-ghost btn-xs" @click="listOp(b.key, 'add', null, a.attrs)" x-text="'+ ' + a.label"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </section>
        </template>
    </div>
</div>

@once
<script>
function fieldDocEditor(cfg) {
    return {
        cfg,
        state: cfg.payload,
        view: 'editar',
        clientSrc: '',
        saving: false,
        error: '',
        savedLabel: '',
        issuesOpen: false,
        timers: {},

        statusLabel(s) { return { vazia: 'vazia', erro: 'regra', falta: 'falta', pronta: 'pronta', opcional: 'opcional' }[s] || ''; },
        statusStyle(s) {
            return {
                vazia:  'background:var(--s3); color:var(--muted)',
                opcional: 'background:var(--s3); color:var(--muted2)',
                erro:   'background:rgba(239,68,68,.12); color:var(--red)',
                falta:  'background:rgba(238,121,25,.12); color:var(--orange)',
                pronta: 'background:rgba(52,211,153,.14); color:var(--green)',
            }[s] || '';
        },
        issuesTitle() {
            const regras = this.state.issues.filter(i => i.level === 'erro').length;
            const faltas = this.state.issues.length - regras;
            const parts = [];
            if (regras) parts.push(regras + (regras === 1 ? ' regra do modelo quebrada' : ' regras do modelo quebradas'));
            if (faltas) parts.push(faltas + (faltas === 1 ? ' coisa faltando' : ' coisas faltando'));
            return parts.join(' · ') + (regras ? ' (corrija as regras antes de seguir)' : '');
        },
        applies(f, item) {
            return Object.entries(f.only || {}).every(([k, v]) => item && item[k] === v);
        },
        optionLabel(b, value) {
            const f = b.fields.find(f => f.key === b.switch);
            return f && f.options ? (f.options[value] || value) : value;
        },
        otherOptions(b, current) {
            const f = b.fields.find(f => f.key === b.switch);
            return Object.entries(f.options).filter(([v]) => v !== current).map(([value, label]) => ({ value, label }));
        },
        refOptions(f, item) {
            return (this.state.content[f.refs] || []).filter(o => o.id !== (item && item.id)
                && Object.entries(f.refs_filter || {}).every(([k, v]) => o[k] === v));
        },
        refLabel(o) {
            return o.nome || o.o_que || o.area || o.plataforma || 'Sem nome';
        },
        // Quem escreveu o campo (some quando uma pessoa edita: aí o campo é dela).
        originLabel(path) {
            const by = ((this.state.provenance || {})[path] || {}).by;
            return { agente: 'escrito pelo agente', planejamento: 'veio do planejamento' }[by] || '';
        },
        // Cartão escrito pelo agente: some quando uma pessoa mexe em qualquer campo dele.
        itemOrigin(list, id) {
            const prefix = list + '.' + id + '.';
            const marks = Object.entries(this.state.provenance || {}).filter(([k]) => k.startsWith(prefix)).map(([, v]) => v.by);
            if (!marks.length || marks.includes('pessoa')) return '';
            return marks.includes('agente') ? 'escrito pelo agente' : '';
        },
        hasIssue(path) {
            return this.state.issues.some(i => i.path === path);
        },
        scrollTo(id) {
            const el = document.getElementById(id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        showClient() {
            this.view = 'cliente';
            this.clientSrc = this.cfg.renderUrl + '?t=' + Date.now();
        },
        grow(el) {
            el.style.height = 'auto';
            el.style.height = (el.scrollHeight + 2) + 'px';
        },

        // Campo: guarda o valor local na hora e salva depois de uma pausa (ou ao sair do campo).
        edit(path, value, item, key) {
            if (item) item[key] = value; else this.state.content[path] = value;
            clearTimeout(this.timers[path]);
            this.timers[path] = setTimeout(() => this.save(path, value), 1200);
        },
        flush(path, value) {
            if (this.timers[path] === undefined) return;
            clearTimeout(this.timers[path]);
            delete this.timers[path];
            this.save(path, value);
        },
        toggleRef(path, item, key, id) {
            const list = item[key] || [];
            item[key] = list.includes(id) ? list.filter(x => x !== id) : [...list, id];
            this.save(path, item[key], true);
        },

        async save(path, value, replace = false) {
            delete this.timers[path];
            const data = await this.send(this.cfg.fieldUrl, 'PATCH', { path, value });
            if (!data) return;
            // Campo: não sobrescreve o conteúdo local (a pessoa pode estar digitando em outro
            // campo); só avisos, situação das seções e o que o servidor ajustou (refs).
            this.state.issues = data.issues;
            this.state.statuses = data.statuses;
            this.state.blocking = data.blocking;
            this.state.provenance = data.provenance;
            if (replace) this.state.content = data.content;
        },
        async listOp(list, op, item = null, attrs = {}) {
            // Antes de mexer na lista, salva o que ainda está esperando a pausa.
            for (const p of Object.keys(this.timers)) {
                clearTimeout(this.timers[p]);
                delete this.timers[p];
            }
            const data = await this.send(this.cfg.listUrl, 'POST', { list, op, item, attrs });
            if (data) this.state = data;
        },
        async removeItem(b, item) {
            const nome = item[b.title_field] || b.item_label;
            if (!(await Alpine.store('confirmDialog').ask('Remover "' + nome + '"?'))) return;
            this.listOp(b.key, 'remove', item.id);
        },
        async send(url, method, body) {
            this.saving = true;
            this.error = '';
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = data.error || data.message || 'Não foi possível salvar. Tente de novo.';
                    return null;
                }
                this.savedLabel = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                return data;
            } catch (e) {
                this.error = 'Sem conexão: o último campo não foi salvo. Tente de novo.';
                return null;
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endonce
