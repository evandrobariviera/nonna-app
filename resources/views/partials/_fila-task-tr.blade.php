{{-- Linha de tarefa para a view de Fila --}}
@php
    $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
    if ($execList->isEmpty() && $task->executor) {
        $execList = collect([$task->executor]);
    }
    $respList      = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
    $hasSituation  = $task->situation && $task->situation !== '';
    $statusUrl     = route('tasks.update-status-direct', $task);
    $situacaoUrl   = route('tasks.update-situation', $task);
    $priorityClass = match($task->priority ?? 'normal') {
        'urgente' => 'priority-urgente',
        'medio'   => 'priority-medio',
        default   => '',
    };
@endphp

<tr class="{{ $priorityClass }} {{ $task->isOverdue() ? 'row-overdue' : '' }}"
    x-show="groupOpen" x-data="{ statusOpen: false, situacaoOpen: false, statusStyle: '', situacaoStyle: '' }">

    {{-- Checkbox --}}
    <td class="text-center">
        <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected">
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
            @if($task->project)
                <span style="font-size:11px; color:var(--muted)">
                    {{ $task->project->macroPlan?->title }} · {{ $task->project->title }}
                </span>
            @elseif($task->is_ticket && $task->requester_name)
                <span style="font-size:11px; color:var(--muted)">{{ $task->requester_name }}</span>
            @endif
        </div>
    </td>

    {{-- Cliente --}}
    <td style="width:150px; max-width:150px">
        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:136px"
             title="{{ $task->client?->company_name }}">
            <span class="text-sm font-medium" style="color:var(--text)">
                {{ $task->client?->company_name ?? '—' }}
            </span>
        </div>
    </td>

    {{-- Responsável --}}
    <td style="width:44px; text-align:center; padding:0 4px">
        @if($respList->isNotEmpty())
            <x-user-avatar :user="$respList->first()" size="7" color="var(--orange)" class="mx-auto" title="{{ $respList->first()->name }}" />
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Executor --}}
    <td style="width:44px; text-align:center; padding:0 4px">
        @if($execList->isNotEmpty())
            <x-user-avatar :user="$execList->first()" size="7" color="var(--purple)" class="mx-auto" title="{{ $execList->first()->name }}" />
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

    {{-- Status (Monday fill clicável + dropdown) --}}
    <td class="monday-fill-td relative" style="width:140px">
        <button @click="statusOpen = !statusOpen; situacaoOpen = false; statusStyle = dropdownStyle($el, 'bottom-left')" type="button"
                style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                       gap:4px; background:{{ $task->statusHex() }}; color:#fff; font-size:11px;
                       font-weight:700; cursor:pointer; border:none; overflow:hidden">
            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">{{ $task->statusLabel() }}</span>
            <span style="opacity:.8; flex-shrink:0">▾</span>
        </button>

        <template x-teleport="body">
            <div x-show="statusOpen" @click.outside="statusOpen = false" x-close-on-scroll="statusOpen" x-cloak
                 class="rounded shadow-lg py-1"
                 :style="statusStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
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
        </template>
    </td>

    {{-- Situação (Monday fill clicável + dropdown) --}}
    <td class="{{ $hasSituation ? 'monday-fill-td' : '' }} relative" style="width:150px">
        <button @click="situacaoOpen = !situacaoOpen; statusOpen = false; situacaoStyle = dropdownStyle($el, 'bottom-left')" type="button"
                style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                       gap:4px; {{ $hasSituation ? 'background:' . $task->situationColor() . '; color:#fff;' : 'background:transparent; color:var(--muted);' }}
                       font-size:11px; font-weight:{{ $hasSituation ? '700' : '400' }};
                       cursor:pointer; border:none; overflow:hidden; width:100%">
            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">
                {{ $hasSituation ? $task->situationLabel() : '—' }}
            </span>
            <span style="opacity:.6; flex-shrink:0">▾</span>
        </button>

        <template x-teleport="body">
            <div x-show="situacaoOpen" @click.outside="situacaoOpen = false" x-close-on-scroll="situacaoOpen" x-cloak
                 class="rounded shadow-lg py-1"
                 :style="situacaoStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:210px'">
                @foreach(\App\Models\Task::$situations as $key => $label)
                    <form method="POST" action="{{ $situacaoUrl }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="situation" value="{{ $key }}">
                        <button type="submit"
                            class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                            style="color:{{ ($task->situation ?? '') === $key ? 'var(--purple)' : 'var(--text)' }}"
                            onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                            @if($key !== '')
                                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                                      style="background:{{ \App\Models\Task::$situationColors[$key] ?? '#94a3b8' }}"></span>
                            @else
                                <span class="inline-block w-2 h-2 flex-shrink-0"></span>
                            @endif
                            {{ $label ?: '— Limpar —' }}
                        </button>
                    </form>
                @endforeach
            </div>
        </template>
    </td>

    {{-- Ações: Sprint --}}
    <td style="width:110px">
        <div class="row-actions flex items-center gap-1.5">
            @if($sprints->count() > 0)
                <div x-data="{ sprintOpen: false, sprintStyle: '' }" class="relative flex items-stretch">
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
                        <button @click="sprintOpen = !sprintOpen; sprintStyle = dropdownStyle($el, 'top-right')" @click.stop type="button"
                            class="btn btn-xs px-1.5"
                            style="background:var(--green); border-color:var(--green); color:#fff;
                                   border-radius:{{ $activeSprint ? '0 6px 6px 0' : '6px' }};
                                   {{ $activeSprint ? 'border-left:1px solid rgba(255,255,255,.3)' : '' }}">
                            ▾
                        </button>
                        <template x-teleport="body">
                            <div x-show="sprintOpen" @click.outside="sprintOpen = false" x-close-on-scroll="sprintOpen" x-cloak
                                 class="py-1"
                                 :style="sprintStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:180px; box-shadow:0 4px 16px rgba(0,0,0,.15)'">
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
                        </template>
                    @endif
                </div>
            @else
                <a href="{{ route('sprints.create') }}" class="btn btn-ghost btn-xs">+ Sprint</a>
            @endif
        </div>
    </td>
</tr>
