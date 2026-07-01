{{--
  Partial: _task-tr.blade.php
  Variáveis esperadas:
    $task    — instância de Task (com relacionamentos: client, executors, responsibles)
    $context — 'fila' | 'ticket' (para ações diferentes)
--}}
@php
    $execList = $task->executors->where('pivot.role', 'executor');
    if ($execList->isEmpty() && $task->executor) {
        $execList = collect([$task->executor]);
    }
    $respList = $task->executors->where('pivot.role', 'responsavel');
@endphp

<tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}"
    x-data="{ statusOpen: false }">

    {{-- Prioridade --}}
    <td style="width:90px; padding-left:16px">
        <span class="badge badge-{{ $task->priorityColor() }}" style="font-size:11px">
            {{ $task->priorityLabel() }}
        </span>
    </td>

    {{-- Status (clicável) --}}
    <td style="width:150px" class="relative">
        <button @click="statusOpen = !statusOpen"
                class="badge badge-{{ $task->statusColor() }} cursor-pointer hover:opacity-80 transition-opacity"
                type="button" style="font-size:11px">
            {{ $task->statusLabel() }} ▾
        </button>

        <div x-show="statusOpen" @click.outside="statusOpen = false" x-cloak
             class="absolute left-0 top-full mt-1 z-20 rounded shadow-lg py-1"
             style="background:var(--s1); border:1px solid var(--border2); min-width:190px">
            @foreach(\App\Models\Task::$statuses as $key => $s)
                @php $route = ($context === 'ticket') ? route('tickets.update-status', $task) : route('tasks.update-status', $task) @endphp
                <form method="POST" action="{{ $route }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button type="submit"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:{{ $task->status === $key ? 'var(--purple)' : 'var(--text)' }}"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style="background: {{ match($s['color']) {
                                  'green' => '#059669', 'blue' => '#2563eb',
                                  'purple' => '#6A5ACD', 'orange' => '#FF8C00',
                                  'red' => '#dc2626', default => '#94a3b8'
                              } }}"></span>
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
    <td style="width:160px">
        <span class="text-sm font-medium" style="color:var(--text)">
            {{ $task->client?->company_name ?? '—' }}
        </span>
    </td>

    {{-- Responsável --}}
    <td style="width:110px">
        @if($respList->isNotEmpty())
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                     style="background:var(--orange); font-size:10px; font-weight:700"
                     title="{{ $respList->first()->name }}">
                    {{ strtoupper(substr($respList->first()->name, 0, 2)) }}
                </div>
                <span class="text-xs truncate" style="color:var(--text); max-width:72px">
                    {{ explode(' ', $respList->first()->name)[0] }}
                </span>
            </div>
        @else
            <span class="text-xs" style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Executor --}}
    <td style="width:110px">
        @if($execList->isNotEmpty())
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                     style="background:var(--purple); font-size:10px; font-weight:700"
                     title="{{ $execList->first()->name }}">
                    {{ strtoupper(substr($execList->first()->name, 0, 2)) }}
                </div>
                <span class="text-xs truncate" style="color:var(--text); max-width:72px">
                    {{ explode(' ', $execList->first()->name)[0] }}
                </span>
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
    <td style="width:90px">
        @if($task->origin)
            <span class="badge badge-muted" style="font-size:10px">{{ $task->originLabel() }}</span>
        @else
            <span style="color:var(--muted)">—</span>
        @endif
    </td>

    {{-- Destino --}}
    <td style="width:130px">
        <span class="text-xs" style="color:var(--muted2)">
            {{ $task->destinationLabel() ?: '—' }}
        </span>
    </td>

    {{-- Situação --}}
    <td style="width:130px">
        <span class="text-xs" style="color:var(--muted2)">
            {{ $task->situationLabel() !== '—' ? $task->situationLabel() : '' }}
            @if($task->situationLabel() === '—') <span style="color:var(--muted)">—</span> @endif
        </span>
    </td>

    {{-- Ações --}}
    <td style="width:120px">
        <div class="row-actions flex items-center gap-1.5">
            @if($context === 'fila' && isset($sprints))
                {{-- Botão "→ Sprint" só na fila --}}
                @if($sprints->isNotEmpty())
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
                                        {{ $sp->name }}
                                        @if($sp->status === 'active') <span class="badge badge-green" style="font-size:9px">ativa</span> @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            @elseif($context === 'ticket')
                <form method="POST" action="{{ route('tickets.destroy', $task) }}"
                      onsubmit="return confirm('Cancelar este ticket?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-xs"
                            style="color:var(--red); border-color:rgba(220,38,38,.2)"
                            onmouseover="this.style.background='rgba(220,38,38,.06)'"
                            onmouseout="this.style.background='transparent'">
                        Cancelar
                    </button>
                </form>
            @endif
            <a href="{{ route('tasks.show', $task) }}" class="btn btn-primary btn-xs">Abrir</a>
        </div>
    </td>
</tr>
