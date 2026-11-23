// Confirmação no próprio lugar (24/11, pedido do Evandro: o aviso no meio da tela com fundo
// escuro era "antigo e deselegante"). Em vez do modal, aparece um balão pequeno colado no botão
// que a pessoa clicou, com a pergunta e os botões. Sem fundo escuro; clicar fora ou Esc cancela,
// Enter confirma. Sem botão de origem (chamada feita por código), o balão aparece no centro.
//
// Uso (não mudou): @submit.prevent="if (await $store.confirmDialog.ask('Mensagem?')) $el.submit()"
// Opcional: ask('Mensagem?', { ok: 'Remover', danger: true }). Sem opções, o texto da pergunta
// decide: "Remover/Excluir/Apagar/Cancelar..." vira botão vermelho com esse verbo.
//
// $el.submit() (não .requestSubmit()) de propósito — o método .submit() do HTMLFormElement NÃO
// dispara o evento 'submit', então não re-entra no próprio @submit.prevent (senão viraria loop).
const DANGER = /^(remover|excluir|apagar|deletar|descartar|desativar|desligar|arquivar|cancelar|encerrar|limpar|tirar|desvincular|revogar|bloquear)\b/i;

let lastTrigger = null;
let lastTriggerAt = 0;
if (typeof document !== 'undefined') {
    // Guarda o botão/link clicado por último: é nele que o balão vai aparecer.
    document.addEventListener('pointerdown', (e) => {
        const el = e.target.closest?.('button, a, [role="button"], input[type="submit"], input[type="checkbox"], label, select');
        if (el && !el.closest('[data-confirm-pop]')) { lastTrigger = el; lastTriggerAt = Date.now(); }
    }, true);
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && document.activeElement && !document.activeElement.closest('[data-confirm-pop]')) {
            lastTrigger = document.activeElement; lastTriggerAt = Date.now();
        }
    }, true);
}

export function registerConfirmDialog(Alpine) {
    Alpine.store('confirmDialog', {
        visible: false,
        message: '',
        okLabel: 'Confirmar',
        danger: false,
        anchored: false,
        pos: '',
        openedAt: 0,
        _resolve: null,

        ask(message, opts = {}) {
            // Pergunta anterior ainda aberta: conta como cancelada.
            if (this._resolve) this._resolve(false);
            this.message = message;
            const verb = String(message).trim().match(DANGER);
            this.danger = opts.danger ?? !!verb;
            this.okLabel = opts.ok ?? (verb ? verb[1].charAt(0).toUpperCase() + verb[1].slice(1).toLowerCase() : 'Confirmar');
            const trigger = Date.now() - lastTriggerAt < 4000 && lastTrigger?.isConnected ? lastTrigger : null;
            this.place(trigger);
            this.openedAt = Date.now();
            this.visible = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },

        // Posição: logo abaixo do botão, alinhado pela direita dele; sem espaço embaixo, vai para cima.
        place(trigger) {
            const rect = trigger?.getBoundingClientRect();
            if (!rect || (rect.width === 0 && rect.height === 0)) {
                this.anchored = false;
                this.pos = 'left:50%; top:30%; transform:translateX(-50%)';
                return;
            }
            this.anchored = true;
            const width = Math.min(320, window.innerWidth - 16);
            const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
            const below = window.innerHeight - rect.bottom > 170;
            this.pos = `left:${left}px; width:${width}px; ` + (below ? `top:${rect.bottom + 6}px` : `bottom:${window.innerHeight - rect.top + 6}px`);
        },

        confirm() {
            if (!this.visible) return;
            this.visible = false;
            const r = this._resolve; this._resolve = null; r?.(true);
        },

        cancel() {
            if (!this.visible) return;
            this.visible = false;
            const r = this._resolve; this._resolve = null; r?.(false);
        },
    });
}
