{{-- Fragmento reaproveitado no load inicial (filas.index) e na busca dinâmica via AJAX
     (FilaController::results(), fetch disparado por live-filter.js). Stats + tabela mudam
     juntos com o filtro (mesmo escopo); "Sprint Ativa" fica fora, não depende de filtro. --}}

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
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

{{-- ══ TABELA AGRUPADA ══ --}}
@if($tasks->isEmpty())
    <div class="tab-placeholder">
        <div class="tab-placeholder-icon"><x-icon name="party-popper" size="32" /></div>
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
