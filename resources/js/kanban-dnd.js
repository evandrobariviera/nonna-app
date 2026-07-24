import Sortable from 'sortablejs';

// Arrastar-e-soltar genérico pros boards Kanban (Sprint, Projeto, Oportunidades).
// Contrato de atributos (ver plano):
//   [data-kanban-board][data-status-field]  — raiz do board ("status" ou "stage")
//   [data-kanban-column][data-status][data-extra] — cada coluna. data-extra (opcional) é um
//                                            JSON com campos extras pra mandar junto no PATCH
//                                            quando o card cai NESSA coluna (ex: uma coluna que
//                                            representa status+situação, não só status puro).
//     [data-kanban-count]                   — contador da coluna (texto = número)
//     [data-kanban-list]                    — contêiner arrastável dos cards
//       [data-kanban-card][data-id][data-update-url] — cada card
export function registerKanbanDnd() {
    window.initKanbanDnd = function (boardSelector) {
        document.querySelectorAll(boardSelector).forEach((board) => {
            const statusField = board.dataset.statusField || 'status';
            const groupName = 'kanban-' + Math.random().toString(36).slice(2);

            board.querySelectorAll('[data-kanban-list]').forEach((list) => {
                new Sortable(list, {
                    group: groupName,
                    sort: false,
                    animation: 150,
                    ghostClass: 'kanban-ghost',
                    // forceFallback: SortableJS usa HTML5 drag nativo por padrão, que é
                    // inconsistente (às vezes simplesmente não inicia) dentro de containers
                    // flex com overflow-x:auto — exatamente o layout dos nossos boards
                    // (colunas lado a lado com scroll horizontal). O fallback (mouse/touch
                    // emulado via JS) é mais confiável nesse cenário e funciona em touch.
                    forceFallback: true,
                    fallbackClass: 'kanban-fallback',
                    fallbackOnBody: true,
                    onEnd(evt) {
                        if (evt.from === evt.to) return;

                        const card = evt.item;
                        const fromColumn = evt.from.closest('[data-kanban-column]');
                        const toColumn = evt.to.closest('[data-kanban-column]');
                        const newStatus = toColumn.dataset.status;
                        const updateUrl = card.dataset.updateUrl;
                        const extra = toColumn.dataset.extra ? JSON.parse(toColumn.dataset.extra) : {};

                        fetch(updateUrl, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ [statusField]: newStatus, ...extra }),
                        }).then((res) => {
                            if (!res.ok) throw new Error('update failed');

                            updateCount(fromColumn, -1);
                            updateCount(toColumn, 1);

                            board.dispatchEvent(new CustomEvent('kanban:moved', {
                                detail: { card, fromColumn, toColumn, newStatus },
                            }));
                        }).catch(() => {
                            const ref = evt.from.children[evt.oldIndex] || null;
                            evt.from.insertBefore(card, ref);
                            alert('Não foi possível mover o card. Tente novamente.');
                        });
                    },
                });
            });
        });
    };

    function updateCount(columnEl, delta) {
        const el = columnEl.querySelector('[data-kanban-count]');
        if (!el) return;
        el.textContent = String(parseInt(el.textContent, 10) + delta);
    }
}
