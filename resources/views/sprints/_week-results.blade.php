{{-- Fragmento da aba Semana — chamado via fetch por live-filter.js conforme o usuário filtra
     (ver SprintController::weekResults()). Kanban por dia útil (seg-sex) da semana atual,
     preenchido pela approval_date da tarefa — quem não tem approval_date nesta janela não
     aparece aqui (só entra na contagem informativa abaixo). --}}
@if($weekOutsideCount > 0)
    <p class="text-xs font-mono mb-4" style="color:var(--muted)">
        {{ $weekOutsideCount }} tarefa{{ $weekOutsideCount !== 1 ? 's' : '' }} com o filtro atual
        sem data de aprovação dentro desta semana (não aparece{{ $weekOutsideCount !== 1 ? 'm' : '' }} no board abaixo).
    </p>
@endif

<div id="sprint-week-board" class="flex gap-4 overflow-x-auto pb-2" style="align-items: start;"
     data-kanban-board data-status-field="approval_date">

    @foreach($weekDays as $day)
        @php $dayKey = $day->toDateString(); $colTasks = $weekKanban[$dayKey]; @endphp
        <div class="flex flex-col gap-2 flex-shrink-0" style="width:270px"
             data-kanban-column data-status="{{ $dayKey }}">
            <div class="flex items-center justify-between px-3 py-2"
                 style="background:var(--s2); border:1px solid var(--border2)">
                <span class="text-xs font-bold font-mono uppercase tracking-widest" style="color:var(--purple)">
                    {{ ucfirst($day->translatedFormat('D')) }} · {{ $day->format('d/m') }}
                </span>
                <span class="text-xs font-mono font-bold" data-kanban-count style="color:var(--muted)">{{ $colTasks->count() }}</span>
            </div>

            <div class="flex flex-col gap-2" style="min-height:40px" data-kanban-list>
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
                            @if($task->project)
                                <span style="color:var(--border2)"> / </span>
                                <span style="color:var(--muted)">{{ $task->project->title }}</span>
                            @elseif($task->is_ticket)
                                <span style="color:var(--border2)"> / </span>
                                <span style="color:var(--orange)">Ticket</span>
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
