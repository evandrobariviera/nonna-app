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

    // Estado inicial do Alpine (linha) pras células "Monday fill" ficarem reativas —
    // atualizado localmente quando o menu único (badgeFill/personFill, ver
    // resources/js/monday-fill.js) dispara badge-fill-applied/person-fill-applied
    // depois de um PATCH bem-sucedido (AJAX, sem reload).
    $respUser = $respList->first();
    $execUser = $execList->first();
    $jsStr    = fn (?string $v) => $v === null ? 'null' : "'" . addslashes($v) . "'";
@endphp

<tr class="{{ $priorityClass }} {{ $task->isOverdue() ? 'row-overdue' : '' }}"
    x-data="{
        statusKey: '{{ $task->status }}', statusLabel: {{ $jsStr($task->statusLabel()) }}, statusColor: '{{ $task->statusHex() }}',
        situacaoKey: '{{ $task->situation ?? '' }}', situacaoLabel: {{ $jsStr($hasSituation ? $task->situationLabel() : '—') }}, situacaoColor: '{{ $hasSituation ? $task->situationColor() : '' }}',
        respName: {{ $jsStr($respUser?->name) }}, respAvatarUrl: {{ $jsStr($respUser?->avatarUrl()) }}, respInitials: {{ $jsStr($respUser ? strtoupper(substr($respUser->name, 0, 2)) : null) }},
        execName: {{ $jsStr($execUser?->name) }}, execAvatarUrl: {{ $jsStr($execUser?->avatarUrl()) }}, execInitials: {{ $jsStr($execUser ? strtoupper(substr($execUser->name, 0, 2)) : null) }}
    }"
    @badge-fill-applied.window="if ($event.detail.taskId === '{{ $task->id }}') {
        if ($event.detail.field === 'status') { statusKey = $event.detail.key; statusLabel = $event.detail.label; statusColor = $event.detail.color }
        else { situacaoKey = $event.detail.key; situacaoLabel = $event.detail.label; situacaoColor = $event.detail.color }
    }"
    @person-fill-applied.window="if ($event.detail.taskId === '{{ $task->id }}') {
        if ($event.detail.role === 'responsavel') { respName = $event.detail.name; respAvatarUrl = $event.detail.avatarUrl; respInitials = $event.detail.initials }
        else { execName = $event.detail.name; execAvatarUrl = $event.detail.avatarUrl; execInitials = $event.detail.initials }
    }">

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
             title="{{ $task->client?->displayName() }}">
            <span class="text-sm font-medium" style="color:var(--text)">
                {{ $task->client?->displayName() ?? '—' }}
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

    {{-- Responsável (avatar clicável + dropdown pra trocar direto da lista) --}}
    <td class="relative" style="width:44px; text-align:center; padding:0 4px">
        @include('partials._person-fill', ['task' => $task, 'role' => 'responsavel', 'currentUser' => $respList->first()])
    </td>

    {{-- Executor (avatar clicável + dropdown pra trocar direto da lista) --}}
    <td class="relative" style="width:44px; text-align:center; padding:0 4px">
        @include('partials._person-fill', ['task' => $task, 'role' => 'executor', 'currentUser' => $execList->first()])
    </td>

    {{-- Data de Aprovação --}}
    <td style="width:100px">
        @if($task->approval_date)
            <span class="text-xs" style="color:var(--muted2); font-family:Arial,"Segoe UI",Tahoma,sans-serif">
                {{ $task->approval_date->format('d/m/Y') }}
            </span>
        @elseif($task->due_date)
            <span class="text-xs {{ $task->isOverdue() ? 'font-semibold' : '' }}"
                  style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted2)' }}; font-family:Arial,"Segoe UI",Tahoma,sans-serif">
                {{ $task->due_date->format('d/m/Y') }}
            </span>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Data de Publicação --}}
    <td style="width:100px">
        @if($task->publish_date)
            <span class="text-xs" style="color:var(--muted2); font-family:Arial,"Segoe UI",Tahoma,sans-serif">
                {{ $task->publish_date->format('d/m/Y') }}
            </span>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
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
        @include('partials._status-fill', ['task' => $task, 'statusUrl' => $statusUrl, 'ajax' => true])
    </td>

    {{-- Situação (Monday fill clicável + dropdown) --}}
    <td class="{{ $hasSituation ? 'monday-fill-td' : '' }} relative" style="width:150px">
        @include('partials._situacao-fill', ['task' => $task, 'situacaoUrl' => $situacaoUrl, 'ajax' => true])
    </td>

    {{-- Ações --}}
    <td style="width:130px">
        <div class="row-actions flex items-center gap-1.5">
            @if($context === 'ticket')
                {{-- Chamado nasce em triagem (Tickets) — precisa ser mandado explicitamente
                     pra Fila ou direto pra uma Sprint; nenhuma das duas é automática. --}}
                <form method="POST" action="{{ route('tasks.send-to-fila', $task) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-xs" title="Enviar para a Fila">
                        → Fila
                    </button>
                </form>

                @if(isset($sprints) && $sprints->isNotEmpty())
                    <div x-data="{ sprintOpen: false, sprintStyle: '' }" class="relative">
                        <button @click="sprintOpen = !sprintOpen; sprintStyle = dropdownStyle($el, 'bottom-right')" type="button" class="btn btn-ghost btn-xs">
                            → Sprint
                        </button>
                        <template x-teleport="body">
                            <div x-show="sprintOpen" @click.outside="sprintOpen = false" x-close-on-scroll="sprintOpen" x-cloak
                                 class="rounded shadow-lg py-1"
                                 :style="sprintStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:160px'">
                                @foreach($sprints as $sp)
                                    <form method="POST" action="{{ route('sprints.add-task', [$sp, $task]) }}">
                                        @csrf
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
            @endif
        </div>
    </td>
</tr>
