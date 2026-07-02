<x-app-layout>
    <x-slot name="header">Projeto — {{ $project->title }}</x-slot>

    {{-- BREADCRUMB + AÇÕES --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-2 flex-wrap text-xs font-semibold">
            @if($macroplan)
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
            @else
                <a href="{{ route('projects.dashboard') }}" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">Projetos & Campanhas</a>
            @endif
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
            <div class="flex items-center gap-4">
                @foreach(\App\Models\Task::$kanbanColumns as $colKey => $col)
                    @php $count = $kanban[$colKey]->count(); @endphp
                    <div class="text-center">
                        <div class="text-lg font-black" style="color:{{ $col['bg'] }}">{{ $count }}</div>
                        <div class="text-xs font-mono" style="color:var(--muted)">{{ $col['label'] }}</div>
                    </div>
                @endforeach
            </div>
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
    <div x-data="{ addCol: null, editTaskId: null, showDone: false }">

        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Tarefas</p>
            <div class="flex items-center gap-2">
                <button @click="showDone = !showDone"
                    class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
                    :style="showDone
                        ? 'border:1px solid var(--purple); color:var(--purple)'
                        : 'border:1px solid var(--border2); color:var(--muted)'">
                    <span x-text="showDone ? '⊙ Ocultar finalizadas' : '○ Mostrar finalizadas'"></span>
                </button>
                <button @click="addCol = addCol === 'backlog' ? null : 'backlog'"
                class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                style="border:1px solid var(--border2); color:var(--muted2)"
                onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                    + Nova Tarefa
                </button>
            </div>
        </div>

        {{-- ── FORMULÁRIO: NOVA TAREFA ── --}}
        <div x-show="addCol !== null" x-cloak class="card px-5 py-5 mb-4" style="border-left:3px solid var(--purple)">
            <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Nova Tarefa</p>
            <form method="POST" action="{{ $standalone ? route('tasks.storeStandalone', $project) : route('tasks.store', [$macroplan, $project]) }}"
                  class="grid grid-cols-2 gap-4 md:grid-cols-3">
                @csrf

                {{-- Título --}}
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" required placeholder="O que precisa ser feito?"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Tipo</label>
                    <select name="task_type" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach(\App\Models\Task::$types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Destino --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Destino</label>
                    <select name="destination" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <option value="">— sem destino —</option>
                        @foreach(\App\Models\Task::$destinations as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach(\App\Models\Task::$statuses as $key => $s)
                            @if($key !== 'cancelado')
                                <option value="{{ $key }}" {{ $key === 'backlog' ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Origem --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Origem</label>
                    <select name="origin" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach(\App\Models\Task::$origins as $key => $label)
                            <option value="{{ $key }}" {{ $key === 'projeto' ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de Aprovação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Método de Aprovação</label>
                    <select name="approval_method" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <option value="">— não definido —</option>
                        @foreach(\App\Models\Task::$approvalMethods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Vencimento --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Vencimento</label>
                    <input type="date" name="due_date" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Data de Aprovação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Data de Aprovação</label>
                    <input type="date" name="approval_date" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Data de Publicação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Data de Publicação</label>
                    <input type="date" name="publish_date" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Executores (múltiplos) --}}
                <div class="col-span-2 md:col-span-3" x-data="executorPicker()">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Executores</label>
                    <div class="flex flex-wrap gap-2 mb-2" x-show="selected.length > 0">
                        <template x-for="(item, idx) in selected" :key="item.id">
                            <div class="flex items-center gap-1 px-2 py-1 text-xs"
                                 style="background:var(--s3); border:1px solid var(--border2)">
                                <span x-text="item.name" style="color:var(--text)"></span>
                                <select :name="'executor_roles[' + item.id + ']'"
                                    class="text-xs focus:outline-none ml-1"
                                    style="background:var(--s3); color:var(--muted); border:none">
                                    <option value="executor">Executor</option>
                                    <option value="responsavel">Responsável</option>
                                    <option value="aprovador">Aprovador</option>
                                </select>
                                <input type="hidden" :name="'executor_ids[]'" :value="item.id">
                                <button type="button" @click="remove(idx)"
                                    class="ml-1" style="color:var(--muted)"
                                    onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
                            </div>
                        </template>
                    </div>
                    <select @change="add($event)" class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <option value="">+ Adicionar executor</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Aprovação Interna --}}
                <div class="flex items-center gap-2">
                    <input type="hidden" name="internal_approval" value="0">
                    <input type="checkbox" name="internal_approval" value="1" id="internal_approval_new"
                        class="w-4 h-4" style="accent-color:var(--purple)">
                    <label for="internal_approval_new" class="text-xs font-mono" style="color:var(--muted)">
                        Aprovação Interna
                    </label>
                </div>

                {{-- Situação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Situação</label>
                    <input type="text" name="situation" placeholder="Ex: Em Redação, Aguardando Arte..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Descrição --}}
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Descrição</label>
                    <textarea name="description" rows="2" placeholder="Briefing ou observações..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                </div>

                <div class="col-span-2 md:col-span-3 flex items-center gap-3">
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

        {{-- ── COLUNAS KANBAN ── --}}
        <div class="grid gap-4" style="align-items: start;"
             :style="showDone ? 'grid-template-columns: repeat(4, 1fr)' : 'grid-template-columns: repeat(3, 1fr)'">

            @foreach(\App\Models\Task::$kanbanColumns as $colKey => $col)
                @php $colTasks = $kanban[$colKey]; @endphp
                <div class="flex flex-col gap-2" @if($colKey === 'concluido') x-show="showDone" x-cloak @endif>

                    {{-- Header da coluna — sólido estilo Monday --}}
                    <div class="flex items-center justify-between px-3 py-2.5 rounded"
                         style="background:{{ $col['bg'] }}">
                        <span class="text-xs font-bold uppercase tracking-wider" style="color:#fff; letter-spacing:.06em">
                            {{ $col['label'] }}
                        </span>
                        <span class="text-xs font-bold" style="color:rgba(255,255,255,.75)">
                            {{ $colTasks->count() }}
                        </span>
                    </div>

                    {{-- Cards --}}
                    @forelse($colTasks as $task)
                        <div class="card px-4 py-3"
                             style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

                            {{-- VIEW do card --}}
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
                                    @if($task->destination)
                                        <span class="text-xs font-mono" style="color:var(--muted2)">{{ $task->destinationLabel() }}</span>
                                    @endif
                                </div>

                                {{-- Executores --}}
                                @if($task->executors->count() > 0)
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        @foreach($task->executors as $exec)
                                            <span class="text-xs px-1.5 py-0.5 font-mono"
                                                  style="background:var(--s3); border:1px solid var(--border2); color:var(--{{ $exec->pivot->role === 'executor' ? 'orange' : ($exec->pivot->role === 'aprovador' ? 'purple' : 'text') }})">
                                                {{ explode(' ', $exec->name)[0] }}
                                                @if($exec->pivot->role !== 'executor')
                                                    <span style="color:var(--muted)"> · {{ \App\Models\TaskExecutor::$roles[$exec->pivot->role] ?? '' }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif($task->executor)
                                    <div class="mb-2">
                                        <span class="text-xs font-mono" style="color:var(--muted)">
                                            {{ explode(' ', $task->executor->name)[0] }}
                                        </span>
                                    </div>
                                @endif

                                {{-- Datas --}}
                                <div class="flex flex-wrap gap-2 mb-2">
                                    @if($task->due_date)
                                        <span class="text-xs font-mono"
                                              style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            Venc: {{ $task->due_date->format('d/m') }}
                                        </span>
                                    @endif
                                    @if($task->publish_date)
                                        <span class="text-xs font-mono" style="color:var(--muted)">
                                            Pub: {{ $task->publish_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Status + badges --}}
                                <div class="flex items-center flex-wrap gap-1">
                                    <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px">
                                        {{ $task->statusLabel() }}
                                    </span>
                                    @if($task->internal_approval)
                                        <span class="badge" style="font-size:10px; color:var(--purple)">Aprov. Int.</span>
                                    @endif
                                    @if($task->approval_method)
                                        <span class="badge" style="font-size:10px">{{ $task->approvalMethodLabel() }}</span>
                                    @endif
                                </div>

                                {{-- Mover entre colunas --}}
                                <div class="flex flex-wrap gap-1 mt-2 pt-2" style="border-top:1px solid var(--border2)">
                                    @foreach(\App\Models\Task::$kanbanColumns as $targetKey => $targetCol)
                                        @if($targetKey !== $colKey)
                                            <form method="POST" action="{{ $standalone ? route('tasks.updateStatusStandalone', [$project, $task]) : route('tasks.update-status', [$macroplan, $project, $task]) }}">
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
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="text-xs px-2 py-0.5 font-mono transition-colors"
                                       style="border:1px solid var(--border2); color:var(--muted)"
                                       onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                                       onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted)'">
                                        Abrir →
                                    </a>
                                    <form method="POST" action="{{ $standalone ? route('tasks.destroyStandalone', [$project, $task]) : route('tasks.destroy', [$macroplan, $project, $task]) }}"
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

                            {{-- ── FORMULÁRIO DE EDIÇÃO INLINE ── --}}
                            <template x-if="editTaskId === '{{ $task->id }}'">
                                <form method="POST" action="{{ $standalone ? route('tasks.updateStandalone', [$project, $task]) : route('tasks.update', [$macroplan, $project, $task]) }}"
                                      class="space-y-2"
                                      x-data="executorPicker({{ json_encode($task->executors->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role])) }})">
                                    @csrf @method('PATCH')

                                    <input type="text" name="title" value="{{ $task->title }}" required
                                        class="w-full px-3 py-2 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">

                                    <div class="grid grid-cols-2 gap-1.5">
                                        <select name="task_type"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            @foreach(\App\Models\Task::$types as $key => $label)
                                                <option value="{{ $key }}" {{ $task->task_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <select name="destination"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            <option value="">— destino —</option>
                                            @foreach(\App\Models\Task::$destinations as $key => $label)
                                                <option value="{{ $key }}" {{ $task->destination === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <select name="status"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            @foreach(\App\Models\Task::$statuses as $key => $s)
                                                <option value="{{ $key }}" {{ $task->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                            @endforeach
                                        </select>

                                        <select name="origin"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            @foreach(\App\Models\Task::$origins as $key => $label)
                                                <option value="{{ $key }}" {{ $task->origin === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <select name="approval_method"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            <option value="">— aprovação —</option>
                                            @foreach(\App\Models\Task::$approvalMethods as $key => $label)
                                                <option value="{{ $key }}" {{ $task->approval_method === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <div class="flex items-center gap-1.5 px-2">
                                            <input type="hidden" name="internal_approval" value="0">
                                            <input type="checkbox" name="internal_approval" value="1"
                                                id="ia_{{ $task->id }}"
                                                {{ $task->internal_approval ? 'checked' : '' }}
                                                class="w-3.5 h-3.5" style="accent-color:var(--purple)">
                                            <label for="ia_{{ $task->id }}" class="text-xs font-mono" style="color:var(--muted)">Aprov. Interna</label>
                                        </div>
                                    </div>

                                    {{-- Datas --}}
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <div>
                                            <p class="text-xs font-mono mb-0.5" style="color:var(--muted)">Vencimento</p>
                                            <input type="date" name="due_date"
                                                value="{{ $task->due_date?->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                        </div>
                                        <div>
                                            <p class="text-xs font-mono mb-0.5" style="color:var(--muted)">Dt. Aprovação</p>
                                            <input type="date" name="approval_date"
                                                value="{{ $task->approval_date?->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                        </div>
                                        <div>
                                            <p class="text-xs font-mono mb-0.5" style="color:var(--muted)">Dt. Publicação</p>
                                            <input type="date" name="publish_date"
                                                value="{{ $task->publish_date?->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                        </div>
                                    </div>

                                    {{-- Executores --}}
                                    <div>
                                        <p class="text-xs font-mono mb-1" style="color:var(--muted)">Executores</p>
                                        <div class="flex flex-wrap gap-1 mb-1.5" x-show="selected.length > 0">
                                            <template x-for="(item, idx) in selected" :key="item.id">
                                                <div class="flex items-center gap-1 px-1.5 py-0.5 text-xs"
                                                     style="background:var(--s3); border:1px solid var(--border2)">
                                                    <span x-text="item.name" style="color:var(--text)"></span>
                                                    <select :name="'executor_roles[' + item.id + ']'"
                                                        class="text-xs focus:outline-none ml-1"
                                                        style="background:var(--s3); color:var(--muted); border:none">
                                                        <option value="executor" :selected="item.role === 'executor'">Executor</option>
                                                        <option value="responsavel" :selected="item.role === 'responsavel'">Responsável</option>
                                                        <option value="aprovador" :selected="item.role === 'aprovador'">Aprovador</option>
                                                    </select>
                                                    <input type="hidden" :name="'executor_ids[]'" :value="item.id">
                                                    <button type="button" @click="remove(idx)"
                                                        style="color:var(--muted)"
                                                        onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
                                                </div>
                                            </template>
                                        </div>
                                        <select @change="add($event)" class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            <option value="">+ Adicionar executor</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Situação --}}
                                    <input type="text" name="situation" value="{{ $task->situation }}"
                                        placeholder="Situação (sub-status)"
                                        class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">

                                    {{-- Descrição --}}
                                    <textarea name="description" rows="2"
                                        placeholder="Descrição (opcional)"
                                        class="w-full px-2 py-2 text-xs focus:outline-none resize-none"
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

        {{-- Tarefas canceladas --}}
        @if($cancelled->count() > 0)
            <div x-show="showDone" x-cloak class="mt-5">
            <div x-data="{ open: false }">
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
            </div>{{-- /x-show showDone --}}
        @endif

    </div>{{-- /x-data kanban --}}

    {{-- BRIEFINGS --}}
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
                            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                {{ \App\Models\Project::$disciplines[$disc] ?? $disc }}
                            </p>
                            <p class="text-xs whitespace-pre-wrap leading-relaxed" style="color:var(--text)">{{ $project->{'briefing_' . $disc} }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

</x-app-layout>

@push('scripts')
<script>
function executorPicker(initial = []) {
    return {
        selected: initial,
        add(event) {
            const id   = event.target.value;
            const name = event.target.selectedOptions[0]?.dataset?.name;
            if (!id || this.selected.find(s => s.id == id)) {
                event.target.value = '';
                return;
            }
            this.selected.push({ id, name, role: 'executor' });
            event.target.value = '';
        },
        remove(idx) {
            this.selected.splice(idx, 1);
        }
    }
}
</script>
@endpush
