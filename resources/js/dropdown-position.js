// Calcula a posição (position:fixed) de um menu dropdown a partir do elemento
// que o abriu, pra usar com x-teleport="body" — assim o menu nunca fica
// cortado por um ancestral com overflow (ex: tabela com scroll horizontal).
export function registerDropdownPosition() {
    window.dropdownStyle = function (el, anchor = 'bottom-left') {
        if (!el) return 'display:none';

        const r = el.getBoundingClientRect();
        const gap = 4;
        // Altura estimada do maior menu que usa este helper (lista de usuários do
        // _person-fill-menu, max-height:280px) — sem isso, uma linha perto do fim da
        // tabela abria o menu pra baixo e ele nascia fora da viewport (cortado pela
        // barra de tarefas do SO, por ex). Vira pra cima quando não sobra espaço embaixo
        // mas sobra em cima; do contrário mantém a direção pedida.
        const estimatedHeight = 280;
        let vertical = anchor.startsWith('bottom') ? 'bottom' : 'top';
        if (vertical === 'bottom' && window.innerHeight - r.bottom < estimatedHeight && r.top > estimatedHeight) {
            vertical = 'top';
        } else if (vertical === 'top' && r.top < estimatedHeight && window.innerHeight - r.bottom > estimatedHeight) {
            vertical = 'bottom';
        }

        let css = 'position:fixed; z-index:9999;';

        css += vertical === 'bottom'
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
// a PÁGINA rolar (padrão comum pra popovers/dropdowns) — mas ignora o scroll
// que acontece dentro do próprio menu (ex: lista de usuários com overflow-y:auto
// no _person-fill.blade.php), senão rolar a lista fecha ela imediatamente.
export function registerCloseOnScroll(Alpine) {
    Alpine.directive('close-on-scroll', (el, { expression }, { effect, evaluateLater }) => {
        const getIsOpen = evaluateLater(expression);
        const setClosed = evaluateLater(`${expression} = false`);
        let listening = false;

        const handler = (event) => {
            if (el.contains(event.target)) return;
            setClosed(() => {});
        };

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
