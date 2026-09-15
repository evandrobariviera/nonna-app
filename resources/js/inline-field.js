// Campo de texto/data — clica, edita, sai do campo (blur) já salva via PATCH, sem
// reload. Nasceu em tasks/show.blade.php (Título, as 3 datas, Legenda, Solicitante) e foi
// generalizado pra reaproveitar em qualquer lista/kanban (Fila, Sprint) via
// <x-inline-date-cell>, não só no detalhe da tarefa.
export function registerInlineField(Alpine) {
    Alpine.data('inlineField', ({ url, field = null, payloadKey = null, value }) => ({
        editing: false,
        value: value,
        original: value,
        saving: false,
        open() {
            this.original = this.value;
            this.editing = true;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        async commit() {
            if (!this.editing) return;
            this.editing = false;
            if (this.value === this.original) return;
            this.saving = true;
            const payload = field ? { field, value: this.value } : { [payloadKey]: this.value };
            const { ok, message } = await window.inlinePatch(url, payload);
            this.saving = false;
            if (!ok) {
                alert(message || 'Falha ao salvar. Tente de novo.');
                this.value = this.original;
            } else {
                this.original = this.value;
            }
        },
        cancel() {
            this.value = this.original;
            this.editing = false;
        },
    }));
}
