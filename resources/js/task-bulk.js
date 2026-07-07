export function registerTaskBulk() {
    const taskBulkFn = () => ({
        selected: [],
        bulkStatus: '',
        bulkExecutor: '',
        bulkSituation: '',
        bulkProject: '',
        applying: false,

        toggleAll(checked, scopeSelector) {
            const boxes = document.querySelectorAll((scopeSelector || '') + ' input[type=checkbox][data-task-id]');
            this.selected = checked ? Array.from(boxes).map(el => el.value) : [];
        },

        async apply(action, extra) {
            if (this.selected.length === 0) return;
            this.applying = true;
            try {
                const res = await fetch('/tarefas/lote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ task_ids: this.selected, action, ...extra }),
                });

                if (!res.ok) {
                    let msg = 'Não foi possível aplicar a ação (erro ' + res.status + ').';
                    try {
                        const errJson = await res.json();
                        if (errJson.message) msg = errJson.message;
                    } catch (e) { /* resposta não era JSON */ }
                    alert(msg);
                    return;
                }

                const json = await res.json();
                if (json.success) {
                    if (json.skipped && json.skipped.length > 0) {
                        alert(json.skipped.length + ' tarefa(s) pulada(s):\n' +
                            json.skipped.map(s => '- ' + s.title + ' (' + s.reason + ')').join('\n'));
                    }
                    window.location.reload();
                }
            } catch (e) {
                alert('Erro de rede ao aplicar a ação. Tente novamente.');
            } finally {
                this.applying = false;
            }
        },
    });

    // Register via Alpine.data() AND expose on window as fallback
    Alpine.data('taskBulk', taskBulkFn);
    window.taskBulk = taskBulkFn;
}
