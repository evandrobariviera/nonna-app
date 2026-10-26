{{-- Tarefas de uma fase (ou "Sem fase") na linha do tempo — com troca de fase na hora
     (setTaskPhase em projects/_timeline). --}}
@if($tasks->isEmpty())
    <p class="text-xs py-1" style="color:var(--muted)">Nenhuma tarefa nesta fase ainda — crie pelo quadro abaixo escolhendo esta fase.</p>
@else
    <div class="flex flex-col" style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
        @foreach($tasks as $t)
            @php $statusHex = \App\Models\Task::colorHex(\App\Models\Task::$statuses[$t->status]['color'] ?? 'muted'); @endphp
            <div class="flex items-center gap-3 px-3 py-2 flex-wrap" style="{{ $loop->first ? '' : 'border-top:1px solid var(--border2);' }}">
                <span class="flex-shrink-0 rounded-full" style="width:8px; height:8px; background:{{ $statusHex }}" title="{{ \App\Models\Task::$statuses[$t->status]['label'] ?? $t->status }}"></span>
                <a href="{{ route('tasks.show', $t) }}" class="text-sm truncate hover:underline flex-1 min-w-0"
                   style="color:var(--text); {{ $t->status === 'concluido' ? 'text-decoration:line-through; opacity:.6' : '' }}">{{ $t->title }}</a>
                <span class="text-xs flex-shrink-0" style="color:{{ $statusHex }}">{{ \Illuminate\Support\Str::before(\App\Models\Task::$statuses[$t->status]['label'] ?? $t->status, ' /') }}</span>
                @if($t->executor)
                    <span class="text-xs flex-shrink-0" style="color:var(--muted2)">{{ \Illuminate\Support\Str::before($t->executor->name, ' ') }}</span>
                @endif
                @if($t->due_date)
                    <span class="text-xs font-mono flex-shrink-0" style="color:{{ $t->status !== 'concluido' && $t->due_date->lt(now()->startOfDay()) ? 'var(--red)' : 'var(--muted)' }}">{{ $t->due_date->format('d/m') }}</span>
                @endif
                <select class="field px-2 py-1 text-xs focus:outline-none flex-shrink-0" style="max-width:180px" title="Fase da tarefa"
                        onchange="setTaskPhase('{{ route('tasks.update-phase', $t) }}', this.value)">
                    <option value="" {{ $currentPhaseId ? '' : 'selected' }}>— sem fase —</option>
                    @foreach($phaseOptions as $opt)
                        <option value="{{ $opt->id }}" {{ $currentPhaseId === $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>
@endif
