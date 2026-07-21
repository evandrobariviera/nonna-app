<x-portal-layout>
    <x-slot name="title">{{ $macroplan->title }}</x-slot>

    <div class="mb-8">
        <a href="{{ route('portal.projects.index') }}"
           class="text-xs font-semibold mb-3 inline-flex items-center gap-1"
           style="color: var(--muted)">
            ← Planejamentos
        </a>
        <h1 class="text-2xl font-black" style="color: var(--text)">{{ $macroplan->title }}</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            {{ $macroplan->period_start?->format('d/m/Y') }} — {{ $macroplan->period_end?->format('d/m/Y') }}
        </p>
    </div>

    @forelse($macroplan->projects as $project)
        @php
            $tasks = $project->tasks;
            $done  = $tasks->where('status', 'concluido')->count();
            $total = $tasks->count();
            $pct   = $total > 0 ? round($done / $total * 100) : 0;
        @endphp
        <div class="card mb-5">
            <div class="p-5 border-b" style="border-color: var(--border2)">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-bold" style="color: var(--text)">{{ $project->title }}</p>
                    <div class="text-right">
                        <span class="text-sm font-black" style="color: var(--purple)">{{ $pct }}%</span>
                        <span class="text-xs ml-1" style="color: var(--muted)">{{ $done }}/{{ $total }}</span>
                    </div>
                </div>
                <div class="h-1.5 rounded-full" style="background: var(--s3)">
                    <div class="h-1.5 rounded-full" style="background: var(--grad); width: {{ $pct }}%"></div>
                </div>
                @if($project->objective)
                    <p class="text-xs mt-3" style="color: var(--muted)">{{ $project->objective }}</p>
                @endif
            </div>

            @if($tasks->isNotEmpty())
                <div class="divide-y" style="--tw-divide-opacity:1">
                    @foreach($tasks as $task)
                        @php
                            $isDone = $task->status === 'concluido';
                            $statusMap = [
                                'backlog'               => ['label' => 'Backlog',              'color' => 'var(--muted)'],
                                'em_producao'           => ['label' => 'Em Produção',          'color' => 'var(--orange)'],
                                'revisao_interna'       => ['label' => 'Em Revisão',           'color' => 'var(--purple)'],
                                'ajuste_alteracao'      => ['label' => 'Em Ajuste',            'color' => 'var(--purple)'],
                                'aprovacao'             => ['label' => 'Ag. Aprovação',        'color' => 'var(--orange)'],
                                'despacho_agendamento'  => ['label' => 'Aprovada — Agendando', 'color' => 'var(--green)'],
                                'concluido'             => ['label' => 'Concluída',            'color' => 'var(--green)'],
                                'cancelado'             => ['label' => 'Cancelada',            'color' => 'var(--red)'],
                            ];
                            $st = $statusMap[$task->status] ?? ['label' => $task->status, 'color' => 'var(--muted)'];
                        @endphp
                        <a href="{{ route('portal.tasks.show', $task) }}"
                           class="px-5 py-3 flex items-center justify-between transition-colors"
                           style="border-color: var(--border2); text-decoration:none"
                           onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
                            <div class="flex items-center gap-3">
                                <div class="h-4 w-4 rounded-full flex-shrink-0 flex items-center justify-center"
                                     style="background: {{ $isDone ? 'rgba(5,150,105,.15)' : 'var(--s3)' }};
                                            border: 1.5px solid {{ $isDone ? 'var(--green)' : 'var(--border2)' }}">
                                    @if($isDone)
                                        <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" style="color: var(--green)">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        </svg>
                                    @endif
                                </div>
                                <span class="text-sm {{ $isDone ? 'line-through' : '' }}"
                                      style="color: {{ $isDone ? 'var(--muted)' : 'var(--text)' }}">
                                    {{ $task->title }}
                                </span>
                            </div>
                            <span class="text-xs font-semibold" style="color: {{ $st['color'] }}">
                                {{ $st['label'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-4 text-xs" style="color: var(--muted)">Nenhuma tarefa neste projeto ainda.</div>
            @endif
        </div>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm" style="color: var(--muted)">Nenhum projeto neste planejamento.</p>
        </div>
    @endforelse

</x-portal-layout>
