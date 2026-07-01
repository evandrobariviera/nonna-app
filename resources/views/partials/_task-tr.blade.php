{{--
  Partial: _task-tr.blade.php
  Variáveis esperadas:
    $task    — instância de Task (com relacionamentos: client, executors)
    $context — 'ticket' | 'fila' (para ações diferentes)
--}}
@php
    $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
    if ($execList->isEmpty() && $task->executor) {
        $execList = collect([$task->executor]);
    }
    $respList     = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
    $hasSituation = $task->situation && $task->situation !== '';
    $statusUrl    = ($context === 'ticket')
        ? route('tickets.update-status', $task)
        : route('tasks.update-status-direct', $task);
    $priorityClass = match($task->priority ?? 'normal') {
        'urgente' => 'priority-urgente',
        'medio'   => 'priority-medio',
        default   => '',
    };
@endphp

<tr class="{{ $priorityClass }} {{ $task->isOverdue() ? 'row-overdue' : '' }}"
    x-data="{ statusOpen: false }">

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
                @if(($task->priority ?? 'normal') === 'urgente')
                    <span style="color:#dc2626; font-size:12px; margin-right:3px">🚩</span>
                @endif
                {{ $task->title }}
            </a>
            @if($task->is_ticket && $task->requester_name)
                <span style="font-size:11px; color:var(--muted)">
                    {{ $task->requester_name }}
                    @if($task->requester_channel)
                        · {{ \App\Models\Task::$requesterChannels[$task->requester_channel] ?? '' }}
                    @endif
                </span>
            @elseif($task->project?->title)
                <span style="font-size:11px; color:var(--muted)">{{ $task->project->title }}</span>
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

    {{-- Data de Aprovação --}}
    <td style="width:100px">
        @if($task->approval_date)
            <span class="text-xs" style="color:var(--muted2); font-family:'IBM Plex Mono', monospace">
                {{ $task->approval_date->format('d/m/Y') }}
            </span>
        @elseif($task->due_date)
            <span class="text-xs {{ $task->isOverdue() ? 'font-semibold' : '' }}"
                  style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted2)' }}; font-family:'IBM Plex Mono', monospace">
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
            <span style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Destino --}}
    <td style="width:120px">
        <span class="text-xs" style="color:var(--muted2)">
            {{ $task->destinationLabel() ?: '—' }}
        </span>
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

    {{-- Ações --}}
    <td style="width:110px">
        <div class="row-actions flex items-center gap-1.5">
            @if($context === 'fila' && isset($sprints) && $sprints->isNotEmpty())
                <div x-data="{ sprintOpen: false }" class="relative">
                    <button @click="sprintOpen = !sprintOpen" type="button" class="btn btn-ghost btn-xs">
                        → Sprint
                    </button>
                    <div x-show="sprintOpen" @click.outside="sprintOpen = false" x-cloak
                         class="absolute right-0 top-full mt-1 z-20 rounded shadow-lg py-1"
                         style="background:var(--s1); border:1px solid var(--border2); min-width:160px">
                        @foreach($sprints as $sp)
                            <form method="POST" action="{{ route('tasks.assign-sprint', $task) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="sprint_id" value="{{ $sp->id }}">
                                <button type="submit"
                                    class="w-full text-left px-3 py-1.5 text-xs"
                                    style="color:var(--text)"
                                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                                    {{ $sp->title }}
                                    @if($sp->status === 'active') <span class="badge badge-green" style="font-size:9px">ativa</span> @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </td>
</tr>
