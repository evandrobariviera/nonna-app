{{-- Fragmento da aba Board — carregado via fetch (SprintController::board()) só quando a
     aba é aberta. Herda selectMode/selected do x-data da página (sprints/show.blade.php).
     Um único menu "Mover" pro board inteiro (antes: 1 cópia escondida com 7 formulários
     por cartão — 1.144 formulários numa sprint de 162 tarefas). --}}
<div x-data="{ moveOpen: false, moveStyle: '', moveUrl: '', moveFrom: '' }">
    {{-- Quadro no padrão Mirbal (redesign etapa 3 — classes .kb-* em app.css): coluna com
         título e risquinho na cor do status; cartão com etiquetas no alto, título em até 2
         linhas, projeto em cinza, prazo, e no rodapé Mover/− Sprint + avatares sobrepostos. --}}
    <div id="sprint-board" class="kb-board" data-kanban-board data-status-field="status">

        @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
            @php $colTasks = $kanban[$statusKey]; @endphp
            <div class="kb-col" style="--kb-bar: {{ \App\Models\Task::colorHex($meta['color']) }}"
                 data-kanban-column data-status="{{ $statusKey }}">
                <div class="kb-col-head">
                    <h5 class="card-title truncate">{{ $meta['label'] }}</h5>
                    <span class="kb-count" data-kanban-count>{{ $colTasks->count() }}</span>
                </div>

                <div class="kb-list" data-kanban-list>
                @forelse($colTasks as $task)
                    @php
                        $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
                        if ($execList->isEmpty() && $task->executor) {
                            $execList = collect([$task->executor]);
                        }
                        $respList  = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
                        $statusUrl = $task->is_ticket ? route('tickets.update-status', $task) : route('tasks.update-status-direct', $task);
                        $hasImage  = $task->hasImageAttachment();
                    @endphp
                    <div class="kb-card {{ $task->isOverdue() ? 'kb-card-overdue' : '' }}"
                         data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $statusUrl }}"
                         style="cursor:pointer"
                         @click="selectMode
                             ? (selected.includes('{{ $task->id }}') ? selected = selected.filter(id => id !== '{{ $task->id }}') : selected.push('{{ $task->id }}'))
                             : (window.location = '{{ route('tasks.show', $task) }}')">

                        <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected"
                            x-show="selectMode" @click.stop
                            class="absolute top-3 right-3 z-10" style="width:14px; height:14px">

                        {{-- Tipo + cliente (+ Ticket) + pendência --}}
                        <div class="kb-card-top">
                            <x-icon-chip :icon="$task->typeIcon()" :color="$task->statusColor()" size="22" />
                            <span class="badge tag-purple truncate" style="max-width:160px" title="{{ $task->client?->displayName() }}">{{ $task->client?->displayName() ?? 'Sem cliente' }}</span>
                            @if($task->is_ticket)
                                <span class="badge tag-orange">Ticket</span>
                            @endif
                            <x-pendency-badge :task="$task" class="ml-auto" />
                        </div>

                        <p class="kb-card-title">{{ $task->title }}</p>
                        @if($task->project)
                            <p class="kb-card-desc">{{ $task->project->title }}</p>
                        @endif

                        <div class="mt-2.5">
                            <x-inline-date-cell :task="$task" field="due_date" :overdue="$task->isOverdue()" />
                        </div>

                        {{-- Rodapé: ações à esquerda, pessoas à direita --}}
                        <div class="kb-card-foot" @click.stop>
                            <div class="flex items-center gap-1.5">
                                {{-- Coluna lida na hora do clique (não no render): o card pode ter
                                     sido arrastado pra outra coluna depois que a aba carregou. --}}
                                <button type="button"
                                    @click.stop="moveUrl = '{{ $statusUrl }}'; moveFrom = $el.closest('[data-kanban-column]').dataset.status; moveStyle = dropdownStyle($el, 'top-left'); moveOpen = true"
                                    class="btn btn-ghost btn-xs">
                                    Mover <span style="opacity:.7">▾</span>
                                </button>

                                @if($sprint->status !== 'closed' && !$sprint->isLocked())
                                    <form method="POST" action="{{ route('sprints.remove-task', [$sprint, $task]) }}"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover da sprint?')) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-soft-red btn-xs" title="Remover da sprint">− Sprint</button>
                                    </form>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if($hasImage)
                                    <span title="Tem imagem anexada"><x-icon name="image" size="14" /></span>
                                @endif
                                {{-- Responsável (laranja) + executor (roxo) --}}
                                <div class="kb-avatars">
                                    @foreach($respList as $resp)
                                        <x-user-avatar :user="$resp" size="6" color="var(--orange)" title="{{ $resp->name }} (Responsável)" />
                                    @endforeach
                                    @foreach($execList as $exec)
                                        <x-user-avatar :user="$exec" size="6" color="var(--purple)" title="{{ $exec->name }} (Executor)" />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="kb-empty">Sem tarefas</div>
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
                        class="dd-hover w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:var(--text)">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style="background:{{ \App\Models\Task::colorHex($targetMeta['color']) }}"></span>
                        {{ $targetMeta['label'] }}
                    </button>
                </form>
            @endforeach
        </div>
    </template>
</div>
