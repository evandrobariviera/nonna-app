// Busca/filtro dinâmico e padronizado pra todas as telas de listagem. Um <form
// data-live-filter data-results-url="..." data-target="#seletor"> dispara fetch pro
// endpoint de resultados (fragmento HTML, sem layout) conforme o usuário digita/filtra,
// troca só o innerHTML do alvo e atualiza a URL da página via history — sem recarregar.
//
// Texto (input[type=text|search]): debounce, só dispara com 0 (campo limpo) ou >= MIN_CHARS.
// Select/checkbox/radio: dispara na hora, sem debounce nem mínimo.
const MIN_CHARS = 4;
const DEBOUNCE_MS = 400;

export function registerLiveFilter() {
    document.querySelectorAll('form[data-live-filter]').forEach(initLiveFilterForm);
    window.refreshLiveFilter = refreshLiveFilter;
}

function initLiveFilterForm(form) {
    const resultsUrl = form.dataset.resultsUrl;
    const target = document.querySelector(form.dataset.target);
    if (!resultsUrl || !target) return;

    let debounceTimer = null;
    let abortController = null;

    function runSearch() {
        clearTimeout(debounceTimer);
        abortController?.abort();
        abortController = new AbortController();

        const params = new URLSearchParams();
        for (const [key, value] of new FormData(form).entries()) {
            if (value !== '') params.append(key, value);
        }
        const qs = params.toString();

        target.style.opacity = '.5';

        fetch(resultsUrl + (qs ? '?' + qs : ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: abortController.signal,
        })
            .then((response) => response.text())
            .then((html) => {
                target.innerHTML = html;
                target.style.opacity = '';
                history.replaceState(null, '', form.action + (qs ? '?' + qs : ''));
            })
            .catch((error) => {
                if (error.name !== 'AbortError') target.style.opacity = '';
            });
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        runSearch();
    });

    form.querySelectorAll('input[type="text"], input[type="search"]').forEach((input) => {
        input.addEventListener('input', () => {
            const len = input.value.length;
            if (len !== 0 && len < MIN_CHARS) return;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
        });
    });

    form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach((el) => {
        el.addEventListener('change', runSearch);
    });

    // Exposto no próprio elemento pra outros scripts (ex: inline-patch.js) poderem forçar um
    // refresh dos resultados sem duplicar a lógica de montar querystring/fetch/troca de HTML
    // daqui — usado depois de uma edição em linha que pode ter mudado o agrupamento atual
    // (ex: mudar Status enquanto a lista está agrupada por Status).
    form._liveFilterRefresh = runSearch;
}

// Atualiza o resultado da tela (se ela tiver um form[data-live-filter]) sem recarregar nada —
// no-op silencioso se a tela atual não usa live-filter.
export function refreshLiveFilter() {
    document.querySelector('form[data-live-filter]')?._liveFilterRefresh?.();
}
