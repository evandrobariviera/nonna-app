<x-app-layout>
    <x-slot name="header">Projetos & Campanhas</x-slot>

    <div
        x-data="projectDashboard({{ $projectsJson }}, {{ $macroplansJson }})"
        x-init="init()"
        x-cloak
    >

        {{-- ── STATS ──────────────────────────────────────────────────────────── --}}
        <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(7, 1fr)">

            <button @click="filterType = ''; filterStatus = ''; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="!filterOverdue && !filterNotStarted && filterStatus === '' && filterType === '' ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--text)">{{ $stats['total'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Total</div>
            </button>

            <button @click="filterType = 'projeto'; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterType === 'projeto' ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--purple)">{{ $stats['projetos'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Projetos</div>
            </button>

            <button @click="filterType = 'campanha'; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterType === 'campanha' ? 'border-color:var(--green); box-shadow:0 0 0 1px var(--green)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--green)">{{ $stats['campanhas'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Campanhas</div>
            </button>

            <button @click="filterStatus = 'active'; filterType = ''; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterStatus === 'active' && !filterOverdue ? 'border-color:var(--green); box-shadow:0 0 0 1px var(--green)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--green)">{{ $stats['active'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Ativos</div>
            </button>

            <button @click="filterOverdue = !filterOverdue; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterOverdue ? 'border-color:var(--red); box-shadow:0 0 0 1px var(--red)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--red)">{{ $stats['overdue'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Com Atraso</div>
            </button>

            <button @click="filterNotStarted = !filterNotStarted; filterOverdue = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterNotStarted ? 'border-color:var(--orange); box-shadow:0 0 0 1px var(--orange)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--orange)">{{ $stats['not_started'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Sem Tarefas</div>
            </button>

            <button @click="filterStatus = 'completed'; filterType = ''; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterStatus === 'completed' ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--purple)">{{ $stats['completed'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Concluídos</div>
            </button>

        </div>

        {{-- ── BARRA DE CONTROLES ──────────────────────────────────────────── --}}
        <div class="card px-4 py-3 mb-5 flex flex-wrap items-center gap-3">

            <div class="relative flex-1" style="min-width:180px; max-width:260px">
                <input type="text" x-model="search" @input="applyFilters()"
                    placeholder="Buscar projeto, campanha ou cliente…"
                    class="w-full pl-8 pr-3 py-2 text-xs focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                    onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                <svg class="absolute left-2.5 top-2.5 h-3.5 w-3.5" style="color:var(--muted)" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>

            <select x-model="filterClient" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text); min-width:160px">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                @endforeach
            </select>

            <select x-model="filterStatus" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os status</option>
                <option value="draft">Rascunho</option>
                <option value="active">Ativo</option>
                <option value="completed">Concluído</option>
                <option value="cancelled">Cancelado</option>
            </select>

            <select x-model="filterDiscipline" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todas as disciplinas</option>
                <option value="criacao">Criação</option>
                <option value="web">Web / Dev</option>
                <option value="trafego">Tráfego</option>
                <option value="setup">Setup</option>
                <option value="social">Social Media</option>
                <option value="seo">SEO</option>
                <option value="email">E-mail</option>
            </select>

            <div class="h-5" style="border-left:1px solid var(--border2)"></div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-mono" style="color:var(--muted)">Ordenar:</span>
                <div class="flex gap-1">
                    <template x-for="opt in sortOptions" :key="opt.key">
                        <button @click="sortBy = opt.key; applyFilters()"
                            class="px-3 py-1.5 text-xs font-mono transition-all"
                            :style="sortBy === opt.key
                                ? 'background:var(--purple); color:#fff; border:1px solid var(--purple)'
                                : 'background:var(--s3); color:var(--muted2); border:1px solid var(--border2)'"
                            x-text="opt.label">
                        </button>
                    </template>
                </div>
            </div>

            <button @click="resetFilters()" x-show="hasActiveFilters()"
                class="text-xs font-mono transition-colors ml-auto" style="color:var(--muted)"
                onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                ✕ Limpar filtros
            </button>

            <span class="text-xs font-mono ml-auto" style="color:var(--muted)">
                <span x-text="filtered.length"></span> resultado<span x-show="filtered.length !== 1">s</span>
            </span>

        </div>

        {{-- ── GRID DE CARDS ───────────────────────────────────────────────── --}}
        <div x-show="filtered.length === 0" class="card px-5 py-12 text-center">
            <p class="text-sm" style="color:var(--muted)">Nenhum item encontrado com os filtros aplicados.</p>
        </div>

        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
            <template x-for="p in filtered" :key="p.id">
                <div class="card flex flex-col relative"
                     :style="typeTopBorder(p) + urgencyLeftBorder(p)">

                    {{-- Lápis de edição rápida --}}
                    <button @click="openEdit(p)"
                        class="absolute top-2 right-2 p-1.5 rounded transition-colors z-10"
                        style="color:var(--muted)"
                        onmouseover="this.style.color='var(--purple)'; this.style.background='var(--s2)'"
                        onmouseout="this.style.color='var(--muted)'; this.style.background='transparent'"
                        title="Editar">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </button>

                    <div class="px-4 pt-4 pb-3 flex-1">

                        {{-- Alertas --}}
                        <div x-show="p.has_overdue"
                             class="flex items-center gap-1.5 text-xs font-bold font-mono mb-2 px-2 py-1"
                             style="background:rgba(239,68,68,.08); color:var(--red); border:1px solid rgba(239,68,68,.2)">
                            <span>⚠</span>
                            <span x-text="p.overdue_tasks + ' tarefa' + (p.overdue_tasks > 1 ? 's atrasadas' : ' atrasada')"></span>
                        </div>
                        <div x-show="p.not_started && !p.has_overdue"
                             class="flex items-center gap-1.5 text-xs font-bold font-mono mb-2 px-2 py-1"
                             style="background:rgba(255,140,0,.08); color:var(--orange); border:1px solid rgba(255,140,0,.2)">
                            <span>◉</span><span>Sem tarefas</span>
                        </div>

                        {{-- Badges tipo + status --}}
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <span class="badge text-xs font-bold"
                                  :class="'badge-' + p.type_color"
                                  x-text="p.type_label"></span>
                            <span class="badge text-xs"
                                  :class="'badge-' + p.status_color"
                                  x-text="p.status_label"></span>
                        </div>

                        {{-- Cliente --}}
                        <div class="text-xs font-mono font-semibold truncate mb-0.5 pr-6" style="color:var(--muted)" x-text="p.client_name"></div>

                        {{-- Título --}}
                        <h3 class="font-bold text-sm leading-snug mb-1 pr-6" style="color:var(--text)" x-text="p.title"></h3>

                        {{-- Planejamento --}}
                        <p class="text-xs mb-3 truncate" style="color:var(--muted2)"
                           x-text="p.macroplan_title !== '—' ? '[ROADMAP] ' + p.macroplan_title : 'Sem planejamento'"></p>

                        {{-- Disciplinas --}}
                        <div class="flex flex-wrap gap-1 mb-3" x-show="p.discipline_labels.length > 0">
                            <template x-for="d in p.discipline_labels" :key="d">
                                <span class="badge text-xs" x-text="d"></span>
                            </template>
                        </div>

                        {{-- Progresso --}}
                        <div class="mb-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">Progresso</span>
                                <span class="text-xs font-black font-mono"
                                      :style="p.progress === 100 ? 'color:var(--green)' : 'color:var(--text)'"
                                      x-text="p.progress + '%'"></span>
                            </div>
                            <div class="w-full h-1.5 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-1.5 rounded-full transition-all duration-700"
                                     :style="'width:' + p.progress + '%; background:' + (p.progress === 100 ? 'var(--green)' : 'var(--grad)')"></div>
                            </div>
                            <p class="text-xs mt-1 font-mono" style="color:var(--muted)"
                               x-text="p.done_tasks + '/' + p.total_tasks + ' tarefas concluídas'"></p>
                        </div>

                        {{-- Kanban cols --}}
                        <div class="grid grid-cols-4 gap-1">
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--muted)" x-text="p.col_counts.backlog"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px">Backlog</div>
                            </div>
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--orange)" x-text="p.col_counts.em_andamento"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px">Andamento</div>
                            </div>
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--purple)" x-text="p.col_counts.revisao"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px">Revisão</div>
                            </div>
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--green)" x-text="p.col_counts.concluido"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px">Concluído</div>
                            </div>
                        </div>

                    </div>

                    {{-- Footer --}}
                    <div class="px-4 py-3 flex items-center justify-between gap-2"
                         style="border-top:1px solid var(--border2)">
                        <a x-show="p.macroplan_url" :href="p.macroplan_url || '#'"
                           class="text-xs font-mono transition-colors" style="color:var(--muted)"
                           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                            ↗ Planejamento
                        </a>
                        <span x-show="!p.macroplan_url" class="text-xs font-mono" style="color:var(--border2)">Sem planejamento</span>

                        <a x-show="p.url" :href="p.url || '#'"
                           class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity"
                           style="background:var(--purple)"
                           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                            Abrir →
                        </a>
                        <span x-show="!p.url"
                              class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest"
                              style="background:var(--border2); color:var(--muted)">
                            Sem planejamento
                        </span>
                    </div>

                </div>
            </template>
        </div>

        {{-- ── MODAL DE EDIÇÃO RÁPIDA ──────────────────────────────────────── --}}
        <div x-show="editOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,.5)">
            <div class="card w-full max-w-md" @click.outside="editOpen = false">

                <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                    <div>
                        <h3 class="font-bold text-sm" style="color:var(--text)" x-text="editTarget?.title"></h3>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--muted)" x-text="editTarget?.client_name"></p>
                    </div>
                    <button @click="editOpen = false" class="text-xs font-mono" style="color:var(--muted)">✕</button>
                </div>

                <div class="px-5 py-4 flex flex-col gap-4">

                    {{-- Tipo --}}
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Tipo</label>
                        <div class="flex gap-2">
                            <button @click="editForm.type = 'projeto'"
                                class="flex-1 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-all"
                                :style="editForm.type === 'projeto'
                                    ? 'background:var(--purple); color:#fff; border:2px solid var(--purple)'
                                    : 'background:var(--s3); color:var(--muted); border:2px solid var(--border2)'">
                                Projeto
                            </button>
                            <button @click="editForm.type = 'campanha'"
                                class="flex-1 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-all"
                                :style="editForm.type === 'campanha'
                                    ? 'background:var(--green); color:#fff; border:2px solid var(--green)'
                                    : 'background:var(--s3); color:var(--muted); border:2px solid var(--border2)'">
                                Campanha
                            </button>
                        </div>
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                        <select x-model="editForm.status"
                            class="w-full px-3 py-2 text-xs focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="draft">Rascunho</option>
                            <option value="active">Ativo</option>
                            <option value="completed">Concluído</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>

                    {{-- Planejamento --}}
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Planejamento (Roadmap)</label>
                        <select x-model="editForm.macro_plan_id"
                            class="w-full px-3 py-2 text-xs focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">— Sem planejamento —</option>
                            <template x-for="mp in macroplans" :key="mp.id">
                                <option :value="mp.id" x-text="mp.title"></option>
                            </template>
                        </select>
                    </div>

                </div>

                <div class="px-5 py-4 flex items-center justify-end gap-3" style="border-top:1px solid var(--border2)">
                    <button @click="editOpen = false" class="px-4 py-2 text-xs font-mono" style="color:var(--muted)">Cancelar</button>
                    <button @click="saveEdit()" :disabled="editSaving"
                        class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity"
                        style="background:var(--purple)"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        <span x-show="!editSaving">Salvar</span>
                        <span x-show="editSaving">Salvando…</span>
                    </button>
                </div>

            </div>
        </div>

    </div>{{-- /x-data --}}

    <script>
    function projectDashboard(projects, macroplans) {
        return {
            all: projects,
            macroplans: macroplans,
            filtered: [],
            search: '',
            filterType: '',
            filterStatus: '',
            filterClient: '',
            filterDiscipline: '',
            filterOverdue: false,
            filterNotStarted: false,
            sortBy: 'urgency',

            editOpen: false,
            editSaving: false,
            editTarget: null,
            editForm: { type: 'projeto', status: 'active', macro_plan_id: '' },

            sortOptions: [
                { key: 'urgency',       label: 'Urgência' },
                { key: 'progress_asc',  label: 'Menos avançado' },
                { key: 'progress_desc', label: 'Mais avançado' },
                { key: 'client',        label: 'Cliente A–Z' },
                { key: 'recent',        label: 'Recente' },
            ],

            typeTopBorder(p) {
                return p.type === 'campanha'
                    ? 'border-top:3px solid var(--green);'
                    : 'border-top:3px solid var(--purple);';
            },

            urgencyLeftBorder(p) {
                if (p.has_overdue)    return 'border-left:3px solid var(--red);';
                if (p.progress===100) return 'border-left:3px solid var(--green);';
                return '';
            },

            init() { this.applyFilters(); },

            applyFilters() {
                let result = [...this.all];
                if (this.search.trim()) {
                    const q = this.search.toLowerCase();
                    result = result.filter(p =>
                        p.title.toLowerCase().includes(q) ||
                        p.client_name.toLowerCase().includes(q) ||
                        p.macroplan_title.toLowerCase().includes(q)
                    );
                }
                if (this.filterType)       result = result.filter(p => p.type === this.filterType);
                if (this.filterStatus)     result = result.filter(p => p.status === this.filterStatus);
                if (this.filterClient)     result = result.filter(p => p.client_id === this.filterClient);
                if (this.filterDiscipline) result = result.filter(p => p.disciplines.includes(this.filterDiscipline));
                if (this.filterOverdue)    result = result.filter(p => p.has_overdue);
                if (this.filterNotStarted) result = result.filter(p => p.not_started);
                this.filtered = this.sortProjects(result);
            },

            sortProjects(list) {
                switch (this.sortBy) {
                    case 'urgency':
                        return list.sort((a, b) => {
                            if (b.has_overdue !== a.has_overdue) return b.has_overdue - a.has_overdue;
                            if (b.overdue_tasks !== a.overdue_tasks) return b.overdue_tasks - a.overdue_tasks;
                            if (b.not_started !== a.not_started) return b.not_started - a.not_started;
                            return a.progress - b.progress;
                        });
                    case 'progress_asc':
                        return list.sort((a, b) => {
                            if (a.not_started !== b.not_started) return b.not_started - a.not_started;
                            return a.progress - b.progress;
                        });
                    case 'progress_desc':
                        return list.sort((a, b) => b.progress - a.progress);
                    case 'client':
                        return list.sort((a, b) => a.client_name.localeCompare(b.client_name));
                    default:
                        return list;
                }
            },

            hasActiveFilters() {
                return this.search.trim() || this.filterType || this.filterStatus || this.filterClient ||
                       this.filterDiscipline || this.filterOverdue || this.filterNotStarted ||
                       this.sortBy !== 'urgency';
            },

            resetFilters() {
                this.search = ''; this.filterType = ''; this.filterStatus = '';
                this.filterClient = ''; this.filterDiscipline = '';
                this.filterOverdue = false; this.filterNotStarted = false;
                this.sortBy = 'urgency';
                this.applyFilters();
            },

            openEdit(p) {
                this.editTarget = p;
                this.editForm = {
                    type:          p.type || 'projeto',
                    status:        p.status,
                    macro_plan_id: p.macroplan_id || '',
                };
                this.editOpen = true;
            },

            async saveEdit() {
                if (!this.editTarget) return;
                this.editSaving = true;
                try {
                    const res = await fetch(this.editTarget.edit_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-HTTP-Method-Override': 'PATCH',
                        },
                        body: JSON.stringify({
                            _method:       'PATCH',
                            type:          this.editForm.type,
                            status:        this.editForm.status,
                            macro_plan_id: this.editForm.macro_plan_id || null,
                        }),
                    });
                    const json = await res.json();
                    if (json.success) {
                        Object.assign(this.editTarget, {
                            type:            json.type,
                            type_label:      json.type_label,
                            type_color:      json.type_color,
                            status:          json.status,
                            status_label:    json.status_label,
                            status_color:    json.status_color,
                            macroplan_id:    json.macroplan_id,
                            macroplan_title: json.macroplan_title,
                            client_name:     json.client_name,
                            url:             json.url,
                            macroplan_url:   json.macroplan_url,
                        });
                        this.editOpen = false;
                        this.applyFilters();
                    }
                } finally {
                    this.editSaving = false;
                }
            },
        };
    }
    </script>

</x-app-layout>
