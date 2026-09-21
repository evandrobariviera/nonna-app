// Extensão dia-a-dia da Semana de Produção: os botões ‹ › nas pontas do quadro buscam UM dia
// a mais via AJAX (não a semana inteira) e inserem a coluna sem recarregar nada — pensado pro
// custo de servidor: cada clique é uma consulta leve (ver ProductionPanelController::
// diaKanban()), não o board inteiro de novo. Limitado a ±7 dias de hoje; pra ir mais longe,
// os botões "‹ Semana anterior / Próxima semana ›" (que também são AJAX, só que buscam 5 dias
// de uma vez) continuam ali em cima.
// Registrado em window (não export puro) igual initKanbanDnd — a página chama isso de um
// <script> comum no blade, não de um módulo ES.
export function registerProducaoWeekScroll() {
    window.initProducaoWeekScroll = initProducaoWeekScroll;
}

function initProducaoWeekScroll() {
    const board = document.getElementById('producao-week-board');
    const btnBefore = document.getElementById('producao-week-extend-before');
    const btnAfter = document.getElementById('producao-week-extend-after');
    const filterForm = document.getElementById('producao-week-filter-form');
    if (!board || !btnBefore || !btnAfter || !filterForm) return;

    const LIMITE_DIAS = 7;

    function proximoDiaUtil(dataIso, direcao) {
        const d = new Date(dataIso + 'T00:00:00');
        do {
            d.setDate(d.getDate() + direcao);
        } while (d.getDay() === 0 || d.getDay() === 6); // pula sábado/domingo — dia útil só
        return d.toISOString().slice(0, 10);
    }

    function diasDeHoje(dataIso) {
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);
        const d = new Date(dataIso + 'T00:00:00');
        return Math.round((d - hoje) / 86400000);
    }

    function colunas() {
        return Array.from(board.querySelectorAll('[data-kanban-column]'));
    }

    function atualizarLimites() {
        const cols = colunas();
        if (!cols.length) return;
        const primeira = cols[0].dataset.status;
        const ultima = cols[cols.length - 1].dataset.status;
        btnBefore.style.display = Math.abs(diasDeHoje(proximoDiaUtil(primeira, -1))) > LIMITE_DIAS ? 'none' : '';
        btnAfter.style.display = Math.abs(diasDeHoje(proximoDiaUtil(ultima, 1))) > LIMITE_DIAS ? 'none' : '';
    }

    function estender(direcao) {
        const cols = colunas();
        if (!cols.length) return;

        const referencia = direcao < 0 ? cols[0].dataset.status : cols[cols.length - 1].dataset.status;
        const alvo = proximoDiaUtil(referencia, direcao);
        if (Math.abs(diasDeHoje(alvo)) > LIMITE_DIAS) return;

        const btn = direcao < 0 ? btnBefore : btnAfter;
        if (btn.disabled) return;
        btn.disabled = true;

        const params = new URLSearchParams(new FormData(filterForm));
        params.set('data', alvo);

        fetch(filterForm.dataset.dayResultsUrl + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => { if (!res.ok) throw res; return res.text(); })
            .then((html) => {
                let novaColuna;
                if (direcao < 0) {
                    btnBefore.insertAdjacentHTML('afterend', html);
                    novaColuna = btnBefore.nextElementSibling;
                } else {
                    btnAfter.insertAdjacentHTML('beforebegin', html);
                    novaColuna = btnAfter.previousElementSibling;
                }
                // Conteúdo inserido via insertAdjacentHTML não passa pelo scan automático do
                // Alpine — sem isso, o @click de abrir o popup e o x-show de "mostrar mais"
                // (coluna com muito card) simplesmente não respondem.
                if (window.Alpine && novaColuna) window.Alpine.initTree(novaColuna);
                window.initKanbanDnd('#producao-week-board');
                atualizarLimites();
            })
            .catch(() => { /* silencioso: o pior caso é o botão simplesmente não responder */ })
            .finally(() => { btn.disabled = false; });
    }

    btnBefore.onclick = () => estender(-1);
    btnAfter.onclick = () => estender(1);
    atualizarLimites();
}
