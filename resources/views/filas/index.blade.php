<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span class="text-base font-bold" style="color:var(--text)">Fila de Tarefas</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">+ Novo Ticket</a>
        </div>
    </x-slot>

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
        'formAction'    => route('fila.index'),
        'prefix'        => '',
        'clients'       => $clients,
        'projects'      => $projects,
        'users'         => $users,
        'groupBy'       => $groupBy,
        'clearUrl'      => route('fila.index', ['group_by' => $groupBy]),
        'resultsUrl'    => route('fila.results'),
        'resultsTarget' => '#filas-results',
    ])

    <div id="filas-results">
        @include('filas._results')
    </div>
    <x-badge-fill-menu />
    <x-person-fill-menu :users="$users" />

</x-app-layout>
