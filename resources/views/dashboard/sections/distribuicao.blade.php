{{-- Cockpit de Distribuição (modo Distribuição) — dados em App\Services\Dashboard\DistributionCockpit.
     Esquerda: "A distribuir" (minhas ou sem Responsável, ainda sem executor ou sem data).
     Direita: grade Pessoa × Dia com a carga REAL de cada pessoa (de qualquer Head), e quantas
     daquelas são do Head logado. Arrastar uma tarefa pra uma célula define executor + data de
     aprovação de uma vez (resources/js/distribution-board.js, usando os mesmos endpoints da
     tarefa — trava de WIP, histórico e automações valem igual). No "Ver como" é só leitura. --}}
@php
    $d = $distribution;
    $canAct = ! $viewingAs;
    $teamOptions = $d['team']->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
    $todayStr = today()->toDateString();
@endphp

{{-- ── Números como Responsável ── --}}
<div class="grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr))">
    @foreach([
        ['abertas', 'Minhas abertas', 'var(--purple)', 'como Responsável'],
        ['sem_executor', 'Sem executor', 'var(--orange)', 'ninguém vai fazer'],
        ['sem_data', 'Sem data', 'var(--orange)', 'ninguém sabe quando'],
        ['atrasadas', 'Atrasadas', 'var(--red)', 'aprovação já passou'],
        ['revisao', 'Revisão interna', 'var(--blue)', 'esperando você'],
    ] as [$key, $label, $color, $hint])
        <div class="card px-4 py-3" style="border-top:3px solid {{ $color }}">
            <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
            <p class="text-2xl font-black mt-1" style="color:{{ $d['numbers'][$key] > 0 && $key !== 'abertas' ? $color : 'var(--text)' }}">{{ $d['numbers'][$key] }}</p>
            <p class="text-xs" style="color:var(--muted2)">{{ $hint }}</p>
        </div>
    @endforeach
</div>

