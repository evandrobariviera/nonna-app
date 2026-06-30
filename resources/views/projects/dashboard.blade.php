<x-app-layout>
    <x-slot name="header">Projetos — Painel do Gestor</x-slot>

    <div
        x-data="projectDashboard({{ $projectsJson }})"
        x-init="init()"
        x-cloak
    >

        {{-- ── STATS RÁPIDAS ──────────────────────────────────────────────── --}}
        <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(5, 1fr)">

            <button @click="filterStatus = filterStatus === '' ? '' : ''; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="!filterOverdue && !filterNotStarted && filterStatus === '' ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--text)">{{ $stats['total'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Total</div>
            </button>

            <button @click="filterStatus = 'active'; filterOverdue = false; filterNotStarted = false; applyFilters()"
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

            <button @click="filterStatus = 'completed'; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterStatus === 'completed' ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : ''">
                <div class="text-2xl font-black mb-1" style="color:var(--purple)">{{ $stats['completed'] }}</div>
                <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Concluídos</div>
            </button>

        </div>

        {{-- ── BARRA DE CONTROLES ──────────────────────────────────────────── --}}
        <div class="card px-4 py-3 mb-5 flex flex-wrap items-center gap-3">

            {{-- Busca --}}
            <div class="relative flex-1" style="min-width:180px; max-width:260px">
                <input
                    type="text"
                    x-model="search"
                    @input="applyFilters()"
                    placeholder="Buscar projeto ou cliente…"
                    class="w-full pl-8 pr-3 py-2 text-xs focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                    onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                <svg class="absolute left-2.5 top-2.5 h-3.5 w-3.5" style="color:var(--muted)" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>

            {{-- Cliente --}}
            <select x-model="filterClient" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text); min-width:160px">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                @endforeach
            </select>

            {{-- Status --}}
            <select x-model="filterStatus" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os status</option>
                <option value="draft">Rascunho</option>
                <option value="active">Ativo</option>
                <option value="completed">Concluído</option>
                <option value="cancelled">Cancelado</option>
            </select>

            {{-- Disciplina --}}
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

            {{-- Separador --}}
            <div class="h-5" style="border-left:1px solid var(--border2)"></div>

            {{-- Ordenação --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono" style="color:var(--muted)">Ordenar:</span>
                <div class="flex gap-1">
                    <template x-for="opt in sortOptions" :key="opt.key">
                        <button
                            @click="sortBy = opt.key; applyFilters()"
                            class="px-3 py-1.5 text-xs font-mono transition-all"
                            :style="sortBy === opt.key
                                ? 'background:var(--purple); color:#fff; border:1px solid var(--purple)'
                                : 'background:var(--s3); color:var(--muted2); border:1px solid var(--border2)'"
                            x-text="opt.label">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Reset --}}
            <button @click="resetFilters()"
                x-show="hasActiveFilters()"
                class="text-xs font-mono transition-colors ml-auto" style="color:var(--muted)"
                onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                ✕ Limpar filtros
            </button>

            {{-- Contador de resultados --}}
            <span class="text-xs font-mono ml-auto" style="color:var(--muted)">
                <span x-text="filtered.length"></span> projeto<span x-show="filtered.length !== 1">s</span>
            </span>

        </div>

        {{-- ── GRID DE CARDS ───────────────────────────────────────────────── --}}
        <div x-show="filtered.length === 0" class="card px-5 py-12 text-center">
            <p class="text-sm" style="color:var(--muted)">Nenhum projeto encontrado com os filtros aplicados.</p>
        </div>

        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
            <template x-for="p in filtered" :key="p.id">
                <div class="card flex flex-col"
                     :style="p.has_overdue
                        ? 'border-left:3px solid var(--red)'
                        : p.not_started
                            ? 'border-left:3px solid var(--orange)'
                            : p.progress === 100
                                ? 'border-left:3px solid var(--green)'
                                : ''">

                    {{-- Card header --}}
                    <div class="px-4 pt-4 pb-3 flex-1">

                        {{-- Alerta de atraso --}}
                        <div x-show="p.has_overdue"
                             class="flex items-center gap-1.5 text-xs font-bold font-mono mb-2 px-2 py-1"
                             style="background:rgba(239,68,68,.08); color:var(--red); border:1px solid rgba(239,68,68,.2)">
                            <span>⚠</span>
                            <span x-text="p.overdue_tasks + ' tarefa' + (p.overdue_tasks > 1 ? 's atrasadas' : ' atrasada')"></span>
                        </div>

                        {{-- Alerta sem tarefas --}}
                        <div x-show="p.not_started && !p.has_overdue"
                             class="flex items-center gap-1.5 text-xs font-bold font-mono mb-2 px-2 py-1"
                             style="background:rgba(255,140,0,.08); color:var(--orange); border:1px solid rgba(255,140,0,.2)">
                            <span>◉</span>
                            <span>Projeto sem tarefas</span>
                        </div>

                        {{-- Cliente + status --}}
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="text-xs font-mono font-semibold truncate" style="color:var(--muted)" x-text="p.client_name"></span>
                            <span class="badge flex-shrink-0"
                                  :class="'badge-' + p.status_color"
                                  x-text="p.status_label"></span>
                        </div>

                        {{-- Título --}}
                        <h3 class="font-bold text-sm leading-snug mb-1" style="color:var(--text)" x-text="p.title"></h3>

                        {{-- Macroplan --}}
                        <p class="text-xs mb-3 truncate" style="color:var(--muted2)" x-text="p.macroplan_title"></p>

                        {{-- Disciplinas --}}
                        <div class="flex flex-wrap gap-1 mb-3" x-show="p.discipline_labels.length > 0">
                            <template x-for="d in p.discipline_labels" :key="d">
                                <span class="badge text-xs" x-text="d"></span>
                            </template>
                        </div>

                        {{-- Barra de progresso --}}
                        <div class="mb-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">Progresso</span>
                                <span class="text-xs font-black font-mono"
                                      :style="p.progress === 100 ? 'color:var(--green)' : 'color:var(--text)'"
                                      x-text="p.progress + '%'"></span>
                            </div>
                            <div class="w-full h-1.5 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-1.5 rounded-full transition-all duration-700"
                                     :style="'width:' + p.progress + '%; background:' + (p.progress === 100 ? 'var(--green)' : 'var(--grad)')">
                                </div>
                            </div>
                            <p class="text-xs mt-1 font-mono" style="color:var(--muted)"
                               x-text="p.done_tasks + '/' + p.total_tasks + ' tarefas concluídas'"></p>
                        </div>

                        {{-- Contadores por coluna kanban --}}
                        <div class="grid grid-cols-4 gap-1">
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--muted)" x-text="p.col_counts.backlog"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px; line-height:1.2">Backlog</div>
                            </div>
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--orange)" x-text="p.col_counts.em_andamento"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px; line-height:1.2">Andamento</div>
                            </div>
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--purple)" x-text="p.col_counts.revisao"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px; line-height:1.2">Revisão</div>
                            </div>
                            <div class="text-center px-1 py-1.5 rounded" style="background:var(--s2)">
                                <div class="text-sm font-black font-mono" style="color:var(--green)" x-text="p.col_counts.concluido"></div>
                                <div class="text-xs font-mono" style="color:var(--muted); font-size:9px; line-height:1.2">Concluído</div>
                            </div>
                        </div>

                    </div>

                    {{-- Card footer --}}
                    <div class="px-4 py-3 flex items-center justify-between gap-2"
                         style="border-top:1px solid var(--border2)">
                        <a x-show="p.macroplan_url"
                           :href="p.macroplan_url || '#'"
                           class="text-xs font-mono transition-colors" style="color:var(--muted)"
                           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                            ↗ Planejamento
                        </a>
                        <span x-show="!p.macroplan_url" class="text-xs font-mono" style="color:var(--border2)">
                            Sem planejamento
                        </span>
                        <a x-show="p.url" :href="p.url || '#'"
                           class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity"
                           style="background:var(--purple)"
                           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                            Abrir Projeto →
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

    </div>{{-- /x-data --}}

    <script>
    function projectDashboard(projects) {
        return {
            all: projects,
            filtered: [],
            search: '',
            filterStatus: '',
            filterClient: '',
            filterDiscipline: '',
            filterOverdue: false,
            filterNotStarted: false,
            sortBy: 'urgency',

            sortOptions: [
                { key: 'urgency',       label: 'Urgência' },
                { key: 'progress_asc',  label: 'Menos avançado' },
                { key: 'progress_desc', label: 'Mais avançado' },
                { key: 'client',        label: 'Cliente A–Z' },
                { key: 'recent',        label: 'Recente' },
            ],

            init() {
                this.applyFilters();
            },

            applyFilters() {
                let result = [...this.all];

                // Busca textual
                if (this.search.trim()) {
                    const q = this.search.toLowerCase();
                    result = result.filter(p =>
                        p.title.toLowerCase().includes(q) ||
                        p.client_name.toLowerCase().includes(q) ||
                        p.macroplan_title.toLowerCase().includes(q)
                    );
                }

                // Filtro status
                if (this.filterStatus) {
                    result = result.filter(p => p.status === this.filterStatus);
                }

                // Filtro cliente
                if (this.filterClient) {
                    result = result.filter(p => p.client_id === this.filterClient);
                }

                // Filtro disciplina
                if (this.filterDiscipline) {
                    result = result.filter(p => p.disciplines.includes(this.filterDiscipline));
                }

                // Filtro: só atrasados
                if (this.filterOverdue) {
                    result = result.filter(p => p.has_overdue);
                }

                // Filtro: sem tarefas
                if (this.filterNotStarted) {
                    result = result.filter(p => p.not_started);
                }

                // Ordenação
                result = this.sortProjects(result);

                this.filtered = result;
            },

            sortProjects(list) {
                switch (this.sortBy) {
                    case 'urgency':
                        // Atrasados primeiro → sem tarefas → por progresso crescente
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

                    case 'recent':
                    default:
                        return list; // já vem ordenado por created_at desc do servidor
                }
            },

            hasActiveFilters() {
                return this.search.trim() || this.filterStatus || this.filterClient ||
                       this.filterDiscipline || this.filterOverdue || this.filterNotStarted ||
                       this.sortBy !== 'urgency';
            },

            resetFilters() {
                this.search = '';
                this.filterStatus = '';
                this.filterClient = '';
                this.filterDiscipline = '';
                this.filterOverdue = false;
                this.filterNotStarted = false;
                this.sortBy = 'urgency';
                this.applyFilters();
            },
        };
    }
    </script>

</x-app-layout>
