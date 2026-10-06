{{-- Modo Planejamento — dados em App\Services\Dashboard\PlanningCockpit. Três blocos:
     1) esteira (em que etapa do ciclo cada cliente está — clique filtra a linha do tempo),
     2) linha do tempo dos ciclos (barra do início ao fim do ciclo, enchendo conforme o tempo
        passa, com a linha de hoje), 3) agenda das reuniões de Macro/Kick-off da agência. --}}
@php
    $p = $planning;
    $stages = \App\Services\Dashboard\PlanningCockpit::STAGES;
    $hex = ['green' => 'var(--green)', 'orange' => 'var(--orange)', 'red' => 'var(--red)', 'blue' => 'var(--blue)', 'purple' => 'var(--purple)'];
    $from = $p['window']['from'];
    $span = $p['window']['days'];
    // posição (%) de uma data na linha do tempo, presa à janela
    $pos = fn ($date) => max(0, min(100, $from->diffInDays($date, false) / $span * 100));
    $todayPos = $pos($p['today']);
@endphp

<div x-data="{ stage: null }" class="mb-6">

    {{-- ── 1. ESTEIRA ── --}}
    <div class="grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))">
        @foreach($stages as $key => $s)
            @php $n = $p['stageCounts'][$key]; $c = $hex[$s['color']]; @endphp
            <button type="button" class="card px-4 py-3 text-left transition-all"
                    style="border-top:3px solid {{ $c }}; {{ $n ? 'cursor:pointer' : 'opacity:.55; cursor:default' }}"
                    :style="stage === '{{ $key }}' ? { boxShadow: '0 0 0 2px {{ $c }}' } : {}"
                    @if($n) @click="stage = stage === '{{ $key }}' ? null : '{{ $key }}'" @endif>
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $s['label'] }}</p>
                <p class="text-2xl font-black mt-1" style="color:{{ $n && in_array($key, ['entrando', 'sem_ciclo']) ? $c : 'var(--text)' }}">{{ $n }}</p>
                <p class="text-xs" style="color:var(--muted2)">{{ $s['hint'] }}</p>
            </button>
        @endforeach
    </div>

    {{-- ── 2. LINHA DO TEMPO DOS CICLOS ── --}}
    <div class="card px-4 py-4 mb-4">
        <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
            <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                <x-icon name="calendar-range" size="14" /> Ciclos de planejamento
                <span class="text-xs font-mono font-normal" style="color:var(--muted)">— {{ $p['rows']->count() }} clientes · mais urgentes no topo</span>
            </h3>
            <button type="button" x-show="stage" x-cloak class="text-xs font-semibold" style="color:var(--purple)" @click="stage = null">✕ mostrar todas as etapas</button>
        </div>
        <p class="text-xs mb-3 flex flex-wrap gap-x-3 gap-y-1" style="color:var(--muted2)">
            <span>A barra vai do início ao fim do ciclo e enche conforme o tempo passa.</span>
            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm" style="background:var(--green)"></span>em dia</span>
            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm" style="background:var(--orange)"></span>últimos 30 dias</span>
            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm" style="background:var(--red)"></span>vencido</span>
            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm" style="background:repeating-linear-gradient(45deg, rgba(100,59,142,.6) 0 3px, rgba(100,59,142,.2) 3px 6px)"></span>próximo ciclo em elaboração</span>
        </p>

        <div style="overflow-x:auto">
            <div style="min-width:720px">
                {{-- Régua dos meses --}}
                <div class="grid items-end gap-3 mb-1" style="grid-template-columns: 210px 1fr 190px">
                    <span></span>
                    <div class="relative h-5">
                        @foreach($p['months'] as $m)
                            @php $left = $pos($m); @endphp
                            <span class="absolute text-xs font-mono uppercase" style="left:{{ $left }}%; color:var(--muted); transform:translateX(2px)">{{ $m->locale('pt_BR')->translatedFormat('M') }}</span>
                        @endforeach
                        <span class="absolute text-xs font-mono font-bold" style="left:{{ $todayPos }}%; transform:translateX(-50%); top:-2px; color:var(--purple)">hoje</span>
                    </div>
                    <span></span>
                </div>

                <div class="flex flex-col gap-1.5">
                    @foreach($p['rows'] as $r)
                        @php
                            $cur = $r['current']; $nxt = $r['next'];
                            $color = match (true) {
                                $cur === null || $r['daysLeft'] < 0 || $cur->status === 'concluido' => 'var(--red)',
                                $r['daysLeft'] <= \App\Services\Dashboard\PlanningCockpit::ALERT_DAYS => 'var(--orange)',
                                default => 'var(--green)',
                            };
                            $stageMeta = $stages[$r['stage']];
                        @endphp
                        <div class="grid items-center gap-3" style="grid-template-columns: 210px 1fr 190px"
                             x-show="!stage || stage === '{{ $r['stage'] }}'">
                            <div class="min-w-0">
                                <a href="{{ route('clients.show', [$r['client'], 'tab' => 'planejamentos']) }}" class="text-xs font-semibold truncate block hover:underline" style="color:var(--text)" title="{{ $r['client']->displayName() }}">
                                    {{ $r['client']->displayName() }}
                                </a>
                                <span class="text-xs font-mono" style="color:{{ $hex[$stageMeta['color']] }}">{{ $stageMeta['label'] }}</span>
                                @if($r['needsScheduling'])
                                    <span class="text-xs font-mono font-bold" style="color:var(--orange)">· agendar reunião</span>
                                @endif
                            </div>

                            {{-- Trilho --}}
                            <div class="relative h-5 rounded" style="background:var(--s2)">
                                @foreach($p['months'] as $m)
                                    <span class="absolute top-0 bottom-0" style="left:{{ $pos($m) }}%; width:1px; background:var(--border2)"></span>
                                @endforeach

                                @if($cur)
                                    @php $l = $pos($cur->period_start); $w = max(0.8, $pos($cur->period_end) - $l); @endphp
                                    <a href="{{ route('macroplans.edit', $cur) }}" class="absolute top-0.5 bottom-0.5 rounded overflow-hidden"
                                       style="left:{{ $l }}%; width:{{ $w }}%; background:var(--border2)"
                                       title="{{ $cur->title }} · {{ $cur->periodLabel() }} · {{ $cur->statusLabel() }}">
                                        <span class="absolute inset-y-0 left-0" style="width:{{ $r['elapsed'] }}%; background:{{ $color }}"></span>
                                    </a>
                                @endif

                                @if($nxt && $nxt->period_start && $nxt->period_end)
                                    @php $l = $pos($nxt->period_start); $w = max(0.8, $pos($nxt->period_end) - $l); @endphp
                                    <a href="{{ route('macroplans.edit', $nxt) }}" class="absolute top-0.5 bottom-0.5 rounded"
                                       style="left:{{ $l }}%; width:{{ $w }}%; background:repeating-linear-gradient(45deg, rgba(100,59,142,.6) 0 3px, rgba(100,59,142,.2) 3px 6px)"
                                       title="Próximo: {{ $nxt->title }} · {{ $nxt->periodLabel() }} · {{ $nxt->statusLabel() }}"></a>
                                @endif

                                <span class="absolute top-0 bottom-0" style="left:{{ $todayPos }}%; width:2px; background:var(--purple)"></span>
                            </div>

                            <div class="text-xs font-mono" style="color:var(--muted)">
                                @if(! $cur)
                                    <span style="color:var(--red)">nunca teve ciclo</span>
                                @elseif($cur->status === 'concluido' && $r['daysLeft'] >= 0)
                                    <span style="color:var(--red)">ciclo concluído antes do fim</span>
                                @elseif($r['daysLeft'] < 0)
                                    <span style="color:var(--red)">venceu há {{ abs($r['daysLeft']) }} dia(s)</span>
                                @else
                                    <strong style="color:{{ $color }}">{{ $r['elapsed'] }}%</strong> · faltam {{ $r['daysLeft'] }} dia(s)
                                @endif
                                @if($r['meeting'])
                                    <span class="block truncate" title="{{ $r['meeting']->title }}">
                                        reunião: {{ $r['meeting']->statusLabel() }}{{ $r['meeting']->scheduled_at ? ' · '.$r['meeting']->scheduled_at->format('d/m') : '' }}
                                    </span>
                                @elseif($nxt)
                                    <span class="block">próximo: {{ $nxt->statusLabel() }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Fora da visão: clientes ativos que nunca tiveram planejamento nem reunião de macro --}}
        @if($p['otherClients']->isNotEmpty())
            <div x-data="{ open: false }" class="mt-3 text-xs">
                <button type="button" class="font-mono" style="color:var(--muted)" @click="open = !open">
                    <span x-text="open ? '▾' : '▸'"></span> {{ $p['otherClients']->count() }} cliente(s) ativos sem planejamento nem reunião de macro
                </button>
                <div x-show="open" x-cloak class="flex flex-wrap gap-1.5 mt-2">
                    @foreach($p['otherClients'] as $c)
                        <a href="{{ route('clients.show', $c) }}" class="px-2 py-1" style="background:var(--s2); color:var(--muted2)">{{ $c->displayName() }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ── 3. AGENDA DAS REUNIÕES DE MACRO / KICK-OFF (agência toda) ── --}}
    <div class="card px-4 py-4">
        <h3 class="text-sm font-bold flex items-center gap-1.5 mb-3" style="color:var(--text)">
            <x-icon name="calendar" size="14" /> Reuniões de Macroplanejamento e Kick-off
        </h3>
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
            @foreach(['para_agendar', 'agendada', 'pos_reuniao', 'revisao_ata', 'despacho'] as $status)
                @php $list = $p['agenda']->get($status, collect()); $meta = \App\Models\Meeting::$statuses[$status]; @endphp
                <div>
                    <div class="flex items-center justify-between px-3 py-2 mb-2" style="background:var(--s2); border-top:3px solid {{ $hex[$meta['color']] ?? 'var(--border2)' }}">
                        <span class="text-xs font-bold uppercase tracking-widest" style="color:var(--text)">{{ $meta['label'] }}</span>
                        <span class="text-xs font-mono font-bold" style="color:var(--muted)">{{ $list->count() }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        @forelse($list as $m)
                            @php $isToday = $m->scheduled_at?->isToday(); $late = $m->status === 'agendada' && $m->scheduled_at?->isPast() && ! $isToday; @endphp
                            <a href="{{ route('meetings.show', $m) }}" class="px-3 py-2 transition-colors"
                               style="background:{{ $isToday ? 'rgba(52,211,153,.10)' : 'var(--s1)' }}; border:1px solid var(--border2); {{ $late ? 'border-left:3px solid var(--red)' : '' }}"
                               onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='{{ $isToday ? 'rgba(52,211,153,.10)' : 'var(--s1)' }}'">
                                <p class="text-xs font-mono truncate" style="color:var(--purple)">{{ $m->client?->displayName() ?? '—' }}</p>
                                <p class="text-xs font-semibold leading-snug mt-0.5" style="color:var(--text)">{{ $m->title }}</p>
                                <p class="text-xs font-mono mt-1" style="color:var(--muted)">
                                    {{ $m->typeLabel() === 'Kick-off Estratégico' ? 'Kick-off' : 'Macro' }}
                                    @if($m->scheduled_at) · <span style="color:{{ $late ? 'var(--red)' : 'var(--muted)' }}">{{ $m->scheduled_at->format('d/m H:i') }}</span>@endif
                                    @if($m->organizer) · {{ explode(' ', $m->organizer->name)[0] }}@endif
                                </p>
                            </a>
                        @empty
                            <p class="text-xs px-1" style="color:var(--muted)">Nada aqui.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
