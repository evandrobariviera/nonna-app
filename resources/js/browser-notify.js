// Aviso ativo em cima do sino de notificação interna que já existe (InternalNotification):
// pede permissão da Notification API do Chrome uma vez (precisa de gesto do usuário, não
// dá pra pedir sozinho no load) e, enquanto ligado, faz polling leve pra tocar um beep +
// mostrar o popup de desktop assim que algo novo chega — só funciona com o navegador aberto,
// de propósito (não é push de verdade, não precisa de Service Worker/VAPID).
const STORAGE_KEY = 'browserNotifyEnabled';
const POLL_MS = 30000;

export function registerBrowserNotify(Alpine) {
    Alpine.store('browserNotify', {
        enabled: localStorage.getItem(STORAGE_KEY) === 'true',
        _timer: null,
        _since: null,
        _audioCtx: null,

        init() {
            // AudioContext só toca de verdade se foi criado/retomado dentro de um
            // gesto do usuário — um clique em QUALQUER lugar da página já libera
            // isso pro resto da sessão (política do Chrome), então escuta uma vez
            // aqui pra também destravar o som na sessão retomada automaticamente
            // (localStorage já ligado de uma visita anterior, sem clique novo no botão).
            document.addEventListener('click', () => this._unlockAudio(), { once: true });

            if (this.enabled && Notification.permission === 'granted') {
                this.startPolling();
            }
        },

        async enable() {
            if (Notification.permission === 'denied') {
                alert('As notificações deste site estão bloqueadas no navegador. Libere em "Configurações do site" (ícone de cadeado/informações na barra de endereço → Notificações → Permitir) e tente de novo.');
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                return;
            }

            this._unlockAudio();
            this.enabled = true;
            localStorage.setItem(STORAGE_KEY, 'true');
            this.startPolling();
        },

        // Cria o AudioContext (ou retoma se já existe e ficou suspenso) — precisa
        // rodar dentro de um gesto real (clique) pra não ficar mudo; reaproveitar a
        // MESMA instância depois (inclusive de dentro do setInterval do polling,
        // sem gesto novo) funciona, é só criar um AudioContext novo a cada beep que
        // o Chrome bloqueia o som.
        _unlockAudio() {
            if (!this._audioCtx) {
                this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (this._audioCtx.state === 'suspended') {
                this._audioCtx.resume();
            }
        },

        _beep() {
            if (!this._audioCtx || this._audioCtx.state !== 'running') {
                return;
            }
            const ctx = this._audioCtx;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.15);
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        },

        disable() {
            this.enabled = false;
            localStorage.setItem(STORAGE_KEY, 'false');
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        startPolling() {
            if (this._timer) {
                return;
            }
            this._since = new Date().toISOString();
            this._timer = setInterval(() => this._poll(), POLL_MS);
        },

        async _poll() {
            try {
                const res = await fetch('/notificacoes/novas?since=' + encodeURIComponent(this._since));
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                this._since = data.server_time;

                for (const n of data.notifications) {
                    const notification = new Notification(n.title, { body: n.body || '', tag: n.id });
                    notification.onclick = () => {
                        window.focus();
                        if (n.link) {
                            window.location.href = n.link;
                        }
                    };
                    this._beep();
                }
            } catch (e) {
                // Falha de rede pontual não deve derrubar o polling — só ignora e tenta de novo no próximo ciclo.
            }
        },
    });
}
