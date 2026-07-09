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
            @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
                @php $cnt = $kanban[$statusKey]->count(); @endphp
                <div class="text-center">
                    <div class="text-lg font-black" style="color:var(--{{ $meta['color'] === 'muted' ? 'muted2' : $meta['color'] }})">{{ $cnt }}</div>
                    <div class="text-xs font-mono" style="color:var(--muted)">{{ $meta['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div x-data="{ tab: 'board', clientFilter: '', ...taskBulk() }" x-cloak>

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

        @include('partials._task-bulk-bar')

        {{-- ── TAB BOARD ── --}}
        <div x-show="tab === 'board'" x-cloak>
            <div class="flex gap-4 overflow-x-auto pb-2" style="align-items: start;">

                @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
                    @php $colTasks = $kanban[$statusKey]; @endphp
                    <div class="flex flex-col gap-2 flex-shrink-0" style="width:270px">
                        <div class="flex items-center justify-between px-3 py-2"
                             style="background:var(--s2); border:1px solid var(--border2)">
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-2 rounded-full"
                                     style="background:var(--{{ $meta['color'] === 'muted' ? 'muted' : $meta['color'] }})"></div>
                                <span class="text-xs font-bold font-mono uppercase tracking-widest"
                                      style="color:var(--{{ $meta['color'] === 'muted' ? 'muted2' : $meta['color'] }})">
                                    {{ $meta['label'] }}
                                </span>
                            </div>
                            <span class="text-xs font-mono font-bold" style="color:var(--muted)">{{ $colTasks->count() }}</span>
                        </div>

                        @forelse($colTasks as $task)
                            @php
                                $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
                                if ($execList->isEmpty() && $task->executor) {
                                    $execList = collect([$task->executor]);
                                }
                                $respList   = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
                                $statusUrl  = $task->is_ticket ? route('tickets.update-status', $task) : route('tasks.update-status-direct', $task);
                                $thumbUrl   = $task->firstImageAttachmentUrl();
                            @endphp
                            <div class="card px-0 py-0 relative overflow-hidden" x-data="{ statusOpen: false }"
                                 style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

                                <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected"
                                    class="absolute top-2 right-2 z-10" style="width:14px; height:14px">

                                @if($thumbUrl)
                                    <img src="{{ $thumbUrl }}" alt="" class="w-full object-cover" style="height:80px">
                                @endif

                                <div class="px-4 py-3">
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

                                    {{-- Avatares: responsável (laranja) + executor (roxo) --}}
                                    @if($respList->isNotEmpty() || $execList->isNotEmpty())
                                        <div class="flex items-center gap-1 mb-2">
                                            @foreach($respList as $resp)
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                                                     style="background:var(--orange); font-size:9px; font-weight:700"
                                                     title="{{ $resp->name }} (Responsável)">
                                                    {{ strtoupper(substr($resp->name, 0, 2)) }}
                                                </div>
                                            @endforeach
                                            @foreach($execList as $exec)
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                                                     style="background:var(--purple); font-size:9px; font-weight:700"
                                                     title="{{ $exec->name }} (Executor)">
                                                    {{ strtoupper(substr($exec->name, 0, 2)) }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Data --}}
                                    @if($task->due_date)
                                        <p class="text-xs font-mono mb-2"
                                           style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            {{ $task->due_date->format('d/m') }}
                                        </p>
                                    @endif

                                    {{-- Mover status + remover da sprint --}}
                                    <div class="flex items-center gap-1.5 pt-2 relative" style="border-top:1px solid var(--border2)">
                                        <button @click="statusOpen = !statusOpen" @click.stop type="button"
                                            class="text-xs px-2 py-0.5 font-mono flex items-center gap-1"
                                            style="border:1px solid var(--border2); color:var(--muted)">
                                            Mover <span style="opacity:.7">▾</span>
                                        </button>

                                        <div x-show="statusOpen" @click.outside="statusOpen = false" x-cloak
                                             class="absolute left-0 bottom-full mb-1 z-20 rounded shadow-lg py-1"
                                             style="background:var(--s1); border:1px solid var(--border2); min-width:190px">
                                            @foreach(\App\Models\Task::$statuses as $targetKey => $targetMeta)
                                                @if($targetKey !== $statusKey)
                                                    <form method="POST" action="{{ $statusUrl }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $targetKey }}">
                                                        <button type="submit"
                                                            class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                                                            style="color:var(--text)"
                                                            onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                                                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                                                                  style="background:{{ \App\Models\Task::colorHex($targetMeta['color']) }}"></span>
                                                            {{ $targetMeta['label'] }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>

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

                        <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected"
                            style="width:14px; height:14px; flex-shrink:0">

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
