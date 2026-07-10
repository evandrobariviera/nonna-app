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
