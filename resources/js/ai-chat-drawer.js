// Lógica compartilhável do drawer de chat IA do Assistente de Lançamento de
// Tarefas (envio, histórico, rascunhos editáveis, confirmação em lote). O
// chrome/markup do drawer NÃO é compartilhado com o de tasks/show.blade.php
// (taskChat) — os dois casos divergem demais no corpo (Q&A simples vs.
// playbook-picker + cards de rascunho) pra justificar generalizar via slots
// agora. Ver resources/views/projects/_task-assistant-drawer.blade.php.
export function registerAiChatDrawer(Alpine) {
    Alpine.data('taskAssistantDrawer', () => ({
        endpoint:        window._taskAssistant?.chatEndpoint    ?? '',
        confirmEndpoint: window._taskAssistant?.confirmEndpoint ?? '',
        clearEndpoint:   window._taskAssistant?.clearEndpoint   ?? '',
        applyEndpoint:   window._taskAssistant?.applyEndpoint   ?? '',
        agentName:       window._taskAssistant?.agentName       ?? null,
        messages:        window._taskAssistant?.messages        ?? [],
        team:            window._taskAssistant?.team            ?? [],
        input:           '',
        thinking:        false,
        confirming:      false,
        error:           '',
        drafts:          [],
        // Alterações em tarefas que JÁ existem, propostas pela IA (ver
        // ProjectTaskAssistantController::sanitizeChanges). Cada uma tem `checked` —
        // só as marcadas vão pro "Aplicar".
        changes:         [],
        applying:        false,
        appliedNote:     '',

        get checkedChanges() {
            return this.changes.filter(c => c.checked);
        },

        // Agrupa por tarefa pra lista ficar legível ("Post X: aprovação 15/10 → 09/10").
        get changeGroups() {
            const groups = [];
            const byTask = {};
            this.changes.forEach(c => {
                if (!byTask[c.task_id]) {
                    byTask[c.task_id] = { task_id: c.task_id, ref: c.ref, title: c.task_title, status: c.task_status, blocked: c.blocked, items: [] };
                    groups.push(byTask[c.task_id]);
                }
                byTask[c.task_id].items.push(c);
            });
            return groups;
        },

        setChanges(list) {
            this.changes = (list || []).map(c => ({ ...c, checked: true }));
            if (this.changes.length) this.appliedNote = '';
        },

        discardChanges() {
            this.changes = [];
        },

        async applyChanges() {
            const selected = this.checkedChanges;
            if (selected.length === 0 || this.applying) return;

            this.applying = true;
            this.error = '';
            try {
                const res = await fetch(this.applyEndpoint, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ changes: selected.map(c => ({ task_id: c.task_id, field: c.field, to: c.to })) }),
                });
                const data = await res.json();
                if (!res.ok) {
                    const firstFieldError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                    this.error = firstFieldError || data.message || 'Erro ao aplicar as alterações.';
                    return;
                }

                this.messages.push({
                    id:         Date.now(),
                    role:       'assistant',
                    content:    data.summary,
                    agent_name: this.agentName,
                    time:       new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                });
                // Sai da lista o que foi aplicado; o que não marcou continua pra decidir depois.
                this.changes = this.changes.filter(c => !c.checked);
                this.appliedNote = data.applied > 0 ? 'A página ainda mostra os dados antigos.' : '';
                this.scrollBottom();
            } catch (e) {
                this.error = 'Erro de conexão ao aplicar.';
            } finally {
                this.applying = false;
            }
        },

        reloadPage() {
            window.location.reload();
        },

        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msgContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        removeDraft(i) {
            this.drafts.splice(i, 1);
        },

        // "2026-10-09" → "sexta-feira, 09/10" — pra conferir de bate-olho se a IA
        // entendeu o "pra sexta" certo. T12:00 evita a data voltar 1 dia pelo fuso.
        weekdayLabel(ymd) {
            const d = new Date(ymd + 'T12:00:00');
            if (isNaN(d)) return '';
            return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit' });
        },

        // Botão "Nova conversa" — apaga o histórico deste projeto no servidor
        // e limpa o estado local (sem reload, a conversa some na hora).
        async newConversation() {
            if (this.messages.length === 0 && this.drafts.length === 0) return;
            if (!(await Alpine.store('confirmDialog').ask('Apagar o histórico desta conversa e começar do zero?'))) return;

            try {
                await fetch(this.clearEndpoint, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                });
            } catch (e) {
                // Mesmo se a chamada falhar, limpa a tela — na pior hipótese o
                // histórico antigo reaparece na próxima vez que a página carregar.
            }

            this.messages = [];
            this.drafts   = [];
            this.changes  = [];
            this.appliedNote = '';
            this.error    = '';
        },

        async send() {
            if (!this.agentName || !this.input.trim() || this.thinking) return;

            const text = this.input.trim();
            this.input    = '';
            this.error    = '';
            this.thinking = true;

            this.messages.push({
                id:         Date.now(),
                role:       'user',
                content:    text,
                user_name:  window._taskAssistant?.currentUserName ?? 'Você',
                agent_name: null,
                time:       new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
            });
            this.scrollBottom();

            try {
                const res = await fetch(this.endpoint, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                    // Cartões como estão agora (com edições manuais) — a IA parte
                    // deles ao ajustar ("a 3 é Reels"), em vez de remontar de memória.
                    // Alterações pendentes também vão, pra IA ajustar a lista ("tira a #5").
                    body: JSON.stringify({ message: text, current_drafts: this.drafts, current_changes: this.changes }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.error = data.error || data.message || 'Erro ao processar.';
                    this.messages.pop();
                    this.input = text; // não perde uma lista grande colada
                    return;
                }

                if (data.warnings && data.warnings.length > 0) {
                    data.content += '\n\n⚠ ' + data.warnings.join('\n⚠ ');
                }
                this.messages.push(data);
                // null = a IA não mexeu no rascunho (só conversou) → mantém os cartões.
                if (Array.isArray(data.draft_tasks)) {
                    this.drafts = data.draft_tasks;
                }
                // Mesma convenção: null = não mexeu nas alterações; lista = substitui.
                if (Array.isArray(data.task_changes)) {
                    this.setChanges(data.task_changes);
                }
                this.scrollBottom();
            } catch (e) {
                this.error = 'Erro de conexão.';
                this.messages.pop();
                this.input = text;
            } finally {
                this.thinking = false;
            }
        },

        async confirmDrafts() {
            if (this.drafts.length === 0 || this.confirming) return;

            // Checagem client-side antes de bater no servidor — task_type é
            // obrigatório pra criar a tarefa de verdade (ver Task::storeRules()),
            // mas o rascunho pode ter chegado sem ele (ver sanitizeDrafts() em
            // ProjectTaskAssistantController) quando o pedido era vago demais.
            if (this.drafts.some(d => !d.task_type)) {
                this.error = 'Escolha o tipo em todos os cartões antes de confirmar (destacados sem tipo selecionado).';
                return;
            }

            this.confirming = true;
            this.error = '';

            try {
                const res = await fetch(this.confirmEndpoint, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ tasks: this.drafts }),
                });

                const data = await res.json();

                if (!res.ok) {
                    const firstFieldError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                    this.error = firstFieldError || data.message || 'Erro ao criar tarefas.';
                    return;
                }

                let msg = `${data.created} tarefa(s) criada(s).`;
                if (data.warnings && data.warnings.length > 0) {
                    msg += ' Atenção: ' + data.warnings.join(' ');
                }
                alert(msg);
                this.drafts = [];
                window.location.reload();
            } catch (e) {
                this.error = 'Erro de conexão ao confirmar.';
            } finally {
                this.confirming = false;
            }
        },
    }));
}
