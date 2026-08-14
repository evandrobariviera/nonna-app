<x-app-layout>
    <x-slot name="header">{{ $sprint->title }}</x-slot>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(220,38,38,.08); border:1px solid rgba(220,38,38,.25); color:var(--red)">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- HEADER DA SPRINT --}}
    <div x-data="{ editing: {{ $errors->hasAny(['title','starts_at','ends_at','status']) ? 'true' : 'false' }} }" class="mb-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <template x-if="!editing">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="badge badge-{{ $sprint->statusColor() }}">{{ $sprint->statusLabel() }}</span>
                    <span class="text-xs font-mono" style="color:var(--muted)">
                        {{ $sprint->starts_at->format('d/m/Y') }} → {{ $sprint->ends_at->format('d/m/Y') }}
                    </span>
                    @if($sprint->isLocked())
                        <span class="text-xs font-mono" style="color:var(--purple)">
                            🔒 Travada por {{ $sprint->lockedBy?->name }} em {{ $sprint->locked_at->format('d/m H:i') }}
                        </span>
                    @endif
                </div>
            </template>

            {{-- Ações da sprint --}}
            <div class="flex items-center gap-2" x-show="!editing">
                <button type="button" @click="editing = true" class="btn btn-ghost btn-sm">
                    ✎ Editar
                </button>
                @if($sprint->status === 'planning')
                    <form method="POST" action="{{ route('sprints.lock', $sprint) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Travar sprint e iniciar execução?')) $el.submit()">
                        @csrf
                        <button type="submit"
                            class="btn btn-sm" style="background:var(--orange); color:#fff; border-color:var(--orange)">
                            🔒 Travar Sprint
                        </button>
                    </form>
                @elseif($sprint->status === 'active')
                    <form method="POST" action="{{ route('sprints.unlock', $sprint) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Reabrir sprint para planejamento?')) $el.submit()">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">
                            Reabrir
                        </button>
                    </form>
                    @php $pendingCount = $sprint->tasks->whereNotIn('status', ['concluido', 'cancelado'])->count(); @endphp
                    @if($pendingCount > 0)
                        <form method="POST" action="{{ route('sprints.move-incomplete', $sprint) }}"
                              @submit.prevent="if (await $store.confirmDialog.ask('Mover {{ $pendingCount }} tarefa(s) pendente(s) para a próxima Sprint (em Planejamento)?')) $el.submit()">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="border-color:var(--orange); color:var(--orange)">
                                Mover Pendentes pra Próxima Sprint ({{ $pendingCount }})
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('sprints.close', $sprint) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Encerrar sprint?')) $el.submit()">
                        @csrf
                        <button type="submit"
                            class="btn btn-sm" style="border-color:var(--green); color:var(--green)">
                            Encerrar Sprint
                        </button>
                    </form>
                @endif
                <a href="{{ route('sprints.index') }}" class="text-xs font-mono" style="color:var(--muted)">← Sprints</a>
            </div>
        </div>

        {{-- Formulário de edição --}}
        <template x-if="editing">
            <form method="POST" action="{{ route('sprints.update', $sprint) }}" class="card px-5 py-4 mt-3 flex items-end gap-3 flex-wrap">
                @csrf @method('PATCH')
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Título</label>
                    <input type="text" name="title" required value="{{ old('title', $sprint->title) }}"
                        class="w-full px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    @error('title') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Início</label>
                    <input type="date" name="starts_at" required value="{{ old('starts_at', $sprint->starts_at->format('Y-m-d')) }}"
                        class="px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    @error('starts_at') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Fim</label>
                    <input type="date" name="ends_at" required value="{{ old('ends_at', $sprint->ends_at->format('Y-m-d')) }}"
                        class="px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    @error('ends_at') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                    <select name="status" class="px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach(\App\Models\Sprint::$statuses as $key => $s)
                            <option value="{{ $key }}" {{ old('status', $sprint->status) === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                    style="background:var(--purple)">
                    Salvar
                </button>
                <button type="button" @click="editing = false"
                    class="px-4 py-2 text-xs font-mono" style="color:var(--muted)">
                    Cancelar
                </button>
            </form>
        </template>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
            {{ session('error') }}
        </div>
    @endif

    {{-- STATS --}}
    @php
        $total     = $sprint->tasks->whereNotIn('status', ['cancelado'])->count();
        $done      = $sprint->tasks->where('status', 'concluido')->count();
        $progress  = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        $statusCounts = collect(\App\Models\Task::$statuses)
            ->map(fn ($meta, $key) => $kanban[$key]->count());
    @endphp
    <div class="card px-5 py-4 mb-5">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Distribuição por Status</span>
            <span id="sprint-progress-pct" class="text-sm font-black" style="color:var(--text)">{{ $progress }}% concluído</span>
        </div>
        <x-status-distribution-bar id="sprint-progress-bar" :counts="$statusCounts" :total="$total" class="rounded-full mb-1" />
        <p id="sprint-progress-text" class="text-xs mt-1 font-mono mb-3" style="color:var(--muted)">{{ $done }} / {{ $total }} concluídas</p>
        <div class="flex items-center gap-8 flex-wrap">
            @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
                @php $cnt = $kanban[$statusKey]->count(); @endphp
                <div class="text-center">
                    <div class="text-lg font-black" data-stat-count="{{ $statusKey }}" style="color:var(--{{ $meta['color'] === 'muted' ? 'muted2' : $meta['color'] }})">{{ $cnt }}</div>
                    <div class="text-xs font-mono" style="color:var(--muted)">{{ $meta['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div x-data="{ tab: '{{ request('view', 'list') }}', clientFilter: '', selectMode: false, ...taskBulk() }" x-cloak>

        {{-- TABS --}}
        <div class="flex items-center gap-1 mb-5" style="border-bottom:1px solid var(--border2)">
            <button @click="tab = 'board'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'board' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Board
            </button>
            <button @click="tab = 'list'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'list' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Lista
            </button>
            <button @click="tab = 'planning'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'planning' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'"
                x-show="{{ $sprint->status !== 'closed' ? 'true' : 'false' }}">
                Planejamento
                @if($backlogTasks->count() > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full font-bold"
                          style="background:var(--s3); color:var(--orange)">{{ $backlogTasks->count() }}</span>
                @endif
            </button>

            <button @click="selectMode = !selectMode; if (!selectMode) selected = []"
                x-show="tab === 'board'"
                class="ml-auto px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="selectMode ? 'color:var(--purple)' : 'color:var(--muted)'">
                <span x-text="selectMode ? '✕ Cancelar seleção' : '☑ Selecionar'"></span>
            </button>
        </div>

        @include('partials._task-bulk-bar')

        {{-- ── TAB BOARD ── --}}
        <div x-show="tab === 'board'" x-cloak>
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
                                $respList   = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
                                $statusUrl  = $task->is_ticket ? route('tickets.update-status', $task) : route('tasks.update-status-direct', $task);
                                $thumbUrl   = $task->firstImageAttachmentUrl();
                            @endphp
                            <div class="card px-0 py-0 relative overflow-hidden" x-data="{ statusOpen: false, moveStyle: '' }"
                                 data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $statusUrl }}"
                                 style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}; cursor:pointer"
                                 @click="selectMode
                                     ? (selected.includes('{{ $task->id }}') ? selected = selected.filter(id => id !== '{{ $task->id }}') : selected.push('{{ $task->id }}'))
                                     : (window.location = '{{ route('tasks.show', $task) }}')">

                                <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected"
                                    x-show="selectMode" @click.stop
                                    class="absolute top-2 right-2 z-10" style="width:14px; height:14px">

                                @if($thumbUrl)
                                    <img src="{{ $thumbUrl }}" alt="" class="w-full object-cover" style="height:80px">
                                @endif

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

                                    {{-- Avatares: responsável (laranja) + executor (roxo) --}}
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

                                    {{-- Data --}}
                                    @if($task->due_date)
                                        <p class="text-xs font-mono mb-2"
                                           style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            {{ $task->due_date->format('d/m') }}
                                        </p>
                                    @endif

                                    {{-- Mover status + remover da sprint --}}
                                    <div class="flex items-center gap-1.5 pt-2 relative" style="border-top:1px solid var(--border2)" @click.stop>
                                        <button @click="statusOpen = !statusOpen; moveStyle = dropdownStyle($el, 'top-left')" @click.stop type="button"
                                            class="text-xs px-2 py-0.5 font-mono flex items-center gap-1"
                                            style="border:1px solid var(--border2); color:var(--muted)">
                                            Mover <span style="opacity:.7">▾</span>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="statusOpen" @click.outside="statusOpen = false" x-close-on-scroll="statusOpen" x-cloak
                                                 class="rounded shadow-lg py-1"
                                                 :style="moveStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
                                                @foreach(\App\Models\Task::$statuses as $targetKey => $targetMeta)
                                                    @if($targetKey !== $statusKey)
                                                        <form method="POST" action="{{ $statusUrl }}">
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
                                                    @endif
                                                @endforeach
                                            </div>
                                        </template>

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
        </div>

        {{-- ── TAB LISTA ── --}}
        <div x-show="tab === 'list'" x-cloak>

            {{-- Filtros — exatamente os mesmos da Fila (ver partials/_task-filter-bar.blade.php) --}}
            @include('partials._task-filter-bar', [
                'formAction'    => route('sprints.show', $sprint),
                'prefix'        => 'list_',
                'extraHidden'   => ['view' => 'list'],
                'clients'       => $clients,
                'projects'      => $projects,
                'users'         => $users,
                'groupBy'       => $listGroupBy,
                'clearUrl'      => route('sprints.show', ['sprint' => $sprint, 'view' => 'list', 'list_group_by' => $listGroupBy]),
                'resultsUrl'    => route('sprints.list-results', $sprint),
                'resultsTarget' => '#sprint-list-results',
            ])

            <div id="sprint-list-results">
                @include('sprints._list-results')
            </div>
            <x-badge-fill-menu />
            <x-person-fill-menu :users="$users" />
        </div>

        {{-- ── TAB PLANEJAMENTO ── --}}
        <div x-show="tab === 'planning'" x-cloak>

            @if($sprint->status === 'closed')
                <p class="text-sm font-mono" style="color:var(--muted)">Sprint encerrada — planejamento bloqueado.</p>
            @else
                {{-- Filtro por cliente --}}
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Filtrar:</span>
                    <select x-model="clientFilter"
                        class="px-3 py-1.5 text-xs focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <option value="">Todos os clientes</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs font-mono" style="color:var(--muted)">
                        {{ $backlogTasks->count() }} tarefa{{ $backlogTasks->count() !== 1 ? 's' : '' }} no backlog
                    </span>
                </div>

                @forelse($backlogTasks as $task)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 mb-2"
                         style="background:var(--s2); border:1px solid var(--border2)"
                         x-show="clientFilter === '' || clientFilter === '{{ $task->client_id }}'">

                        <input type="checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}" x-model="selected"
                            style="width:14px; height:14px; flex-shrink:0">

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono font-bold" style="color:var(--purple)">
                                    {{ $task->client?->displayName() ?? '—' }}
                                </span>
                                @if($task->project)
                                    <span class="text-xs font-mono" style="color:var(--muted)">/ {{ $task->project->title }}</span>
                                @elseif($task->is_ticket)
                                    <span class="badge badge-orange" style="font-size:10px">Ticket</span>
                                @endif
                            </div>
                            <p class="text-xs font-semibold mt-0.5 leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <x-task-type-badge :task="$task" class="text-xs" />
                                @if($task->executor)
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ explode(' ', $task->executor->name)[0] }}</span>
                                @endif
                                @if($task->due_date)
                                    <span class="text-xs font-mono" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                        {{ $task->due_date->format('d/m') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('sprints.add-task', [$sprint, $task]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-xs flex-shrink-0">
                                + Sprint
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center" style="border:1px dashed var(--border2)">
                        <p class="text-sm font-mono" style="color:var(--muted)">Backlog vazio — todas as tarefas estão em sprints ou concluídas.</p>
                    </div>
                @endforelse
            @endif
        </div>

    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var board = document.getElementById('sprint-board');
            if (!board) return;

            initKanbanDnd('#sprint-board');

            board.addEventListener('kanban:moved', function (evt) {
                var fromStatus = evt.detail.fromColumn.dataset.status;
                var toStatus = evt.detail.toColumn.dataset.status;
                var fromStat = document.querySelector('[data-stat-count="' + fromStatus + '"]');
                var toStat = document.querySelector('[data-stat-count="' + toStatus + '"]');
                if (fromStat) fromStat.textContent = String(parseInt(fromStat.textContent, 10) - 1);
                if (toStat) toStat.textContent = String(parseInt(toStat.textContent, 10) + 1);

                var total = 0, done = 0, counts = {};
                document.querySelectorAll('[data-stat-count]').forEach(function (el) {
                    var status = el.dataset.statCount;
                    var n = parseInt(el.textContent, 10) || 0;
                    counts[status] = n;
                    if (status !== 'cancelado') total += n;
                    if (status === 'concluido') done = n;
                });
                var pct = total > 0 ? Math.round((done / total) * 100) : 0;

                document.getElementById('sprint-progress-pct').textContent = pct + '% concluído';
                document.getElementById('sprint-progress-text').textContent = done + ' / ' + total + ' concluídas';
                document.querySelectorAll('#sprint-progress-bar [data-status-segment]').forEach(function (seg) {
                    var n = counts[seg.dataset.statusSegment] || 0;
                    seg.style.width = (total > 0 ? (n / total * 100) : 0) + '%';
                });
            });
        });
    </script>
    @endpush

</x-app-layout>
