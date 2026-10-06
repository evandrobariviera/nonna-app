{{-- Cockpit de Distribuição (modo Distribuição) — dados em App\Services\Dashboard\DistributionCockpit.
     Grade Pessoa × Dia com a carga REAL de cada pessoa (de qualquer Head) e quantas são do Head
     logado; "A distribuir" com as tarefas do Head ainda sem executor ou sem data.

     Mover é "clique pra mover", uma tarefa por vez (pedido do usuário — arrastar de longe até
     uma célula pequena era confuso e não funcionava no celular): clica na célula → clica na
     tarefa → clica na célula de destino. Isso troca executor + data de aprovação pelos mesmos
     endpoints da página da tarefa (window.distAssign, resources/js/distribution-board.js), então
     trava de WIP, histórico e automações valem igual. Arrastar da caixa "A distribuir" continua
     funcionando no desktop. No "Ver como" é só leitura. --}}
@php
    $d = $distribution;
    $canAct = ! $viewingAs;
    $todayStr = today()->toDateString();
    $hasInbox = $d['toDistribute']->isNotEmpty();

    // O que o JS precisa pra mover uma tarefa (mesmas chaves do dataset dos cards arrastáveis).
    $movePayload = function ($task) {
        $execIds = $task->executors->filter(fn ($u) => $u->pivot->role === 'executor')->pluck('id');
        return [
            'id'              => $task->id,
            'title'           => $task->title,
            'executorUrl'     => route('tasks.update-executor', $task),
            'dateUrl'         => route('tasks.update-approval-date-direct', $task),
            'currentExecutor' => (string) ($execIds->first() ?? $task->executor_id ?? ''),
            'date'            => $task->approval_date?->toDateString(),
        ];
    };
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

