<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span>Projetos & Campanhas</span>
            <div class="flex items-center gap-2">
                @php
                    $toggleInativos = request()->except('mostrar_inativos');
                    if (!request()->boolean('mostrar_inativos')) $toggleInativos['mostrar_inativos'] = '1';
                @endphp
                <a href="{{ route('projects.dashboard', $toggleInativos) }}"
                   class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
                   style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_inativos') ? 'var(--purple)' : 'var(--muted)' }}">
                    {{ request()->boolean('mostrar_inativos') ? '⊙ Ocultar clientes inativos' : '○ Mostrar clientes inativos' }}
                </a>
                <button @click="$dispatch('toggle-new-project')" class="btn btn-primary btn-sm">
                    + Novo Projeto
                </button>
            </div>
        </div>
    </x-slot>

    {{-- Criação de projeto/campanha avulso — sem macroplanejamento por trás.
         Escopo Alpine próprio (fora do projectDashboard) porque é um POST
         clássico com redirect, não precisa integrar com o JSON da listagem. --}}
    <div x-data="{ addOpen: false }" @toggle-new-project.window="addOpen = !addOpen" class="mb-5">
        <div x-show="addOpen" x-cloak class="card px-5 py-5 space-y-4">
            <form method="POST" action="{{ route('projects.storeDirect') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Cliente <span style="color:var(--orange)">*</span>
                        </label>
                        <select name="client_id" required
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">Selecione...</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tipo</label>
                        <select name="type"
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            @foreach(\App\Models\Project::$types as $key => $t)
                                <option value="{{ $key }}">{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Nome <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="text" name="title" required
                            placeholder="Ex: Chamado — Ajuste na Landing Page"
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Objetivo</label>
                    <textarea name="objective" rows="2"
                        placeholder="O que este projeto/campanha precisa alcançar?"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Início</label>
                        <input type="date" name="start_date"
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Término</label>
                        <input type="date" name="end_date"
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Disciplinas Envolvidas</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\Project::$disciplines as $key => $label)
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer px-3 py-1.5"
                                   style="border:1px solid var(--border2); color:var(--muted2)">
                                <input type="checkbox" name="disciplines[]" value="{{ $key }}"
                                    style="accent-color:var(--purple)">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn btn-primary btn-sm">Criar Projeto</button>
                    <button type="button" @click="addOpen = false" class="btn btn-ghost btn-sm">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-data="projectDashboard({{ $projectsJson }}, {{ $macroplansJson }}, {{ $statusOptionsJson }}, {{ $disciplineLabelsJson }})"
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

            <button @click="filterStatus = 'concluido'; filterType = ''; filterOverdue = false; filterNotStarted = false; applyFilters()"
                class="card px-4 py-4 text-left transition-all"
                :style="filterStatus === 'concluido' ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : ''">
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
                    <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                @endforeach
            </select>

            <select x-model="filterStatus" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Project::$statuses as $key => $s)
                    <option value="{{ $key }}">{{ $s['label'] }}</option>
                @endforeach
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

            <div class="flex items-center gap-2">
                <span class="text-xs font-mono" style="color:var(--muted)">Agrupar:</span>
                <select x-model="groupBy"
                    class="px-3 py-2 text-xs focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    <template x-for="opt in groupOptions" :key="opt.key">
                        <option :value="opt.key" x-text="opt.label"></option>
                    </template>
                </select>
            </div>

            <button @click="showClosed = !showClosed; applyFilters()"
                class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
                :style="showClosed
                    ? 'border:1px solid var(--purple); color:var(--purple)'
                    : 'border:1px solid var(--border2); color:var(--muted)'">
                <span x-text="showClosed ? '⊙ Ocultar finalizados' : '○ Mostrar finalizados'"></span>
            </button>

            <button @click="resetFilters()" x-show="hasActiveFilters()" class="btn btn-ghost btn-sm ml-auto">
                ✕ Limpar filtros
            </button>

            <span class="text-xs font-mono" :class="hasActiveFilters() ? '' : 'ml-auto'" style="color:var(--muted)">
                <span x-text="filtered.length"></span> resultado<span x-show="filtered.length !== 1">s</span>
            </span>

            <div class="flex gap-1 p-0.5" style="background:var(--s3); border:1px solid var(--border2)">
                <button @click="setViewMode('cards')"
                    class="px-3 py-1.5 text-xs font-mono transition-all"
                    :style="viewMode === 'cards'
                        ? 'background:var(--purple); color:#fff'
                        : 'background:transparent; color:var(--muted2)'"
                    title="Ver como cards">
                    ▦ Cards
                </button>
                <button @click="setViewMode('table')"
                    class="px-3 py-1.5 text-xs font-mono transition-all"
                    :style="viewMode === 'table'
                        ? 'background:var(--purple); color:#fff'
                        : 'background:transparent; color:var(--muted2)'"
                    title="Ver como tabela">
                    ☰ Tabela
                </button>
            </div>

        </div>

        {{-- ── SEM RESULTADOS ──────────────────────────────────────────────── --}}
        <div x-show="filtered.length === 0" class="card px-5 py-12 text-center">
            <p class="text-sm" style="color:var(--muted)">Nenhum item encontrado com os filtros aplicados.</p>
        </div>

        {{-- ── TABELA COMPACTA ─────────────────────────────────────────────── --}}
        <div x-show="viewMode === 'table' && filtered.length > 0" class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Projeto</th>
                            <th>Planejamento</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th>Progresso</th>
                            <th>Tarefas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <template x-for="group in displayGroups()" :key="group.key">
                        <tbody>
                                <tr x-show="group.label">
                                    <td colspan="8" class="text-xs font-bold font-mono uppercase tracking-widest"
                                        style="background:var(--s2); color:var(--muted)"
                                        x-text="group.label + ' (' + group.items.length + ')'"></td>
                                </tr>
                                <template x-for="p in group.items" :key="p.id">
                                <tr :class="p.has_overdue ? 'row-overdue' : ''">
                                    <td class="font-mono" x-text="p.client_name" style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"></td>
                                    <td style="max-width:320px">
                                        <div class="flex items-center gap-2">
                                            <span class="badge text-xs font-bold flex-shrink-0" :class="'badge-' + p.type_color" x-text="p.type_label"></span>
                                            <a :href="p.url" class="font-semibold truncate" style="color:var(--text)" x-text="p.title"
                                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--text)'"></a>
                                            <span x-show="p.has_overdue" title="Tem tarefas atrasadas">⚠</span>
                                        </div>
                                    </td>
                                    <td class="truncate" style="max-width:180px; color:var(--muted2)" x-text="p.macroplan_title !== '—' ? p.macroplan_title : '—'"></td>
                                    <td class="text-xs font-mono whitespace-nowrap" :style="p.deadline_overdue ? 'color:var(--red); font-weight:600' : 'color:var(--muted2)'">
                                        <span x-show="!p.start_date && !p.end_date">—</span>
                                        <span x-text="p.start_date"></span><span x-show="p.start_date && p.end_date"> → </span><span x-text="p.end_date"></span>
                                    </td>
                                    <td class="monday-fill-td relative" style="width:150px" x-data="{ open: false, style: '' }">
                                        <button type="button" @click="open = !open; style = dropdownStyle($el, 'bottom-left')"
                                            :style="'position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                                                   gap:4px; color:#fff; font-size:11px; font-weight:700; cursor:pointer; border:none; overflow:hidden;
                                                   background:' + colorHex(p.status_color)">
                                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)" x-text="p.status_label"></span>
                                            <span style="opacity:.8; flex-shrink:0">▾</span>
                                        </button>
                                        <template x-teleport="body">
                                            <div x-show="open" @click.outside="open = false" x-close-on-scroll="open" x-cloak
                                                 class="rounded shadow-lg py-1"
                                                 :style="style + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
                                                <template x-for="opt in statusOptions" :key="opt.key">
                                                    <button type="button" @click="quickSetStatus(p, opt.key); open = false"
                                                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                                                        :style="p.status === opt.key ? 'color:var(--purple)' : 'color:var(--text)'"
                                                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" :style="'background:' + colorHex(opt.color)"></span>
                                                        <span x-text="opt.label"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                    </td>
                                    <td style="min-width:120px">
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 h-1.5 rounded-full overflow-hidden flex-shrink-0" style="background:var(--border2)">
                                                <div class="h-1.5 rounded-full" :style="'width:' + p.progress + '%; background:' + (p.progress === 100 ? 'var(--green)' : 'var(--grad)')"></div>
                                            </div>
                                            <span class="text-xs font-mono" style="color:var(--muted)" x-text="p.progress + '%'"></span>
                                        </div>
                                    </td>
                                    <td class="text-xs font-mono" style="color:var(--muted)" x-text="p.done_tasks + '/' + p.total_tasks"></td>
                                    <td class="row-actions">
                                        <div class="flex items-center gap-2 justify-end">
                                            <button @click="openEdit(p)" class="btn btn-ghost btn-xs" title="Editar">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </button>
                                            <a :href="p.url" class="btn btn-ghost btn-xs">Abrir →</a>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </template>
                </table>
            </div>
        </div>

        {{-- ── GRID DE CARDS ───────────────────────────────────────────────── --}}
        <div x-show="viewMode === 'cards' && filtered.length > 0" class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
            <template x-for="group in displayGroups()" :key="group.key">
            <div class="contents">
                <div x-show="group.label" class="col-span-full">
                    <p class="text-xs font-bold font-mono uppercase tracking-widest px-1 mt-2 mb-1" style="color:var(--muted)"
                       x-text="group.label + ' (' + group.items.length + ')'"></p>
                </div>
            <template x-for="p in group.items" :key="p.id">
                <div class="card flex flex-col relative"
                     :style="typeTopBorder(p) + urgencyLeftBorder(p)">

                    {{-- Lápis de edição rápida --}}
                    <button @click="openEdit(p)"
                        class="btn btn-ghost btn-xs absolute top-2 right-2 z-10"
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
                             style="background:rgba(238, 121, 25,.08); color:var(--orange); border:1px solid rgba(238, 121, 25,.2)">
                            <span>◉</span><span>Sem tarefas</span>
                        </div>

                        {{-- Badges tipo + status --}}
                        <div class="flex items-center gap-1.5 mb-1.5" x-data="{ open: false, style: '' }">
                            <span class="badge text-xs font-bold"
                                  :class="'badge-' + p.type_color"
                                  x-text="p.type_label"></span>

                            <span class="relative inline-block">
                                <button type="button" @click="open = !open; style = dropdownStyle($el, 'bottom-left')"
                                    class="badge text-xs font-bold"
                                    :style="'border:none; cursor:pointer; background:' + colorHex(p.status_color) + '; color:#fff'">
                                    <span x-text="p.status_label"></span> ▾
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open" @click.outside="open = false" x-close-on-scroll="open" x-cloak
                                         class="rounded shadow-lg py-1"
                                         :style="style + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
                                        <template x-for="opt in statusOptions" :key="opt.key">
                                            <button type="button" @click="quickSetStatus(p, opt.key); open = false"
                                                class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                                                :style="p.status === opt.key ? 'color:var(--purple)' : 'color:var(--text)'"
                                                onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                                                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" :style="'background:' + colorHex(opt.color)"></span>
                                                <span x-text="opt.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </span>
                        </div>

                        {{-- Cliente --}}
                        <div class="text-xs font-mono font-semibold truncate mb-0.5 pr-6" style="color:var(--muted)" x-text="p.client_name"></div>

                        {{-- Título --}}
                        <h3 class="font-bold text-sm leading-snug mb-1 pr-6" style="color:var(--text)" x-text="p.title"></h3>

                        {{-- Planejamento --}}
                        <p class="text-xs mb-1 truncate" style="color:var(--muted2)"
                           x-text="p.macroplan_title !== '—' ? '[ROADMAP] ' + p.macroplan_title : 'Sem planejamento'"></p>

                        {{-- Período --}}
                        <p class="text-xs font-mono mb-3" x-show="p.start_date || p.end_date"
                           :style="p.deadline_overdue ? 'color:var(--red); font-weight:600' : 'color:var(--muted)'">
                            <span x-text="p.start_date"></span><span x-show="p.start_date && p.end_date"> → </span><span x-text="p.end_date"></span>
                            <span x-show="p.deadline_overdue"> · vencido</span>
                        </p>

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
                        <a x-show="p.macroplan_url" :href="p.macroplan_url || '#'" class="btn btn-ghost btn-xs">
                            ↗ Planejamento
                        </a>
                        <span x-show="!p.macroplan_url" class="text-xs font-mono" style="color:var(--border2)">Sem planejamento</span>

                        <a :href="p.url" class="btn btn-primary btn-sm">
                            Abrir →
                        </a>
                    </div>

                </div>
            </template>
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
                    <button @click="editOpen = false" class="btn btn-ghost btn-sm">Cancelar</button>
                    <button @click="saveEdit()" :disabled="editSaving" class="btn btn-primary btn-sm">
                        <span x-show="!editSaving">Salvar</span>
                        <span x-show="editSaving">Salvando…</span>
                    </button>
                </div>

            </div>
        </div>

    </div>{{-- /x-data --}}

    <script>
    function projectDashboard(projects, macroplans, statusOptions, disciplineLabels) {
        return {
            all: projects,
            macroplans: macroplans,
            statusOptions: statusOptions,
            disciplineLabels: disciplineLabels,
            filtered: [],
            search: '',
            filterType: '',
            filterStatus: '',
            filterClient: '',
            filterDiscipline: '',
            filterOverdue: false,
            filterNotStarted: false,
            showClosed: false,
            sortBy: 'urgency',
            groupBy: '',
            viewMode: localStorage.getItem('projectsViewMode') || 'cards',

            editOpen: false,
            editSaving: false,
            editTarget: null,
            editForm: { type: 'projeto', macro_plan_id: '' },

            sortOptions: [
                { key: 'urgency',       label: 'Urgência' },
                { key: 'progress_asc',  label: 'Menos avançado' },
                { key: 'progress_desc', label: 'Mais avançado' },
                { key: 'client',        label: 'Cliente A–Z' },
                { key: 'recent',        label: 'Recente' },
            ],

            groupOptions: [
                { key: '',           label: 'Sem agrupamento' },
                { key: 'client',     label: 'Cliente' },
                { key: 'discipline', label: 'Disciplina' },
                { key: 'status',     label: 'Status' },
            ],

            // Mesmo mapeamento semântico de cor usado em Task::colorHex() (PHP)
            // — aqui em JS porque esta tela é 100% renderizada via Alpine/JSON,
            // não tem loop do servidor pra rodar o helper PHP.
            colorHex(color) {
                const map = {
                    green: '#10B981', blue: '#2E90FA', purple: '#643B8E',
                    orange: '#EE7919', red: '#DC2626', yellow: '#F59E0B',
                    teal: '#0d9488', cyan: '#0891b2', muted: '#98A1B2',
                };
                return map[color] || '#98A1B2';
            },

            // Agrupa this.filtered em { key, label, items }[]. Sem agrupamento
            // (groupBy === ''), devolve um único grupo sem cabeçalho — assim o
            // template sempre itera grupo→item, sem duplicar o markup da linha/card.
            displayGroups() {
                if (!this.groupBy) {
                    return [{ key: '__all__', label: null, items: this.filtered }];
                }

                const groups = {};
                const pushTo = (key, label, p) => {
                    if (!groups[key]) groups[key] = { key, label, items: [] };
                    groups[key].items.push(p);
                };

                for (const p of this.filtered) {
                    if (this.groupBy === 'discipline') {
                        const list = p.disciplines.length ? p.disciplines : ['__sem_disciplina__'];
                        for (const d of list) {
                            pushTo(d, d === '__sem_disciplina__' ? 'Sem disciplina' : (this.disciplineLabels[d] || d), p);
                        }
                    } else if (this.groupBy === 'status') {
                        pushTo(p.status, p.status_label, p);
                    } else {
                        pushTo(p.client_id || '__sem_cliente__', p.client_name, p);
                    }
                }

                return Object.values(groups).sort((a, b) => a.label.localeCompare(b.label));
            },

            // Troca só o status, direto do dropdown inline da tabela/card —
            // reaproveita a mesma rota de projects.quickUpdate já usada pelo
            // modal de edição, só que enviando um único campo.
            async quickSetStatus(p, statusKey) {
                try {
                    const res = await fetch(p.edit_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-HTTP-Method-Override': 'PATCH',
                        },
                        body: JSON.stringify({ _method: 'PATCH', status: statusKey }),
                    });
                    const json = await res.json();
                    if (json.success) {
                        Object.assign(p, {
                            status:       json.status,
                            status_label: json.status_label,
                            status_color: json.status_color,
                        });
                    }
                } catch (e) {
                    console.error('quickSetStatus falhou', e);
                }
            },

            typeTopBorder(p) {
                return p.type === 'campanha'
                    ? 'border-top:3px solid var(--green);'
                    : 'border-top:3px solid var(--purple);';
            },

            urgencyLeftBorder(p) {
                if (p.has_overdue)    return 'border-left:3px solid var(--red);';
                if (p.not_started)    return 'border-left:3px solid var(--orange);';
                if (p.progress===100) return 'border-left:3px solid var(--green);';
                return '';
            },

            init() { this.applyFilters(); },

            setViewMode(mode) {
                this.viewMode = mode;
                localStorage.setItem('projectsViewMode', mode);
            },

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
                if (this.filterStatus === 'active') {
                    // Sentinel do card "Ativos" — agrega tudo que não é um status terminal,
                    // já que não existe mais um único status "active" (ver Project::$statuses)
                    result = result.filter(p => !['concluido','cancelado'].includes(p.status));
                } else if (this.filterStatus) {
                    result = result.filter(p => p.status === this.filterStatus);
                }
                if (this.filterClient)     result = result.filter(p => p.client_id === this.filterClient);
                if (this.filterDiscipline) result = result.filter(p => p.disciplines.includes(this.filterDiscipline));
                if (this.filterOverdue)    result = result.filter(p => p.has_overdue);
                if (this.filterNotStarted) result = result.filter(p => p.not_started);
                if (!this.showClosed && !['concluido','cancelado'].includes(this.filterStatus)) {
                    result = result.filter(p => !['concluido','cancelado'].includes(p.status));
                }
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
                       this.sortBy !== 'urgency' || this.groupBy !== '';
            },

            resetFilters() {
                this.search = ''; this.filterType = ''; this.filterStatus = '';
                this.filterClient = ''; this.filterDiscipline = '';
                this.filterOverdue = false; this.filterNotStarted = false;
                this.showClosed = false;
                this.sortBy = 'urgency';
                this.groupBy = '';
                this.applyFilters();
            },

            openEdit(p) {
                this.editTarget = p;
                this.editForm = {
                    type:          p.type || 'projeto',
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
