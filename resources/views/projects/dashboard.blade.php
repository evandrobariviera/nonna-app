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
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Objetivo</label>
                    <textarea name="objective" rows="2"
                        placeholder="O que este projeto/campanha precisa alcançar?"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Início</label>
                        <input type="date" name="start_date"
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Término</label>
                        <input type="date" name="end_date"
                            class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                    onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                <x-icon name="search" size="14" class="absolute left-2.5 top-2.5" style="color:var(--muted)" />
            </div>

            <select x-model="filterClient" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text); min-width:160px">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                @endforeach
            </select>

            <select x-model="filterStatus" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Project::$statuses as $key => $s)
                    <option value="{{ $key }}">{{ $s['label'] }}</option>
                @endforeach
            </select>

            <select x-model="filterDiscipline" @change="applyFilters()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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

            <div class="flex gap-1 p-0.5" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
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
                                            <span class="inline-flex items-center justify-center flex-shrink-0"
                                                  style="width:20px; height:20px"
                                                  :style="'width:20px; height:20px; color:' + colorHex(p.status_color)">
                                                <span x-show="p.type_icon === 'megaphone'"><x-icon name="megaphone" size="18" /></span>
                                                <span x-show="p.type_icon === 'folder-kanban'"><x-icon name="folder-kanban" size="18" /></span>
                                            </span>
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
                                            :style="'position:absolute; inset:0; display:flex; align-items:center; justify-content:center; gap:4px; color:#fff; font-size:11px; font-weight:700; cursor:pointer; border:none; overflow:hidden; background:' + colorHex(p.status_color)">
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
                                                <x-icon name="pencil" size="14" />
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
                {{-- Card no mesmo visual do "Faz parte de" da tarefa (2026-10): faixa na cor do
                     tipo, rosca de progresso, datas + régua do período e botão que abre o painel
                     lateral. Borda esquerda continua sinalizando urgência (atraso/sem tarefas). --}}
                <div class="card flex flex-col relative"
                     :style="'border-top:3px solid ' + typeHex(p) + ';' + urgencyLeftBorder(p)">

                    {{-- Lápis de edição rápida --}}
                    <button @click="openEdit(p)"
                        class="btn btn-ghost btn-xs absolute top-2 right-2 z-10"
                        title="Editar">
                        <x-icon name="pencil" size="14" />
                    </button>

                    <div class="px-5 pt-4 pb-4 flex-1 flex flex-col gap-4">

                        {{-- Tipo + cliente --}}
                        <div class="pr-8">
                            <p class="text-xs font-semibold uppercase tracking-widest flex items-center gap-2" :style="'color:' + typeHex(p) + '; letter-spacing:.1em'">
                                <span class="icon-badge" :style="'color:' + typeHex(p)">
                                    <span x-show="p.type_icon === 'megaphone'" class="inline-flex"><x-icon name="megaphone" size="16" /></span>
                                    <span x-show="p.type_icon === 'folder-kanban'" class="inline-flex"><x-icon name="folder-kanban" size="16" /></span>
                                </span>
                                <span x-text="p.type_label"></span>
                                <span style="color:var(--border2)">·</span>
                                <span class="truncate normal-case tracking-normal font-semibold" style="color:var(--muted); letter-spacing:0" x-text="p.client_name"></span>
                            </p>
                        </div>

                        {{-- Título --}}
                        <a :href="p.url" class="block text-lg font-extrabold leading-snug transition-opacity"
                           style="color:var(--text)"
                           @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open(p.preview_url) }"
                           onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'"
                           x-text="p.title"></a>

                        {{-- Alertas --}}
                        <div x-show="p.has_overdue"
                             class="flex items-center gap-1.5 text-xs font-bold px-2 py-1 -mt-1"
                             style="background:rgba(239,68,68,.08); color:var(--red); border:1px solid rgba(239,68,68,.2); border-radius:6px">
                            <span>⚠</span>
                            <span x-text="p.overdue_tasks + ' tarefa' + (p.overdue_tasks > 1 ? 's atrasadas' : ' atrasada')"></span>
                        </div>
                        <div x-show="p.not_started && !p.has_overdue"
                             class="flex items-center gap-1.5 text-xs font-bold px-2 py-1 -mt-1"
                             style="background:rgba(238, 121, 25,.08); color:var(--orange); border:1px solid rgba(238, 121, 25,.2); border-radius:6px">
                            <span>◉</span><span>Sem tarefas</span>
                        </div>

                        {{-- Rosca de progresso + status (status continua editável aqui) --}}
                        <div class="flex items-center gap-4">
                            <svg width="76" height="76" viewBox="0 0 76 76" class="flex-shrink-0" role="img" :aria-label="p.progress + '% concluído'">
                                <circle cx="38" cy="38" r="30" fill="none" stroke="var(--s3)" stroke-width="8" />
                                <circle cx="38" cy="38" r="30" fill="none" :stroke="typeHex(p)" stroke-width="8" stroke-linecap="round"
                                        :stroke-dasharray="ringLen" :stroke-dashoffset="ringLen * (1 - p.progress / 100)"
                                        transform="rotate(-90 38 38)" />
                                <text x="38" y="43" text-anchor="middle" font-size="16" font-weight="800" fill="var(--text)" x-text="p.progress + '%'"></text>
                            </svg>
                            <div class="min-w-0">
                                <p class="text-sm font-bold" style="color:var(--text)" x-text="p.done_tasks + ' de ' + p.total_tasks + ' tarefas'"></p>
                                <p class="text-xs" style="color:var(--muted)">concluídas</p>
                                <div class="mt-1.5" x-data="{ open: false, style: '' }">
                                    <button type="button" @click="open = !open; style = dropdownStyle($el, 'bottom-left')"
                                        class="text-xs font-semibold inline-flex items-center gap-1"
                                        :style="'color:' + colorHex(p.status_color)" title="Mudar status">
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
                                </div>
                            </div>
                        </div>

                        {{-- Datas + régua do período --}}
                        <div x-show="p.start_date || p.end_date || p.pieces_due_date" class="flex flex-col gap-2 pt-3" style="border-top:1px solid var(--border2)">
                            <div x-show="p.pieces_due_date" class="flex items-center justify-between">
                                <span class="text-xs" style="color:var(--muted)">Entrega das peças</span>
                                <span class="text-sm font-semibold" style="color:var(--text)" x-text="p.pieces_due_date"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs" style="color:var(--muted)" x-text="p.type === 'campanha' ? 'Vai ao ar' : 'Início'"></span>
                                <span class="text-sm font-semibold" style="color:var(--text)" x-text="p.start_date || '—'"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs" style="color:var(--muted)">Término</span>
                                <span class="text-sm font-semibold" :style="p.deadline_overdue ? 'color:var(--red)' : 'color:var(--text)'"
                                      x-text="(p.end_date || '—') + (p.deadline_overdue ? ' · vencido' : '')"></span>
                            </div>
                            <div x-show="p.period_pct !== null" class="mt-1">
                                <div class="relative h-1.5 rounded-full" style="background:var(--s3)">
                                    <div class="absolute left-0 top-0 h-1.5 rounded-full" :style="'width:' + p.period_pct + '%; background:' + typeHex(p) + '; opacity:.45'"></div>
                                    <div class="absolute rounded-full" title="Hoje"
                                         :style="'left:calc(' + p.period_pct + '% - 5px); top:-3px; width:10px; height:10px; background:' + typeHex(p) + '; border:2px solid var(--s1)'"></div>
                                </div>
                                <p class="text-xs mt-2" style="color:var(--muted2)" x-text="p.period_text"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-5 pb-4 flex items-center gap-2">
                        <a x-show="p.macroplan_url" :href="p.macroplan_url || '#'" class="btn btn-ghost btn-sm" title="Abrir o planejamento">
                            ↗ Planejamento
                        </a>
                        {{-- Clique abre no painel lateral; Ctrl/Cmd+clique abre a página inteira. --}}
                        <a :href="p.url" class="btn btn-primary btn-sm flex-1 justify-center"
                           @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open(p.preview_url) }"
                           :style="'background:' + typeHex(p) + '; border-color:' + typeHex(p)"
                           x-text="'Ver ' + (p.type === 'campanha' ? 'campanha' : 'projeto') + ' →'"></a>
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
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
            // Sempre abre em Cards (padrão da agência, 2026-10) — a escolha de Tabela vale só
            // enquanto a página está aberta, não fica lembrada.
            viewMode: 'cards',

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

            // Circunferência da rosca de progresso dos cards (r=30).
            ringLen: 2 * Math.PI * 30,

            typeHex(p) {
                return this.colorHex(p.type_color);
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