<div id="distribution-board" class="mb-6"
     x-data="{
        openCell: null,
        moving: null,
        saving: false,
        toggle(key) { this.openCell = this.openCell === key ? null : key },
        pick(task) { this.moving = (this.moving && this.moving.id === task.id) ? null : task },
        place(userId, date) {
            if (!this.moving || this.saving) return;
            if (String(this.moving.currentExecutor) === String(userId) && this.moving.date === date) { this.moving = null; return; }
            this.saving = true;
            window.distAssign(this.moving, userId, date).finally(() => { this.saving = false });
        },
     }"
     @keydown.escape.window="moving = null">

    {{-- Aviso do "modo mover" — fica grudado no topo enquanto a pessoa escolhe o destino. --}}
    @if($canAct)
        <div x-show="moving" x-cloak class="sticky top-2 z-20 mb-3 flex items-center gap-3 px-4 py-3"
             style="background:var(--purple); color:#fff; box-shadow:0 4px 16px rgba(0,0,0,.18)">
            <x-icon name="move" size="16" />
            <p class="text-sm flex-1 min-w-0">
                <span x-show="!saving">Movendo <strong x-text="moving?.title"></strong> — clique na célula da pessoa e do dia de destino.</span>
                <span x-show="saving">Salvando…</span>
            </p>
            <button type="button" class="text-xs font-semibold px-2.5 py-1" style="background:rgba(255,255,255,.18)" @click="moving = null">Cancelar (Esc)</button>
        </div>
    @endif

    <div class="grid gap-4 items-start {{ $hasInbox ? 'lg:grid-cols-[300px_1fr]' : '' }}">

        {{-- ── A DISTRIBUIR (só aparece como coluna quando tem algo) ── --}}
        @if($hasInbox)
            <div class="card px-4 py-4">
                <h3 class="text-sm font-bold flex items-center gap-1.5 mb-1" style="color:var(--text)">
                    <x-icon name="inbox" size="14" /> A distribuir ({{ $d['toDistributeCount'] }})
                </h3>
                <p class="text-xs mb-3" style="color:var(--muted2)">
                    Suas, ainda sem executor ou sem data.
                    @if($canAct) Clique numa e depois na célula de destino. @endif
                </p>
                <div class="flex flex-col gap-2" style="max-height:640px; overflow-y:auto" data-dist-source>
                    @foreach($d['toDistribute'] as $task)
                        @php
                            $p = $movePayload($task);
                            $execName = $task->executors->firstWhere('id', (int) $p['currentExecutor'])?->name ?? $task->executor?->name;
                        @endphp
                        <div class="px-3 py-2.5" style="background:var(--s2); border:1px solid var(--border2); {{ $canAct ? 'cursor:pointer' : '' }}"
                             :style="moving && moving.id === '{{ $task->id }}' ? { outline: '2px solid var(--purple)', background: 'rgba(100,59,142,.10)' } : {}"
                             @if($canAct) @click="pick(@js($p))" @endif
                             data-dist-task
                             data-executor-url="{{ $p['executorUrl'] }}"
                             data-date-url="{{ $p['dateUrl'] }}"
                             data-current-executor="{{ $p['currentExecutor'] }}">
                            <p class="text-xs font-mono truncate" style="color:var(--purple)">{{ $task->client?->displayName() ?? 'Interno' }}</p>
                            <p class="text-sm font-semibold leading-snug mt-1" style="color:var(--text)">{{ $task->title }}</p>
                            <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs font-mono">
                                <span class="badge badge-{{ $task->statusColor() }}" style="font-size:8px; padding:1px 5px">{{ $task->statusLabel() }}</span>
                                <span style="color:{{ $execName ? 'var(--muted)' : 'var(--orange)' }}">{{ $execName ? explode(' ', $execName)[0] : 'sem executor' }}</span>
                                <span style="color:{{ $task->approval_date ? 'var(--muted)' : 'var(--orange)' }}">{{ $task->approval_date?->format('d/m') ?? 'sem data' }}</span>
                                <button type="button" class="ml-auto" style="color:var(--muted)" title="Abrir tarefa"
                                        @click.stop="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">
                                    <x-icon name="external-link" size="13" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                    @if($d['toDistributeCount'] > $d['toDistribute']->count())
                        <p class="text-xs font-mono py-1" style="color:var(--muted)">
                            + {{ $d['toDistributeCount'] - $d['toDistribute']->count() }} — veja no Painel de Produção.
                        </p>
                    @endif
                </div>
            </div>
        @endif

        {{-- ── GRADE PESSOA × DIA ── --}}
        <div class="card px-4 py-4 min-w-0">
            <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                    <x-icon name="users" size="14" /> Carga do time
                    <span class="text-xs font-mono font-normal" style="color:var(--muted)">— tarefas reais de cada pessoa (e pontos) · <span style="color:var(--purple)">roxo = suas</span></span>
                </h3>
                @php
                    // Filtro de status só vai pra URL quando sai do padrão — link limpo no dia a dia.
                    $customLoad = $d['loadStatuses'] != \App\Services\Dashboard\DistributionCockpit::DEFAULT_LOAD_STATUSES;
                    $loadParam = $customLoad ? ['carga' => $d['loadStatuses']] : [];
                @endphp
                <div class="flex items-center gap-1.5">
                    <a href="{{ $dashUrl(['semana' => $d['weekOffset'] - 1] + $loadParam) }}" class="btn btn-ghost btn-xs">‹ Anterior</a>
                    <span class="text-xs font-mono px-1" style="color:var(--text)">{{ $d['days'][0]->format('d/m') }} a {{ $d['days'][4]->format('d/m') }}</span>
                    <a href="{{ $dashUrl(['semana' => $d['weekOffset'] + 1] + $loadParam) }}" class="btn btn-ghost btn-xs">Próxima ›</a>
                    @if($d['weekOffset'] !== 0)
                        <a href="{{ $dashUrl($loadParam) }}" class="btn btn-ghost btn-xs">Hoje</a>
                    @endif
                </div>
            </div>

            {{-- Status que contam como carga — padrão Backlog + Ajuste/Alteração (o que ainda vai
                 ocupar o executor). Revisão, aprovação e despacho já saíram da mão dele. --}}
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-1.5 mb-2">
                @if($viewingAs)
                    <input type="hidden" name="ver_como" value="{{ $viewingAs->id }}">
                    <input type="hidden" name="modo" value="{{ $mode }}">
                @endif
                @if($d['weekOffset'] !== 0)
                    <input type="hidden" name="semana" value="{{ $d['weekOffset'] }}">
                @endif
                <span class="text-xs font-mono uppercase tracking-widest mr-1" style="color:var(--muted)">Contar como carga:</span>
                @foreach(\App\Models\Task::$statuses as $s => $meta)
                    @continue($s === 'cancelado')
                    @php $on = in_array($s, $d['loadStatuses'], true); @endphp
                    <label class="flex items-center gap-1.5 text-xs cursor-pointer px-2 py-1"
                           style="border:1px solid {{ $on ? 'rgba(100,59,142,.4)' : 'var(--border2)' }}; border-radius:6px; background:{{ $on ? 'rgba(100,59,142,.08)' : 'transparent' }}; color:{{ $on ? 'var(--purple)' : 'var(--muted2)' }}">
                        <input type="checkbox" name="carga[]" value="{{ $s }}" @checked($on) onchange="this.form.submit()" style="accent-color:var(--purple)">
                        {{ $meta['label'] }}
                    </label>
                @endforeach
                @if($customLoad)
                    <a href="{{ $dashUrl($d['weekOffset'] !== 0 ? ['semana' => $d['weekOffset']] : []) }}" class="text-xs font-semibold ml-1" style="color:var(--purple)">voltar ao padrão</a>
                @endif
            </form>
            <p class="text-xs mb-3" style="color:var(--muted2)">
                @if(! $hasInbox)
                    <span style="color:var(--green)">✓ Nada seu pra distribuir.</span>
                @endif
                @if($canAct)
                    Pra mover: clique na célula, clique na tarefa e depois na célula de destino (pessoa e dia).
                @else
                    Clique numa célula pra ver as tarefas.
                @endif
            </p>

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
                                    <th class="font-mono uppercase tracking-widest px-1" style="color:{{ $day->isToday() ? 'var(--green)' : 'var(--purple)' }}; font-weight:700">
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

                                    {{-- Atrasadas: só origem (não dá pra mover PRA data no passado) --}}
                                    @php $c = $row['overdue']; $key = "{$uid}-overdue"; @endphp
                                    <td class="text-center py-2" style="background:{{ $c['total'] ? 'rgba(220,38,38,.10)' : 'var(--s2)' }}; {{ $c['total'] ? 'cursor:pointer' : '' }}"
                                        :style="moving ? { opacity: .35 } : (openCell === '{{ $key }}' ? { boxShadow: 'inset 0 0 0 2px var(--red)' } : {})"
                                        @if($c['total']) @click="moving ? null : toggle('{{ $key }}')" @endif>
                                        @if($c['total'])
                                            <span class="text-base font-black" style="color:var(--red)">{{ $c['total'] }}</span>
                                            <span class="block font-mono" style="color:var(--red); opacity:.7; font-size:10px">{{ $c['points'] }} pts</span>
                                        @else
                                            <span class="text-base font-black" style="color:var(--muted)">·</span>
                                        @endif
                                    </td>

                                    @foreach($row['days'] as $date => $c)
                                        @php
                                            $key = "{$uid}-{$date}";
                                            $alpha = $c['total'] ? round(0.08 + 0.42 * $c['total'] / $d['maxCell'], 2) : 0; // cor pela QUANTIDADE (pontos ficam discretos por enquanto)
                                        @endphp
                                        <td class="text-center py-2 align-middle" style="min-width:70px; background:{{ $c['total'] ? "rgba(100,59,142,{$alpha})" : 'var(--s2)' }}; {{ $date === $todayStr ? 'outline:2px solid var(--green); outline-offset:-2px;' : '' }} cursor:pointer"
                                            :style="moving ? { boxShadow: 'inset 0 0 0 2px var(--purple)', cursor: 'copy' } : (openCell === '{{ $key }}' ? { boxShadow: 'inset 0 0 0 2px var(--purple)' } : {})"
                                            @click="moving ? place({{ $uid }}, '{{ $date }}') : {{ $c['total'] ? "toggle('{$key}')" : 'null' }}"
                                            @if($canAct) data-dist-cell data-user-id="{{ $uid }}" data-date="{{ $date }}" @endif>
                                            {{-- Híbrido: quantidade em destaque, pontos discretos embaixo (pedido do usuário: ênfase na quantidade por enquanto) --}}
                                            @if($c['total'])
                                                <span class="text-base font-black" style="color:var(--text)">{{ $c['total'] }}</span>
                                                <span class="block font-mono" style="color:var(--muted); font-size:10px">{{ $c['points'] }} pts</span>
                                                @if($c['mine'])
                                                    <span class="block font-mono" style="color:var(--purple); font-size:10px">{{ $c['mine'] < $c['total'] ? $c['mine'].' suas' : 'todas suas' }}</span>
                                                @endif
                                            @else
                                                <span class="text-base font-black" style="color:var(--muted)">·</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="text-center py-2 font-mono" style="color:var(--muted2)">
                                        <span class="font-bold" style="color:var(--text)">{{ $row['week_total'] }} tarefa(s)</span>
                                        <span class="block" style="font-size:10px">{{ $row['week_points'] }} pts</span>
                                    </td>
                                </tr>

                                {{-- Tarefas da célula aberta — cada uma pode ser escolhida pra mover --}}
                                @foreach(array_merge(['overdue' => $row['overdue']], $row['days']) as $date => $c)
                                    @continue(! $c['total'])
                                    <tr x-show="openCell === '{{ $uid }}-{{ $date }}'" x-cloak>
                                        <td colspan="{{ count($d['days']) + 3 }}" class="px-2 pb-2">
                                            <div class="flex flex-col gap-1.5 p-2" style="background:var(--s1); border:1px solid var(--border2)">
                                                <p class="text-xs font-mono" style="color:var(--muted)">
                                                    {{ $row['user']->name }} · {{ $date === 'overdue' ? 'atrasadas' : \Carbon\Carbon::parse($date)->translatedFormat('D d/m') }} · {{ $c['points'] }} pts · {{ $c['total'] }} tarefa(s)
                                                    @if($canAct) — <span style="color:var(--purple)">clique numa tarefa pra mover</span> @endif
                                                </p>
                                                @foreach($c['tasks'] as $task)
                                                    @php
                                                        $isMine = $task->responsibles->contains('id', (int) $subjectUserId);
                                                        $done = $task->status === 'concluido';
                                                        $p = $movePayload($task);
                                                    @endphp
                                                    <div class="flex items-center gap-2 px-2 py-1.5 text-xs"
                                                         style="background:var(--s2); border-left:3px solid {{ $isMine ? 'var(--purple)' : 'var(--border2)' }}; {{ $done ? 'opacity:.55' : '' }} {{ $canAct && ! $done ? 'cursor:pointer' : '' }}"
                                                         :style="moving && moving.id === '{{ $task->id }}' ? { outline: '2px solid var(--purple)', background: 'rgba(100,59,142,.12)' } : {}"
                                                         @if($canAct && ! $done) @click="pick(@js($p))" @endif>
                                                        <span class="badge badge-{{ $task->statusColor() }}" style="font-size:8px; padding:1px 5px">{{ $task->statusLabel() }}</span>
                                                        <span class="font-mono flex-shrink-0" style="color:var(--muted)">{{ $task->sprint_points ?? 1 }}pt</span>
                                                        <span class="font-semibold truncate" style="color:var(--text)">{{ $task->title }}</span>
                                                        <span class="truncate" style="color:var(--muted)">{{ $task->client?->displayName() }}</span>
                                                        @if($date === 'overdue')
                                                            <span class="font-mono" style="color:var(--red)">{{ $task->approval_date->format('d/m') }}</span>
                                                        @endif
                                                        <span class="ml-auto font-mono flex-shrink-0" style="color:{{ $isMine ? 'var(--purple)' : 'var(--muted)' }}">
                                                            {{ $isMine ? 'sua' : ($task->responsibles->first()?->name ? explode(' ', $task->responsibles->first()->name)[0] : 'sem Resp.') }}
                                                        </span>
                                                        @if($canAct && ! $done)
                                                            <span class="font-semibold flex-shrink-0" style="color:var(--purple)"
                                                                  x-text="moving && moving.id === '{{ $task->id }}' ? 'escolha o destino ↑' : 'mover'"></span>
                                                        @endif
                                                        <button type="button" class="flex-shrink-0" style="color:var(--muted)" title="Abrir tarefa"
                                                                @click.stop="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">
                                                            <x-icon name="external-link" size="13" />
                                                        </button>
                                                    </div>
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

            <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
                {{-- Sem Responsável — discreto: hoje são quase todas de Estratégia (automação de
                     reuniões), não produção. Ficam à vista pra não sumirem, sem ocupar a tela. --}}
                @if($d['noResponsible']->isNotEmpty())
                    <div x-data="{ open: false }" class="text-xs">
                        <button type="button" class="font-mono" style="color:var(--muted)" @click="open = !open">
                            <span x-text="open ? '▾' : '▸'"></span> {{ $d['noResponsible']->count() }} tarefa(s) sem Responsável
                        </button>
                        <div x-show="open" x-cloak class="flex flex-col gap-1 mt-1.5">
                            @foreach($d['noResponsible'] as $task)
                                <button type="button" class="text-left px-2 py-1" style="background:var(--s2); color:var(--text)"
                                        @click="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">
                                    {{ $task->title }} <span style="color:var(--muted)">· {{ $task->client?->displayName() ?? 'Interno' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <span></span>
                @endif
                <a href="{{ route('production-panel.index', ['responsavel' => $subjectUserId]) }}" class="text-xs font-mono" style="color:var(--purple)">
                    Abrir no Painel de Produção, filtrado nas suas →
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ── EQUILÍBRIO POR CLIENTE — uma barra por cliente no mês, por status (igual à da Sprint),
     com o trecho tracejado do que ainda nem foi pedido do volume combinado. Mais atrasados no
     topo. Régua e escopo em DistributionCockpit::clientBalance(). ── --}}
