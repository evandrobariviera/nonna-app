{{-- Fragmento da aba Board — carregado via fetch (SprintController::board()) só quando a
     aba é aberta. Herda selectMode/selected do x-data da página (sprints/show.blade.php).
     Um único menu "Mover" pro board inteiro (antes: 1 cópia escondida com 7 formulários
     por cartão — 1.144 formulários numa sprint de 162 tarefas). --}}
<div x-data="{ moveOpen: false, moveStyle: '', moveUrl: '', moveFrom: '' }">
    <div id="sprint-board" class="flex gap-4 overflow-x-auto pb-2" style="align-items: start;"
         data-kanban-board data-status-field="status">

        @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
            @php $colTasks = $kanban[$statusKey]; @endphp
            <div class="flex flex-col gap-2 flex-shrink-0" style="width:270px"
                 data-kanban-column data-status="{{ $statusKey }}">
                <div class="flex items-center justify-between px-3 py-2"
                     style="background:var(--s2); border:1px solid var(--border2)">
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full"
                             style="background:var(--{{ $meta['color'] === 'muted' ? 'muted' : $meta['color'] }})"></div>
                        <span class="text-xs font-bold font-mono uppercase tracking-widest"
                              style="color:var(--{{ $meta['color'] === 'muted' ? 'muted2' : $meta['color'] }})">
                            {{ $meta['label'] }}
                        </span>
                    </div>
                    <span class="text-xs font-mono font-bold" data-kanban-count style="color:var(--muted)">{{ $colTasks->count() }}</span>
                </div>

                <div class="flex flex-col gap-2" style="min-height:40px" data-kanban-list>
                @forelse($colTasks as $task)
                    @php
                        $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
                        if ($execList->isEmpty() && $task->executor) {
                            $execList = collect([$task->executor]);
                        }
                        $respList  = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
                        $statusUrl = $task->is_ticket ? route('tickets.update-status', $task) : route('tasks.update-status-direct', $task);
                    @endphp
                    <div class="card px-0 py-0 relative overflow-hidden"
                         data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $statusUrl }}"
                         style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}; cursor:pointer"
                         @click="selectMode
                             ? (selected.includes('{{ $task->id }}') ? selected = selected.filter(id => id !== '{{ $task->id }}') : selected.push('{{ $task->id }}'))
                             : (window.location = '{{ route('tasks.show', $task) }}')">

                        <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected"
                            x-show="selectMode" @click.stop
                            class="absolute top-2 right-2 z-10" style="width:14px; height:14px">

                        <div class="px-4 py-3">
                            {{-- Cliente --}}
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

                            {{-- Avatares: responsável (laranja) + executor (roxo) + aviso de imagem anexada --}}
                            @php $hasImage = $task->hasImageAttachment(); @endphp
                            @if($respList->isNotEmpty() || $execList->isNotEmpty() || $hasImage)
                                <div class="flex items-center gap-1 mb-2">
                                    @foreach($respList as $resp)
                                        <x-user-avatar :user="$resp" size="6" color="var(--orange)" title="{{ $resp->name }} (Responsável)" />
                                    @endforeach
                                    @foreach($execList as $exec)
                                        <x-user-avatar :user="$exec" size="6" color="var(--purple)" title="{{ $exec->name }} (Executor)" />
                                    @endforeach
                                    @if($hasImage)
                                        <span class="ml-auto" title="Tem imagem anexada" style="color:var(--muted)">
                                            <x-icon name="image" size="14" />
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Data --}}
                            <div class="mb-2">
                                <x-inline-date-cell :task="$task" field="due_date" :overdue="$task->isOverdue()" />
                            </div>

                            {{-- Mover status + remover da sprint --}}
                            <div class="flex items-center gap-1.5 pt-2 relative" style="border-top:1px solid var(--border2)" @click.stop>
                                {{-- Coluna lida na hora do clique (não no render): o card pode ter
                                     sido arrastado pra outra coluna depois que a aba carregou. --}}
                                <button type="button"
                                    @click.stop="moveUrl = '{{ $statusUrl }}'; moveFrom = $el.closest('[data-kanban-column]').dataset.status; moveStyle = dropdownStyle($el, 'top-left'); moveOpen = true"
                                    class="text-xs px-2 py-0.5 font-mono flex items-center gap-1"
                                    style="border:1px solid var(--border2); color:var(--muted)">
                                    Mover <span style="opacity:.7">▾</span>
                                </button>

                                @if($sprint->status !== 'closed' && !$sprint->isLocked())
                                    <form method="POST" action="{{ route('sprints.remove-task', [$sprint, $task]) }}"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover da sprint?')) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-xs">
                                            − Sprint
                                        </button>
                                    </form>
                                @endif
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

    {{-- Menu "Mover" compartilhado por todos os cartões --}}
    <template x-teleport="body">
        <div x-show="moveOpen" @click.outside="moveOpen = false" x-close-on-scroll="moveOpen" x-cloak
             class="rounded shadow-lg py-1"
             :style="moveStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
            @foreach(\App\Models\Task::$statuses as $targetKey => $targetMeta)
                <form method="POST" :action="moveUrl" x-show="moveFrom !== '{{ $targetKey }}'">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $targetKey }}">
                    <button type="submit"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:var(--text)"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style="background:{{ \App\Models\Task::colorHex($targetMeta['color']) }}"></span>
                        {{ $targetMeta['label'] }}
                    </button>
                </form>
            @endforeach
        </div>
    </template>
</div>
