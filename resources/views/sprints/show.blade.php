<x-app-layout>
    <x-slot name="header">{{ $sprint->title }}</x-slot>

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
            <div class="flex flex-wrap items-center gap-2" x-show="!editing">
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
                {{-- Sem campo de título: o nome é derivado do número + período
                     (ver Sprint::buildTitle) e se reajusta sozinho ao salvar. --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Início</label>
                    <input type="date" name="starts_at" required value="{{ old('starts_at', $sprint->starts_at->format('Y-m-d')) }}"
                        class="field px-3 py-2 text-sm focus:outline-none">
                    @error('starts_at') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Fim</label>
                    <input type="date" name="ends_at" required value="{{ old('ends_at', $sprint->ends_at->format('Y-m-d')) }}"
                        class="field px-3 py-2 text-sm focus:outline-none">
                    @error('ends_at') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                    <select name="status" class="field px-3 py-2 text-sm focus:outline-none">
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
            @foreach(array_reverse(\App\Models\Task::$statuses, true) as $statusKey => $meta)
                @php
                    $cnt = $kanban[$statusKey]->count();
                    // % sobre o total (sem cancelado) — cancelado em si não entra no total,
                    // então seu tile mostra 0% mesmo tendo tarefas (é informativo, não faz
                    // parte da distribuição de carga da sprint).
                    $pct = ($total > 0 && $statusKey !== 'cancelado') ? round($cnt / $total * 100) : 0;
                @endphp
                <div class="text-center">
                    <div class="text-lg font-black" data-stat-count="{{ $statusKey }}" data-count="{{ $cnt }}" style="color:var(--{{ $meta['color'] === 'muted' ? 'muted2' : $meta['color'] }})">{{ $pct }}%</div>
                    <div class="text-xs font-mono" style="color:var(--muted)">{{ $meta['label'] }} · {{ $cnt }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- A aba aberta vai pra URL (?view=) — recarregar ou voltar de um "Mover"/"+ Sprint"
         cai na mesma aba, em vez de sempre voltar pra Lista. --}}
    <div x-data="{ tab: '{{ request('view', 'list') }}', clientFilter: '', selectMode: false, ...taskBulk() }"
         x-init="$watch('tab', v => { const u = new URL(location.href); u.searchParams.set('view', v); history.replaceState(null, '', u.toString()) })"
         x-cloak>

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
                @if($backlogCount > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full font-bold"
                          style="background:var(--s3); color:var(--orange)">{{ $backlogCount }}</span>
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
        {{-- Board e Planejamento só são montados quando a aba é aberta (fetch do fragmento) —
             antes a página trazia as 3 abas de uma vez: ~3 MB de HTML + 428 MB de imagens
             escondidas numa sprint de 162 tarefas, e o navegador travava. --}}
        <div x-show="tab === 'board'" x-cloak
             data-lazy-tab data-url="{{ route('sprints.board', $sprint) }}"
             x-effect="if (tab === 'board') loadSprintTab($el)">
            @include('sprints._tab-loading')
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
        <div x-show="tab === 'planning'" x-cloak
             data-lazy-tab data-url="{{ route('sprints.planning', $sprint) }}"
             x-effect="if (tab === 'planning') loadSprintTab($el)">
            @include('sprints._tab-loading')
        </div>

    </div>

    @push('scripts')
    <script>
        // Carrega o fragmento de uma aba (Board/Planejamento) na primeira vez que ela é aberta.
        // O HTML entra dentro do x-data da página, então o Alpine inicializa sozinho e os
        // cartões enxergam selectMode/selected/clientFilter normalmente.
        window.loadSprintTab = function (el) {
            if (el.dataset.loaded) return;
            el.dataset.loaded = '1';

            fetch(el.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) throw res;
                    return res.text();
                })
                .then(function (html) {
                    el.innerHTML = html;
                    if (el.querySelector('#sprint-board')) initSprintBoard();
                })
                .catch(function () {
                    delete el.dataset.loaded;
                    el.innerHTML = '<div class="px-6 py-10 text-center text-sm font-mono" style="border:1px dashed var(--border2); color:var(--red)">'
                        + 'Não foi possível carregar. <button type="button" class="underline" data-retry>Tentar de novo</button></div>';
                    el.querySelector('[data-retry]').addEventListener('click', function () { window.loadSprintTab(el); });
                });
        };

        function initSprintBoard() {
            var board = document.getElementById('sprint-board');
            if (!board) return;

            initKanbanDnd('#sprint-board');

            board.addEventListener('kanban:moved', function (evt) {
                var fromStatus = evt.detail.fromColumn.dataset.status;
                var toStatus = evt.detail.toColumn.dataset.status;
                var fromStat = document.querySelector('[data-stat-count="' + fromStatus + '"]');
                var toStat = document.querySelector('[data-stat-count="' + toStatus + '"]');
                if (fromStat) fromStat.dataset.count = String(parseInt(fromStat.dataset.count, 10) - 1);
                if (toStat) toStat.dataset.count = String(parseInt(toStat.dataset.count, 10) + 1);

                var total = 0, done = 0, counts = {};
                document.querySelectorAll('[data-stat-count]').forEach(function (el) {
                    var status = el.dataset.statCount;
                    var n = parseInt(el.dataset.count, 10) || 0;
                    counts[status] = n;
                    if (status !== 'cancelado') total += n;
                    if (status === 'concluido') done = n;
                });
                var pct = total > 0 ? Math.round((done / total) * 100) : 0;

                document.querySelectorAll('[data-stat-count]').forEach(function (el) {
                    var status = el.dataset.statCount;
                    var n = counts[status];
                    var tilePct = (total > 0 && status !== 'cancelado') ? Math.round(n / total * 100) : 0;
                    el.textContent = tilePct + '%';
                    var label = el.parentElement.querySelector('.text-xs');
                    if (label) label.textContent = label.textContent.replace(/·\s*\d+$/, '· ' + n);
                });

                document.getElementById('sprint-progress-pct').textContent = pct + '% concluído';
                document.getElementById('sprint-progress-text').textContent = done + ' / ' + total + ' concluídas';
                document.querySelectorAll('#sprint-progress-bar [data-status-segment]').forEach(function (seg) {
                    var n = counts[seg.dataset.statusSegment] || 0;
                    seg.style.width = (total > 0 ? (n / total * 100) : 0) + '%';
                });
            });
        }
    </script>
    @endpush

</x-app-layout>
