{{-- Fragmento da aba Lista — reaproveitado no load inicial (sprints.show) e na busca
     dinâmica via AJAX (SprintController::listResults(), fetch disparado por live-filter.js). --}}
@if($listTasks->isEmpty())
    <div class="tab-placeholder">
        <div class="tab-placeholder-icon"><x-icon name="search" size="32" /></div>
        <p class="tab-placeholder-title">Nenhuma tarefa encontrada</p>
        <p class="tab-placeholder-desc">Ajuste os filtros ou volte para o Board.</p>
    </div>
@else
    @include('sprints._carga-sprint')

    {{-- Celular recebe só os cards (mesmo partial de Filas); o resto, só a tabela. A Sprint
         tem centenas de tarefas: mandar as duas versões deixava o HTML ~30% maior e mais
         lento pra todo mundo, por isso decide pelo aparelho (User-Agent) e não por CSS. --}}
    @if(preg_match('/Mobile|Android|iPhone|iPod/i', (string) request()->userAgent()))
        <div class="card overflow-hidden">
            @foreach($listGrouped as $groupKey => $groupTasks)
                @include('partials._task-group-cards', ['groupBy' => $listGroupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks, 'activeSprint' => $activeSprint, 'sprints' => $sprints, 'defaultOpen' => false])
            @endforeach
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    @include('partials._task-thead')
                    @foreach($listGrouped as $groupKey => $groupTasks)
                        @include('partials._task-group-tbody', ['groupBy' => $listGroupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks, 'activeSprint' => $activeSprint, 'sprints' => $sprints, 'defaultOpen' => false])
                    @endforeach
                </table>
            </div>
        </div>
    @endif
@endif
