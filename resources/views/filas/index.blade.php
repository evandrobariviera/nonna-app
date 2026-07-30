<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span class="text-base font-bold" style="color:var(--text)">Fila de Tarefas</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">+ Novo Ticket</a>
        </div>
    </x-slot>

    {{-- ══ STATS ══ --}}
    <div class="grid grid-cols-6 gap-3 mb-5">
        @php
            $statsConfig = [
                ['label' => 'Na Fila',      'value' => $stats['total'],      'color' => 'var(--text)',   'filter' => null],
                ['label' => 'Projetos',     'value' => $stats['projetos'],   'color' => 'var(--purple)', 'filter' => 'origin=projeto'],
                ['label' => 'Tickets',      'value' => $stats['tickets'],    'color' => 'var(--orange)', 'filter' => 'origin=ticket'],
                ['label' => 'Atrasadas',    'value' => $stats['atrasadas'],  'color' => 'var(--red)',    'filter' => 'atrasadas=1'],
                ['label' => 'Pendências',   'value' => $stats['pendencias'], 'color' => 'var(--red)',    'filter' => 'pendencia=1'],
                ['label' => 'Clientes',     'value' => $stats['clientes'],   'color' => 'var(--muted2)', 'filter' => null],
            ];
        @endphp
        @foreach($statsConfig as $s)
            <div class="stat-card">
                <p class="stat-label">{{ $s['label'] }}</p>
                <p class="stat-value" style="color:{{ $s['color'] }}">{{ $s['value'] }}</p>
                @if($s['filter'])
                    <a href="{{ route('fila.index') }}?{{ $s['filter'] }}"
                       class="text-xs mt-2 block" style="color:var(--muted)"
                       onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                        filtrar →
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ══ SPRINT ATIVA ══ --}}
    @if($activeSprint)
        <div class="mb-4 flex items-center gap-3 px-5 py-3 card"
             style="background:rgba(5,150,105,.04); border-color:rgba(5,150,105,.2)">
            <span class="h-2 w-2 rounded-full flex-shrink-0 animate-pulse" style="background:var(--green)"></span>
            <div class="flex-1">
                <span class="text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.08em">Sprint Ativa</span>
                <span class="text-sm font-semibold ml-2" style="color:var(--text)">{{ $activeSprint->title }}</span>
                @if($activeSprint->ends_at)
                    <span class="text-xs ml-2" style="color:var(--muted)">· encerra {{ $activeSprint->ends_at->format('d/m/Y') }} ({{ $activeSprint->ends_at->diffForHumans() }})</span>
                @endif
            </div>
            <a href="{{ route('sprints.show', $activeSprint) }}"
               class="text-xs font-semibold" style="color:var(--green)"
               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                Ver Sprint →
            </a>
        </div>
    @else
        <div class="mb-4 flex items-center gap-3 px-5 py-3 card"
             style="background:rgba(217,119,6,.04); border-color:rgba(217,119,6,.2)">
            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--yellow)"></span>
            <p class="text-sm" style="color:var(--muted)">Nenhuma sprint ativa.
                <a href="{{ route('sprints.create') }}" style="color:var(--orange)"
                   onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                    Criar sprint →
                </a>
            </p>
        </div>
    @endif

    {{-- ══ FILTROS + AGRUPAMENTO ══ --}}
    @include('partials._task-filter-bar', [
        'formAction' => route('fila.index'),
        'prefix'     => '',
        'clients'    => $clients,
        'projects'   => $projects,
        'groupBy'    => $groupBy,
        'clearUrl'   => route('fila.index', ['group_by' => $groupBy]),
    ])

    {{-- ══ TABELA AGRUPADA ══ --}}
    @if($tasks->isEmpty())
        <div class="tab-placeholder">
            <div class="tab-placeholder-icon">🎉</div>
            <p class="tab-placeholder-title">Fila vazia!</p>
            <p class="tab-placeholder-desc">Todas as tarefas foram alocadas em sprints ou não há tarefas no backlog.</p>
        </div>
    @else
        <div x-data="taskBulk()" x-cloak>
        @include('partials._task-bulk-bar')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    @include('partials._task-thead')

                    @foreach($grouped as $groupKey => $groupTasks)
                        @include('partials._task-group-tbody', ['groupBy' => $groupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks, 'activeSprint' => $activeSprint, 'sprints' => $sprints])
                    @endforeach
                </table>
            </div>
        </div>
        </div>{{-- /x-data taskBulk --}}
    @endif

</x-app-layout>
