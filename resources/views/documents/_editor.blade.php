{{-- Editor de documento em campos (23/11) — genérico: tudo vem do modelo do tipo
     (App\Services\Documents\DocumentSchema). Cada campo salva sozinho (DocumentEditor);
     a lista "O que falta" e a situação de cada seção são calculadas no servidor.
     Recebe: $document (App\Models\Document); opcional $itemLinks (lista => [item id => [url, rótulo]]). --}}
@php
    $schema  = $document->schema();
    $payload = app(\App\Services\Documents\DocumentEditor::class)->payload($document);
    // Reuniões do cliente com ATA, para o agente conferir (gaveta do Agente Nonna).
    $agentMeetings = \App\Models\Meeting::where('client_id', $document->client_id)->whereNotNull('ata')
        ->orderByDesc('scheduled_at')->limit(10)->get(['id', 'title', 'scheduled_at'])
        ->map(fn ($m) => ['id' => $m->id, 'label' => $m->scheduled_at?->format('d/m/Y') . ' · ' . $m->title])->values();
@endphp

<div x-data="fieldDocEditor(@js([
        'sections'  => $schema->sections(),
        'payload'   => $payload,
        'fieldUrl'  => route('documents.field', $document),
        'listUrl'   => route('documents.list', $document),
        'renderUrl' => route('documents.render', $document),
        'itemLinks' => $itemLinks ?? (object) [],
        'askUrl'    => route('documents.agent.ask', $document),
        'decideUrl' => url('/documentos/' . $document->id . '/agente'),
        'chat'      => $document->agent_state['chat'] ?? [],
        'meetings'  => $agentMeetings,
    ]))" class="fdoc"
     x-init="try { const v = localStorage.getItem('fdocAgent'); agentOpen = v === null ? true : v === '1'; } catch (e) {}
             $watch('agentOpen', v => { try { localStorage.setItem('fdocAgent', v ? '1' : '0'); } catch (e) {} if (v) $nextTick(() => scrollChat()); });
             $nextTick(() => scrollChat())">
    {{-- Agente Nonna em coluna (24/11, pedido do Evandro: gaveta que abre e fecha cansava). Tela larga:
         coluna à direita do documento, que acompanha a rolagem. Tela menor: painel logo abaixo do
         cabeçalho. Abre/fecha pelo botão; a escolha fica guardada no navegador. --}}
    <style>
        /* Mesa do documento (protótipo de 23/11): seções · documento · agente. */
        .fdoc-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
        .fdoc-nav { position: static; }
        .fdoc-agent { display: flex; flex-direction: column; max-height: 75vh; overflow: hidden; }
        @media (min-width: 64rem) {
            .fdoc-grid { grid-template-columns: 210px minmax(0, 1fr); }
            .fdoc-nav { position: sticky; top: .75rem; grid-row: span 2; }
            .fdoc-agent { grid-column: 2; grid-row: 1; }
        }
        @media (min-width: 90rem) {
            .fdoc-grid { grid-template-columns: 210px minmax(0, 1fr) 390px; }
            .fdoc-grid.sem-agente { grid-template-columns: 210px minmax(0, 1fr); }
            .fdoc-nav { grid-row: auto; }
            .fdoc-agent { grid-column: 3; grid-row: 1; position: sticky; top: .75rem; height: calc(100vh - 9rem); max-height: none; }
        }
    </style>

    {{-- Cabeçalho --}}
    <div class="card mb-4 px-5 py-3 flex items-center justify-between gap-3 flex-wrap">
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
            <button type="button" class="btn btn-primary btn-xs flex items-center gap-1" @click="agentOpen = !agentOpen"
                    :title="agentOpen ? 'Esconder o Agente Nonna' : 'Mostrar o Agente Nonna ao lado do documento'">
                <x-icon name="sparkles" size="12" /> Agente Nonna
                <span x-show="pendingCount()" x-cloak class="ml-1 px-1.5 rounded-full text-[10px]" style="background:var(--orange); color:#fff" x-text="pendingCount()"></span>
            </button>
        </div>
    </div>

    <div class="fdoc-grid" :class="agentOpen ? '' : 'sem-agente'">

    {{-- SEÇÕES (esquerda): situação de cada uma + progresso + o que falta --}}
    <nav class="fdoc-nav card py-2" aria-label="Seções do documento">
        <p class="px-4 pt-2 pb-1 text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Seções</p>
        <ol class="px-2 space-y-0.5">
            <template x-for="s in cfg.sections" :key="s.key">
                <li>
                    <button type="button" class="w-full text-left px-2 py-1.5 rounded-lg grid gap-x-2 gap-y-0.5 hover:bg-black/5"
                            style="grid-template-columns:20px minmax(0,1fr)" @click="openSection(s.key)">
                        <span class="text-xs font-mono font-semibold" style="color:var(--muted)" x-text="s.num || '··'"></span>
                        <span class="text-xs font-medium leading-snug" style="color:var(--text)" x-text="s.title"></span>
                        <span></span>
                        <span style="justify-self:start"><span class="font-semibold px-1.5 rounded-full text-[10px]"
                              :style="statusStyle(state.statuses[s.key])" x-text="statusLabel(state.statuses[s.key])"></span></span>
                    </button>
                </li>
            </template>
        </ol>
        <div class="px-4 pt-3 pb-2 text-xs" style="color:var(--muted)">
            <span x-text="readyCount() + ' de ' + cfg.sections.length + ' seções prontas'"></span>
            <div class="mt-1.5 h-1.5 rounded-full overflow-hidden" style="background:var(--border2)">
                <div class="h-full" style="background:var(--purple)" :style="{ width: Math.round(100 * readyCount() / cfg.sections.length) + '%' }"></div>
            </div>
        </div>
        <div class="px-2 pb-1">
        {{-- O que falta --}}
        <div x-show="state.issues.length" class="px-3 py-2 text-xs" style="border-radius:8px"
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
                                @click="collapsed[i.section] = false; $nextTick(() => scrollTo(i.path ? 'f-' + i.path : 'sec-' + i.section))"></button>
                    </li>
                </template>
            </ul>
        </div>

        </div>
    </nav>

    {{-- DOCUMENTO (centro) --}}
    <div class="fdoc-main card min-w-0">
    {{-- Como o cliente vê --}}
    <div x-show="view === 'cliente'" x-cloak>
        <iframe :src="clientSrc" title="Versão do cliente" style="width:100%; height:78vh; border:0; background:#F6F7FB; display:block"></iframe>
    </div>

    {{-- Editar --}}
    <div x-show="view === 'editar'" class="p-5">

        {{-- Seções --}}
        <template x-for="s in cfg.sections" :key="s.key">
            <section :id="'sec-' + s.key" class="mb-5 pb-5" style="border-bottom:1px solid var(--border2); scroll-margin-top:80px">
                <button type="button" class="w-full flex items-baseline gap-2 text-left" @click="collapsed[s.key] = !collapsed[s.key]"
                        :title="collapsed[s.key] ? 'Abrir a seção' : 'Recolher a seção'">
                    <span class="text-xs font-mono font-bold" style="color:var(--purple)" x-text="s.num"></span>
                    <h3 class="text-base font-bold" style="color:var(--text)" x-text="s.title"></h3>
                    <span class="font-semibold px-1.5 rounded-full text-[10px]" :style="statusStyle(state.statuses[s.key])" x-text="statusLabel(state.statuses[s.key])"></span>
                    <span class="ml-auto text-xs" style="color:var(--muted)" x-text="collapsed[s.key] ? 'Abrir' : 'Recolher'"></span>
                </button>
                <div x-show="!collapsed[s.key]">
                <p class="text-xs mt-1 mb-4" style="color:var(--muted); max-width:80ch" x-text="s.help"></p>

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
                </div>
            </section>
        </template>
        {{-- Próximo passo (cada página manda o seu: criar campanhas, peças viram tarefas...) --}}
        @isset($docFooter){!! $docFooter !!}@endisset
    </div>
    </div>{{-- /fdoc-main --}}

    {{-- Agente Nonna (DocumentAssistant): conversa + propostas com Aceitar/Descartar --}}
    <aside class="fdoc-agent card" x-show="agentOpen" x-cloak>
            <div class="px-4 py-3 flex items-center justify-between gap-2" style="border-bottom:1px solid var(--border2)">
                <div>
                    <p class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)"><x-icon name="sparkles" size="14" style="color:var(--purple)" /> Agente Nonna</p>
                    <p class="text-xs" style="color:var(--muted)">Ele propõe; você aceita ou descarta. A conversa fica no documento, para a equipe.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-xs" @click="agentOpen = false" title="Esconder (o botão Agente Nonna mostra de novo)">Esconder</button>
            </div>

            <div x-ref="chatBox" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                @isset($agentTop)<div class="fdoc-agent-top">{!! $agentTop !!}</div>@endisset
                <template x-if="!chat.length">
                    <div class="text-xs space-y-2" style="color:var(--muted)">
                        <p>Peça em palavras. Por exemplo:</p>
                        <p class="px-2 py-1.5 rounded" style="background:var(--s2)">"Junta as duas campanhas de captação numa só."</p>
                        <p class="px-2 py-1.5 rounded" style="background:var(--s2)">"Confere a reunião e me diz o que mudou." (escolha a reunião logo abaixo)</p>
                        <p class="px-2 py-1.5 rounded" style="background:var(--s2)">"Reescreve o foco do ciclo de um jeito mais simples."</p>
                    </div>
                </template>
                <template x-for="(m, mi) in chat" :key="mi">
                    <div :class="m.role === 'user' ? 'ml-8' : 'mr-4'">
                        <div class="text-[11px] mb-0.5" style="color:var(--muted)" x-text="m.role === 'user' ? (m.name + (m.meeting ? ' · reunião ' + m.meeting : '')) : 'Agente Nonna'"></div>
                        <div class="text-sm px-3 py-2 rounded-lg whitespace-pre-line" x-text="m.text"
                             :style="m.role === 'user' ? 'background:rgba(100,59,142,.10); color:var(--text)' : 'background:var(--s2); color:var(--text)'"></div>
                        <template x-for="sg in (m.sugestoes || [])" :key="sg.id">
                            <div class="mt-2 p-3 rounded-lg text-xs" style="border:1.5px dashed var(--orange); background:rgba(238,121,25,.05)"
                                 :style="sg.status !== 'pendente' ? 'opacity:.6; border-style:solid; border-color:var(--border2)' : ''">
                                <p class="font-semibold text-sm" style="color:var(--text)" x-text="sg.resumo"></p>
                                <p x-show="sg.motivo" class="mt-0.5" style="color:var(--muted)" x-text="'Por quê: ' + sg.motivo"></p>
                                <ul class="mt-2 space-y-1">
                                    <template x-for="(op, oi) in sg.ops" :key="oi">
                                        <li class="flex gap-1.5" style="color:var(--text)"><span style="color:var(--orange)">•</span><span x-text="describeOp(op)"></span></li>
                                    </template>
                                </ul>
                                <div class="mt-2 flex items-center gap-2" x-show="sg.status === 'pendente'">
                                    <button type="button" class="btn btn-primary btn-xs" :disabled="busy" @click="decide(sg.id, true)">Aceitar</button>
                                    <button type="button" class="btn btn-ghost btn-xs" :disabled="busy" @click="decide(sg.id, false)">Descartar</button>
                                </div>
                                <p x-show="sg.status !== 'pendente'" class="mt-2 font-semibold" style="color:var(--muted)"
                                   x-text="(sg.status === 'aceita' ? 'Aceita' : 'Descartada') + (sg.by ? ' por ' + sg.by : '')"></p>
                            </div>
                        </template>
                    </div>
                </template>
                <div x-show="busy" x-cloak class="text-xs flex items-center gap-2" style="color:var(--muted)">
                    <span class="inline-block w-3 h-3 rounded-full animate-spin" style="border:2px solid rgba(100,59,142,.25); border-top-color:var(--purple)"></span>
                    O agente está pensando… (de 10 a 40 segundos)
                </div>
                <p x-show="agentError" x-cloak class="text-xs" style="color:var(--red)" x-text="agentError"></p>
            </div>

            <form class="px-4 py-3 space-y-2" style="border-top:1px solid var(--border2)" @submit.prevent="ask()">
                <div x-show="cfg.meetings.length">
                    <label class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Ler também uma reunião?</label>
                    <select x-model="meetingId" class="field w-full px-2 py-1.5 text-xs mt-1">
                        <option value="">Não: responder só com o documento</option>
                        <template x-for="mt in cfg.meetings" :key="mt.id">
                            <option :value="mt.id" x-text="'Sim: ' + mt.label"></option>
                        </template>
                    </select>
                    <p class="text-[11px] mt-1" style="color:var(--muted2)">Escolha uma reunião quando o pedido depender dela: o agente lê a ATA e os trechos da transcrição ligados ao que você pediu (ex.: "o que a reunião mudou no plano?").</p>
                </div>
                <textarea x-model="question" rows="3" class="field w-full px-3 py-2 text-sm" placeholder="Peça ou pergunte…"
                          @keydown.enter.prevent="if (!$event.shiftKey) ask(); else question += '\n'"></textarea>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary btn-xs" :disabled="busy || !question.trim()">Enviar</button>
                    <span class="text-[11px]" style="color:var(--muted2)">Enter envia · Shift+Enter pula linha</span>
                </div>
            </form>
    </aside>
    </div>{{-- /fdoc-grid --}}
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
        agentOpen: true,
        collapsed: Object.fromEntries(cfg.sections.map(s => [s.key, cfg.payload.statuses[s.key] === 'pronta'])),

        readyCount() {
            return this.cfg.sections.filter(s => ['pronta', 'opcional'].includes(this.state.statuses[s.key])).length;
        },
        openSection(key) {
            this.collapsed[key] = false;
            this.$nextTick(() => this.scrollTo('sec-' + key));
        },
        chat: cfg.chat || [],
        question: '',
        meetingId: '',
        busy: false,
        agentError: '',

        pendingCount() {
            return this.chat.reduce((n, m) => n + (m.sugestoes || []).filter(s => s.status === 'pendente').length, 0);
        },
        scrollChat() {
            const box = this.$refs.chatBox;
            if (box) box.scrollTop = box.scrollHeight;
        },
        // Rótulo humano de um caminho ("metas.ab12.alvo" -> "Metas do ciclo · Pedidos · Quanto").
        pathLabel(path) {
            const [key, id, field] = String(path || '').split('.');
            for (const s of this.cfg.sections) {
                for (const b of s.blocks) {
                    if (b.key !== key) continue;
                    if (b.type === 'field') return b.label;
                    const item = (this.state.content[key] || []).find(i => i.id === id);
                    const f = (b.fields || []).find(f => f.key === field);
                    const name = item ? (item[b.title_field] || b.item_label) : b.item_label;
                    return name + (f ? ' · ' + f.label : '');
                }
            }
            return path;
        },
        valueLabel(v) {
            if (Array.isArray(v)) {
                const all = Object.values(this.state.content).filter(Array.isArray).flat();
                return v.map(id => { const it = all.find(i => i && i.id === id); return it ? (it.nome || it.o_que || id) : id; }).join(', ') || '(nada)';
            }
            if (v === null || v === '') return '(vazio)';
            return String(v).length > 140 ? String(v).slice(0, 140) + '…' : String(v);
        },
        describeOp(op) {
            if (op.op === 'set') return this.pathLabel(op.path) + ' → ' + this.valueLabel(op.value);
            if (op.op === 'remove') return 'Remover: ' + this.pathLabel(op.list + '.' + op.item + '.');
            if (op.op === 'tipo') return 'Trocar para ' + op.value + ': ' + this.pathLabel(op.list + '.' + op.item + '.');
            if (op.op === 'add') {
                const vals = op.values || {};
                return 'Adicionar em ' + (this.cfg.sections.flatMap(s => s.blocks).find(b => b.key === op.list)?.item_label || op.list) + ': ' + (vals.nome || vals.o_que || vals.area || vals.se || vals.plataforma || '(novo)');
            }
            return op.op;
        },
        async ask() {
            const message = this.question.trim();
            if (!message || this.busy) return;
            this.busy = true; this.agentError = '';
            this.chat.push({ role: 'user', name: 'Você', text: message });
            this.question = '';
            this.$nextTick(() => this.scrollChat());
            const data = await this.agentSend(this.cfg.askUrl, { message, meeting_id: this.meetingId || null });
            if (data) this.chat = data.chat; else this.chat.pop();
            this.busy = false;
            this.$nextTick(() => this.scrollChat());
        },
        async decide(id, accept) {
            if (this.busy) return;
            this.busy = true; this.agentError = '';
            const data = await this.agentSend(this.cfg.decideUrl + '/' + id, { accept });
            if (data) {
                this.chat = data.chat;
                this.state = { content: data.content, provenance: data.provenance, issues: data.issues, statuses: data.statuses, blocking: data.blocking };
                if (data.skipped && data.skipped.length) this.agentError = 'Parte não entrou: ' + data.skipped.join(' · ');
            }
            this.busy = false;
        },
        async agentSend(url, body) {
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body) });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.agentError = data.error || data.message || 'Não deu certo. Tente de novo.'; return null; }
                return data;
            } catch (e) { this.agentError = 'Sem conexão. Tente de novo.'; return null; }
        },

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
