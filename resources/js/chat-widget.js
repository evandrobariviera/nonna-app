// Chat interno — widget flutuante (todas as telas internas) e tela cheia (/chat).
// Os dois usam este mesmo componente; muda só o "mode". Ver
// .claude/docs/internal-chat-plan.md e resources/views/chat/_panel.blade.php.
//
// O app recarrega a página inteira a cada navegação, então o widget guarda em
// sessionStorage o que precisa sobreviver ao clique (aberto, conversa, rascunhos)
// e reabre igual na tela seguinte.
//
// "Tempo real" desta etapa = polling: estado geral a cada 10 s (30 s com a aba
// escondida) e mensagens da conversa aberta a cada 3 s. Etapa 2 troca por Reverb.

const STORAGE_KEY = 'nonnaChat';
const STATUS_MS = 10000;
const STATUS_HIDDEN_MS = 30000;
const MESSAGES_MS = 3000;
const MAX_FILE_MB = 50;

// Atalho "/" na caixa de mensagem: cita tarefa/projeto/campanha/cliente. No texto
// digitado aparece "@Título"; ao enviar vira [[tipo:uuid|Título]] (ver
// ChatMessage::REFERENCE_PATTERN), que o servidor desenha como chip clicável.
const REF_TYPES = [
    { key: 'tarefa',   label: 'Tarefa',   hint: 'Tarefas e chamados' },
    { key: 'projeto',  label: 'Projeto',  hint: 'Projetos dos planejamentos' },
    { key: 'campanha', label: 'Campanha', hint: 'Campanhas de mídia paga' },
    { key: 'cliente',  label: 'Cliente',  hint: 'Clientes ativos' },
];

function normalize(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// Mesmos headers do browser-notify.js: sem eles, se a sessão expirar, o Laravel
// trata o fetch como navegação e manda o usuário pro JSON depois do login.
async function api(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...(options.headers ?? {}),
        },
    });
    let data = null;
    try { data = await res.json(); } catch (e) { /* resposta sem corpo */ }
    if (!res.ok) {
        const err = new Error(data?.message ?? 'Erro de comunicação.');
        err.status = res.status;
        throw err;
    }
    return data;
}

function loadState() {
    try {
        return JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '{}') ?? {};
    } catch (e) {
        return {};
    }
}

