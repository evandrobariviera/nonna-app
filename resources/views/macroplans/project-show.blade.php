<x-app-layout>
    <x-slot name="header">Projeto — {{ $project->title }}</x-slot>

    {{-- BREADCRUMB + AÇÕES --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-2 flex-wrap text-xs font-semibold">
            <a href="{{ route('macroplans.index') }}" style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">Planejamentos</a>
            <span style="color:var(--border2)">/</span>
            <a href="{{ route('macroplans.edit', $macroplan) }}" style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                {{ $macroplan->client->company_name }}
            </a>
            <span style="color:var(--border2)">/</span>
            <a href="{{ route('macroplans.edit', [$macroplan, 'bloco' => 'bloco3']) }}" style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                {{ $macroplan->title }}
            </a>
            <span style="color:var(--border2)">/</span>
            <span style="color:var(--text)">{{ $project->title }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-{{ $project->statusColor() }}">{{ $project->statusLabel() }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- PAINEL DE PROGRESSO --}}
    <div class="card px-5 py-4 mb-5">
        <div class="flex items-center justify-between gap-6 flex-wrap">

            {{-- Barra de progresso --}}
            <div class="flex-1 min-w-0" style="min-width:200px">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Progresso</span>
                    <span class="text-sm font-black" style="color:var(--text)">{{ $progress }}%</span>
                </div>
                <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                    <div class="h-2 rounded-full transition-all duration-500"
                         style="width:{{ $progress }}%; background:{{ $progress >= 100 ? 'var(--green)' : 'var(--grad)' }}">
                    </div>
                </div>
                <p class="text-xs mt-1.5 font-mono" style="color:var(--muted)">
                    {{ $doneTasks }} de {{ $totalTasks }} tarefa{{ $totalTasks !== 1 ? 's' : '' }} concluída{{ $doneTasks !== 1 ? 's' : '' }}
                </p>
            </div>

            {{-- Stats por coluna --}}
            <div class="flex items-center gap-4">
                @foreach(\App\Models\Task::$kanbanColumns as $colKey => $col)
                    @php $count = $kanban[$colKey]->count(); @endphp
                    <div class="text-center">
                        <div class="text-lg font-black" style="color:var(--{{ $col['color'] === 'muted' ? 'muted2' : $col['color'] }})">
                            {{ $count }}
                        </div>
                        <div class="text-xs font-mono" style="color:var(--muted)">{{ $col['label'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Disciplinas --}}
            @if($project->disciplines)
                <div class="flex flex-wrap gap-1">
                    @foreach($project->disciplineLabels() as $d)
                        <span class="badge">{{ $d }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        @if($project->objective)
            <p class="text-xs mt-3 pt-3" style="color:var(--muted2); border-top:1px solid var(--border2)">
                {{ $project->objective }}
            </p>
        @endif
    </div>

    {{-- KANBAN --}}
    <div x-data="{ addCol: null, editTaskId: null }">

        {{-- Botão nova tarefa global --}}
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Tarefas</p>
            <button @click="addCol = addCol === 'backlog' ? null : 'backlog'"
                class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                style="border:1px solid var(--border2); color:var(--muted2)"
                onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                + Nova Tarefa
            </button>
        </div>

        {{-- Formulário adicionar tarefa (acima do kanban) --}}
        <div x-show="addCol !== null" x-cloak
             class="card px-5 py-5 mb-4"
             style="border-left:3px solid var(--purple)">
            <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Nova Tarefa</p>
            <form method="POST" action="{{ route('tasks.store', [$macroplan, $project]) }}"
                  class="grid grid-cols-2 gap-4">
                @csrf

                <div class="col-span-2">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" required
                        placeholder="O que precisa ser feito?"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Tipo</label>
                    <select name="task_type"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach(\App\Models\Task::$types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Executor</label>
                    <select name="executor_id"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <option value="">— sem executor —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Vencimento</label>
                    <input type="date" name="due_date"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status inicial</label>
                    <select name="status"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach(\App\Models\Task::$statuses as $key => $s)
                            @if($key !== 'cancelado')
                                <option value="{{ $key }}" {{ $key === 'backlog' ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="col-span-2 flex items-center gap-3">
                    <button type="submit"
                        class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background:var(--purple)">
                        Criar Tarefa
                    </button>
                    <button type="button" @click="addCol = null"
                        class="text-xs font-mono transition-colors" style="color:var(--muted)">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        {{-- COLUNAS KANBAN --}}
        <div class="grid gap-4" style="grid-template-columns: repeat(4, 1fr); align-items: start;">

            @foreach(\App\Models\Task::$kanbanColumns as $colKey => $col)
                @php $colTasks = $kanban[$colKey]; @endphp
                <div class="flex flex-col gap-2">

                    {{-- Header da coluna --}}
                    <div class="flex items-center justify-between px-3 py-2 rounded"
                         style="background:var(--s2); border:1px solid var(--border2)">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-2 rounded-full"
                                 style="background:var(--{{ $col['color'] === 'muted' ? 'muted' : $col['color'] }})"></div>
                            <span class="text-xs font-bold font-mono uppercase tracking-widest"
                                  style="color:var(--{{ $col['color'] === 'muted' ? 'muted2' : $col['color'] }})">
                                {{ $col['label'] }}
                            </span>
                        </div>
                        <span class="text-xs font-mono font-bold" style="color:var(--muted)">{{ $colTasks->count() }}</span>
                    </div>

                    {{-- Cards da coluna --}}
                    @forelse($colTasks as $task)
                        <div class="card px-4 py-3"
                             style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

                            {{-- View do card --}}
                            <div x-show="editTaskId !== '{{ $task->id }}'">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <p class="text-xs font-semibold leading-snug flex-1" style="color:var(--text)">
                                        {{ $task->title }}
                                    </p>
                                    <button @click="editTaskId = '{{ $task->id }}'"
                                        class="text-xs flex-shrink-0 transition-colors" style="color:var(--muted)"
                                        onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                        ✎
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                    <span class="badge text-xs">{{ $task->typeLabel() }}</span>
                                    @if($task->executor)
                                        <span class="text-xs font-mono" style="color:var(--muted)">
                                            {{ explode(' ', $task->executor->name)[0] }}
                                        </span>
                                    @endif
                                    @if($task->due_date)
                                        <span class="text-xs font-mono {{ $task->isOverdue() ? '' : '' }}"
                                              style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            {{ $task->due_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Status detalhado --}}
                                <div class="flex items-center gap-1">
                                    <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px">
                                        {{ $task->statusLabel() }}
                                    </span>
                                </div>

                                {{-- Mover entre colunas --}}
                                <div class="flex flex-wrap gap-1 mt-2 pt-2" style="border-top:1px solid var(--border2)">
                                    @foreach(\App\Models\Task::$kanbanColumns as $targetKey => $targetCol)
                                        @if($targetKey !== $colKey)
                                            <form method="POST" action="{{ route('tasks.update-status', [$macroplan, $project, $task]) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ \App\Models\Task::$kanbanDefaultStatus[$targetKey] }}">
                                                <button type="submit"
                                                    class="text-xs px-2 py-0.5 font-mono transition-colors"
                                                    style="border:1px solid var(--border2); color:var(--muted)"
                                                    onmouseover="this.style.borderColor='var(--{{ $targetCol['color'] === 'muted' ? 'border2' : $targetCol['color'] }})';"
                                                    onmouseout="this.style.borderColor='var(--border2)';">
                                                    → {{ $targetCol['label'] }}
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                    <form method="POST" action="{{ route('tasks.destroy', [$macroplan, $project, $task]) }}"
                                          onsubmit="return confirm('Remover?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="text-xs px-2 py-0.5 font-mono transition-colors"
                                            style="border:1px solid var(--border2); color:var(--muted)"
                                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                                            ✕
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Formulário de edição inline --}}
                            <template x-if="editTaskId === '{{ $task->id }}'">
                                <form method="POST" action="{{ route('tasks.update', [$macroplan, $project, $task]) }}"
                                      class="space-y-3">
                                    @csrf @method('PATCH')

                                    <input type="text" name="title" value="{{ $task->title }}" required
                                        class="w-full px-3 py-2 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">

                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="task_type"
                                            class="w-full px-3 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            @foreach(\App\Models\Task::$types as $key => $label)
                                                <option value="{{ $key }}" {{ $task->task_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <select name="status"
                                            class="w-full px-3 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            @foreach(\App\Models\Task::$statuses as $key => $s)
                                                <option value="{{ $key }}" {{ $task->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <select name="executor_id"
                                        class="w-full px-3 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                        <option value="">— sem executor —</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ $task->executor_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                        @endforeach
                                    </select>

                                    <input type="date" name="due_date"
                                        value="{{ $task->due_date?->format('Y-m-d') }}"
                                        class="w-full px-3 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">

                                    <textarea name="description" rows="2"
                                        placeholder="Descrição (opcional)"
                                        class="w-full px-3 py-2 text-xs focus:outline-none resize-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $task->description }}</textarea>

                                    <div class="flex gap-2">
                                        <button type="submit"
                                            class="px-3 py-1.5 text-xs font-bold font-mono uppercase text-white"
                                            style="background:var(--purple)">
                                            Salvar
                                        </button>
                                        <button type="button" @click="editTaskId = null"
                                            class="text-xs font-mono" style="color:var(--muted)">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </template>

                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs rounded"
                             style="border:1px dashed var(--border2); color:var(--muted)">
                            Sem tarefas
                        </div>
                    @endforelse

                </div>
            @endforeach

        </div>

        {{-- Tarefas canceladas (colapsável) --}}
        @if($cancelled->count() > 0)
            <div x-data="{ open: false }" class="mt-5">
                <button @click="open = !open"
                    class="text-xs font-mono transition-colors flex items-center gap-2" style="color:var(--muted)"
                    onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
                    {{ $cancelled->count() }} tarefa{{ $cancelled->count() !== 1 ? 's' : '' }} cancelada{{ $cancelled->count() !== 1 ? 's' : '' }}
                </button>
                <div x-show="open" x-cloak class="mt-2 flex flex-col gap-1">
                    @foreach($cancelled as $task)
                        <div class="px-4 py-2 text-xs" style="background:var(--s2); border:1px solid var(--border2); color:var(--muted); text-decoration:line-through">
                            {{ $task->title }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>{{-- /x-data kanban --}}

    {{-- BRIEFINGS (colapsável) --}}
    @if($project->disciplines)
        <div x-data="{ open: false }" class="mt-6">
            <button @click="open = !open"
                class="text-xs font-mono uppercase tracking-widest transition-colors flex items-center gap-2"
                style="color:var(--muted)"
                onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
                Briefings por Head
            </button>
            <div x-show="open" x-cloak class="mt-3 grid gap-3"
                 style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))">
                @foreach($project->disciplines as $disc)
                    @if($project->hasBriefingFor($disc))
                        <div class="card px-4 py-4">
                            <p class="text-xs font-mono uppercase tracking-widest mb-2"
                               style="color:var(--muted)">
                                {{ \App\Models\Project::$disciplines[$disc] ?? $disc }}
                            </p>
                            <p class="text-xs whitespace-pre-wrap leading-relaxed"
                               style="color:var(--text)">{{ $project->{'briefing_' . $disc} }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

</x-app-layout>
