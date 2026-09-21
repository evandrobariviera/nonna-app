{{-- Fragmento AJAX da Semana de Produção (ver ProductionPanelController::semanaKanban()) —
     mesmo padrão visual e de arrastar-e-soltar da aba "Semana" da Sprint, mas cruzando a
     agência inteira (com os filtros globais do topo) em vez de uma sprint só.

     Coluna "Semana anterior" reúne toda tarefa aberta com data de aprovação antes da segunda
     em exibição. Tarefa sem data de aprovação, ou com data muito à frente, não aparece em
     nenhuma coluna — só entra na linha informativa abaixo da navegação. --}}
@php
    // Texto informativo montado aqui, fora do HTML — evita empilhar vários @if/@endif
    // numa linha só, que já se mostrou frágil na compilação do Blade nesta tela.
    $foraDoQuadro = collect([
        $semDataCount > 0 ? $semDataCount . ' tarefa' . ($semDataCount !== 1 ? 's' : '') . ' sem data de aprovação' : null,
        $depoisCount > 0 ? $depoisCount . ' com aprovação depois desta semana' : null,
    ])->filter()->implode(' · ');

    $weekColumns = collect([[
        'key'        => 'atrasadas',
        'dataStatus' => $beforeWeekDate->toDateString(),
        'label'      => 'Semana anterior',
        'color'      => 'var(--red)',
        'isToday'    => false,
        'tasks'      => $weekKanban['atrasadas'],
    ]]);
    foreach ($weekDays as $day) {
        $weekColumns->push([
            'key'        => $day->toDateString(),
            'dataStatus' => $day->toDateString(),
            'label'      => ucfirst($day->translatedFormat('D')) . ' · ' . $day->format('d/m'),
            'color'      => $day->isToday() ? 'var(--green)' : 'var(--purple)',
            'isToday'    => $day->isToday(),
            'tasks'      => $weekKanban[$day->toDateString()],
        ]);
    }
@endphp

{{-- Navegação semanal — os botões só mexem no #producao-week-offset-input (fora deste
     fragmento, em producao/index.blade.php) e disparam o refresh via live-filter. --}}
<div class="flex items-center gap-3 mb-4">
    <button type="button"
        onclick="document.getElementById('producao-week-offset-input').value='{{ $weekOffset - 1 }}'; document.getElementById('producao-week-filter-form')._liveFilterRefresh();"
        class="btn btn-ghost btn-sm">‹ Semana anterior</button>

    <span class="text-xs font-mono font-bold" style="color:var(--text)">
        {{ $weekDays->first()->format('d/m') }} a {{ $weekDays->last()->format('d/m/Y') }}
    </span>

    @if($weekOffset !== 0)
        <button type="button"
            onclick="document.getElementById('producao-week-offset-input').value='0'; document.getElementById('producao-week-filter-form')._liveFilterRefresh();"
            class="btn btn-ghost btn-sm">Hoje</button>
    @endif

    <button type="button"
        onclick="document.getElementById('producao-week-offset-input').value='{{ $weekOffset + 1 }}'; document.getElementById('producao-week-filter-form')._liveFilterRefresh();"
        class="btn btn-ghost btn-sm">Próxima semana ›</button>
</div>

@if($foraDoQuadro !== '')
    <p class="text-xs font-mono mb-4" style="color:var(--muted)">
        {{ $foraDoQuadro }}
        — não aparece{{ ($semDataCount + $depoisCount) !== 1 ? 'm' : '' }} no quadro abaixo.
    </p>
@endif