export function registerChatWidget(Alpine) {
    // Contador global — o widget atualiza, o menu lateral só lê.
    Alpine.store('chat', { unreadTotal: 0 });

    Alpine.data('chatWidget', (config = {}) => ({
        mode: config.mode ?? 'widget', // widget | page
        open: false,
        view: 'list',                  // list | conversation | people
        conversations: [],
        listLoaded: false,
        people: [],
        search: '',
        active: null,                  // identidade da conversa aberta
        messages: [],
        hasMore: false,
        loadingMessages: false,
        loadingOlder: false,
        sending: false,
        draft: '',
        drafts: {},
        draftRefs: {},                 // conversa → [{ label: '@Título', token: '[[tipo:id|Título]]' }]
        refPicker: null,               // { step: 'type'|'search', start, end, query, index, type, search, results, loading }
        _refTimer: null,
        files: [],
        error: '',
        toast: null,
        dragging: false,
        readUpTo: 0,                   // conversa individual: até qual mensagem minha o outro leu (✓✓)
        latestId: null,
        _statusTimer: null,
        _messagesTimer: null,
        _toastTimer: null,

        // ── Ciclo de vida ────────────────────────────────────────────────

        init() {
            const saved = loadState();
            this.drafts = saved.drafts ?? {};
            this.draftRefs = saved.draftRefs ?? {};

            if (this.mode === 'page') {
                this.open = true;
                this.loadConversations().then(() => {
                    const target = config.openConversation
                        ? this.conversations.find(c => c.id === config.openConversation)
                        : null;
                    if (target) this.openConversation(target);
                });
            } else if (saved.open) {
                // Reabre sem animação, no mesmo ponto da tela anterior.
                this.open = true;
                this.view = saved.view === 'conversation' && saved.active ? 'conversation' : 'list';
                if (this.view === 'conversation') {
                    this.openConversation(saved.active, { restoring: true });
                } else {
                    this.loadConversations();
                }
            }

            this.$watch('draft', value => {
                if (!this.active) return;
                if (value) this.drafts[this.active.id] = value;
                else delete this.drafts[this.active.id];
                this.persist();
            });

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.pollStatus();
                    if (this.isReading()) this.markRead();
                }
            });

            this.pollStatus();
            this.startMessagesLoop();
        },

        persist() {
            if (this.mode === 'page') return;
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                    open: this.open,
                    view: this.view === 'people' ? 'list' : this.view,
                    active: this.view === 'conversation' ? this.active : null,
                    drafts: this.drafts,
                    draftRefs: this.draftRefs,
                }));
            } catch (e) {
                // Aba anônima/armazenamento bloqueado: o chat funciona, só não lembra o estado.
            }
        },

        // ── Abrir/fechar ─────────────────────────────────────────────────

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            if (this.view === 'list') this.loadConversations();
            if (this.view === 'conversation') this.$nextTick(() => this.scrollBottom());
            this.persist();
        },

        close() {
            this.open = false;
            this.persist();
        },

        backToList() {
            this.view = 'list';
            this.active = null;
            this.messages = [];
            this.files = [];
            this.error = '';
            this.loadConversations();
            this.persist();
        },

        expand() {
            const url = '/chat' + (this.active ? '?c=' + encodeURIComponent(this.active.id) : '');
            this.open = false;
            this.persist();
            window.location.href = url;
        },

        // ── Lista de conversas / pessoas ─────────────────────────────────

        async loadConversations() {
            try {
                const data = await api('/chat/conversas');
                this.conversations = data.conversations;
                this.listLoaded = true;
            } catch (e) {
                this.error = 'Não foi possível carregar as conversas.';
            }
        },

        get filteredConversations() {
            const q = this.search.trim().toLowerCase();
            return q ? this.conversations.filter(c => c.name.toLowerCase().includes(q)) : this.conversations;
        },

        get filteredPeople() {
            const q = this.search.trim().toLowerCase();
            return q ? this.people.filter(p => p.name.toLowerCase().includes(q)) : this.people;
        },

        async showPeople() {
            this.view = 'people';
            this.search = '';
            if (!this.people.length) {
                try {
                    this.people = (await api('/chat/pessoas')).people;
                } catch (e) {
                    this.error = 'Não foi possível carregar a equipe.';
                }
            }
        },

        async startDirect(person) {
            try {
                const data = await api('/chat/conversas/direta', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: person.id }),
                });
                this.search = '';
                this.openConversation(data.conversation);
            } catch (e) {
                this.error = e.message;
            }
        },

        // ── Conversa ─────────────────────────────────────────────────────

        async openConversation(conversation, { restoring = false } = {}) {
            this.active = conversation;
            this.view = 'conversation';
            this.messages = [];
            this.readUpTo = 0;
            this.files = [];
            this.error = '';
            this.refPicker = null;
            this.draft = this.drafts[conversation.id] ?? '';
            this.loadingMessages = !restoring;
            this.persist();

            try {
                const data = await api(`/chat/conversas/${conversation.id}/mensagens`);
                this.active = data.conversation;
                this.messages = data.messages;
                this.hasMore = data.has_more;
                this.readUpTo = data.receipts?.read_up_to ?? 0;
                this.$nextTick(() => {
                    this.scrollBottom();
                    this.$refs.input?.focus();
                });
                this.markRead();
                this.persist();
            } catch (e) {
                // Conversa que não existe mais ou da qual a pessoa saiu (ex: removida do Setor).
                if (e.status === 403 || e.status === 404) {
                    delete this.drafts[conversation.id];
                    this.backToList();
                } else {
                    this.error = 'Não foi possível abrir a conversa.';
                }
            } finally {
                this.loadingMessages = false;
            }
        },

        async loadOlder() {
            if (!this.hasMore || this.loadingOlder || !this.messages.length) return;
            this.loadingOlder = true;
            const box = this.$refs.messages;
            const previousHeight = box.scrollHeight;

            try {
                const data = await api(`/chat/conversas/${this.active.id}/mensagens?before=${this.messages[0].id}`);
                this.messages = [...data.messages, ...this.messages];
                this.hasMore = data.has_more;
                // Mantém o olho no mesmo ponto em vez de pular pro topo.
                this.$nextTick(() => { box.scrollTop = box.scrollHeight - previousHeight; });
            } catch (e) {
                // tenta de novo no próximo scroll
            } finally {
                this.loadingOlder = false;
            }
        },

        onScroll() {
            if (this.$refs.messages.scrollTop < 60) this.loadOlder();
        },

        isNearBottom() {
            const box = this.$refs.messages;
            return !box || box.scrollHeight - box.scrollTop - box.clientHeight < 120;
        },

        scrollBottom() {
            const box = this.$refs.messages;
            if (box) box.scrollTop = box.scrollHeight;
        },

        // A pessoa está de fato vendo a conversa aberta?
        isReading() {
            return this.open && this.view === 'conversation' && this.active && !document.hidden;
        },

        async markRead() {
            if (!this.active || !this.messages.length) return;
            const lastId = this.messages[this.messages.length - 1].id;
            const conv = this.conversations.find(c => c.id === this.active.id);
            if (conv) conv.unread = 0;
            try {
                await api(`/chat/conversas/${this.active.id}/lida`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: lastId }),
                });
                this.pollStatus();
            } catch (e) { /* não crítico */ }
        },

        async toggleMute() {
            if (!this.active) return;
            try {
                const data = await api(`/chat/conversas/${this.active.id}/silenciar`, { method: 'PATCH' });
                this.active.muted = data.muted;
            } catch (e) {
                this.error = e.message;
            }
        },

        // ── Envio ────────────────────────────────────────────────────────

        // Teclas da caixa de mensagem: com o menu "/" aberto, setas/Enter/Tab/Esc navegam
        // nele; senão Enter envia e Shift+Enter quebra linha.
        onInputKeydown(event) {
            if (this.refPicker?.step === 'type' && this.refTypeOptions.length) {
                if (this.navigatePicker(event, this.refTypeOptions.length)) return;
                if (event.key === 'Enter' || event.key === 'Tab') {
                    event.preventDefault();
                    this.chooseRefType(this.refTypeOptions[this.refPicker.index].key);
                    return;
                }
            }
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                this.send();
            }
        },

        // ── Citação com "/" ──────────────────────────────────────────────

        onInput(event) {
            const pos = event.target.selectionStart;
            const match = this.draft.slice(0, pos).match(/(^|\s)\/([^\s\/]*)$/);
            if (match) {
                this.refPicker = { step: 'type', start: pos - match[2].length - 1, end: pos, query: match[2], index: 0 };
            } else if (this.refPicker?.step === 'type') {
                this.refPicker = null;
            }
        },

        get refTypeOptions() {
            if (!this.refPicker || this.refPicker.step !== 'type') return [];
            const q = normalize(this.refPicker.query);
            return REF_TYPES.filter(t => normalize(t.label).startsWith(q));
        },

        // Setas e Esc compartilhados pelos dois passos do menu. Devolve true se tratou a tecla.
        navigatePicker(event, count) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                this.refPicker.index = (this.refPicker.index + step + count) % count;
                return true;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.closeRefPicker();
                return true;
            }
            return false;
        },

        chooseRefType(type) {
            this.refPicker = { ...this.refPicker, step: 'search', type, search: '', results: [], index: 0, loading: true };
            this.fetchRefs();
            this.$nextTick(() => this.$refs.refSearch?.focus());
        },

        refTypeLabel(type) {
            return REF_TYPES.find(t => t.key === type)?.label ?? '';
        },

        fetchRefs() {
            clearTimeout(this._refTimer);
            this._refTimer = setTimeout(async () => {
                const picker = this.refPicker;
                if (!picker || picker.step !== 'search') return;
                const search = picker.search;
                try {
                    const data = await api(`/chat/referencias?type=${picker.type}&q=${encodeURIComponent(search)}`);
                    if (this.refPicker === picker && picker.search === search) {
                        picker.results = data.items;
                        picker.index = 0;
                    }
                } catch (e) {
                    picker.results = [];
                } finally {
                    picker.loading = false;
                }
            }, 200);
        },

        onRefSearchKeydown(event) {
            const results = this.refPicker?.results ?? [];
            if (this.navigatePicker(event, Math.max(results.length, 1))) return;
            if (event.key === 'Enter') {
                event.preventDefault();
                if (results[this.refPicker.index]) this.pickRef(results[this.refPicker.index]);
            } else if (event.key === 'Backspace' && this.refPicker.search === '') {
                // Apagar com a busca vazia volta pra escolha do tipo.
                event.preventDefault();
                this.refPicker = { ...this.refPicker, step: 'type', index: 0 };
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },

        closeRefPicker() {
            this.refPicker = null;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        pickRef(item) {
            const { start, end, type } = this.refPicker;
            const clean = item.label.replace(/[\[\]|\n\r]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 150);
            const visible = '@' + clean;

            this.draft = this.draft.slice(0, start) + visible + ' ' + this.draft.slice(end);
            const refs = this.draftRefs[this.active.id] ?? [];
            refs.push({ label: visible, token: `[[${type}:${item.id}|${clean}]]` });
            this.draftRefs[this.active.id] = refs;
            this.persist();

            this.refPicker = null;
            this.$nextTick(() => {
                const input = this.$refs.input;
                if (!input) return;
                input.focus();
                const caret = start + visible.length + 1;
                input.setSelectionRange(caret, caret);
            });
        },

        // "@Título" → [[tipo:id|Título]] na hora de enviar. Se a pessoa editou/apagou o
        // "@Título" no texto, aquela citação simplesmente não vai (fica texto comum).
        applyRefs(text) {
            for (const ref of this.draftRefs[this.active.id] ?? []) {
                const i = text.indexOf(ref.label);
                if (i >= 0) text = text.slice(0, i) + ref.token + text.slice(i + ref.label.length);
            }
            return text;
        },

        // Citação de tarefa abre no popup da tarefa, sem sair da tela atual.
        onMessageClick(event) {
            const link = event.target.closest('a[data-chat-ref="tarefa"]');
            if (link) {
                event.preventDefault();
                Alpine.store('taskPopup').open(link.getAttribute('href'));
            }
        },

        addFiles(fileList) {
            for (const file of Array.from(fileList ?? [])) {
                if (file.size > MAX_FILE_MB * 1024 * 1024) {
                    this.error = `"${file.name}" passa de ${MAX_FILE_MB} MB — use o Drive pra arquivos grandes.`;
                    continue;
                }
                if (this.files.length >= 10) {
                    this.error = 'Máximo de 10 arquivos por mensagem.';
                    break;
                }
                file.previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
                this.files.push(file);
            }
        },

        removeFile(index) {
            const [file] = this.files.splice(index, 1);
            if (file?.previewUrl) URL.revokeObjectURL(file.previewUrl);
        },

        onPaste(event) {
            const files = event.clipboardData?.files;
            if (files && files.length) {
                event.preventDefault();
                this.addFiles(files);
            }
        },

        onDrop(event) {
            this.dragging = false;
            if (this.view === 'conversation') this.addFiles(event.dataTransfer?.files);
        },

        async send() {
            if (this.sending || !this.active) return;
            const text = this.draft.trim();
            if (!text && !this.files.length) return;

            const form = new FormData();
            form.append('body', this.applyRefs(text));
            this.files.forEach(f => form.append('files[]', f));

            this.sending = true;
            this.error = '';
            try {
                const data = await api(`/chat/conversas/${this.active.id}/mensagens`, { method: 'POST', body: form });
                this.appendMessages([data.message]);
                delete this.draftRefs[this.active.id];
                this.draft = '';
                this.files.forEach(f => f.previewUrl && URL.revokeObjectURL(f.previewUrl));
                this.files = [];
                this.$nextTick(() => {
                    this.scrollBottom();
                    this.$refs.input?.focus();
                });
            } catch (e) {
                this.error = e.status === 429
                    ? 'Muitas mensagens em pouco tempo — espere alguns segundos.'
                    : e.message;
            } finally {
                this.sending = false;
            }
        },

        appendMessages(list) {
            const known = new Set(this.messages.map(m => m.id));
            const fresh = (list ?? []).filter(m => m && m.id && !known.has(m.id));
            if (fresh.length) this.messages.push(...fresh);
            return fresh.length;
        },

        // ── Confirmação de leitura (só conversa individual) ──────────────

        applyReceipts(receipts) {
            if (!receipts) return;
            for (const [id, readAt] of Object.entries(receipts.times ?? {})) {
                const m = this.messages.find(x => x.id === Number(id));
                if (m) m.read_at = readAt;
            }
            this.readUpTo = Math.max(this.readUpTo, receipts.read_up_to ?? 0);
        },

        showReceipt(m) {
            return m.mine && this.active?.type === 'direct';
        },

        isRead(m) {
            return m.id <= this.readUpTo;
        },

        receiptTitle(m) {
            if (!this.isRead(m)) return 'Enviada';
            if (!m.read_at) return 'Lida';
            const d = new Date(m.read_at);
            const day = d.toDateString() === new Date().toDateString()
                ? 'hoje'
                : 'em ' + d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            return `Lida ${day} às ${this.time(m.read_at)}`;
        },

        // "Lida às 14:32" escrito embaixo só da ÚLTIMA mensagem minha já lida — nas
        // outras, a hora fica no passar do mouse (igual "Visto" do Instagram/WhatsApp).
        isLastReadMine(index) {
            const m = this.messages[index];
            if (!this.showReceipt(m) || !this.isRead(m) || !m.read_at) return false;
            return !this.messages.slice(index + 1).some(x => x.mine && this.isRead(x));
        },

        // ── Polling ──────────────────────────────────────────────────────

        startMessagesLoop() {
            this._messagesTimer = setInterval(async () => {
                if (!this.isReading() || this.loadingMessages || !this.messages) return;
                const after = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
                const conversationId = this.active.id;
                try {
                    const data = await api(`/chat/conversas/${conversationId}/mensagens?after=${after}&receipts_since=${this.readUpTo}`);
                    if (!this.active || this.active.id !== conversationId) return;
                    this.applyReceipts(data.receipts);
                    const stick = this.isNearBottom();
                    if (this.appendMessages(data.messages)) {
                        if (stick) this.$nextTick(() => this.scrollBottom());
                        this.markRead();
                    }
                } catch (e) { /* tenta de novo no próximo ciclo */ }
            }, MESSAGES_MS);
        },

        async pollStatus() {
            clearTimeout(this._statusTimer);
            try {
                const query = this.latestId === null ? '' : `?since=${this.latestId}`;
                const data = await api('/chat/estado' + query);
                const hadNews = this.latestId !== null && data.latest_id > this.latestId;
                this.latestId = data.latest_id;
                Alpine.store('chat').unreadTotal = data.unread_total;

                for (const msg of data.new) this.notify(msg);

                if (hadNews && this.open && this.view !== 'conversation') this.loadConversations();
            } catch (e) {
                // sessão expirada / rede: tenta de novo mais tarde
            }
            this._statusTimer = setTimeout(() => this.pollStatus(), document.hidden ? STATUS_HIDDEN_MS : STATUS_MS);
        },

        notify(msg) {
            // Já está lendo essa conversa: o loop de mensagens mostra, sem alarde.
            if (this.isReading() && this.active.id === msg.conversation_id) return;

            const title = msg.conversation_type === 'direct'
                ? msg.sender
                : `${msg.sender} · ${msg.conversation_name}`;

            this.toast = { title, body: msg.preview, conversationId: msg.conversation_id };
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toast = null; }, 6000);

            const bell = Alpine.store('browserNotify');
            bell?._beep?.();

            // Aba em segundo plano: aviso do navegador, se a pessoa já liberou no sino.
            if (document.hidden && bell?.enabled && window.Notification?.permission === 'granted') {
                const n = new Notification(title, { body: msg.preview, tag: 'chat-' + msg.conversation_id });
                n.onclick = () => {
                    window.focus();
                    this.openFromToast(msg.conversation_id);
                };
            }
        },

        async openFromToast(conversationId) {
            this.toast = null;
            this.open = true;
            if (!this.listLoaded) await this.loadConversations();
            const conv = this.conversations.find(c => c.id === conversationId) ?? { id: conversationId, name: '', type: 'direct' };
            this.openConversation(conv);
        },

        // ── Formatação ───────────────────────────────────────────────────

        time(iso) {
            return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        },

        dayLabel(iso) {
            const d = new Date(iso);
            const today = new Date();
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            if (d.toDateString() === today.toDateString()) return 'Hoje';
            if (d.toDateString() === yesterday.toDateString()) return 'Ontem';
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
        },

        listTime(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return d.toDateString() === new Date().toDateString()
                ? this.time(iso)
                : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        },

        // Separador de dia antes da 1ª mensagem de cada dia.
        showDay(index) {
            if (index === 0) return true;
            return new Date(this.messages[index - 1].created_at).toDateString()
                !== new Date(this.messages[index].created_at).toDateString();
        },

        // Nome do autor só em Setor/grupo, e só quando muda de pessoa (ou passa 5 min).
        showAuthor(index) {
            const m = this.messages[index];
            if (m.mine || !this.active || this.active.type === 'direct') return false;
            if (index === 0 || this.showDay(index)) return true;
            const prev = this.messages[index - 1];
            return prev.user_id !== m.user_id
                || new Date(m.created_at) - new Date(prev.created_at) > 5 * 60 * 1000;
        },

        fileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
            return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
        },
    }));
}
