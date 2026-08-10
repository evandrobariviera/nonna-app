// Estado de aberto/fechado dos grupos colapsáveis (Filas/Lista da Sprint), guardado fora do
// x-data de cada <tbody> pra sobreviver a um refresh de live-filter (troca de innerHTML —
// ver live-filter.js). Sem isso, toda edição inline que força um refresh (ex: mudar Status
// enquanto a lista está agrupada por Status, ver monday-fill.js) recriava os <tbody> do zero
// e todo grupo que o usuário tinha aberto voltava a fechar.
export function registerGroupCollapse(Alpine) {
    Alpine.store('groupCollapse', {
        open: {},

        isOpen(key, fallback) {
            return key in this.open ? this.open[key] : fallback;
        },

        setOpen(key, value) {
            this.open[key] = value;
        },
    });
}
