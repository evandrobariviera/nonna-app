// Dropdowns "Monday fill" (Status/Situação/Responsável/Executor) — um único menu
// compartilhado por tipo (badge ou pessoa), reposicionado a cada clique, em vez de
// duplicar a lista de opções (status/situação/usuários) em CADA linha da tabela.
// Com centenas de tarefas isso gerava um HTML de vários MB (~8 mil botões nunca
// abertos), deixando a página lenta pra carregar/hidratar. Cada linha só guarda seu
// próprio valor atual (texto/avatar); a lista de opções vive uma vez só, fora da tabela
// (ver components/badge-fill-menu.blade.php e components/person-fill-menu.blade.php).
// Só vale a pena re-buscar a lista inteira (fecha/pisca a tabela) quando o campo que acabou
// de mudar é justamente o critério de agrupamento ATUAL da tela — ex: mudou o Executor mas a
// tela está agrupada por Cliente, a tarefa continua no grupo certo, não precisa de nada do
// servidor. Sem isso, toda edição (mesmo sem afetar agrupamento nenhum) recarregava a tabela
// à toa, fechando o dropdown com uma piscada visível.
function refreshLiveFilterIfGroupedBy(...groupKeys) {
    const select = document.querySelector('form[data-live-filter] select[name$="group_by"]');
    if (select && groupKeys.includes(select.value)) {
        window.refreshLiveFilter?.();
    }
}

export function registerMondayFill(Alpine) {
    Alpine.store('badgeFill', {
        open: false,
        style: '',
        field: null, // 'status' | 'situation'
        taskId: null,
        url: null,
        currentKey: null,

        openFor(el, { field, taskId, url, currentKey }) {
            Alpine.store('personFill').close();
            this.field = field;
            this.taskId = taskId;
            this.url = url;
            this.currentKey = currentKey;
            this.style = window.dropdownStyle(el, 'bottom-left');
            this.open = true;
        },

        close() {
            this.open = false;
        },

        select(key, label, color) {
            const taskId = this.taskId;
            const field = this.field;
            window.applyFill(this.url, { [field]: key }, () => {
                window.dispatchEvent(new CustomEvent('badge-fill-applied', {
                    detail: { taskId, field, key, label, color },
                }));
                this.open = false;
                if (field === 'status') refreshLiveFilterIfGroupedBy('status');
            });
        },
    });

    Alpine.store('personFill', {
        open: false,
        style: '',
        role: null, // 'responsavel' | 'executor'
        fieldName: null,
        taskId: null,
        url: null,
        currentName: null,

        openFor(el, { role, fieldName, taskId, url, currentName }) {
            Alpine.store('badgeFill').close();
            this.role = role;
            this.fieldName = fieldName;
            this.taskId = taskId;
            this.url = url;
            this.currentName = currentName;
            this.style = window.dropdownStyle(el, 'bottom-left');
            this.open = true;
        },

        close() {
            this.open = false;
        },

        select(userId, name, avatarUrl, initials) {
            const taskId = this.taskId;
            const role = this.role;
            window.applyFill(this.url, { [this.fieldName]: userId }, () => {
                window.dispatchEvent(new CustomEvent('person-fill-applied', {
                    detail: { taskId, role, name, avatarUrl, initials },
                }));
                this.open = false;
                refreshLiveFilterIfGroupedBy(role);
            });
        },
    });
}