<div id="producao-week-board" class="flex gap-4 overflow-x-auto pb-2" style="align-items: start;"
     data-kanban-board data-status-field="approval_date">

    @foreach($weekColumns as $col)
        @php $dayKey = $col['key']; $colTasks = $col['tasks']; @endphp
        <div class="flex flex-col gap-2 flex-shrink-0" style="width:270px{{ $col['isToday'] ? '; padding:6px; border-radius:10px; border:2px solid var(--green); background:rgba(52,211,153,.05)' : '' }}"
             data-kanban-column data-status="{{ $col['dataStatus'] }}">
            <div class="flex items-center justify-between px-3 py-2"
                 style="{{ $col['isToday'] ? 'background:rgba(52,211,153,.12); border:1px solid var(--green)' : 'background:var(--s2); border:1px solid var(--border2)' }}">
                <span class="text-xs font-bold font-mono uppercase tracking-widest" style="color:{{ $col['color'] }}">
                    {{ $col['label'] }}{{ $col['isToday'] ? ' · Hoje' : '' }}
                </span>
                <span class="text-xs font-mono font-bold" data-kanban-count style="color:var(--muted)">{{ $colTasks->count() }}</span>
            </div>

            <div class="flex flex-col gap-2" style="min-height:40px; max-height:70vh; overflow-y:auto" data-kanban-list>
            @forelse($colTasks as $task)
                @php
                    $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
                    if ($execList->isEmpty() && $task->executor) {
                        $execList = collect([$task->executor]);
                    }
                    $respList = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
                    $approvalUrl = route('tasks.update-approval-date-direct', $task);
                    $thumbUrl = $task->firstImageAttachmentUrl();
                @endphp
                <div class="card px-0 py-0 relative overflow-hidden" x-data="{ moveOpen: false, moveStyle: '' }"
                     data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $approvalUrl }}"
                     style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}; cursor:pointer"
                     @click="window.location = '{{ route('tasks.show', $task) }}'">

                    @if($thumbUrl)
                        <img src="{{ $thumbUrl }}" alt="" class="w-full object-cover" style="height:80px">
                    @endif

                    <div class="px-4 py-3">
                        <p class="text-xs font-mono mb-1" style="color:var(--purple)">
                            {{ $task->client?->displayName() ?? '—' }}
                            @if($task->sprint_id)
                                <span style="color:var(--border2)"> / </span>
                                <span style="color:var(--muted)">Sprint</span>
                            @else
                                <span style="color:var(--border2)"> / </span>
                                <span style="color:var(--orange)">Fila</span>
                            @endif
                        </p>

                        <div class="flex items-center gap-2 mb-2">
                            <x-icon-chip :icon="$task->typeIcon()" :color="$task->statusColor()" size="30" />
                            <p class="text-xs font-semibold leading-snug min-w-0" style="color:var(--text)">
                                {{ $task->title }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 mb-2">
                            <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px">{{ $task->statusLabel() }}</span>
                            @if($task->priority && $task->priority !== 'normal')
                                <span class="badge badge-{{ $task->priorityColor() }}" style="font-size:10px">{{ $task->priorityLabel() }}</span>
                            @endif
                        </div>

                        @if($respList->isNotEmpty() || $execList->isNotEmpty())
                            <div class="flex items-center gap-1 mb-2">
                                @foreach($respList as $resp)
                                    <x-user-avatar :user="$resp" size="6" color="var(--orange)" title="{{ $resp->name }} (Responsável)" />
                                @endforeach
                                @foreach($execList as $exec)
                                    <x-user-avatar :user="$exec" size="6" color="var(--purple)" title="{{ $exec->name }} (Executor)" />
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center gap-1.5 pt-2 relative" style="border-top:1px solid var(--border2)" @click.stop>
                            <button @click="moveOpen = !moveOpen; moveStyle = dropdownStyle($el, 'top-left')" @click.stop type="button"
                                class="text-xs px-2 py-0.5 font-mono flex items-center gap-1"
                                style="border:1px solid var(--border2); color:var(--muted)">
                                Mover <span style="opacity:.7">▾</span>
                            </button>

                            <template x-teleport="body">
                                <div x-show="moveOpen" @click.outside="moveOpen = false" x-close-on-scroll="moveOpen" x-cloak
                                     class="rounded shadow-lg py-1"
                                     :style="moveStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:170px'">
                                    @foreach($weekDays as $targetDay)
                                        @if($targetDay->toDateString() !== $dayKey)
                                            <form method="POST" action="{{ $approvalUrl }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="approval_date" value="{{ $targetDay->toDateString() }}">
                                                <button type="submit"
                                                    class="w-full text-left px-3 py-1.5 text-xs transition-colors"
                                                    style="color:var(--text)"
                                                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                                                    {{ ucfirst($targetDay->translatedFormat('D')) }} · {{ $targetDay->format('d/m') }}
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-5 text-center text-xs"
                     style="border:1px dashed var(--border2); color:var(--muted)">
                    Sem tarefas
                </div>
            @endforelse
            </div>
        </div>
    @endforeach

</div>
