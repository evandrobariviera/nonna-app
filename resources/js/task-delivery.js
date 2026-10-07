// Janela "Entrega" — retorno obrigatório pra mandar tarefa pra Revisão Interna (ver
// TaskDeliveryService). Qualquer tela que muda status recebe do servidor um 422 com
// `delivery_required` em vez de mudar; aí chama $store.delivery.ask(payload), que abre a
// janela e resolve true quando a entrega foi gravada (o servidor já mudou o status junto)
// ou false se a pessoa desistiu. Modal em resources/views/components/task-delivery-modal.blade.php.
export function registerTaskDelivery(Alpine) {
    Alpine.store('delivery', {
        visible: false,
        task: null,          // { task_id, title, url }
        fullyDone: null,     // true | false (ainda não escolhido = null)
        missing: '',
        body: '',
        error: '',
        saving: false,
        _resolve: null,

        ask(payload) {
            // Já aberta (ex: dois cliques seguidos): a 2ª pergunta desiste da 1ª.
            this._resolve?.(false);
            this.task = payload;
            this.fullyDone = null;
            this.missing = '';
            this.body = '';
            this.error = '';
            this.saving = false;
            this.visible = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },

        cancel() {
            if (this.saving) return;
            this.visible = false;
            this._finish(false);
        },

        async submit() {
            if (this.saving) return;
            if (this.fullyDone === null) { this.error = 'Diga se a tarefa foi executada por completo.'; return; }
            this.saving = true;
            this.error = '';
            try {
                const res = await fetch(this.task.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ fully_done: this.fullyDone, missing: this.missing, body: this.body }),
                });
                if (!res.ok) {
                    let message = 'Não foi possível registrar a entrega. Tente de novo.';
                    try { message = (await res.json()).message || message; } catch (e) { /* não era JSON */ }
                    this.error = message;
                    return;
                }
                this.visible = false;
                this._finish(true);
            } catch (e) {
                this.error = 'Sem conexão — tente de novo.';
            } finally {
                this.saving = false;
            }
        },

        _finish(result) {
            const resolve = this._resolve;
            this._resolve = null;
            resolve?.(result);
        },
    });

    // Resposta 422 de mudança de status → se for a trava de entrega, abre a janela.
    // Devolve null quando a resposta não é a trava (quem chamou segue o fluxo normal de erro).
    window.handleDeliveryRequired = async function (json) {
        if (!json || !json.delivery_required) return null;
        return Alpine.store('delivery').ask(json.delivery_required);
    };
}
