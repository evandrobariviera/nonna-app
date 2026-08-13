// Aviso ativo em cima do sino de notificação interna que já existe (InternalNotification):
// pede permissão da Notification API do Chrome uma vez (precisa de gesto do usuário, não
// dá pra pedir sozinho no load) e, enquanto ligado, faz polling leve pra tocar um beep +
// mostrar o popup de desktop assim que algo novo chega — só funciona com o navegador aberto,
// de propósito (não é push de verdade, não precisa de Service Worker/VAPID).
const STORAGE_KEY = 'browserNotifyEnabled';
const POLL_MS = 30000;

function beep() {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.frequency.value = 880;
    gain.gain.setValueAtTime(0.15, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.15);
    osc.connect(gain).connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.15);
}

export function registerBrowserNotify(Alpine) {
    Alpine.store('browserNotify', {
        enabled: localStorage.getItem(STORAGE_KEY) === 'true',
        _timer: null,
        _since: null,

        init() {
            if (this.enabled && Notification.permission === 'granted') {
                this.startPolling();
            }
        },

        async enable() {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                return;
            }
            this.enabled = true;
            localStorage.setItem(STORAGE_KEY, 'true');
            this.startPolling();
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
                    beep();
                }
            } catch (e) {
                // Falha de rede pontual não deve derrubar o polling — só ignora e tenta de novo no próximo ciclo.
            }
        },
    });
}