@php $cb = $d['clientBalance']; @endphp
@if(! empty($cb['rows']))
    <div class="card px-4 py-4 mb-6">
        <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
            <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                <x-icon name="bar-chart-3" size="14" /> Equilíbrio por cliente
                <span class="text-xs font-mono font-normal" style="color:var(--muted)">— {{ $cb['monthName'] }} · seus clientes</span>
            </h3>
            <span class="text-xs font-mono" style="color:var(--muted)">faltam {{ $cb['daysLeft'] }} dia(s) pro fim do mês</span>
        </div>
        <p class="text-xs mb-3" style="color:var(--muted2)">
            Tarefas do mês de cada cliente por status. O trecho <span style="color:var(--orange)">tracejado</span> é o que
            ainda falta pedir do volume combinado. Quem está mais atrás aparece primeiro.
        </p>

        {{-- Legenda das cores (só os status que aparecem em alguma barra) --}}
        @php $usedStatuses = collect($cb['rows'])->flatMap(fn ($r) => array_keys($r['byStatus']))->unique(); @endphp
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mb-3">
            @foreach(\App\Models\Task::$statuses as $s => $meta)
                @continue(! $usedStatuses->contains($s))
                <span class="flex items-center gap-1 text-xs font-mono" style="color:var(--muted2)">
                    <span class="h-2 w-2 rounded-full" style="background:{{ \App\Models\Task::colorHex($meta['color']) }}"></span>{{ $meta['label'] }}
                </span>
            @endforeach
            <span class="flex items-center gap-1 text-xs font-mono" style="color:var(--muted2)">
                <span class="h-2 w-3" style="background:repeating-linear-gradient(45deg, rgba(238,121,25,.55) 0 3px, transparent 3px 6px); border:1px solid rgba(238,121,25,.5)"></span>Falta pedir
            </span>
        </div>

        <div class="flex flex-col gap-2" style="max-height:520px; overflow-y:auto">
            @foreach($cb['rows'] as $r)
                <div class="grid items-center gap-x-3 gap-y-1 grid-cols-1 sm:grid-cols-[200px_1fr_190px]">
                    <span class="text-xs font-semibold truncate" style="color:var(--text)" title="{{ $r['client']->displayName() }}">{{ $r['client']->displayName() }}</span>
                    <div class="flex h-3 rounded-full overflow-hidden gap-0.5" style="background:var(--border2)">
                        @foreach($r['byStatus'] as $s => $n)
                            <div style="width:{{ round($n / $r['scale'] * 100, 2) }}%; background:{{ \App\Models\Task::colorHex(\App\Models\Task::$statuses[$s]['color']) }}"
                                 title="{{ \App\Models\Task::$statuses[$s]['label'] }}: {{ $n }}"></div>
                        @endforeach
                        @if($r['toRequest'] > 0)
                            <div style="width:{{ round($r['toRequest'] / $r['scale'] * 100, 2) }}%; background:repeating-linear-gradient(45deg, rgba(238,121,25,.55) 0 3px, transparent 3px 6px)"
                                 title="Falta pedir: {{ $r['toRequest'] }}"></div>
                        @endif
                    </div>
                    <span class="text-xs font-mono sm:text-right" style="color:var(--muted)">
                        <strong style="color:var(--green)">{{ $r['done'] }}</strong>/{{ $r['scale'] }} concluídas
                        @if($r['open'])· <span style="color:var(--text)">{{ $r['open'] }} abertas</span>@endif
                        @if($r['toRequest'])· <span style="color:var(--orange)">{{ $r['toRequest'] }} pedir</span>@endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($canAct && $hasInbox)
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
