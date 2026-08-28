{{-- Card de tarefa pro mobile (md:hidden) — versão enxuta de _task-tr / _fila-task-tr:
     sem seleção em massa e sem os dropdowns "Monday fill" inline (toque abre a tarefa;
     ações de contexto ficam na página da tarefa). Ações rápidas mantidas: ticket → Fila,
     fila → Sprint ativa.
     Espera: $task, $context ('fila'|'ticket'). Opcional: $activeSprint, $sprints. --}}
@php
    $execUser = $task->executors->first(fn ($u) => $u->pivot->role === 'executor') ?? $task->executor;
    $respUser = $task->executors->first(fn ($u) => $u->pivot->role === 'responsavel');
    $hasSituation = $task->situation && $task->situation !== '';
    $overdue = $task->isOverdue();
    $dateLabel = $task->approval_date
        ? 'Aprv ' . $task->approval_date->format('d/m')
        : ($task->due_date ? 'Prazo ' . $task->due_date->format('d/m') : null);

    if ($task->project?->title)          { $sub = $task->project->title; }
    elseif ($task->is_ticket && $task->requester_name) { $sub = $task->requester_name; }
    elseif ($task->macroPlan)            { $sub = $task->macroPlan->title; }
    elseif ($task->meeting)              { $sub = $task->meeting->title; }
    else                                { $sub = null; }
@endphp

<div class="px-4 py-3.5" style="border-bottom:1px solid var(--border2); {{ $overdue ? 'border-left:2px solid var(--red)' : '' }}">
    <div class="flex items-start gap-2.5">
        <x-icon-chip :icon="$task->typeIcon()" :color="$task->statusColor()" size="32" />
        <div class="min-w-0 flex-1">
            <a href="{{ route('tasks.show', $task) }}" class="block font-semibold text-sm leading-snug" style="color:var(--text)">
                @if(($task->priority ?? 'normal') === 'urgente')
                    <span style="color:#dc2626">⚑ </span>
                @endif
                {{ $task->title }}
            </a>

            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span class="badge" style="background:{{ $task->statusHex() }}; color:#fff; border-color:transparent">{{ $task->statusLabel() }}</span>
                @if($hasSituation)
                    <span class="badge" style="background:{{ $task->situationColor() }}; color:#fff; border-color:transparent">{{ $task->situationLabel() }}</span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1.5 text-xs" style="color:var(--muted)">
                @if($task->client)<span>{{ $task->client->displayName() }}</span>@endif
                @if($sub)<span>· {{ \Illuminate\Support\Str::limit($sub, 40) }}</span>@endif
                @if($dateLabel)
                    <span style="{{ $overdue ? 'color:var(--red); font-weight:600' : '' }}">· {{ $dateLabel }}</span>
                @endif
            </div>

            <div class="flex items-center gap-3 mt-2">
                @if($respUser)
                    <span class="flex items-center gap-1 text-xs" style="color:var(--muted)" title="Responsável">
                        <x-user-avatar :user="$respUser" size="5" /> Resp
                    </span>
                @endif
                @if($execUser)
                    <span class="flex items-center gap-1 text-xs" style="color:var(--muted)" title="Executor">
                        <x-user-avatar :user="$execUser" size="5" /> Exec
                    </span>
                @endif

                @if($context === 'ticket')
                    <form method="POST" action="{{ route('tasks.send-to-fila', $task) }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-xs">→ Fila</button>
                    </form>
                @elseif($context === 'fila' && ($activeSprint ?? null) && !$task->sprint_id)
                    <form method="POST" action="{{ route('sprints.add-task', [$activeSprint, $task]) }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="btn btn-xs text-white" style="background:var(--green); border-color:var(--green)"
                                title="Enviar para {{ $activeSprint->title }}">
                            → Sprint
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
