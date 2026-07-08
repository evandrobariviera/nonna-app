<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span class="text-base font-bold" style="color:var(--text)">Fila de Tarefas</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">+ Novo Ticket</a>
        </div>
    </x-slot>

    {{-- ══ STATS ══ --}}
    <div class="grid grid-cols-5 gap-3 mb-5">
        @php
            $statsConfig = [
                ['label' => 'Na Fila',   'value' => $stats['total'],     'color' => 'var(--text)',   'filter' => null],
                ['label' => 'Projetos',  'value' => $stats['projetos'],  'color' => 'var(--purple)', 'filter' => 'origin=projeto'],
                ['label' => 'Tickets',   'value' => $stats['tickets'],   'color' => 'var(--orange)', 'filter' => 'origin=ticket'],
                ['label' => 'Atrasadas', 'value' => $stats['atrasadas'], 'color' => 'var(--red)',    'filter' => 'atrasadas=1'],
                ['label' => 'Clientes',  'value' => $stats['clientes'],  'color' => 'var(--muted2)', 'filter' => null],
            ];
        @endphp
        @foreach($statsConfig as $s)
            <div class="stat-card">
                <p class="stat-label">{{ $s['label'] }}</p>
                <p class="stat-value" style="color:{{ $s['color'] }}">{{ $s['value'] }}</p>
                @if($s['filter'])
                    <a href="{{ route('fila.index') }}?{{ $s['filter'] }}"
                       class="text-xs mt-2 block" style="color:var(--muted)"
                       onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                        filtrar →
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ══ SPRINT ATIVA ══ --}}
    @if($activeSprint)
        <div class="mb-4 flex items-center gap-3 px-5 py-3 card"
             style="background:rgba(5,150,105,.04); border-color:rgba(5,150,105,.2)">
            <span class="h-2 w-2 rounded-full flex-shrink-0 animate-pulse" style="background:var(--green)"></span>
            <div class="flex-1">
                <span class="text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.08em">Sprint Ativa</span>
                <span class="text-sm font-semibold ml-2" style="color:var(--text)">{{ $activeSprint->title }}</span>
                @if($activeSprint->ends_at)
                    <span class="text-xs ml-2" style="color:var(--muted)">· encerra {{ $activeSprint->ends_at->format('d/m/Y') }} ({{ $activeSprint->ends_at->diffForHumans() }})</span>
                @endif
            </div>
            <a href="{{ route('sprints.show', $activeSprint) }}"
               class="text-xs font-semibold" style="color:var(--green)"
               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                Ver Sprint →
            </a>
        </div>
    @else
        <div class="mb-4 flex items-center gap-3 px-5 py-3 card"
             style="background:rgba(217,119,6,.04); border-color:rgba(217,119,6,.2)">
            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--yellow)"></span>
            <p class="text-sm" style="color:var(--muted)">Nenhuma sprint ativa.
                <a href="{{ route('sprints.create') }}" style="color:var(--orange)"
                   onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                    Criar sprint →
                </a>
            </p>
        </div>
    @endif

    {{-- ══ FILTROS + AGRUPAMENTO ══ --}}
    <form method="GET" action="{{ route('fila.index') }}"
          class="card card-body mb-5 flex flex-wrap items-end gap-3">

        {{-- Agrupar por --}}
        <div class="min-w-40">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Agrupar por</label>
            <select name="group_by" class="filter-select w-full" onchange="this.form.submit()">
                <option value="cliente"    {{ $groupBy === 'cliente'    ? 'selected' : '' }}>Cliente</option>
                <option value="executor"   {{ $groupBy === 'executor'   ? 'selected' : '' }}>Executor</option>
                <option value="responsavel"{{ $groupBy === 'responsavel'? 'selected' : '' }}>Responsável</option>
                <option value="status"     {{ $groupBy === 'status'     ? 'selected' : '' }}>Status</option>
            </select>
        </div>

        <div class="w-px self-stretch" style="background:var(--border2)"></div>

        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
            <select name="client_id" class="filter-select w-full">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-36">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Origem</label>
            <select name="origin" class="filter-select w-full">
                <option value="">Todas</option>
                @foreach(\App\Models\Task::$origins as $key => $label)
                    <option value="{{ $key }}" {{ request('origin') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-44">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Tipo</label>
            <select name="task_type" class="filter-select w-full">
                <option value="">Todos os tipos</option>
                @foreach(\App\Models\Task::$types as $key => $label)
                    <option value="{{ $key }}" {{ request('task_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2 pb-0.5">
            <input type="checkbox" name="atrasadas" value="1" id="chk_atrasadas"
                {{ request('atrasadas') ? 'checked' : '' }}
                class="w-4 h-4" style="accent-color:var(--red)">
            <label for="chk_atrasadas" class="text-sm font-medium cursor-pointer" style="color:var(--red)">
                Só atrasadas
            </label>
        </div>

        <div class="flex items-center gap-2 pb-0.5">
            <input type="checkbox" name="mostrar_fechados" value="1" id="chk_mostrar_fechados"
                {{ request()->boolean('mostrar_fechados') ? 'checked' : '' }}
                class="w-4 h-4" style="accent-color:var(--purple)">
            <label for="chk_mostrar_fechados" class="text-sm font-medium cursor-pointer" style="color:var(--muted)">
                Mostrar concluídas/canceladas
            </label>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            @if(request()->hasAny(['client_id','origin','task_type','atrasadas','mostrar_fechados']))
                <a href="{{ route('fila.index', ['group_by' => $groupBy]) }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
            @endif
        </div>
    </form>

    {{-- ══ TABELA AGRUPADA ══ --}}
    @if($tasks->isEmpty())
        <div class="tab-placeholder">
            <div class="tab-placeholder-icon">🎉</div>
            <p class="tab-placeholder-title">Fila vazia!</p>
            <p class="tab-placeholder-desc">Todas as tarefas foram alocadas em sprints ou não há tarefas no backlog.</p>
        </div>
    @else
        <div x-data="taskBulk()" x-cloak>
        @include('partials._task-bulk-bar')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    @include('partials._task-thead')

                    @foreach($grouped as $groupKey => $groupTasks)
                        @php
                            $overdueCount = $groupTasks->filter(fn($t) => $t->isOverdue())->count();

                            // Resolve o label do grupo baseado no tipo de agrupamento
                            if ($groupBy === 'cliente') {
                                $firstTask = $groupTasks->first();
                                $groupLabel = $firstTask->client?->company_name ?? 'Sem cliente';
                                $groupInitials = strtoupper(substr($groupLabel, 0, 2));
                                $groupSubLabel = null;
                                $groupColor = 'var(--grad)';
                                $groupTextColor = '#fff';
                            } elseif ($groupBy === 'status') {
                                $statusInfo = \App\Models\Task::$statuses[$groupKey] ?? null;
                                $groupLabel = $statusInfo ? $statusInfo['label'] : $groupKey;
                                $groupInitials = strtoupper(substr($groupLabel, 0, 2));
                                $groupColor = match($statusInfo['color'] ?? '') {
                                    'green'  => '#059669', 'blue'   => '#2563eb',
                                    'purple' => '#6A5ACD', 'orange' => '#FF8C00',
                                    'red'    => '#dc2626', default  => '#94a3b8',
                                };
                                $groupTextColor = '#fff';
                                $groupSubLabel = null;
                            } else {
                                // executor ou responsavel: key = "id|nome" ou "__xxx__|Sem ..."
                                $parts = explode('|', $groupKey, 2);
                                $groupLabel = $parts[1] ?? $groupKey;
                                $groupInitials = strtoupper(substr($groupLabel, 0, 2));
                                $groupColor = 'var(--purple)';
                                $groupTextColor = '#fff';
                                $groupSubLabel = $groupBy === 'executor' ? 'Executor' : 'Responsável';
                            }
                        @endphp

                        <tbody x-data="{ groupOpen: true }">
                            {{-- Cabeçalho do grupo --}}
                            <tr class="group-header-row" style="background:var(--s2)">
                                <td colspan="11" style="padding:10px 16px">
                                    <button @click="groupOpen = !groupOpen"
                                            type="button"
                                            class="flex items-center gap-3 w-full text-left">

                                        <svg class="h-3 w-3 flex-shrink-0 transition-transform duration-200"
                                             :class="groupOpen ? 'rotate-90' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"
                                             style="color:var(--muted)">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                        </svg>

                                        <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                                             style="background:{{ $groupColor }}; color:{{ $groupTextColor }}; font-size:9px; font-weight:700">
                                            {{ $groupInitials }}
                                        </div>

                                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $groupLabel }}</span>

                                        @if($groupSubLabel)
                                            <span class="text-xs" style="color:var(--muted)">· {{ $groupSubLabel }}</span>
                                        @endif

                                        <span class="badge" style="background:rgba(106,90,205,.08); border-color:rgba(106,90,205,.2); color:var(--purple); font-size:11px">
                                            {{ $groupTasks->count() }} tarefa{{ $groupTasks->count() !== 1 ? 's' : '' }}
                                        </span>

                                        @if($overdueCount > 0)
                                            <span class="badge" style="background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.2); color:var(--red); font-size:11px">
                                                {{ $overdueCount }} atrasada{{ $overdueCount > 1 ? 's' : '' }}
                                            </span>
                                        @endif
                                    </button>
                                </td>
                            </tr>

                            {{-- Linhas de tarefas --}}
                            @foreach($groupTasks as $task)
                                @php $context = 'fila' @endphp
                                @include('partials._fila-task-tr', ['task' => $task, 'activeSprint' => $activeSprint, 'sprints' => $sprints])
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
        </div>{{-- /x-data taskBulk --}}
    @endif

</x-app-layout>
