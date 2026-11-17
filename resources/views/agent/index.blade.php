{{-- Agente Nonna — conversa com IA em página inteira (estilo ChatGPT/Claude).
     Lista de conversas à esquerda; conversa no centro; caixa de texto embaixo.
     O agente consulta o App por ferramentas e PROPÕE ações em cartões que a pessoa
     confirma (ver App\Services\Agent\*). --}}
<x-app-layout>
    <style>
        .agent-md { font-size: 14.5px; line-height: 1.65; color: var(--text); overflow-wrap: anywhere; }
        .agent-md > * + * { margin-top: .7em; }
        .agent-md ul { list-style: disc; padding-left: 1.3em; }
        .agent-md ol { list-style: decimal; padding-left: 1.3em; }
        .agent-md li + li { margin-top: .25em; }
        .agent-md a { color: var(--purple); text-decoration: underline; text-underline-offset: 2px; }
        .agent-md strong { font-weight: 600; }
        .agent-md h1, .agent-md h2, .agent-md h3, .agent-md h4 { font-weight: 600; font-size: 15px; margin-top: 1.1em; }
        .agent-md code { background: var(--s2); padding: 1px 5px; border-radius: 4px; font-size: .9em; }
        .agent-md table { border-collapse: collapse; font-size: 13px; display: block; overflow-x: auto; }
        .agent-md th, .agent-md td { border: 1px solid var(--border2); padding: 5px 9px; text-align: left; }
        .agent-md th { background: var(--s2); font-weight: 600; }
        .agent-dots span { width: 6px; height: 6px; border-radius: 50%; background: var(--muted); display: inline-block; animation: agentDot 1.2s infinite ease-in-out; }
        .agent-dots span:nth-child(2) { animation-delay: .15s; } .agent-dots span:nth-child(3) { animation-delay: .3s; }
        @keyframes agentDot { 0%, 80%, 100% { opacity: .25; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
    </style>

    <div x-data="agentChat(@js([
            'conversations' => $conversations,
            'current'       => $current,
            'messages'      => $messages,
            'userName'      => \Illuminate\Support\Str::of(auth()->user()->name)->before(' '),
            'urls'          => [
                'index'   => route('agent.index'),
                'send'    => route('agent.send'),
                'show'    => url('/agente'),
            ],
        ]))"
         class="grid grid-cols-1 md:grid-cols-[260px_1fr] overflow-hidden rounded-[10px]"
         style="height:calc(100vh - 8rem); background:var(--s1); border:1px solid var(--border2)">

        {{-- ── Lista de conversas ── --}}
        <aside class="min-h-0 flex-col" style="border-right:1px solid var(--border2); background:var(--s2)"
               :class="listOpen ? 'flex' : 'hidden md:flex'">
            <div class="p-3">
                <button type="button" @click="newConversation()" class="btn w-full justify-center gap-2" style="background:var(--s1)">
                    <x-icon name="plus" size="15" /> Nova conversa
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto px-2 pb-3">
                <template x-if="conversations.length === 0">
                    <p class="px-2 py-4 text-xs" style="color:var(--muted)">Suas conversas aparecem aqui.</p>
                </template>
                <template x-for="c in conversations" :key="c.id">
                    <div class="group flex items-center rounded-[7px] mb-0.5"
                         :style="c.id === current ? 'background:var(--s1); box-shadow:var(--shadow)' : ''">
                        <a :href="urls.show + '/' + c.id" @click.prevent="openConversation(c.id)"
                           class="flex-1 min-w-0 px-2.5 py-2 text-[13px]">
                            <span class="block truncate" :style="c.id === current ? 'color:var(--text); font-weight:500' : 'color:var(--muted2)'" x-text="c.title"></span>
                        </a>
                        <button type="button" title="Apagar conversa"
                                class="p-1.5 mr-1 opacity-0 group-hover:opacity-100 transition-opacity" style="color:var(--muted)"
                                @click="if (await $store.confirmDialog.ask('Apagar esta conversa? O que já foi criado (tarefas, reuniões) continua no App.')) removeConversation(c.id)">
                            <x-icon name="trash-2" size="14" />
                        </button>
                    </div>
                </template>
            </div>
        </aside>

        {{-- ── Conversa ── --}}
        <section class="min-h-0 flex-col" :class="listOpen ? 'hidden md:flex' : 'flex'">
            <div class="md:hidden flex items-center gap-2 px-3 py-2" style="border-bottom:1px solid var(--border2)">
                <button type="button" @click="listOpen = true" class="p-1.5" style="color:var(--muted2)"><x-icon name="menu" size="18" /></button>
                <span class="flex-1 truncate text-sm font-medium" x-text="currentTitle()"></span>
                <button type="button" @click="newConversation()" class="p-1.5" style="color:var(--muted2)"><x-icon name="plus" size="18" /></button>
            </div>

            <div x-ref="scroller" class="flex-1 min-h-0 overflow-y-auto">
                <div class="max-w-3xl mx-auto px-4 md:px-6 py-6">

                    {{-- Estado vazio --}}
                    <template x-if="items.length === 0 && !busy">
                        <div class="pt-[8vh] text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full text-white" style="background:var(--grad)">
                                <x-icon name="sparkles" size="22" />
                            </div>
                            <h1 class="mt-4 text-2xl font-semibold" style="color:var(--text)">Olá, <span x-text="userName"></span>. Em que posso ajudar?</h1>
                            <p class="mt-2 text-sm" style="color:var(--muted)">Pergunte sobre qualquer cliente, peça uma tarefa ou marque uma reunião.</p>
                            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-left">
                                <template x-for="s in suggestions" :key="s">
                                    <button type="button" @click="draft = s; send()"
                                            class="px-4 py-3 rounded-[10px] text-[13px] transition-colors hover:border-[var(--purple)]"
                                            style="border:1px solid var(--border2); background:var(--s1); color:var(--muted2)" x-text="s"></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Mensagens --}}
                    <template x-for="item in items" :key="item.id">
                        <div>
                            {{-- Pessoa --}}
                            <template x-if="item.kind === 'user'">
                                <div class="flex justify-end my-5">
                                    <div class="max-w-[85%] px-4 py-2.5 rounded-[18px] text-[14.5px] whitespace-pre-wrap break-words"
                                         style="background:var(--s2); color:var(--text)" x-text="item.text"></div>
                                </div>
                            </template>

                            {{-- O que o agente consultou --}}
                            <template x-if="item.kind === 'activity'">
                                <div class="flex items-center gap-2 my-1.5 text-xs" style="color:var(--muted)">
                                    <x-icon name="check" size="13" />
                                    <span x-text="item.label"></span>
                                </div>
                            </template>

                            {{-- Resposta --}}
                            <template x-if="item.kind === 'assistant'">
                                <div class="flex gap-3 my-5">
                                    <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-white mt-0.5" style="background:var(--grad)">
                                        <x-icon name="sparkles" size="14" />
                                    </div>
                                    <div class="agent-md min-w-0 flex-1" x-html="item.html"></div>
                                </div>
                            </template>

                            {{-- Proposta (tarefa/reunião) esperando confirmação --}}
                            <template x-if="item.kind === 'proposal'">
                                <div class="my-4 sm:ml-10 rounded-[10px] overflow-hidden" style="border:1px solid var(--border2); background:var(--s1)">
                                    <div class="flex items-center gap-2 px-4 py-2.5 text-[13px] font-semibold" style="background:var(--s2); color:var(--text)">
                                        <span x-show="item.type === 'meeting'"><x-icon name="calendar-plus" size="15" /></span>
                                        <span x-show="item.type !== 'meeting'"><x-icon name="clipboard-list" size="15" /></span>
                                        <span x-text="item.title"></span>
                                        <span class="ml-auto text-[11px] font-medium px-2 py-0.5 rounded-full"
                                              :style="item.status === 'confirmed' ? 'background:rgba(16,185,129,.12); color:var(--green)'
                                                    : item.status === 'discarded' ? 'background:var(--s3); color:var(--muted)'
                                                    : 'background:rgba(238,121,25,.12); color:var(--orange)'"
                                              x-text="item.status === 'confirmed' ? 'Criado' : item.status === 'discarded' ? 'Descartado' : 'Aguardando você'"></span>
                                    </div>
                                    <dl class="px-4 py-3 text-[13px] grid grid-cols-[110px_1fr] gap-x-3 gap-y-1.5">
                                        <template x-for="row in item.display" :key="row[0]">
                                            <div class="contents">
                                                <dt style="color:var(--muted)" x-text="row[0]"></dt>
                                                <dd class="whitespace-pre-wrap break-words" style="color:var(--text)" x-text="row[1]"></dd>
                                            </div>
                                        </template>
                                    </dl>
                                    <div class="flex items-center gap-2 px-4 py-2.5" style="border-top:1px solid var(--border2)">
                                        <template x-if="item.status === 'pending'">
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="btn btn-primary" :disabled="!!item.saving" @click="resolve(item, 'confirm')">
                                                    <span x-text="item.saving ? 'Criando…' : 'Confirmar'"></span>
                                                </button>
                                                <button type="button" class="btn" :disabled="!!item.saving" @click="resolve(item, 'discard')">Descartar</button>
                                                <span class="text-xs" style="color:var(--muted)">Quer mudar algo? É só pedir na conversa.</span>
                                            </div>
                                        </template>
                                        <template x-if="item.status === 'confirmed' && item.result">
                                            <a :href="item.result.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-[13px] font-medium" style="color:var(--purple)">
                                                <span x-text="item.result.label"></span> <x-icon name="external-link" size="13" />
                                            </a>
                                        </template>
                                        <template x-if="item.status === 'discarded'">
                                            <span class="text-xs" style="color:var(--muted)">Nada foi criado.</span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="item.kind === 'error'">
                                <div class="my-4 sm:ml-10 px-4 py-3 rounded-[10px] text-[13px]" style="background:rgba(220,38,38,.08); color:var(--red)" x-text="item.text"></div>
                            </template>
                        </div>
                    </template>

                    <div x-show="busy" class="flex gap-3 my-5 items-center">
                        <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-white" style="background:var(--grad)">
                            <x-icon name="sparkles" size="14" />
                        </div>
                        <div class="agent-dots flex gap-1"><span></span><span></span><span></span></div>
                        <span class="text-xs" style="color:var(--muted)">Consultando o App…</span>
                    </div>
                </div>
            </div>

            {{-- Caixa de texto --}}
            {{-- pr maior no celular: a bolha flutuante do Chat fica no canto e cobriria o "enviar" --}}
            <div class="pl-4 pr-[4.75rem] md:px-6 pb-4 pt-2">
                <form class="max-w-3xl mx-auto" @submit.prevent="send()">
                    <div class="flex items-end gap-2 rounded-[22px] pl-4 pr-2 py-2 transition-shadow focus-within:shadow-md"
                         style="border:1px solid var(--border2); background:var(--s1)">
                        <textarea x-ref="input" x-model="draft" rows="1" maxlength="20000"
                                  @input="grow()" @keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); send(); }"
                                  placeholder="Pergunte ao Agente Nonna…"
                                  class="flex-1 resize-none bg-transparent border-0 focus:ring-0 p-0 py-1.5 text-[14.5px] max-h-[200px]"
                                  style="color:var(--text); outline:none; box-shadow:none"></textarea>
                        <button type="submit" :disabled="busy || !draft.trim()"
                                class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-white transition-opacity disabled:opacity-30"
                                style="background:var(--purple)" title="Enviar (Enter)">
                            <x-icon name="arrow-up" size="18" />
                        </button>
                    </div>
                    <p class="mt-1.5 text-center text-[11px]" style="color:var(--muted)">
                        O agente pode errar. Nada é criado sem você clicar em "Confirmar".
                    </p>
                </form>
            </div>
        </section>
    </div>

    <script>
        function agentChat(cfg) {
            return {
                conversations: cfg.conversations,
                current: cfg.current,
                items: cfg.messages,
                userName: cfg.userName,
                urls: cfg.urls,
                draft: '',
                busy: false,
                listOpen: false,
                suggestions: [
                    'Como está o cliente … ? Me dá um panorama.',
                    'Quais tarefas estão atrasadas hoje?',
                    'O que ficou decidido na última reunião com o cliente … ?',
                    'Marca uma reunião de alinhamento amanhã às 10h com …',
                ],

                init() {
                    this.$nextTick(() => { this.scrollDown(); this.$refs.input.focus(); });
                    window.addEventListener('popstate', () => window.location.reload());
                },

                csrf() { return document.querySelector('meta[name=csrf-token]').content; },

                currentTitle() {
                    return (this.conversations.find(c => c.id === this.current) || {}).title || 'Agente Nonna';
                },

                grow() {
                    const el = this.$refs.input;
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 200) + 'px';
                },

                scrollDown() {
                    this.$nextTick(() => { const s = this.$refs.scroller; s.scrollTop = s.scrollHeight; });
                },

                newConversation() {
                    if (this.busy) return;
                    this.current = null;
                    this.items = [];
                    this.listOpen = false;
                    history.pushState({}, '', this.urls.index);
                    this.$nextTick(() => this.$refs.input.focus());
                },

                openConversation(id) {
                    if (this.busy) return;
                    window.location.href = this.urls.show + '/' + id;
                },

                async send() {
                    const text = this.draft.trim();
                    if (!text || this.busy) return;

                    // Sugestão com "…" = modelo pra completar, não manda direto.
                    if (text.includes('…')) {
                        this.$refs.input.focus();
                        const at = text.indexOf('…');
                        this.$nextTick(() => this.$refs.input.setSelectionRange(at, at + 1));
                        return;
                    }

                    this.items.push({ id: 'u' + Date.now(), kind: 'user', text });
                    this.draft = '';
                    this.$nextTick(() => this.grow());
                    this.busy = true;
                    this.scrollDown();

                    try {
                        const res = await fetch(this.urls.send, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify({ message: text, conversation_id: this.current }),
                        });
                        const data = await res.json().catch(() => ({}));

                        if (data.conversation_id && data.conversation_id !== this.current) {
                            this.current = data.conversation_id;
                            history.pushState({}, '', this.urls.show + '/' + this.current);
                        }
                        if (this.current) {
                            const existing = this.conversations.find(c => c.id === this.current);
                            const title = data.title || (existing && existing.title) || text.slice(0, 70);
                            this.conversations = [{ id: this.current, title }, ...this.conversations.filter(c => c.id !== this.current)];
                        }

                        if (!res.ok) {
                            this.items.push({ id: 'e' + Date.now(), kind: 'error', text: data.error || data.message || 'O agente não conseguiu responder agora.' });
                        } else {
                            this.items.push(...data.items);
                        }
                    } catch (e) {
                        this.items.push({ id: 'e' + Date.now(), kind: 'error', text: 'Sem conexão com o App. Confira a internet e tente de novo.' });
                    } finally {
                        this.busy = false;
                        this.scrollDown();
                        this.$nextTick(() => this.$refs.input.focus());
                    }
                },

                async resolve(item, action) {
                    item.saving = true;
                    try {
                        const res = await fetch(`${this.urls.show}/propostas/${item.id}/${action === 'confirm' ? 'confirmar' : 'descartar'}`, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            this.items.push({ id: 'e' + Date.now(), kind: 'error', text: data.error || data.message || 'Não deu certo. Tente de novo.' });
                            this.scrollDown();
                            return;
                        }
                        Object.assign(item, data.item);
                    } catch (e) {
                        this.items.push({ id: 'e' + Date.now(), kind: 'error', text: 'Sem conexão com o App. Confira a internet e tente de novo.' });
                        this.scrollDown();
                    } finally {
                        item.saving = false;
                    }
                },

                async removeConversation(id) {
                    const res = await fetch(`${this.urls.show}/${id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    this.conversations = this.conversations.filter(c => c.id !== id);
                    if (id === this.current) this.newConversation();
                },
            };
        }
    </script>
</x-app-layout>
