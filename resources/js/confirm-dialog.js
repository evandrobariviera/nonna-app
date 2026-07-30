// Substitui window.confirm() nativo por um modal no estilo do app. Uso em
// qualquer form/botão: @submit.prevent="if (await $store.confirmDialog.ask('Mensagem?')) $el.submit()"
// $el.submit() (não .requestSubmit()) de propósito — o método .submit() do
// HTMLFormElement NÃO dispara o evento 'submit', então não re-entra no
// próprio @submit.prevent (senão viraria loop infinito pedindo confirmação).
export function registerConfirmDialog(Alpine) {
    Alpine.store('confirmDialog', {
        visible: false,
        message: '',
        _resolve: null,

        ask(message) {
            this.message = message;
            this.visible = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },

        confirm() {
            this.visible = false;
            this._resolve?.(true);
        },

        cancel() {
            this.visible = false;
            this._resolve?.(false);
        },
    });
}
