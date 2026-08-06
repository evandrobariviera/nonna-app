{{-- Preview leve pro painel lateral (canvas). Não inclui layout — só o fragmento. --}}
<div class="p-6 flex flex-col gap-6">

    {{-- Cabeçalho --}}
    <div>
        <p class="text-base font-bold" style="color:var(--text)">{{ $project->title }}</p>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="badge badge-{{ $project->statusColor() }}">{{ $project->statusLabel() }}</span>
            <span class="badge badge-{{ $project->typeColor() }}">{{ $project->typeLabel() }}</span>
            @if($project->client)
                <span class="text-xs" style="color:var(--muted)">{{ $project->client->displayName() }}</span>
            @endif
        </div>
    </div>

    @if($project->macroPlan)
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Planejamento</p>
            <a href="{{ route('macroplans.edit', $project->macroPlan) }}"
               class="text-sm font-medium transition-colors" style="color:var(--purple)"
               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                {{ $project->macroPlan->title }}
            </a>
        </div>
    @endif

    {{-- Progresso --}}
    <div class="card card-body">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Progresso</p>
            <p class="text-sm font-bold" style="color:var(--text)">{{ $progress }}%</p>
        </div>
        <div class="w-full h-1.5 rounded-full overflow-hidden" style="background:var(--s3)">
            <div class="h-full rounded-full" style="width:{{ $progress }}%; background:var(--purple)"></div>
        </div>
        <p class="text-xs mt-2" style="color:var(--muted)">{{ $doneTasks }} de {{ $totalTasks }} tarefas concluídas</p>
    </div>

    {{-- Tarefas recentes --}}
    @if($recentTasks->isNotEmpty())
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Tarefas recentes</p>
            <div class="flex flex-col gap-2">
                @foreach($recentTasks as $task)
                    <a href="{{ route('tasks.show', $task) }}"
                       class="card card-body flex items-center justify-between gap-3 transition-colors"
                       onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                        <span class="text-sm font-medium truncate" style="color:var(--text)">{{ $task->title }}</span>
                        <span class="badge badge-{{ $task->statusColor() }} flex-shrink-0">{{ $task->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Produção (entregáveis) --}}
    @if($deliverables->isNotEmpty())
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Produção ({{ $deliverables->count() }})</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach($deliverables as $d)
                    <a href="{{ $d->url() }}" target="_blank"
                       class="card flex items-center justify-center overflow-hidden transition-colors"
                       style="aspect-ratio:1; padding:0"
                       onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'"
                       title="{{ $d->filename }}">
                        @if($d->isImage())
                            <img src="{{ $d->url() }}" alt="{{ $d->filename }}" class="w-full h-full object-cover">
                        @else
                            <div class="flex flex-col items-center gap-1 px-2 text-center">
                                <span style="font-size:22px">{{ $d->icon() }}</span>
                                <span class="text-xs truncate w-full" style="color:var(--muted)">{{ $d->filename }}</span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Link pra página completa --}}
    <a href="{{ $project->macro_plan_id ? route('macroplans.projects.show', [$project->macro_plan_id, $project]) : route('projects.showDirect', $project) }}"
       class="text-center px-4 py-2.5 text-xs font-bold transition-colors"
       style="border:1px solid var(--border2); color:var(--muted2)"
       onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
       onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
        Ver página completa do projeto →
    </a>
</div>
