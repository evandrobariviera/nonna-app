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
    $respList      = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
    $hasSituation  = $task->situation && $task->situation !== '';
    $statusUrl     = ($context === 'ticket')
        ? route('tickets.update-status', $task)
        : route('tasks.update-status-direct', $task);
    $situacaoUrl   = route('tasks.update-situation', $task);
    $priorityClass = match($task->priority ?? 'normal') {
        'urgente' => 'priority-urgente',
        'medio'   => 'priority-medio',
        default   => '',
    };
@endphp

<tr class="{{ $priorityClass }} {{ $task->isOverdue() ? 'row-overdue' : '' }}"
    x-data="{ statusOpen: false, situacaoOpen: false, statusStyle: '', situacaoStyle: '' }">

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

    {{-- Cliente --}}
    <td style="width:150px; max-width:150px">
        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:136px"
             title="{{ $task->client?->company_name }}">
            <span class="text-sm font-medium" style="color:var(--text)">
                {{ $task->client?->company_name ?? '—' }}
            </span>
        </div>
    </td>

    {{-- Projeto --}}
    <td style="width:150px; max-width:150px">
        @if($task->project)
            <a href="{{ $task->project->macro_plan_id ? route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) : route('projects.showDirect', $task->project) }}"
               class="text-sm hover:underline" style="color:var(--text)"
               title="{{ $task->project->title }}">
                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block; max-width:136px">
                    {{ $task->project->title }}
                </span>
            </a>
        @else
            <span class="text-sm" style="color:var(--muted)">—</span>
        @endif
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

    {{-- Status (Monday fill clicável + dropdown) --}}
    <td class="monday-fill-td relative" style="width:140px">
        @include('partials._status-fill', ['task' => $task, 'statusUrl' => $statusUrl])
    </td>

    {{-- Situação (Monday fill clicável + dropdown) --}}
    <td class="{{ $hasSituation ? 'monday-fill-td' : '' }} relative" style="width:150px">
        @include('partials._situacao-fill', ['task' => $task, 'situacaoUrl' => $situacaoUrl])
    </td>

    {{-- Ações --}}
    <td style="width:110px">
        <div class="row-actions flex items-center gap-1.5">
            @if($context === 'fila' && isset($sprints) && $sprints->isNotEmpty())
                <div x-data="{ sprintOpen: false, sprintStyle: '' }" class="relative">
                    <button @click="sprintOpen = !sprintOpen; sprintStyle = dropdownStyle($el, 'bottom-right')" type="button" class="btn btn-ghost btn-xs">
                        → Sprint
                    </button>
                    <template x-teleport="body">
                        <div x-show="sprintOpen" @click.outside="sprintOpen = false" x-close-on-scroll="sprintOpen" x-cloak
                             class="rounded shadow-lg py-1"
                             :style="sprintStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:160px'">
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
                    </template>
                </div>
            @endif
        </div>
    </td>
</tr>
