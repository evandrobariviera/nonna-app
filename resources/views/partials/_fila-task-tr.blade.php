{{-- Linha de tarefa para a view de Fila --}}
@php
    $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
    if ($execList->isEmpty() && $task->executor) {
        $execList = collect([$task->executor]);
    }
    $respList     = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
    $statusUrl    = route('tasks.update-status-direct', $task);
    $hasSituation = $task->situation && $task->situation !== '';
@endphp

<tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}" x-show="groupOpen"
    x-data="{ statusOpen: false }">

    {{-- Prioridade (Monday fill) --}}
    <td class="monday-fill-td relative" style="width:80px">
        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                    background:{{ $task->priorityHex() }}; color:#fff; font-size:11px; font-weight:700">
            {{ $task->priorityLabel() }}
        </div>
    </td>

    {{-- Status (Monday fill clicável + dropdown) --}}
    <td class="monday-fill-td relative" style="width:140px">
        <button @click="statusOpen = !statusOpen" type="button"
                style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                       gap:4px; background:{{ $task->statusHex() }}; color:#fff; font-size:11px;
                       font-weight:700; cursor:pointer; border:none; overflow:hidden">
            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">{{ $task->statusLabel() }}</span>
            <span style="opacity:.8; flex-shrink:0">▾</span>
        </button>

        <div x-show="statusOpen" @click.outside="statusOpen = false" x-cloak
             class="absolute left-0 top-full mt-1 z-20 rounded shadow-lg py-1"
             style="background:var(--s1); border:1px solid var(--border2); min-width:190px">
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <form method="POST" action="{{ $statusUrl }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button type="submit"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:{{ $task->status === $key ? 'var(--purple)' : 'var(--text)' }}"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style="background:{{ \App\Models\Task::colorHex($s['color']) }}"></span>
                        {{ $s['label'] }}
                    </button>
                </form>
            @endforeach
        </div>
    </td>

    {{-- Título --}}
    <td>
        <div class="flex flex-col justify-center gap-0.5">
            <a href="{{ route('tasks.show', $task) }}"
               class="font-semibold leading-snug hover:underline"
               style="color:var(--text); font-size:13.5px">
                {{ $task->title }}
            </a>
            @if($task->project)
                <span style="font-size:11px; color:var(--muted)">
                    {{ $task->project->macroPlan?->title }} · {{ $task->project->title }}
                </span>
            @elseif($task->is_ticket && $task->requester_name)
                <span style="font-size:11px; color:var(--muted)">{{ $task->requester_name }}</span>
            @endif
        </div>
    </td>

    {{-- Cliente (truncado com tooltip) --}}
    <td style="width:150px; max-width:150px">
        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:136px"
             title="{{ $task->client?->company_name }}">
            <span class="text-sm font-medium" style="color:var(--text)">
                {{ $task->client?->company_name ?? '—' }}
            </span>
        </div>
    </td>

    {{-- Responsável (avatar only) --}}
    <td style="width:44px; text-align:center; padding:0 4px">
        @if($respList->isNotEmpty())
            <div class="flex h-7 w-7 items-center justify-center rounded-full text-white mx-auto"
                 style="background:var(--orange); font-size:10px; font-weight:700"
                 title="{{ $respList->first()->name }}">
                {{ strtoupper(substr($respList->first()->name, 0, 2)) }}
            </div>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Executor (avatar only) --}}
    <td style="width:44px; text-align:center; padding:0 4px">
        @if($execList->isNotEmpty())
            <div class="flex h-7 w-7 items-center justify-center rounded-full text-white mx-auto"
                 style="background:var(--purple); font-size:10px; font-weight:700"
                 title="{{ $execList->first()->name }}">
                {{ strtoupper(substr($execList->first()->name, 0, 2)) }}
            </div>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Data de Aprovação (ou prazo como fallback) --}}
    <td style="width:100px">
        @if($task->approval_date)
            <span class="text-xs" style="color:var(--muted2); font-family:'IBM Plex Mono',monospace">
                {{ $task->approval_date->format('d/m/Y') }}
            </span>
        @elseif($task->due_date)
            <span class="text-xs {{ $task->isOverdue() ? 'font-semibold' : '' }}"
                  style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted2)' }}; font-family:'IBM Plex Mono',monospace">
                {{ $task->due_date->format('d/m/Y') }}
            </span>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Origem --}}
    <td style="width:80px">
        @if($task->origin)
            <span class="badge badge-muted" style="font-size:10px">{{ $task->originLabel() }}</span>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Destino --}}
    <td style="width:120px">
        <span class="text-xs" style="color:var(--muted2)">{{ $task->destinationLabel() ?: '—' }}</span>
    </td>

    {{-- Situação (Monday fill quando há valor) --}}
    <td class="{{ $hasSituation ? 'monday-fill-td relative' : '' }}" style="width:130px">
        @if($hasSituation)
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                        background:{{ $task->situationColor() }}; color:#fff; font-size:11px; font-weight:700;
                        overflow:hidden; padding:0 8px">
                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ $task->situationLabel() }}</span>
            </div>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Ações: Sprint apenas --}}
    <td style="width:110px">
        <div class="row-actions flex items-center gap-1.5">
            @if($sprints->count() > 0)
                <div x-data="{ sprintOpen: false }" class="relative flex items-stretch">
                    @if($activeSprint)
                        <form method="POST" action="{{ route('sprints.add-task', [$activeSprint, $task]) }}">
                            @csrf
                            <button type="submit" class="btn btn-xs text-white"
                                style="background:var(--green); border-color:var(--green);
                                       border-radius:{{ $sprints->count() > 1 ? '6px 0 0 6px' : '6px' }}"
                                title="Enviar para: {{ $activeSprint->title }}">
                                → Sprint
                            </button>
                        </form>
                    @endif
                    @if($sprints->count() > 1 || !$activeSprint)
                        <button @click="sprintOpen = !sprintOpen" @click.stop type="button"
                            class="btn btn-xs px-1.5"
                            style="background:var(--green); border-color:var(--green); color:#fff;
                                   border-radius:{{ $activeSprint ? '0 6px 6px 0' : '6px' }};
                                   {{ $activeSprint ? 'border-left:1px solid rgba(255,255,255,.3)' : '' }}">
                            ▾
                        </button>
                        <div x-show="sprintOpen" @click.outside="sprintOpen = false" x-cloak
                             class="absolute right-0 bottom-full mb-1 z-30 py-1"
                             style="background:var(--s1); border:1px solid var(--border2); min-width:180px; box-shadow:0 4px 16px rgba(0,0,0,.15)">
                            <p class="px-3 py-1.5 text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.08em">Enviar para</p>
                            @foreach($sprints as $sp)
                                <form method="POST" action="{{ route('sprints.add-task', [$sp, $task]) }}">
                                    @csrf
                                    <button type="submit" @click="sprintOpen = false"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left"
                                        style="color:var(--text)"
                                        onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                        <span class="h-1.5 w-1.5 rounded-full flex-shrink-0"
                                              style="background:{{ $sp->status === 'active' ? 'var(--green)' : 'var(--orange)' }}"></span>
                                        <span class="font-medium truncate">{{ $sp->title }}</span>
                                        <span class="ml-auto flex-shrink-0" style="color:var(--muted)">{{ $sp->status === 'active' ? 'Ativa' : 'Planning' }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <a href="{{ route('sprints.create') }}" class="btn btn-ghost btn-xs">+ Sprint</a>
            @endif
        </div>
    </td>
</tr>
