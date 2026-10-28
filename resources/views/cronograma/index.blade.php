{{-- CRONOGRAMA DA AGÊNCIA — controle visual dos planejamentos (Services\Projects\AgencyTimeline).
     Uma linha por cliente: ciclo atual (e o próximo, se já cadastrado) com as 4 etapas
     automáticas, marcos das campanhas e alertas. Abrir o cliente mostra as campanhas/projetos
     com as fases. Filtros (etapa, responsável, busca) são no navegador. --}}
@php
    $range    = $data['range'];
    $stages   = \App\Services\Projects\AgencyTimeline::STAGES;
    $hex      = fn (string $color) => \App\Models\Task::colorHex($color);
    $stageHex = fn (string $key) => $hex(\App\Services\Projects\AgencyTimeline::STAGES[$key]['color'] ?? 'muted');
    $phaseHex = fn (?string $status) => $hex($status ? \App\Models\ProjectPhase::statusColor($status) : 'muted');
@endphp
<x-app-layout>
    <x-slot name="header">Cronograma</x-slot>

    <div x-data="{ stage: null, resp: '', q: '', alert: null }">

    {{-- Topo: título + janela --}}
    <div class="flex items-start justify-between gap-3 flex-wrap mb-5">
        <div>
            <h1 class="text-xl font-extrabold flex items-center gap-2" style="color:var(--text)">
                <x-icon name="chart-gantt" size="20" style="color:var(--purple)" /> Cronograma
            </h1>
            <p class="text-sm mt-1" style="color:var(--muted2)">
                Todos os planejamentos lado a lado: em que etapa cada cliente está, o que vem por aí e o que precisa de atenção.
            </p>
        </div>
        <div class="flex items-center" style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
            @foreach(\App\Services\Projects\AgencyTimeline::WINDOWS as $key => $w)
                <a href="{{ route('cronograma.index', ['janela' => $key]) }}" class="text-xs font-semibold px-3 py-1.5"
                   style="{{ $data['window'] === $key ? 'background:var(--purple); color:#fff' : 'color:var(--muted)' }}">{{ $w['label'] }}</a>
            @endforeach
        </div>
    </div>

    {{-- Etapas (clique filtra) + alertas --}}
    <div class="grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(130px, 1fr))">
        @foreach($stages as $key => $s)
            @php $n = $data['counts'][$key]; $c = $hex($s['color']); @endphp
            <button type="button" class="card px-4 py-3 text-left"
                    style="border-top:3px solid {{ $c }}; {{ $n ? 'cursor:pointer' : 'opacity:.55; cursor:default' }}"
                    :style="stage === '{{ $key }}' ? { boxShadow: '0 0 0 2px {{ $c }}' } : {}"
                    @if($n) @click="stage = stage === '{{ $key }}' ? null : '{{ $key }}'; alert = null" @endif>
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">{{ $s['label'] }}</p>
                <p class="text-2xl font-black mt-1" style="color:{{ $n && $key === 'sem_ciclo' ? $c : 'var(--text)' }}">{{ $n }}</p>
                <p class="text-xs" style="color:var(--muted2)">{{ $s['hint'] }}</p>
            </button>
        @endforeach
    </div>
    <div class="flex items-center gap-2 flex-wrap mb-5">
        @php
            $alertDefs = [
                'needsNext' => ['label' => 'Renovação sem próximo ciclo', 'n' => $data['alerts']['needsNext'], 'unit' => 'cliente(s)'],
                'late'      => ['label' => 'Fases atrasadas',             'n' => $data['alerts']['late'],      'unit' => ''],
                'undated'   => ['label' => 'Campanhas/projetos sem data', 'n' => $data['alerts']['undated'],   'unit' => ''],
            ];
        @endphp
        @foreach($alertDefs as $key => $a)
            <button type="button" class="text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1.5"
                    style="border:1px solid {{ $a['n'] ? 'rgba(238,121,25,.4)' : 'var(--border2)' }}; color:{{ $a['n'] ? 'var(--orange)' : 'var(--muted)' }}; {{ $a['n'] ? '' : 'cursor:default' }}"
                    :style="alert === '{{ $key }}' ? { background: 'rgba(238,121,25,.12)' } : {}"
                    @if($a['n']) @click="alert = alert === '{{ $key }}' ? null : '{{ $key }}'; stage = null" @endif>
                @if($a['n'])<x-icon name="triangle-alert" size="12" />@endif
                {{ $a['label'] }}: {{ $a['n'] }}
            </button>
        @endforeach
        <span class="flex-1"></span>
        <select x-model="resp" class="field px-2 py-1.5 text-xs focus:outline-none">
            <option value="">Todos os responsáveis</option>
            @foreach($data['responsibles'] as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="search" x-model="q" placeholder="Buscar cliente…" class="field px-3 py-1.5 text-xs focus:outline-none" style="width:180px">
    </div>

    <div class="grid grid-cols-1 gap-5 items-start xl:grid-cols-[minmax(0,1fr)_300px]">

    {{-- ── GANTT ── --}}
    <div class="card overflow-hidden min-w-0">
        <div class="overflow-x-auto">
            <div style="min-width:860px">
                {{-- Régua --}}
                <div class="grid" style="grid-template-columns:260px minmax(0,1fr); border-bottom:1px solid var(--border2)">
                    <div class="px-4 py-2 text-xs" style="color:var(--muted)">{{ $range['from']->format('d/m') }} a {{ $range['to']->format('d/m/y') }} · {{ $data['rows']->count() }} clientes</div>
                    <div class="relative" style="height:30px; border-left:1px solid var(--border2)">
                        @foreach($range['ticks'] as $tick)
                            <span class="absolute font-mono" style="left:{{ $tick['left'] }}%; top:8px; transform:translateX(-50%); color:var(--muted); font-size:10px">{{ $tick['label'] }}</span>
                        @endforeach
                        <span class="absolute font-bold" style="left:{{ $range['todayPct'] }}%; top:8px; transform:translateX(-50%); font-size:10px; color:var(--purple); background:var(--s1); padding:0 3px">Hoje</span>
                    </div>
                </div>

                @forelse($data['rows'] as $row)
                    @php
                        $planUrl = $row['mainPlan'] ? route('macroplans.edit', [$row['mainPlan'], 'bloco' => 'linha-do-tempo']) : null;
                        $flags = array_values(array_filter([$row['needsNext'] ? 'needsNext' : null, $row['late'] ? 'late' : null, $row['undated'] ? 'undated' : null]));
                    @endphp
                    <div x-data="{ open: false }"
                         x-show="(!stage || stage === '{{ $row['stage'] }}') && (!resp || resp == '{{ $row['responsible']?->id }}') && (!q || @js(mb_strtolower($row['client']->displayName())).includes(q.toLowerCase())) && (!alert || @js($flags).includes(alert))"
                         style="border-bottom:1px solid var(--border2)">
                        <div class="grid" style="grid-template-columns:260px minmax(0,1fr)">
                            {{-- Cliente --}}
                            <div class="px-4 py-2.5 min-w-0 flex gap-2">
                                <button type="button" @click="open = !open" class="flex-shrink-0 mt-0.5" style="color:var(--muted)" title="Ver campanhas e projetos">
                                    <x-icon name="chevron-right" size="14" class="transition-transform" ::style="open ? 'transform:rotate(90deg)' : ''" />
                                </button>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ $planUrl ?? route('clients.show', $row['client']) }}" class="text-sm font-bold truncate block hover:underline" style="color:var(--text)">{{ $row['client']->displayName() }}</a>
                                    <p class="text-xs mt-0.5 flex items-center gap-1.5 flex-wrap" style="color:var(--muted)">
                                        <span class="font-semibold" style="color:{{ $stageHex($row['stage']) }}">{{ $stages[$row['stage']]['label'] }}</span>
                                        @if($row['daysLeft'] !== null)
                                            <span>· {{ $row['daysLeft'] >= 0 ? 'faltam ' . $row['daysLeft'] . 'd' : 'venceu' }}</span>
                                        @endif
                                        @if($row['responsible'])<span class="truncate">· {{ \Illuminate\Support\Str::before($row['responsible']->name, ' ') }}</span>@endif
                                    </p>
                                    <p class="text-xs mt-0.5 flex items-center gap-2 flex-wrap">
                                        @if($row['needsNext'])<span style="color:var(--orange)" title="Ciclo em Renovação sem reunião nem planejamento do próximo ciclo">⚠ sem próximo ciclo</span>@endif
                                        @if($row['nextPlan'])<span style="color:var(--purple)">+ próximo: {{ $row['nextPlan']->statusLabel() }}</span>@endif
                                        @if($row['late'])<span style="color:var(--red)">{{ $row['late'] }} fase(s) atrasada(s)</span>@endif
                                        @if($row['undated'])<span style="color:var(--orange)">{{ $row['undated'] }} sem data</span>@endif
                                    </p>
                                </div>
                            </div>
                            {{-- Faixas do ciclo --}}
                            <div class="relative py-2" style="border-left:1px solid var(--border2); min-height:58px">
                                <span class="absolute top-0 bottom-0" style="left:{{ $range['todayPct'] }}%; width:2px; background:var(--purple); opacity:.5; z-index:1"></span>
                                @foreach($row['lanes'] as $i => $lane)
                                    <div class="relative" style="height:{{ count($row['lanes']) > 1 ? 18 : 24 }}px; margin-top:{{ $i ? 4 : 6 }}px">
                                        @foreach($lane['stages'] as $s)
                                            @php
                                                $c = $stageHex($s['key']);
                                                $style = match ($s['state']) {
                                                    'atual'  => "background:{$c}; color:#fff; border:1px solid {$c}",
                                                    'feita'  => "background:{$c}33; color:{$c}; border:1px solid {$c}55",
                                                    default  => "background:transparent; color:{$c}; border:1px dashed {$c}",
                                                };
                                            @endphp
                                            <span class="absolute top-0 bottom-0 flex items-center justify-center overflow-hidden whitespace-nowrap font-semibold"
                                                  title="{{ $lane['kind'] === 'proximo' ? 'Próximo ciclo · ' : '' }}{{ $s['label'] }} — {{ $s['start']->format('d/m') }}{{ $s['end']->eq($s['start']) ? '' : ' a ' . $s['end']->format('d/m') }}"
                                                  style="left:{{ $s['bar']['left'] }}%; width:{{ $s['bar']['width'] }}%; border-radius:5px; font-size:10px; padding:0 3px; {{ $style }}">
                                                {{ $s['bar']['width'] > 7 ? $s['label'] : '' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endforeach
                                {{-- Marcos das campanhas (◆) --}}
                                @foreach($row['milestones'] as $m)
                                    <span class="absolute" title="{{ $m['project']->title }} — {{ $m['label'] }} ({{ $m['date']->format('d/m') }})"
                                          style="left:{{ $m['pos'] }}%; bottom:2px; width:9px; height:9px; transform:translateX(-50%) rotate(45deg); background:var(--orange); border:1.5px solid var(--s1); z-index:2"></span>
                                @endforeach
                                @if($row['nextMeeting'] && $row['nextMeeting']->scheduled_at->between($range['from'], $range['to']))
                                    @php $nmPos = round($range['from']->diffInDays($row['nextMeeting']->scheduled_at->copy()->startOfDay(), false) / max(1, $range['from']->diffInDays($range['to'])) * 100, 2); @endphp
                                    <span class="absolute" title="Reunião do próximo ciclo — {{ $row['nextMeeting']->scheduled_at->format('d/m/Y') }}"
                                          style="left:{{ $nmPos }}%; top:3px; width:11px; height:11px; transform:translateX(-50%) rotate(45deg); background:var(--blue); border:1.5px solid var(--s1); z-index:2"></span>
                                @endif
                            </div>
                        </div>

                        {{-- Aberto: campanhas/projetos --}}
                        <div x-show="open" x-cloak style="background:var(--s2); border-top:1px solid var(--border2)">
                            @foreach($row['projects'] as $r)
                                @php $p = $r['project']; @endphp
                                <div class="grid" style="grid-template-columns:260px minmax(0,1fr); border-bottom:1px solid var(--border2)">
                                    <div class="pl-10 pr-4 py-2 min-w-0">
                                        <a href="{{ route('macroplans.projects.show', [$r['plan'], $p]) }}" class="text-xs font-semibold truncate flex items-center gap-1.5 hover:underline" style="color:var(--text)" title="{{ $p->title }}">
                                            <x-icon :name="$p->typeIcon()" size="11" style="color:{{ $hex($p->typeColor()) }}; flex-shrink:0" /> {{ $p->title }}
                                        </a>
                                        <p class="truncate" style="font-size:11px; color:var(--muted)">
                                            @if($r['plan']->id === $row['nextPlan']?->id)<span style="color:var(--purple)">próximo ciclo · </span>@endif
                                            @if($p->status === 'concluido')
                                                <span style="color:var(--green)">Concluído</span>
                                            @elseif($r['current'])
                                                Agora: <span style="color:{{ $phaseHex($r['current']['status']) }}">{{ $r['current']['phase']->name }}</span>
                                            @else
                                                sem fases
                                            @endif
                                        </p>
                                    </div>
                                    <div class="relative" style="border-left:1px solid var(--border2); min-height:36px">
                                        <span class="absolute top-0 bottom-0" style="left:{{ $range['todayPct'] }}%; width:2px; background:var(--purple); opacity:.35"></span>
                                        @if($r['spanBar'])
                                            <span class="absolute" style="left:{{ $r['spanBar']['left'] }}%; width:{{ $r['spanBar']['width'] }}%; top:50%; height:5px; transform:translateY(-50%); border-radius:3px; background:{{ $hex($p->typeColor()) }}33"></span>
                                        @endif
                                        @foreach($r['segBars'] as $seg)
                                            <span class="absolute" title="{{ $seg['name'] }} — {{ \App\Models\ProjectPhase::statusLabel($seg['status']) }} ({{ $seg['start']->format('d/m') }} a {{ $seg['end']->format('d/m') }})"
                                                  style="left:{{ $seg['bar']['left'] }}%; width:{{ $seg['bar']['width'] }}%; top:50%; height:13px; transform:translateY(-50%); border-radius:3px; background:{{ $phaseHex($seg['status']) }}CC; border:1px solid var(--s2)"></span>
                                        @endforeach
                                        @foreach($r['msPoints'] as $m)
                                            <span class="absolute" title="{{ $m['label'] }} — {{ $m['date']->format('d/m/Y') }}"
                                                  style="left:{{ $m['pos'] }}%; top:50%; width:10px; height:10px; transform:translate(-50%,-50%) rotate(45deg); background:{{ $m['status'] ? $phaseHex($m['status']) : 'var(--orange)' }}; border:1.5px solid var(--s2)"></span>
                                        @endforeach
                                        @if(!$r['spanBar'])
                                            <span class="absolute text-xs" style="left:8px; top:50%; transform:translateY(-50%); color:var(--muted)">fora da janela</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            <div class="pl-10 pr-4 py-2 flex items-center gap-3 flex-wrap text-xs">
                                @if($row['projects']->isEmpty())
                                    <span style="color:var(--muted)">Nenhuma campanha/projeto com datas.</span>
                                @endif
                                @if($row['undatedList']->isNotEmpty())
                                    <span style="color:var(--orange)">Sem data: {{ $row['undatedList']->pluck('title')->implode(' · ') }}</span>
                                @endif
                                <span class="flex-1"></span>
                                @if($planUrl)
                                    <a href="{{ $planUrl }}" class="btn btn-ghost btn-xs">Abrir planejamento</a>
                                @endif
                                @if($row['canCreateNext'])
                                    <a href="{{ route('macroplans.create', ['client_id' => $row['client']->id]) }}" class="btn btn-primary btn-xs">+ Próximo planejamento</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm" style="color:var(--muted)">Nenhum planejamento ativo.</p>
                @endforelse
            </div>
        </div>

        {{-- Legenda --}}
        <div class="px-4 py-2.5 flex items-center gap-4 flex-wrap" style="border-top:1px solid var(--border2); font-size:11px; color:var(--muted)">
            <span class="flex items-center gap-1.5"><span style="width:14px; height:9px; border-radius:2px; background:var(--purple)"></span>etapa atual</span>
            <span class="flex items-center gap-1.5"><span style="width:14px; height:9px; border-radius:2px; background:rgba(100,59,142,.2)"></span>já passou</span>
            <span class="flex items-center gap-1.5"><span style="width:14px; height:9px; border-radius:2px; border:1px dashed var(--purple)"></span>vem depois</span>
            <span>2ª faixa = próximo planejamento</span>
            <span class="flex items-center gap-1.5"><span style="width:8px; height:8px; transform:rotate(45deg); background:var(--orange)"></span>marco de campanha</span>
            <span class="flex items-center gap-1.5"><span style="width:8px; height:8px; transform:rotate(45deg); background:var(--blue)"></span>reunião do próximo ciclo</span>
            <span class="flex items-center gap-1.5"><span style="width:2px; height:12px; background:var(--purple)"></span>hoje</span>
        </div>
    </div>

    {{-- ── PRÓXIMOS 14 DIAS ── --}}
    <aside class="card overflow-hidden">
        <div class="px-4 py-3" style="border-bottom:1px solid var(--border2)">
            <p class="text-sm font-bold" style="color:var(--text)">Próximos 14 dias</p>
            <p class="text-xs" style="color:var(--muted)">marcos de campanhas/projetos e reuniões de Macro</p>
        </div>
        @forelse($data['upcoming']->groupBy(fn ($u) => $u['date']->format('Y-m-d')) as $day => $items)
            @php $d = \Carbon\Carbon::parse($day); @endphp
            <div class="px-4 py-2.5" style="border-bottom:1px solid var(--border2)">
                <p class="text-xs font-bold mb-1" style="color:{{ $d->isToday() ? 'var(--purple)' : 'var(--muted2)' }}">
                    {{ $d->isToday() ? 'Hoje' : ($d->isTomorrow() ? 'Amanhã' : ucfirst($d->translatedFormat('D, d/m'))) }}
                </p>
                @foreach($items as $u)
                    <div class="flex items-start gap-2 py-0.5">
                        <span class="flex-shrink-0 mt-1.5" style="width:7px; height:7px; transform:rotate(45deg); background:{{ isset($u['meeting']) ? 'var(--blue)' : 'var(--orange)' }}"></span>
                        <div class="min-w-0 text-xs">
                            <span class="font-semibold" style="color:var(--text)">{{ $u['label'] }}</span>
                            <span style="color:var(--muted)">· {{ $u['client']->displayName() }}</span>
                            @isset($u['project'])
                                <a href="{{ route('macroplans.projects.show', [$u['plan'], $u['project']]) }}" class="block truncate hover:underline" style="color:var(--muted2)">{{ $u['project']->title }}</a>
                            @endisset
                            @isset($u['meeting'])
                                <a href="{{ route('meetings.show', $u['meeting']) }}" class="block truncate hover:underline" style="color:var(--muted2)">{{ $u['meeting']->title }}</a>
                            @endisset
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="px-4 py-4 text-xs" style="color:var(--muted)">Nenhum marco nos próximos 14 dias. Campanhas sem data não aparecem aqui.</p>
        @endforelse
    </aside>

    </div>
    </div>
</x-app-layout>
