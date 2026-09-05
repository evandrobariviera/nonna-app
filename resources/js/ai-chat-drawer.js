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
        agents:          window._taskAssistant?.agents          ?? [],
        messages:        window._taskAssistant?.messages        ?? [],
        functionalRoles: window._taskAssistant?.functionalRoles ?? [],
        selectedAgent:   '',
        input:           '',
        thinking:        false,
        confirming:      false,
        error:           '',
        drafts:          [],

        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msgContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        removeDraft(i) {
            this.drafts.splice(i, 1);
        },

        // Botão "Nova conversa" — apaga o histórico deste projeto no servidor
        // e limpa o estado local (sem reload, a conversa some na hora).
        async newConversation() {
            if (this.messages.length === 0 && this.drafts.length === 0) return;
            if (!confirm('Apagar o histórico desta conversa e começar do zero?')) return;

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
            this.error    = '';
        },

        async send() {
            if (!this.selectedAgent || !this.input.trim() || this.thinking) return;

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
                    body: JSON.stringify({ agent_id: this.selectedAgent, message: text }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.error = data.error || 'Erro ao processar.';
                    this.messages.pop();
                    return;
                }

                this.messages.push(data);
                this.drafts = data.draft_tasks ?? [];
                this.scrollBottom();
            } catch (e) {
                this.error = 'Erro de conexão.';
                this.messages.pop();
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