<div id="distribution-board" class="grid gap-4 mb-6 lg:grid-cols-[320px_1fr] items-start"
     x-data="{ openCell: null, assigning: null }">

    {{-- ── A DISTRIBUIR ── --}}
    <div class="card px-4 py-4">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                <x-icon name="inbox" size="14" /> A distribuir ({{ $d['toDistributeCount'] }})
            </h3>
        </div>
        <p class="text-xs mb-3" style="color:var(--muted2)">
            Suas ou sem Responsável, ainda sem executor ou sem data.
            @if($canAct)<span class="hidden md:inline">Arraste pra uma célula da grade.</span>@endif
        </p>
        <div class="flex flex-col gap-2" style="max-height:640px; overflow-y:auto" data-dist-source>
            @forelse($d['toDistribute'] as $task)
                @php
                    $execIds = $task->executors->filter(fn ($u) => $u->pivot->role === 'executor')->pluck('id');
                    $currentExecutor = $execIds->first() ?? $task->executor_id;
                    $execName = $task->executors->firstWhere('id', $currentExecutor)?->name ?? $task->executor?->name;
                    $noResp = $task->responsibles->isEmpty();
                @endphp
                <div class="px-3 py-2.5" style="background:var(--s2); border:1px solid var(--border2); {{ $canAct ? 'cursor:grab' : '' }}"
                     data-dist-task
                     data-executor-url="{{ route('tasks.update-executor', $task) }}"
                     data-date-url="{{ route('tasks.update-approval-date-direct', $task) }}"
                     data-current-executor="{{ $currentExecutor }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-mono truncate" style="color:var(--purple)">{{ $task->client?->displayName() ?? 'Interno' }}</p>
                        @if($noResp)
                            <span class="text-xs font-mono flex-shrink-0" style="color:var(--orange)">sem Responsável</span>
                        @endif
                    </div>
                    <p class="text-sm font-semibold leading-snug mt-1" style="color:var(--text); cursor:pointer"
                       @click="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">{{ $task->title }}</p>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs font-mono">
                        <span class="badge badge-{{ $task->statusColor() }}" style="font-size:8px; padding:1px 5px">{{ $task->statusLabel() }}</span>
                        <span style="color:{{ $execName ? 'var(--muted)' : 'var(--orange)' }}">{{ $execName ? explode(' ', $execName)[0] : 'sem executor' }}</span>
                        <span style="color:{{ $task->approval_date ? ($task->approval_date->lt(today()) ? 'var(--red)' : 'var(--muted)') : 'var(--orange)' }}">
                            {{ $task->approval_date?->format('d/m') ?? 'sem data' }}
                        </span>
                        @if($canAct)
                            <button type="button" class="ml-auto text-xs font-semibold" style="color:var(--purple)"
                                    @click="assigning = assigning === '{{ $task->id }}' ? null : '{{ $task->id }}'">Atribuir</button>
                        @endif
                    </div>

                    {{-- Atribuir sem arrastar (celular, ou quando a pessoa/dia nem aparecem na grade) --}}
                    @if($canAct)
                        <div x-show="assigning === '{{ $task->id }}'" x-cloak class="mt-2 flex flex-col gap-1.5"
                             x-data="{ userId: '{{ $currentExecutor }}', date: '{{ $task->approval_date?->toDateString() ?? $todayStr }}', saving: false }">
                            <select x-model="userId" class="px-2 py-1 text-xs" style="background:var(--s1); border:1px solid var(--border2); color:var(--text)">
                                <option value="">Escolha quem faz</option>
                                @foreach($teamOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                @endforeach
                            </select>
                            <div class="flex gap-1.5">
                                <input type="date" x-model="date" class="flex-1 px-2 py-1 text-xs" style="background:var(--s1); border:1px solid var(--border2); color:var(--text)">
                                <button type="button" class="btn btn-primary btn-xs" :disabled="!userId || !date || saving"
                                        @click="saving = true; window.distAssign($el.closest('[data-dist-task]'), userId, date).finally(() => saving = false)">
                                    Salvar
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-xs flex items-center gap-1.5 py-2" style="color:var(--muted)">
                    <x-icon name="party-popper" size="13" /> Tudo distribuído.
                </p>
            @endforelse
            @if($d['toDistributeCount'] > $d['toDistribute']->count())
                <p class="text-xs font-mono py-1" style="color:var(--muted)">
                    + {{ $d['toDistributeCount'] - $d['toDistribute']->count() }} — veja no Painel de Produção.
                </p>
            @endif
        </div>
    </div>

    {{-- ── GRADE PESSOA × DIA ── --}}
    <div class="card px-4 py-4 min-w-0">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                <x-icon name="users" size="14" /> Carga do time
                <span class="text-xs font-mono font-normal" style="color:var(--muted)">— total real de cada pessoa · <span style="color:var(--purple)">roxo = suas</span></span>
            </h3>
            <div class="flex items-center gap-1.5">
                <a href="{{ $dashUrl(['semana' => $d['weekOffset'] - 1]) }}" class="btn btn-ghost btn-xs">‹ Anterior</a>
                <span class="text-xs font-mono px-1" style="color:var(--text)">{{ $d['days'][0]->format('d/m') }} a {{ $d['days'][4]->format('d/m') }}</span>
                <a href="{{ $dashUrl(['semana' => $d['weekOffset'] + 1]) }}" class="btn btn-ghost btn-xs">Próxima ›</a>
                @if($d['weekOffset'] !== 0)
                    <a href="{{ $dashUrl() }}" class="btn btn-ghost btn-xs">Hoje</a>
                @endif
            </div>
        </div>

        @if($d['team']->isEmpty())
            <p class="text-xs py-3" style="color:var(--muted)">
                Ninguém no seu time ainda. O time vem dos seus <strong>Setores</strong> (Configurações → Setores)
                e de quem executa tarefas em que você é Responsável.
            </p>
        @else
            <div style="overflow-x:auto">
                <table class="w-full text-xs" style="border-collapse:separate; border-spacing:4px; min-width:640px">
                    <thead>
                        <tr>
                            <th class="text-left font-mono uppercase tracking-widest px-2" style="color:var(--muted); font-weight:600">Pessoa</th>
                            <th class="font-mono uppercase tracking-widest px-1" style="color:var(--red); font-weight:600" title="Abertas com data de aprovação antes desta semana">Atrasadas</th>
                            @foreach($d['days'] as $day)
                                @php $isToday = $day->isToday(); @endphp
                                <th class="font-mono uppercase tracking-widest px-1" style="color:{{ $isToday ? 'var(--green)' : 'var(--purple)' }}; font-weight:700">
                                    {{ ucfirst($day->translatedFormat('D')) }} {{ $day->format('d/m') }}
                                </th>
                            @endforeach
                            <th class="font-mono uppercase tracking-widest px-1" style="color:var(--muted); font-weight:600">Semana</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($d['grid'] as $row)
                            @php $uid = $row['user']->id; @endphp
                            <tr>
                                <td class="px-2 py-1 font-semibold whitespace-nowrap" style="color:var(--text)">{{ $row['user']->name }}</td>

                                {{-- Atrasadas: não aceita soltar (não faz sentido marcar data no passado) --}}
                                @php $c = $row['overdue']; $key = "{$uid}-overdue"; @endphp
                                <td class="text-center py-2" style="background:{{ $c['total'] ? 'rgba(220,38,38,.10)' : 'var(--s2)' }}; {{ $c['total'] ? 'cursor:pointer' : '' }}"
                                    @if($c['total']) @click="openCell = openCell === '{{ $key }}' ? null : '{{ $key }}'" @endif>
                                    <span class="text-base font-black" style="color:{{ $c['total'] ? 'var(--red)' : 'var(--muted)' }}">{{ $c['total'] ?: '·' }}</span>
                                </td>

                                @foreach($row['days'] as $date => $c)
                                    @php
                                        $key = "{$uid}-{$date}";
                                        $alpha = $c['total'] ? round(0.08 + 0.42 * $c['total'] / $d['maxCell'], 2) : 0;
                                        $isToday = $date === $todayStr;
                                    @endphp
                                    <td class="text-center py-2 align-middle" style="min-width:70px; background:{{ $c['total'] ? "rgba(100,59,142,{$alpha})" : 'var(--s2)' }}; {{ $isToday ? 'outline:2px solid var(--green); outline-offset:-2px;' : '' }} {{ $c['total'] ? 'cursor:pointer' : '' }}"
                                        @if($c['total']) @click="openCell = openCell === '{{ $key }}' ? null : '{{ $key }}'" @endif
                                        @if($canAct) data-dist-cell data-user-id="{{ $uid }}" data-date="{{ $date }}" @endif>
                                        <span class="text-base font-black" style="color:{{ $c['total'] ? 'var(--text)' : 'var(--muted)' }}">{{ $c['total'] ?: '·' }}</span>
                                        @if($c['mine'] && $c['mine'] < $c['total'])
                                            <span class="block font-mono" style="color:var(--purple); font-size:10px">{{ $c['mine'] }} suas</span>
                                        @elseif($c['mine'])
                                            <span class="block font-mono" style="color:var(--purple); font-size:10px">todas suas</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="text-center py-2 font-mono font-bold" style="color:var(--muted2)">{{ $row['week_total'] }}</td>
                            </tr>

                            {{-- Detalhe da célula clicada (uma linha inteira abaixo da pessoa) --}}
                            @foreach(array_merge(['overdue' => $row['overdue']], $row['days']) as $date => $c)
                                @continue(! $c['total'])
                                <tr x-show="openCell === '{{ $uid }}-{{ $date }}'" x-cloak>
                                    <td colspan="{{ count($d['days']) + 3 }}" class="px-2 pb-2">
                                        <div class="flex flex-col gap-1.5 p-2" style="background:var(--s1); border:1px solid var(--border2)">
                                            <p class="text-xs font-mono" style="color:var(--muted)">
                                                {{ $row['user']->name }} · {{ $date === 'overdue' ? 'atrasadas' : \Carbon\Carbon::parse($date)->translatedFormat('D d/m') }} · {{ $c['total'] }} tarefa(s)
                                            </p>
                                            @foreach($c['tasks'] as $task)
                                                @php $isMine = $task->responsibles->contains('id', (int) $subjectUserId); @endphp
                                                <button type="button" class="flex items-center gap-2 px-2 py-1.5 text-left text-xs"
                                                        style="background:var(--s2); {{ $isMine ? 'border-left:3px solid var(--purple)' : 'border-left:3px solid var(--border2)' }}; {{ $task->status === 'concluido' ? 'opacity:.55' : '' }}"
                                                        @click="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">
                                                    <span class="badge badge-{{ $task->statusColor() }}" style="font-size:8px; padding:1px 5px">{{ $task->statusLabel() }}</span>
                                                    <span class="font-semibold truncate" style="color:var(--text)">{{ $task->title }}</span>
                                                    <span class="truncate" style="color:var(--muted)">{{ $task->client?->displayName() }}</span>
                                                    @if($date === 'overdue')
                                                        <span class="font-mono" style="color:var(--red)">{{ $task->approval_date->format('d/m') }}</span>
                                                    @endif
                                                    <span class="ml-auto font-mono flex-shrink-0" style="color:{{ $isMine ? 'var(--purple)' : 'var(--muted)' }}">
                                                        {{ $isMine ? 'sua' : ($task->responsibles->first()?->name ? explode(' ', $task->responsibles->first()->name)[0] : 'sem Resp.') }}
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex justify-end mt-3">
            <a href="{{ route('production-panel.index', ['responsavel' => $subjectUserId]) }}" class="text-xs font-mono" style="color:var(--purple)">
                Abrir no Painel de Produção, filtrado nas suas →
            </a>
        </div>
    </div>
</div>

@if($canAct)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.matchMedia('(min-width: 768px)').matches) {
                window.initDistributionBoard('#distribution-board');
            }
        });
    </script>
    @endpush
@endif
