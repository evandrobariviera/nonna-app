{{-- Fragmento da aba Lista — reaproveitado no load inicial (sprints.show) e na busca
     dinâmica via AJAX (SprintController::listResults(), fetch disparado por live-filter.js). --}}
@if($listTasks->isEmpty())
    <div class="tab-placeholder">
        <div class="tab-placeholder-icon">🔍</div>
        <p class="tab-placeholder-title">Nenhuma tarefa encontrada</p>
        <p class="tab-placeholder-desc">Ajuste os filtros ou volte para o Board.</p>
    </div>
@else
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nonna-table">
                @include('partials._task-thead')
                @foreach($listGrouped as $groupKey => $groupTasks)
                    @include('partials._task-group-tbody', ['groupBy' => $listGroupBy, 'groupKey' => $groupKey, 'groupTasks' => $groupTasks, 'activeSprint' => $activeSprint, 'sprints' => $sprints])
                @endforeach
            </table>
        </div>
    </div>
@endif
