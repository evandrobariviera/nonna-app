import Sortable from 'sortablejs';

// Cockpit de Distribuição da Dashboard (resources/views/dashboard/sections/distribuicao.blade.php).
// Arrastar uma tarefa de "A distribuir" pra uma célula da grade Pessoa × Dia define executor
// e data de aprovação de uma vez. Não tem endpoint próprio de propósito: chama os mesmos
// PATCH que a página da tarefa usa (executor-direto e data-aprovacao), então trava de WIP,
// histórico (TaskActivity) e automações (executor_added) valem exatamente igual.
//
// Contrato de atributos:
//   [data-dist-source]                       — lista "A distribuir"
//     [data-dist-task][data-executor-url][data-date-url][data-current-executor]
//   [data-dist-cell][data-user-id][data-date] — célula da grade (só quando dá pra agir)
export function registerDistributionBoard() {
    const headers = () => ({
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    });

    async function patch(url, body) {
        const res = await fetch(url, { method: 'PATCH', headers: headers(), body: JSON.stringify(body) });
        if (!res.ok) {
            let msg = 'Não foi possível atribuir a tarefa. Tente novamente.';
            try {
                const json = await res.json();
                if (json.message) msg = json.message;
            } catch (e) { /* resposta não era JSON */ }
            throw new Error(msg);
        }
    }

    // Usado pelo "clique pra mover" (recebe o objeto da tarefa) e pelo arrastar (recebe o card;
    // o dataset tem as mesmas chaves: executorUrl, dateUrl, currentExecutor).
    // Executor primeiro: se a trava de WIP barrar, a data nem chega a mudar.
    window.distAssign = async function (task, userId, date) {
        const t = task instanceof HTMLElement ? task.dataset : task;
        try {
            if (String(t.currentExecutor || '') !== String(userId)) {
                await patch(t.executorUrl, { executor_id: Number(userId) });
            }
            await patch(t.dateUrl, { approval_date: date });
            window.location.reload();
        } catch (err) {
            alert(err.message);
        }
    };

    window.initDistributionBoard = function (selector) {
        const root = document.querySelector(selector);
        if (!root) return;

        const source = root.querySelector('[data-dist-source]');
        if (source) {
            new Sortable(source, {
                group: { name: 'distribution', pull: 'clone', put: false },
                sort: false,
                animation: 150,
                forceFallback: true,
                fallbackOnBody: true,
                fallbackClass: 'kanban-fallback',
                // Cliques no título/botão/form do card não podem virar arraste.
                filter: 'button, select, input, [x-show]',
                preventOnFilter: false,
                draggable: '[data-dist-task]',
            });
        }

        root.querySelectorAll('[data-dist-cell]').forEach((cell) => {
            new Sortable(cell, {
                group: { name: 'distribution', pull: false, put: true },
                sort: false,
                onAdd(evt) {
                    const taskEl = evt.item;
                    // O card "clonado" só serviu de transporte — a célula mostra número, não card.
                    taskEl.remove();
                    cell.style.opacity = '.5';
                    window.distAssign(taskEl, cell.dataset.userId, cell.dataset.date)
                        .finally(() => { cell.style.opacity = ''; });
                },
            });
        });
    };
}
