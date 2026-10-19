{{-- Fragmento da aba Planejamento — carregado via fetch (SprintController::planning()) só
     quando a aba é aberta. Herda clientFilter/selected do x-data da página. --}}
@if($sprint->status === 'closed')
    <p class="text-sm font-mono" style="color:var(--muted)">Sprint encerrada — planejamento bloqueado.</p>
@else
    {{-- Filtro por cliente --}}
    <div class="flex items-center gap-3 mb-4">
        <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Filtrar:</span>
        <select x-model="clientFilter"
            class="px-3 py-1.5 text-xs focus:outline-none"
            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
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
                        {{ $task->client?->displayName() ?? '—' }}
                    </span>
                    @if($task->project)
                        <span class="text-xs font-mono" style="color:var(--muted)">/ {{ $task->project->title }}</span>
                    @elseif($task->is_ticket)
                        <span class="badge badge-orange" style="font-size:10px">Ticket</span>
                    @endif
                </div>
                <p class="text-xs font-semibold mt-0.5 leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                <div class="flex items-center gap-2 mt-1">
                    <x-task-type-badge :task="$task" class="text-xs" />
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
                <button type="submit" class="btn btn-primary btn-xs flex-shrink-0">
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
