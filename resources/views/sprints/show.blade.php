<x-app-layout>
    <x-slot name="header">{{ $sprint->title }}</x-slot>

    {{-- HEADER DA SPRINT --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <span class="badge badge-{{ $sprint->statusColor() }}">{{ $sprint->statusLabel() }}</span>
            <span class="text-xs font-mono" style="color:var(--muted)">
                {{ $sprint->starts_at->format('d/m/Y') }} → {{ $sprint->ends_at->format('d/m/Y') }}
            </span>
            @if($sprint->isLocked())
                <span class="text-xs font-mono" style="color:var(--purple)">
                    🔒 Travada por {{ $sprint->lockedBy?->name }} em {{ $sprint->locked_at->format('d/m H:i') }}
                </span>
            @endif
        </div>

        {{-- Ações da sprint --}}
        <div class="flex items-center gap-2">
            @if($sprint->status === 'planning')
                <form method="POST" action="{{ route('sprints.lock', $sprint) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest"
                        style="background:var(--orange); color:#fff"
                        onclick="return confirm('Travar sprint e iniciar execução?')">
                        🔒 Travar Sprint
                    </button>
                </form>
            @elseif($sprint->status === 'active')
                <form method="POST" action="{{ route('sprints.unlock', $sprint) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest"
                        style="border:1px solid var(--border2); color:var(--muted)"
                        onclick="return confirm('Reabrir sprint para planejamento?')">
                        Reabrir
                    </button>
                </form>
                <form method="POST" action="{{ route('sprints.close', $sprint) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest"
                        style="border:1px solid var(--green); color:var(--green)"
                        onclick="return confirm('Encerrar sprint? Tarefas pendentes voltam ao backlog.')">
                        Encerrar Sprint
                    </button>
                </form>
            @endif
            <a href="{{ route('sprints.index') }}" class="text-xs font-mono" style="color:var(--muted)">← Sprints</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- STATS --}}
    @php
        $total     = $sprint->tasks->whereNotIn('status', ['cancelado'])->count();
        $done      = $sprint->tasks->where('status', 'concluido')->count();
        $progress  = $total > 0 ? (int) round(($done / $total) * 100) : 0;
    @endphp
    <div class="card px-5 py-4 mb-5">
        <div class="flex items-center gap-8 flex-wrap">
            <div class="flex-1" style="min-width:180px">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Progresso</span>
                    <span class="text-sm font-black" style="color:var(--text)">{{ $progress }}%</span>
                </div>
                <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                    <div class="h-2 rounded-full transition-all"
                         style="width:{{ $progress }}%; background:{{ $progress >= 100 ? 'var(--green)' : 'var(--grad)' }}"></div>
                </div>
                <p class="text-xs mt-1 font-mono" style="color:var(--muted)">{{ $done }} / {{ $total }} concluídas</p>
            </div>
            @foreach(\App\Models\Task::$kanbanColumns as $colKey => $col)
                @php $cnt = $kanban[$colKey]->count(); @endphp
                <div class="text-center">
                    <div class="text-lg font-black" style="color:var(--{{ $col['color'] === 'muted' ? 'muted2' : $col['color'] }})">{{ $cnt }}</div>
                    <div class="text-xs font-mono" style="color:var(--muted)">{{ $col['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div x-data="{ tab: 'board', clientFilter: '' }">

        {{-- TABS --}}
        <div class="flex gap-1 mb-5" style="border-bottom:1px solid var(--border2)">
            <button @click="tab = 'board'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'board' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Board
            </button>
            <button @click="tab = 'planning'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'planning' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'"
                x-show="{{ $sprint->status !== 'closed' ? 'true' : 'false' }}">
                Planejamento
                @if($backlogTasks->count() > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full font-bold"
                          style="background:var(--s3); color:var(--orange)">{{ $backlogTasks->count() }}</span>
                @endif
            </button>
        </div>

        {{-- ── TAB BOARD ── --}}
        <div x-show="tab === 'board'" x-cloak>
            <div class="grid gap-4" style="grid-template-columns: repeat(4, 1fr); align-items: start;">

                @foreach(\App\Models\Task::$kanbanColumns as $colKey => $col)
                    @php $colTasks = $kanban[$colKey]; @endphp
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between px-3 py-2"
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

                        @forelse($colTasks as $task)
                            <div class="card px-4 py-3"
                                 style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

                                {{-- Cliente --}}
                                <p class="text-xs font-mono mb-1" style="color:var(--purple)">
                                    {{ $task->client?->company_name ?? '—' }}
                                    @if($task->project)
                                        <span style="color:var(--border2)"> / </span>
                                        <span style="color:var(--muted)">{{ $task->project->title }}</span>
                                    @elseif($task->is_ticket)
                                        <span style="color:var(--border2)"> / </span>
                                        <span style="color:var(--orange)">Ticket</span>
                                    @endif
                                </p>

                                <p class="text-xs font-semibold leading-snug mb-2" style="color:var(--text)">
                                    {{ $task->title }}
                                </p>

                                {{-- Executores --}}
                                @if($task->executors->count() > 0)
                                    <div class="flex flex-wrap gap-1 mb-1.5">
                                        @foreach($task->executors as $exec)
                                            <span class="text-xs px-1.5 py-0.5 font-mono"
                                                  style="background:var(--s3); border:1px solid var(--border2); color:var(--orange)">
                                                {{ explode(' ', $exec->name)[0] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif($task->executor)
                                    <p class="text-xs font-mono mb-1.5" style="color:var(--muted)">
                                        {{ explode(' ', $task->executor->name)[0] }}
                                    </p>
                                @endif

                                {{-- Datas + status --}}
                                <div class="flex items-center flex-wrap gap-1.5">
                                    <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px">
                                        {{ $task->statusLabel() }}
                                    </span>
                                    @if($task->due_date)
                                        <span class="text-xs font-mono"
                                              style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            {{ $task->due_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Mover status --}}
                                <div class="flex flex-wrap gap-1 mt-2 pt-2" style="border-top:1px solid var(--border2)">
                                    @foreach(\App\Models\Task::$kanbanColumns as $targetKey => $targetCol)
                                        @if($targetKey !== $colKey)
                                            @if($task->is_ticket)
                                                <form method="POST" action="{{ route('tickets.update-status', $task) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ \App\Models\Task::$kanbanDefaultStatus[$targetKey] }}">
                                                    <button type="submit"
                                                        class="text-xs px-2 py-0.5 font-mono"
                                                        style="border:1px solid var(--border2); color:var(--muted)">
                                                        → {{ $targetCol['label'] }}
                                                    </button>
                                                </form>
                                            @elseif($task->project)
                                                <form method="POST" action="{{ route('tasks.update-status', [$task->macroPlan, $task->project, $task]) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ \App\Models\Task::$kanbanDefaultStatus[$targetKey] }}">
                                                    <button type="submit"
                                                        class="text-xs px-2 py-0.5 font-mono"
                                                        style="border:1px solid var(--border2); color:var(--muted)">
                                                        → {{ $targetCol['label'] }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    @endforeach

                                    @if($sprint->status !== 'closed' && !$sprint->isLocked())
                                        <form method="POST" action="{{ route('sprints.remove-task', [$sprint, $task]) }}"
                                              onsubmit="return confirm('Remover da sprint?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="text-xs px-2 py-0.5 font-mono"
                                                style="border:1px solid var(--border2); color:var(--muted)"
                                                onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                                                − Sprint
                                            </button>
                                        </form>
                                    @endif
                                </div>

                            </div>
                        @empty
                            <div class="px-4 py-5 text-center text-xs"
                                 style="border:1px dashed var(--border2); color:var(--muted)">
                                Sem tarefas
                            </div>
                        @endforelse
                    </div>
                @endforeach

            </div>
        </div>

        {{-- ── TAB PLANEJAMENTO ── --}}
        <div x-show="tab === 'planning'" x-cloak>

            @if($sprint->status === 'closed')
                <p class="text-sm font-mono" style="color:var(--muted)">Sprint encerrada — planejamento bloqueado.</p>
            @else
                {{-- Filtro por cliente --}}
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Filtrar:</span>
                    <select x-model="clientFilter"
                        class="px-3 py-1.5 text-xs focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <option value="">Todos os clientes</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs font-mono" style="color:var(--muted)">
                        {{ $backlogTasks->count() }} tarefa{{ $backlogTasks->count() !== 1 ? 's' : '' }} no backlog
                    </span>
                </div>

                @forelse($backlogTasks as $task)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 mb-2"
                         style="background:var(--s2); border:1px solid var(--border2)"
                         x-show="clientFilter === '' || clientFilter === '{{ $task->client_id }}'">

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono font-bold" style="color:var(--purple)">
                                    {{ $task->client?->company_name ?? '—' }}
                                </span>
                                @if($task->project)
                                    <span class="text-xs font-mono" style="color:var(--muted)">/ {{ $task->project->title }}</span>
                                @elseif($task->is_ticket)
                                    <span class="badge badge-orange" style="font-size:10px">Ticket</span>
                                @endif
                            </div>
                            <p class="text-xs font-semibold mt-0.5 leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="badge" style="font-size:10px">{{ $task->typeLabel() }}</span>
                                @if($task->executor)
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ explode(' ', $task->executor->name)[0] }}</span>
                                @endif
                                @if($task->due_date)
                                    <span class="text-xs font-mono" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                        {{ $task->due_date->format('d/m') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('sprints.add-task', [$sprint, $task]) }}">
                            @csrf
                            <button type="submit"
                                class="px-3 py-1.5 text-xs font-bold font-mono uppercase tracking-widest flex-shrink-0"
                                style="border:1px solid var(--purple); color:var(--purple)"
                                onmouseover="this.style.background='var(--purple)'; this.style.color='#fff'"
                                onmouseout="this.style.background='transparent'; this.style.color='var(--purple)'">
                                + Sprint
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center" style="border:1px dashed var(--border2)">
                        <p class="text-sm font-mono" style="color:var(--muted)">Backlog vazio — todas as tarefas estão em sprints ou concluídas.</p>
                    </div>
                @endforelse
            @endif
        </div>

    </div>

</x-app-layout>
