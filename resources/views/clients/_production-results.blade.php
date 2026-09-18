{{-- Panorama de produção do cliente — Fila + Sprint + Chamado numa lista só
     (ver ClientController::productionData()).

     Montado em dois lugares, com a mesma consulta e o mesmo agrupamento:
       · ficha do cliente (tela cheia)  → tabela completa, operável, com ação em massa
       · canvas / painel lateral        → $compact = true: só cards, sem ação em massa
     A tabela de 10 colunas não cabe no painel lateral, e o card agrupado já existe
     (é o mesmo usado no mobile da Fila). --}}
@props([])
@php $compact = $compact ?? false; @endphp

@if($tasks->isEmpty())
    <div class="tab-placeholder">
        <div class="tab-placeholder-icon"><x-icon name="party-popper" size="32" /></div>
        <p class="tab-placeholder-title">Nada em produção</p>
        <p class="tab-placeholder-desc">
            {{ $showDone ? 'Este cliente não tem nenhuma tarefa registrada.' : 'Nenhuma tarefa aberta para este cliente.' }}
        </p>
    </div>
@elseif($compact)
    <div class="card overflow-hidden">
        @foreach($grouped as $groupKey => $groupTasks)
            @include('partials._task-group-cards', [
                'groupBy' => $groupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks,
                'activeSprint' => $activeSprint, 'sprints' => $sprints,
            ])
        @endforeach
    </div>
@else
    <div x-data="taskBulk()" x-cloak>
        @include('partials._task-bulk-bar')

        {{-- Mobile: cards (seleção em massa é desktop-only, igual na Fila) --}}
        <div class="card overflow-hidden md:hidden">
            @foreach($grouped as $groupKey => $groupTasks)
                @include('partials._task-group-cards', [
                    'groupBy' => $groupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks,
                    'activeSprint' => $activeSprint, 'sprints' => $sprints,
                ])
            @endforeach
        </div>

        {{-- Desktop: tabela --}}
        <div class="card overflow-hidden hidden md:block">
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    @include('partials._task-thead')
                    @foreach($grouped as $groupKey => $groupTasks)
                        @include('partials._task-group-tbody', [
                            'groupBy' => $groupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks,
                            'activeSprint' => $activeSprint, 'sprints' => $sprints,
                        ])
                    @endforeach
                </table>
            </div>
        </div>
    </div>
@endif
