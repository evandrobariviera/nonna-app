export function registerDossierEditor() {
    const dossierEditorFn = (initialCompetitors, initialPersonas, initialValores, initialExemplos) => {
        const urls = window._dossierUrls || {};
        const safeArr = v => Array.isArray(v) ? v : [];
        const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

        return {
            mode: 'roteiro',
            section: 'config',
            saving: false,
            savedAt: '',
            dirty: false,
            timer: null,

            competitors: safeArr(initialCompetitors).map(c => ({ ...c, _editing: false })),
            personas:    safeArr(initialPersonas),
            addingCompetitor: false,
            addingPersona:    false,

            valores:     safeArr(initialValores).length ? initialValores : [],
            exemplosTom: safeArr(initialExemplos).length ? initialExemplos : [],

            newCompetitor: { nome: '', o_que_comunica: '', como_se_posiciona: '', o_que_nao_ocupa: '' },
            newPersona:    { tipo: 'principal', nome_ficticio: '', idade_genero: '', cargo_setor: '', renda_contexto: '', como_se_informa: '', o_que_valida_compra: '', dores_principais: '', o_que_motiva: '', o_que_nunca_diria: '', insight_persona: '' },

            init() {},

            markDirty() {
                this.dirty = true;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.save(), 3000);
            },

            async save() {
                if (this.saving) return;
                this.saving = true;
                const form = document.getElementById('dossier-form');
                const data = new FormData(form);
                data.append('_method', 'PATCH');
                try {
                    const res = await fetch(urls.update, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                        body: data,
                    });
                    if (res.ok) {
                        const json = await res.json();
                        this.savedAt = json.saved_at;
                        this.dirty = false;
                    } else {
                        const err = await res.json().catch(() => ({}));
                        console.error('[dossier save] HTTP ' + res.status, err.error ?? '');
                    }
                } catch(e) { console.error('save error', e); }
                this.saving = false;
            },

            addValor() {
                this.valores.push({ nome: '', descricao: '' });
                this.markDirty();
            },
            removeValor(i) {
                this.valores.splice(i, 1);
                this.markDirty();
            },

            addExemplo() {
                this.exemplosTom.push({ correto: '', incorreto: '' });
                this.markDirty();
            },
            removeExemplo(i) {
                this.exemplosTom.splice(i, 1);
                this.markDirty();
            },

            resetNewCompetitor() {
                this.newCompetitor = { nome: '', o_que_comunica: '', como_se_posiciona: '', o_que_nao_ocupa: '' };
            },
            resetNewPersona() {
                this.newPersona = { tipo: 'principal', nome_ficticio: '', idade_genero: '', cargo_setor: '', renda_contexto: '', como_se_informa: '', o_que_valida_compra: '', dores_principais: '', o_que_motiva: '', o_que_nunca_diria: '', insight_persona: '' };
            },

            async saveCompetitor() {
                if (!this.newCompetitor.nome.trim()) return;
                const res = await fetch(urls.competitorsStore, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify(this.newCompetitor),
                });
                if (res.ok) {
                    const c = await res.json();
                    this.competitors.push({ ...c, _editing: false });
                    this.resetNewCompetitor();
                    this.addingCompetitor = false;
                }
            },

            async updateCompetitor(c) {
                c._editing = false;
                await fetch(urls.competitorUrl.replace('__ID__', c.id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ ...c, _method: 'PATCH' }),
                });
            },

            async deleteCompetitor(id) {
                if (!confirm('Remover este concorrente?')) return;
                const res = await fetch(urls.competitorUrl.replace('__ID__', id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                if (res.ok) this.competitors = this.competitors.filter(c => c.id !== id);
            },

            async savePersona() {
                const res = await fetch(urls.personasStore, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify(this.newPersona),
                });
                if (res.ok) {
                    const p = await res.json();
                    this.personas.push(p);
                    this.resetNewPersona();
                    this.addingPersona = false;
                }
            },

            async deletePersona(id) {
                if (!confirm('Remover esta persona?')) return;
                const res = await fetch(urls.personaUrl.replace('__ID__', id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                if (res.ok) this.personas = this.personas.filter(p => p.id !== id);
            },
        };
    };

    // Register via Alpine.data() AND expose on window as fallback
    Alpine.data('dossierEditor', dossierEditorFn);
    window.dossierEditor = dossierEditorFn;
}
