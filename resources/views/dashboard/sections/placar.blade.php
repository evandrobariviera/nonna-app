{{-- Placar de pontos de sprint (modo Execução) — dados em App\Services\Dashboard\SprintScoreboard.
     Pedido do usuário: "competitividade" com as pontuações — cada um vê os próprios pontos
     (semana / sprint / mês), a posição no ranking e o andamento da agência na sprint.
     Regras de quando a tarefa pontua (status por tipo, só se passou por Em Produção) e das
     otimizações de campanha: ver SprintScoreboard. Pontos de tarefa vão pro executor. --}}
@php
    $sb = $scoreboard;
    $ag = $sb['agency'];
    $agPct = $ag['launched'] > 0 ? (int) round($ag['done'] / $ag['launched'] * 100) : 0;
    $medals = [1 => '#F5B301', 2 => '#A7B0BE', 3 => '#C97B3C'];
    $subjectName = explode(' ', ($viewingAs ?? Auth::user())->name)[0];
@endphp

<div class="card px-5 py-4 mb-4" x-data="{ period: 'sprint' }">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <span class="text-sm font-bold flex items-center gap-2" style="color:var(--text)">
            <x-icon name="trophy" size="15" style="color:var(--orange)" />
            Placar de Pontos
        </span>
        <div class="flex items-center gap-1">
            @foreach($sb['labels'] as $key => $label)
                <button type="button" class="text-xs font-semibold px-3 py-1"
                        style="border-radius:100px; border:1px solid var(--border2)"
                        :style="period === '{{ $key }}' ? { background: 'var(--purple)', color: '#fff', borderColor: 'var(--purple)' } : { color: 'var(--muted2)' }"
                        @click="period = '{{ $key }}'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(240px,320px)_1fr]">

        {{-- ── Esquerda: os meus pontos + a agência ── --}}
        <div class="flex flex-col gap-4">
            @foreach($sb['periods'] as $key => $pd)
                <div x-show="period === '{{ $key }}'" @if($key !== 'sprint') x-cloak @endif
                     class="px-4 py-4 text-center" style="background:linear-gradient(135deg, rgba(100,59,142,.10), rgba(238,121,25,.08)); border:1px solid rgba(100,59,142,.2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $viewingAs ? 'Pontos de '.$subjectName : 'Seus pontos' }} · {{ $sb['labels'][$key] }}</p>
                    <p class="text-5xl font-black mt-1" style="color:var(--purple)">{{ $pd['mine']['points'] }}</p>
                    <p class="text-xs font-mono" style="color:var(--muted2)">
                        {{ $pd['mine']['tasks'] }} tarefa(s) entregue(s) @if($pd['mine']['opts'])· {{ $pd['mine']['opts'] }} otimização(ões)@endif
                    </p>
                    @if($pd['myRank'])
                        <p class="text-sm font-bold mt-2" style="color:{{ $medals[$pd['myRank']] ?? 'var(--text)' }}">
                            {{ $pd['myRank'] }}º lugar <span class="text-xs font-normal" style="color:var(--muted)">de {{ $pd['rows']->count() }}</span>
                        </p>
                        @if($pd['myRank'] > 1)
                            @php $ahead = $pd['rows'][$pd['myRank'] - 2]; $gap = $ahead['points'] - $pd['mine']['points']; @endphp
                            <p class="text-xs mt-0.5" style="color:var(--muted2)">
                                {{ $gap === 0 ? 'empatado com' : 'faltam '.$gap.' pts pra passar' }} {{ explode(' ', $ahead['user']->name)[0] }}
                            </p>
                        @else
                            <p class="text-xs mt-0.5" style="color:var(--green)">liderando 🏆</p>
                        @endif
                    @else
                        <p class="text-xs mt-2" style="color:var(--muted2)">Ainda sem pontos {{ $key === 'semana' ? 'nesta semana' : ($key === 'mes' ? 'neste mês' : 'nesta sprint') }} — a primeira tarefa entregue já entra no ranking.</p>
                    @endif
                </div>
            @endforeach

            {{-- Os três períodos lado a lado, pra ver tudo de uma vez --}}
            <div class="grid grid-cols-3 gap-2">
                @foreach($sb['periods'] as $key => $pd)
                    <button type="button" class="px-2 py-2 text-center" style="background:var(--s2)"
                            :style="period === '{{ $key }}' ? { outline: '2px solid var(--purple)' } : {}"
                            @click="period = '{{ $key }}'">
                        <p class="text-lg font-black" style="color:var(--text)">{{ $pd['mine']['points'] }}</p>
                        <p class="text-xs font-mono" style="color:var(--muted)">{{ $sb['labels'][$key] }}</p>
                    </button>
                @endforeach
            </div>

            {{-- Agência na sprint: lançado × feito --}}
            @if($sb['sprint'])
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Agência · {{ $sb['sprint']->title }}</p>
                        <span class="text-xs font-mono font-bold" style="color:var(--text)">{{ $agPct }}%</span>
                    </div>
                    <div class="h-3 rounded-full overflow-hidden" style="background:var(--border2)">
                        <div class="h-full rounded-full" style="width:{{ $agPct }}%; background:linear-gradient(90deg, var(--purple), var(--orange))"></div>
                    </div>
                    <p class="text-xs font-mono mt-1" style="color:var(--muted2)">
                        <strong style="color:var(--text)">{{ $ag['done'] }}</strong> de {{ $ag['launched'] }} pts feitos
                        · {{ $ag['doneTasks'] }} de {{ $ag['launchedTasks'] }} tarefas
                    </p>
                </div>
            @endif
        </div>

        {{-- ── Direita: ranking ── --}}
        <div class="min-w-0">
            @foreach($sb['periods'] as $key => $pd)
                <div x-show="period === '{{ $key }}'" @if($key !== 'sprint') x-cloak @endif>
                    <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Ranking · {{ $sb['labels'][$key] }} <span style="color:var(--muted2)">· {{ $pd['total'] }} pts no total</span>
                    </p>
                    @if($pd['rows']->isEmpty())
                        <p class="text-xs py-4" style="color:var(--muted)">Ninguém pontuou ainda nesse período.</p>
                    @else
                        @php $leader = max(1, $pd['rows']->first()['points']); @endphp
                        <div class="flex flex-col gap-1.5" style="max-height:360px; overflow-y:auto">
                            @foreach($pd['rows'] as $i => $r)
                                @php $pos = $i + 1; $isMe = $r['user']->id === $subjectUserId; @endphp
                                <div class="grid items-center gap-3 px-2 py-1.5" style="grid-template-columns: 28px 130px 1fr 80px; {{ $isMe ? 'background:rgba(100,59,142,.10); outline:1px solid rgba(100,59,142,.35)' : '' }}">
                                    <span class="text-sm font-black text-center" style="color:{{ $medals[$pos] ?? 'var(--muted)' }}">{{ $pos }}º</span>
                                    <span class="text-xs font-semibold truncate" style="color:var(--text)" title="{{ $r['user']->name }}">
                                        {{ $r['user']->name }}{{ $isMe && ! $viewingAs ? ' (você)' : '' }}
                                    </span>
                                    <div class="h-2.5 rounded-full overflow-hidden" style="background:var(--border2)">
                                        <div class="h-full rounded-full" style="width:{{ round($r['points'] / $leader * 100, 1) }}%; background:{{ $medals[$pos] ?? ($isMe ? 'var(--purple)' : 'rgba(100,59,142,.45)') }}"></div>
                                    </div>
                                    <span class="text-xs font-mono text-right" style="color:var(--muted2)">
                                        <strong style="color:var(--text)">{{ $r['points'] }}</strong> pts · <span title="{{ $r['tasks'] }} tarefa(s){{ $r['opts'] ? ' + '.$r['opts'].' otimização(ões)' : '' }}">{{ $r['tasks'] + $r['opts'] }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
