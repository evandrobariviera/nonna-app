// Painel lateral (canvas) que carrega um preview via fetch sem sair da página atual.
// Uso em qualquer link: @click.prevent="$store.sidePanel.open(url)"
export function registerSidePanel(Alpine) {
    Alpine.store('sidePanel', {
        open: false,
        loading: false,
        error: false,
        html: '',
        url: null,

        async load(url) {
            this.open = true;
            this.loading = true;
            this.error = false;
            this.html = '';
            this.url = url;

            const requestUrl = url;

            try {
                const response = await fetch(requestUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('http-' + response.status);

                const html = await response.text();

                // Se o usuário já trocou de painel enquanto o fetch corria, descarta.
                if (this.url !== requestUrl) return;

                this.html = html;
            } catch (e) {
                if (this.url !== requestUrl) return;
                this.error = true;
            } finally {
                if (this.url === requestUrl) this.loading = false;
            }
        },

        close() {
            this.open = false;
            this.html = '';
            this.url = null;
            this.error = false;
        },
    });
}
