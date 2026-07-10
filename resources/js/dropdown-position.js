// Calcula a posição (position:fixed) de um menu dropdown a partir do elemento
// que o abriu, pra usar com x-teleport="body" — assim o menu nunca fica
// cortado por um ancestral com overflow (ex: tabela com scroll horizontal).
export function registerDropdownPosition() {
    window.dropdownStyle = function (el, anchor = 'bottom-left') {
        if (!el) return 'display:none';

        const r = el.getBoundingClientRect();
        const gap = 4;
        let css = 'position:fixed; z-index:9999;';

        css += anchor.startsWith('bottom')
            ? `top:${r.bottom + gap}px;`
            : `bottom:${window.innerHeight - r.top + gap}px;`;

        css += anchor.endsWith('left')
            ? `left:${r.left}px;`
            : `right:${window.innerWidth - r.right}px;`;

        return css;
    };
}

// Diretiva x-close-on-scroll="statusOpen" — como o dropdown usa position:fixed
// calculado só no clique, ele não acompanha o scroll depois de aberto. Em vez
// de recalcular a posição a cada pixel rolado (frágil), fecha o menu assim que
// qualquer scroll acontecer (padrão comum pra popovers/dropdowns).
export function registerCloseOnScroll(Alpine) {
    Alpine.directive('close-on-scroll', (el, { expression }, { effect, evaluateLater }) => {
        const getIsOpen = evaluateLater(expression);
        const setClosed = evaluateLater(`${expression} = false`);
        let listening = false;

        const handler = () => setClosed(() => {});

        effect(() => {
            getIsOpen((isOpen) => {
                if (isOpen && !listening) {
                    window.addEventListener('scroll', handler, true);
                    listening = true;
                } else if (!isOpen && listening) {
                    window.removeEventListener('scroll', handler, true);
                    listening = false;
                }
            });
        });
    });
}
