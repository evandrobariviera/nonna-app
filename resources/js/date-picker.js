import flatpickr from 'flatpickr';
import { Portuguese } from 'flatpickr/dist/esm/l10n/pt.js';

// Calendário em português (dd/mm/aaaa) pra todo <input type="date"> e "datetime-local".
// O campo nativo desenha no idioma do NAVEGADOR (Chrome em inglês = mm/dd/yyyy), então
// trocamos por flatpickr: o input original vira hidden e continua com o valor no formato
// do servidor (Y-m-d / Y-m-dTH:i) — forms, x-model e @change seguem iguais — e um campo
// visível ao lado mostra/aceita dd/mm/aaaa. Pega também campos que entram depois
// (x-if, x-for, AJAX) via MutationObserver.
const SELECTOR = 'input[type="date"]:not([data-native]), input[type="datetime-local"]:not([data-native])';

// Atributos que o Alpine mexe no input original (x-show, :disabled, :required, x-cloak)
// e que precisam valer no campo visível.
const MIRRORED = ['style', 'disabled', 'required', 'x-cloak', 'title'];

const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

function enhance(input) {
    if (input._datePicker) return;
    const withTime = input.type === 'datetime-local';
    let syncing = false;

    const fp = flatpickr(input, {
        locale: Portuguese,
        dateFormat: withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
        altInput: true,
        altFormat: withTime ? 'd/m/Y H:i' : 'd/m/Y',
        altInputClass: input.className + ' dp-input',
        allowInput: true,
        enableTime: withTime,
        time_24hr: true,
        disableMobile: true,
        minDate: input.min || null,
        maxDate: input.max || null,
        // flatpickr dispara "change" antes de "input": quem escuta @change (ex.: salvar ao
        // trocar a data) leria o x-model ainda com o valor antigo. Avisa o x-model antes.
        onChange: () => input.dispatchEvent(new Event('input', { bubbles: true })),
        // Quem fecha o editor no @blur (datas clicáveis da tarefa) fica sabendo quando o
        // calendário fecha — o blur de verdade acontece no campo visível. Ao clicar num dia o
        // flatpickr fecha ANTES do onChange: sem adiar, o @blur salvava a data antiga e o
        // editor fechava, e a data nova era ignorada.
        onClose: () => setTimeout(() => input.dispatchEvent(new Event('blur')), 0),
    });

    const alt = fp.altInput;
    // Largura natural de um campo de data (sem classe w-*), não a de um campo de texto.
    alt.size = withTime ? 15 : 10;
    alt.setAttribute('placeholder', input.getAttribute('placeholder') || (withTime ? 'dd/mm/aaaa hh:mm' : 'dd/mm/aaaa'));
    MIRRORED.forEach((name) => mirror(input, alt, name));
    new MutationObserver((records) => records.forEach((r) => mirror(input, alt, r.attributeName)))
        .observe(input, { attributes: true, attributeFilter: MIRRORED });

    // x-model / scripts gravam direto em input.value — o calendário acompanha.
    Object.defineProperty(input, 'value', {
        configurable: true,
        get() { return nativeValue.get.call(this); },
        set(v) {
            nativeValue.set.call(this, v);
            const current = fp.selectedDates[0] ? fp.formatDate(fp.selectedDates[0], fp.config.dateFormat) : '';
            if (syncing || (v || '') === current) return;
            syncing = true;
            fp.setDate(v || null, false);
            syncing = false;
        },
    });
    const setDate = fp.setDate;
    fp.setDate = (...args) => { syncing = true; try { return setDate(...args); } finally { syncing = false; } };

    // $refs.input.focus() (abrir o editor) foca o campo visível. O x-show do Alpine só
    // mostra o campo num setTimeout — tenta de novo logo depois, quando ele já apareceu.
    input.focus = () => {
        const go = () => { mirror(input, alt, 'style'); alt.focus(); };
        go();
        setTimeout(go, 0);
    };
    alt.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key === 'Enter') {
            input.dispatchEvent(new KeyboardEvent('keydown', { key: e.key }));
        }
    });

    input._datePicker = fp;
}

function mirror(from, to, name) {
    if (name === 'disabled' || name === 'required') {
        to[name] = from.hasAttribute(name);
    } else if (from.hasAttribute(name)) {
        to.setAttribute(name, from.getAttribute(name));
    } else {
        to.removeAttribute(name);
    }
}

function scan(root) {
    if (root.matches?.(SELECTOR)) enhance(root);
    root.querySelectorAll?.(SELECTOR).forEach(enhance);
}

function cleanup(root) {
    const inputs = root._datePicker ? [root] : [...(root.querySelectorAll?.('.flatpickr-input') || [])];
    inputs.forEach((input) => {
        if (input._datePicker && !input.isConnected) {
            input._datePicker.destroy();
            delete input._datePicker;
        }
    });
}

export function registerDatePicker() {
    if (window.__datePickerReady) return;
    window.__datePickerReady = true;

    const start = () => {
        scan(document.body);
        new MutationObserver((records) => {
            records.forEach((r) => {
                r.addedNodes.forEach((n) => n.nodeType === 1 && scan(n));
                r.removedNodes.forEach((n) => n.nodeType === 1 && cleanup(n));
            });
        }).observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}
