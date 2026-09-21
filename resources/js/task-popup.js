// Popup de tarefa: abre a tela real da tarefa dentro de um iframe, num modal por cima da
// página atual — a tela de baixo (Painel de Produção, Sprint, etc.) nunca navega nem recarrega.
// Diferente do side-panel.js (que busca um fragmento por fetch), aqui é a página COMPLETA da
// tarefa carregando de verdade dentro do iframe, com ?embed=1 pra layouts/app.blade.php
// suprimir sidebar/topbar — assim tudo que já funciona na tarefa (aprovação, anexos,
// comentários) continua funcionando sem precisar reescrever nada pra AJAX.
export function registerTaskPopup(Alpine) {
    Alpine.store('taskPopup', {
        visible: false,
        url: null,

        open(url) {
            const separator = url.includes('?') ? '&' : '?';
            this.url = url + separator + 'embed=1';
            this.visible = true;
        },

        close() {
            this.visible = false;
            this.url = null;
        },
    });
}
