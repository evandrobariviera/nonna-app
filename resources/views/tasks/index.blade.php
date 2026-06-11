<x-app-layout>
    <x-slot name="header">Tarefas</x-slot>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          class="flex items-center gap-2 flex-wrap mb-6">

        <select name="status" onchange="this.form.submit()"
            class="px-3 py-2 text-xs focus:outline-none"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                    {{ $s['label'] }}
                </option>
            @endforeach
        </select>

        <select name="client_id" onchange="this.form.submit()"
            class="px-3 py-2 text-xs focus:outline-none"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>
                    {{ $c->company_name }}
                </option>
            @endforeach
        </select>

        <select name="executor_id" onchange="this.form.submit()"
            class="px-3 py-2 text-xs focus:outline-none"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
            @endforeach
        </select>

        <select name="sprint_id" onchange="this.form.submit()"
            class="px-3 py-2 text-xs focus:outline-none"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
            <option value="">Todas as sprints</option>
            <option value="" {{ request()->boolean('sem_sprint') ? 'selected' : '' }}>— Sem sprint (Backlog) —</option>
            @foreach($sprints as $s)
                <option value="{{ $s->id }}" {{ request('sprint_id') === $s->id ? 'selected' : '' }}>
                    {{ $s->title }}
                </option>
            @endforeach
        </select>

        @if(request()->hasAny(['status','client_id','executor_id','sprint_id','sem_sprint']))
            <a href="{{ route('tasks.index') }}"
               class="text-xs font-mono transition-colors" style="color:var(--muted)"
               onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                ✕ limpar filtros
            </a>
        @endif

        <span class="ml-auto text-xs font-mono" style="color:var(--muted)">
            {{ $tasks->total() }} tarefa{{ $tasks->total() !== 1 ? 's' : '' }}
        </span>
    </form>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- LISTA DE TAREFAS --}}
    @forelse($tasks as $task)
        <div class="card px-5 py-3 mb-2 flex items-start justify-between gap-4 flex-wrap"
             style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

            <div class="flex-1 min-w-0">
                {{-- Linha 1: cliente / projeto --}}
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="text-xs font-mono font-bold" style="color:var(--purple)">
                        {{ $task->client?->company_name ?? '—' }}
                    </span>
                    @if($task->project)
                        <span style="color:var(--border2)" class="text-xs">/</span>
                        <a href="{{ route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) }}"
                           class="text-xs font-mono transition-colors" style="color:var(--muted)"
                           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                            {{ $task->project->title }}
                        </a>
                    @endif
                    @if($task->sprint)
                        <span class="text-xs px-1.5 py-0.5 font-mono"
                              style="background:rgba(106,90,205,.12); color:var(--purple); border:1px solid rgba(106,90,205,.3)">
                            {{ $task->sprint->title }}
                        </span>
                    @endif
                </div>

                {{-- Linha 2: título --}}
                <p class="text-sm font-semibold leading-snug mb-1.5" style="color:var(--text)">
                    {{ $task->title }}
                </p>

                {{-- Linha 3: badges + executores + datas --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px">
                        {{ $task->statusLabel() }}
                    </span>
                    <span class="badge" style="font-size:10px">{{ $task->typeLabel() }}</span>

                    @if($task->executors->count() > 0)
                        @foreach($task->executors as $exec)
                            <span class="text-xs font-mono" style="color:var(--orange)">
                                {{ explode(' ', $exec->name)[0] }}
                            </span>
                        @endforeach
                    @elseif($task->executor)
                        <span class="text-xs font-mono" style="color:var(--muted)">
                            {{ explode(' ', $task->executor->name)[0] }}
                        </span>
                    @endif

                    @if($task->due_date)
                        <span class="text-xs font-mono"
                              style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                            Venc: {{ $task->due_date->format('d/m/Y') }}
                        </span>
                    @endif
                    @if($task->publish_date)
                        <span class="text-xs font-mono" style="color:var(--muted)">
                            Pub: {{ $task->publish_date->format('d/m/Y') }}
                        </span>
                    @endif
                    @if($task->destination)
                        <span class="text-xs font-mono" style="color:var(--muted2)">
                            {{ $task->destinationLabel() }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Ações --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('tasks.show', $task) }}"
                   class="text-xs px-3 py-1.5 font-mono transition-colors"
                   style="border:1px solid var(--purple); color:var(--purple)"
                   onmouseover="this.style.background='var(--purple)'; this.style.color='#fff'"
                   onmouseout="this.style.background='transparent'; this.style.color='var(--purple)'">
                    Abrir →
                </a>
                @if($task->project)
                    <a href="{{ route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) }}"
                       class="text-xs px-3 py-1.5 font-mono flex-shrink-0 transition-colors"
                       style="border:1px solid var(--border2); color:var(--muted2)"
                       onmouseover="this.style.borderColor='var(--border2)'; this.style.color='var(--text)'"
                       onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                        Projeto
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="px-6 py-12 text-center" style="border:1px dashed var(--border2)">
            <p class="text-sm font-mono" style="color:var(--muted)">Nenhuma tarefa encontrada.</p>
        </div>
    @endforelse

    {{ $tasks->links() }}

</x-app-layout>
