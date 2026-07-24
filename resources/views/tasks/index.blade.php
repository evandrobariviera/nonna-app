<x-app-layout>
    <x-slot name="header">
        <span class="text-base font-bold" style="color:var(--text)">Tarefas</span>
    </x-slot>

    {{-- BARRA DE FILTROS --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          class="flex items-center gap-2 flex-wrap mb-5 pb-4"
          style="border-bottom:1px solid var(--border2)">

        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por título…"
            class="filter-select" style="cursor:text; min-width:220px">
        <button type="submit" class="btn btn-ghost btn-sm">🔍 Buscar</button>

        <select name="status" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                    {{ $s['label'] }}
                </option>
            @endforeach
        </select>

        <select name="client_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>
                    {{ $c->company_name }}
                </option>
            @endforeach
        </select>

        <select name="executor_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
            @endforeach
        </select>

        <select name="sprint_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todas as sprints</option>
            <option value="sem_sprint" {{ request()->boolean('sem_sprint') ? 'selected' : '' }}>— Backlog (sem sprint) —</option>
            @foreach($sprints as $s)
                <option value="{{ $s->id }}" {{ request('sprint_id') === $s->id ? 'selected' : '' }}>
                    {{ $s->title }}
                </option>
            @endforeach
        </select>

        @if(request()->hasAny(['search','status','client_id','executor_id','sprint_id','sem_sprint','mostrar_concluidos']))
            <a href="{{ route('tasks.index') }}"
               class="btn btn-ghost btn-sm">
                ✕ Limpar
            </a>
        @endif

        @php
            $toggleParams = request()->except('mostrar_concluidos', 'page');
            if (!request()->boolean('mostrar_concluidos')) $toggleParams['mostrar_concluidos'] = '1';
        @endphp
        <a href="{{ route('tasks.index', $toggleParams) }}"
           class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
           style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_concluidos') ? 'var(--purple)' : 'var(--muted)' }}">
            {{ request()->boolean('mostrar_concluidos') ? '⊙ Ocultar finalizados' : '○ Mostrar finalizados' }}
        </a>

        @php
            $semProjetoParams = request()->except('sem_projeto', 'page');
            if (!request()->boolean('sem_projeto')) $semProjetoParams['sem_projeto'] = '1';
        @endphp
        <a href="{{ route('tasks.index', $semProjetoParams) }}"
           class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
           style="border:1px solid var(--border2); color:{{ request()->boolean('sem_projeto') ? 'var(--purple)' : 'var(--muted)' }}">
            {{ request()->boolean('sem_projeto') ? '⊙ Só sem projeto' : '○ Só sem projeto' }}
        </a>

        <span class="ml-auto text-sm" style="color:var(--muted)">
            {{ $tasks->total() }} tarefa{{ $tasks->total() !== 1 ? 's' : '' }}
        </span>
    </form>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 text-sm font-semibold rounded"
             style="background:rgba(5,150,105,.08); border:1px solid rgba(5,150,105,.2); color:#059669">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="taskBulk()" x-cloak>

        @include('partials._task-bulk-bar')

        {{-- TABELA ESTILO MONDAY --}}
        <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" @click="toggleAll($event.target.checked)" title="Selecionar todos"></th>
                        <th style="width:130px">Status</th>
                        <th>Tarefa</th>
                        <th style="width:200px">Cliente</th>
                        <th style="width:180px">Projeto</th>
                        <th style="width:160px">Tipo</th>
                        <th style="width:130px">Executor</th>
                        <th style="width:100px">Prazo</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    <tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}">

                        {{-- Checkbox --}}
                        <td class="text-center">
                            <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected">
                        </td>

                        {{-- Status --}}
                        <td class="monday-fill-td relative" style="width:130px">
                            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                                        background:{{ $task->statusHex() }}; color:#fff;
                                        font-size:11px; font-weight:700; overflow:hidden; padding:0 8px">
                                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ $task->statusLabel() }}</span>
                            </div>
                        </td>

                        {{-- Tarefa --}}
                        <td>
                            <div class="flex flex-col justify-center gap-0.5">
                                <a href="{{ route('tasks.show', $task) }}"
                                   class="font-semibold leading-snug hover:underline"
                                   style="color:var(--text); font-size:13.5px">
                                    {{ $task->title }}
                                </a>
                                <div class="flex items-center gap-2">
                                    @if($task->sprint)
                                        <span class="badge" style="font-size:10px; padding:1px 6px">
                                            {{ $task->sprint->title }}
                                        </span>
                                    @endif
                                    @if($task->situation)
                                        <span style="font-size:11px; color:var(--muted)">
                                            {{ $task->situationLabel() }}
                                        </span>
                                    @endif
                                    @if($task->requester_name)
                                        <span style="font-size:11px; color:var(--muted)">
                                            Solicitante: {{ $task->requester_name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Cliente --}}
                        <td>
                            <span class="text-sm font-medium" style="color:var(--text)">
                                {{ $task->client?->company_name ?? '—' }}
                            </span>
                        </td>

                        {{-- Projeto --}}
                        <td>
                            @if($task->project)
                                <a href="{{ $task->project->macro_plan_id ? route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) : route('projects.showDirect', $task->project) }}"
                                   class="text-sm hover:underline" style="color:var(--text)"
                                   title="{{ $task->project->title }}">
                                    {{ $task->project->title }}
                                </a>
                            @else
                                <span class="text-sm" style="color:var(--muted)">—</span>
                            @endif
                        </td>

                        {{-- Tipo --}}
                        <td>
                            <span class="text-sm" style="color:var(--muted2)">
                                {{ $task->typeLabel() }}
                            </span>
                        </td>

                        {{-- Executor --}}
                        <td>
                            @if($task->executors->count() > 0)
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @foreach($task->executors as $exec)
                                        <div class="flex items-center gap-1.5">
                                            <x-user-avatar :user="$exec" size="6" color="var(--purple)" />
                                            <span class="text-sm" style="color:var(--text)">
                                                {{ explode(' ', $exec->name)[0] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($task->executor)
                                <div class="flex items-center gap-1.5">
                                    <x-user-avatar :user="$task->executor" size="6" color="var(--slate)" />
                                    <span class="text-sm" style="color:var(--text)">
                                        {{ explode(' ', $task->executor->name)[0] }}
                                    </span>
                                </div>
                            @else
                                <span class="text-sm" style="color:var(--muted)">—</span>
                            @endif
                        </td>

                        {{-- Prazo --}}
                        <td>
                            @if($task->due_date)
                                <span class="text-sm {{ $task->isOverdue() ? 'font-semibold' : '' }}"
                                      style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted2)' }}">
                                    {{ $task->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-sm" style="color:var(--muted)">—</span>
                            @endif
                        </td>

                        {{-- Ações --}}
                        <td>
                            <div class="row-actions flex items-center gap-1.5">
                                <a href="{{ route('tasks.show', $task) }}"
                                   class="btn btn-primary btn-xs">
                                    Abrir
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
                            <div class="tab-placeholder">
                                <div class="tab-placeholder-icon">📋</div>
                                <div class="tab-placeholder-title">Nenhuma tarefa encontrada</div>
                                <div class="tab-placeholder-desc">Ajuste os filtros ou crie um novo ticket.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

    </div>{{-- /x-data taskBulk --}}

    <div class="mt-4">
        {{ $tasks->links() }}
    </div>

</x-app-layout>
