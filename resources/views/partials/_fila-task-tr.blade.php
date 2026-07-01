{{-- Linha de tarefa para a view de Fila (com botão de atribuição de sprint) --}}
@php
    $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
    if ($execList->isEmpty() && $task->executor) {
        $execList = collect([$task->executor]);
    }
    $respList = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
@endphp

<tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}" x-show="groupOpen"
    x-data="{ statusOpen: false }">

    {{-- Prioridade --}}
    <td style="padding-left:16px">
        <span class="badge badge-{{ $task->priorityColor() }}" style="font-size:11px">
            {{ $task->priorityLabel() }}
        </span>
    </td>

    {{-- Status (clicável) --}}
    <td class="relative">
        <button @click="statusOpen = !statusOpen"
                class="badge badge-{{ $task->statusColor() }} cursor-pointer hover:opacity-80 transition-opacity"
                type="button" style="font-size:11px">
            {{ $task->statusLabel() }} ▾
        </button>

        <div x-show="statusOpen" @click.outside="statusOpen = false" x-cloak
             class="absolute left-0 top-full mt-1 z-20 rounded shadow-lg py-1"
             style="background:var(--s1); border:1px solid var(--border2); min-width:190px">
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <form method="POST" action="{{ route('tasks.update-status', $task) }}">
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
    <td>
        <span class="text-sm font-medium" style="color:var(--text)">
            {{ $task->client?->company_name ?? '—' }}
        </span>
    </td>

    {{-- Responsável --}}
    <td>
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
    <td>
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

    {{-- Data de Aprovação (ou prazo como fallback) --}}
    <td>
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
    <td>
        <span class="badge badge-muted" style="font-size:10px">{{ $task->originLabel() }}</span>
    </td>

    {{-- Destino --}}
    <td>
        <span class="text-xs" style="color:var(--muted2)">
            {{ $task->destinationLabel() ?: '—' }}
        </span>
    </td>

    {{-- Situação --}}
    <td>
        <span class="text-xs" style="color:var(--muted2)">
            @if($task->situation && $task->situationLabel() !== '—')
                {{ $task->situationLabel() }}
            @else
                <span style="color:var(--muted)">—</span>
            @endif
        </span>
    </td>

    {{-- Ações: Abrir + → Sprint --}}
    <td>
        <div class="row-actions flex items-center gap-1.5">
            <a href="{{ route('tasks.show', $task) }}" class="btn btn-primary btn-xs">Abrir</a>

            @if($sprints->count() > 0)
                <div x-data="{ sprintOpen: false }" class="relative flex items-stretch">
                    {{-- Botão rápido → sprint ativa --}}
                    @if($activeSprint)
                        <form method="POST" action="{{ route('sprints.add-task', [$activeSprint, $task]) }}">
                            @csrf
                            <button type="submit"
                                class="btn btn-xs text-white"
                                style="background:var(--green); border-color:var(--green); border-radius:6px 0 0 6px"
                                title="Enviar para: {{ $activeSprint->title }}">
                                → Sprint
                            </button>
                        </form>
                    @endif

                    {{-- Dropdown para escolher outra sprint --}}
                    @if($sprints->count() > 1 || !$activeSprint)
                        <button @click="sprintOpen = !sprintOpen" @click.stop type="button"
                            class="btn btn-xs px-1.5"
                            style="background:var(--green); border-color:var(--green); color:#fff; border-radius:0 6px 6px 0; border-left:1px solid rgba(255,255,255,.3)">
                            ▾
                        </button>
                        <div x-show="sprintOpen" @click.outside="sprintOpen = false" x-cloak
                             class="absolute right-0 bottom-full mb-1 z-30 py-1 min-w-48"
                             style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.15)">
                            <p class="px-3 py-1.5 text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.08em">Enviar para</p>
                            @foreach($sprints as $sp)
                                <form method="POST" action="{{ route('sprints.add-task', [$sp, $task]) }}">
                                    @csrf
                                    <button type="submit" @click="sprintOpen = false"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left transition-colors"
                                        style="color:var(--text)"
                                        onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                        <span class="h-1.5 w-1.5 rounded-full flex-shrink-0"
                                              style="background:{{ $sp->status === 'active' ? 'var(--green)' : 'var(--orange)' }}"></span>
                                        <span class="font-medium truncate">{{ $sp->title }}</span>
                                        <span class="ml-auto flex-shrink-0" style="color:var(--muted)">
                                            {{ $sp->status === 'active' ? 'Ativa' : 'Planning' }}
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <a href="{{ route('sprints.create') }}" class="btn btn-ghost btn-xs" title="Nenhuma sprint disponível">
                    + Sprint
                </a>
            @endif
        </div>
    </td>
</tr>
