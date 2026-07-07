<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span class="text-base font-bold" style="color:var(--text)">Tickets / Suporte</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">+ Novo Ticket</a>
        </div>
    </x-slot>

    {{-- BARRA DE FILTROS --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          class="flex items-center gap-2 flex-wrap mb-5 pb-4"
          style="border-bottom:1px solid var(--border2)">

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

        @if(request()->hasAny(['status','client_id','executor_id','sprint_id','sem_sprint','mostrar_concluidos']))
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

        {{-- BARRA DE AÇÕES EM MASSA --}}
        <div x-show="selected.length > 0"
             class="card px-4 py-3 mb-4 flex flex-wrap items-center gap-2"
             style="border-color:var(--purple)">

            <span class="text-xs font-mono font-semibold" style="color:var(--purple)">
                <span x-text="selected.length"></span> selecionada<span x-show="selected.length !== 1">s</span>
            </span>

            <div class="h-5" style="border-left:1px solid var(--border2)"></div>

            <select x-model="bulkStatus" class="filter-select">
                <option value="">Status…</option>
                @foreach(\App\Models\Task::$statuses as $key => $s)
                    <option value="{{ $key }}">{{ $s['label'] }}</option>
                @endforeach
            </select>
            <button @click="apply('status', { status: bulkStatus })" :disabled="!bulkStatus || applying" class="btn btn-ghost btn-xs">Aplicar</button>

            <select x-model="bulkExecutor" class="filter-select">
                <option value="">Executor…</option>
                <option value="null">— Remover executor —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <button @click="apply('executor', { executor_id: bulkExecutor === 'null' ? null : bulkExecutor })" :disabled="!bulkExecutor || applying" class="btn btn-ghost btn-xs">Aplicar</button>

            <select x-model="bulkSituation" class="filter-select">
                <option value="">Situação…</option>
                @foreach(\App\Models\Task::$situations as $key => $label)
                    @if($key !== '')
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endif
                @endforeach
            </select>
            <button @click="apply('situation', { situation: bulkSituation })" :disabled="!bulkSituation || applying" class="btn btn-ghost btn-xs">Aplicar</button>

            <select x-model="bulkProject" class="filter-select">
                <option value="">Vincular a projeto…</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->title }} — {{ $p->client?->company_name ?? '—' }}</option>
                @endforeach
            </select>
            <button @click="apply('project', { project_id: bulkProject })" :disabled="!bulkProject || applying" class="btn btn-ghost btn-xs">Vincular</button>

            <button @click="if (confirm('Excluir ' + selected.length + ' tarefa(s) selecionada(s)? Essa ação não pode ser desfeita.')) apply('delete', {})"
                    :disabled="applying" class="btn btn-xs ml-auto" style="color:var(--red); border:1px solid var(--red)">
                Excluir selecionadas
            </button>
        </div>

        {{-- TABELA ESTILO MONDAY --}}
        <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" @click="toggleAll($event.target.checked)"></th>
                        <th style="width:130px">Status</th>
                        <th>Tarefa</th>
                        <th style="width:200px">Cliente</th>
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
                            <input type="checkbox" value="{{ $task->id }}" x-model="selected">
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
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                                                 style="background:var(--purple); font-size:10px; font-weight:700">
                                                {{ strtoupper(substr($exec->name, 0, 2)) }}
                                            </div>
                                            <span class="text-sm" style="color:var(--text)">
                                                {{ explode(' ', $exec->name)[0] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($task->executor)
                                <div class="flex items-center gap-1.5">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                                         style="background:var(--slate); font-size:10px; font-weight:700">
                                        {{ strtoupper(substr($task->executor->name, 0, 2)) }}
                                    </div>
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
                                @if($task->project)
                                    <a href="{{ $task->project->macro_plan_id ? route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) : route('projects.showDirect', $task->project) }}"
                                       class="btn btn-ghost btn-xs">
                                        Projeto
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
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

    <script>
    function taskBulk() {
        return {
            selected: [],
            bulkStatus: '',
            bulkExecutor: '',
            bulkSituation: '',
            bulkProject: '',
            applying: false,

            toggleAll(checked) {
                this.selected = checked
                    ? Array.from(document.querySelectorAll('tbody input[type=checkbox]')).map(el => el.value)
                    : [];
            },

            async apply(action, extra) {
                if (this.selected.length === 0) return;
                this.applying = true;
                try {
                    const res = await fetch('{{ route('tasks.bulkUpdate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ task_ids: this.selected, action, ...extra }),
                    });
                    if (!res.ok) {
                        let msg = 'Não foi possível aplicar a ação (erro ' + res.status + ').';
                        try {
                            const errJson = await res.json();
                            if (errJson.message) msg = errJson.message;
                        } catch (e) { /* resposta não era JSON */ }
                        alert(msg);
                        return;
                    }

                    const json = await res.json();
                    if (json.success) {
                        if (json.skipped && json.skipped.length > 0) {
                            alert(json.skipped.length + ' tarefa(s) pulada(s):\n' +
                                json.skipped.map(s => '- ' + s.title + ' (' + s.reason + ')').join('\n'));
                        }
                        window.location.reload();
                    }
                } catch (e) {
                    alert('Erro de rede ao aplicar a ação. Tente novamente.');
                } finally {
                    this.applying = false;
                }
            },
        };
    }
    </script>

</x-app-layout>
