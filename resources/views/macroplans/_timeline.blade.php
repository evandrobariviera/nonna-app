{{-- LINHA DO TEMPO DO PLANEJAMENTO — 4 etapas automáticas do ciclo + uma linha por
     campanha/projeto com as fases e marcos de cada um ($planTimeline, MacroPlanTimeline). --}}
@php
    $pt       = $planTimeline;
    $ptRange  = $pt['range'];
    $stageHex = ['feita' => \App\Models\Task::colorHex('green'), 'atual' => \App\Models\Task::colorHex('purple'), 'futura' => \App\Models\Task::colorHex('muted')];
    $phaseHex = fn (?string $status) => \App\Models\Task::colorHex($status ? \App\Models\ProjectPhase::statusColor($status) : 'muted');
    $ptCurrentLabel = $pt['current'] ? \App\Services\Projects\MacroPlanTimeline::$stageMeta[$pt['current']]['label'] : null;
@endphp

<div class="card overflow-hidden">
    <div class="px-5 py-4 flex items-start justify-between gap-3 flex-wrap" style="border-bottom:1px solid var(--border2)">
        <div class="min-w-0">
            <h2 class="text-base font-bold flex items-center gap-2" style="color:var(--text)">
                <x-icon name="chart-gantt" size="16" style="color:var(--purple)" /> Linha do tempo do ciclo
            </h2>
            <p class="text-xs mt-1" style="color:var(--muted)">
                {{ $macroplan->periodLabel() }}
                @if($ptCurrentLabel)
                    · etapa atual: <strong style="color:var(--purple)">{{ $ptCurrentLabel }}</strong>
                @endif
                · as etapas andam sozinhas (reunião, status do planejamento e datas do ciclo)
            </p>
        </div>
        <div class="text-xs text-right" style="color:var(--muted2)">
            @if($pt['meeting'])
                Reunião do ciclo:
                <a href="{{ route('meetings.show', $pt['meeting']) }}" class="font-semibold hover:underline" style="color:var(--purple)">{{ $pt['meeting']->scheduled_at->format('d/m/Y') }}</a>
            @else
                <span style="color:var(--orange)">Reunião de Macro/Kick-off deste ciclo não encontrada</span>
            @endif
            <br>
            @if($pt['nextMeeting'])
                Próximo ciclo:
                <a href="{{ route('meetings.show', $pt['nextMeeting']) }}" class="font-semibold hover:underline" style="color:var(--purple)">reunião em {{ $pt['nextMeeting']->scheduled_at->format('d/m/Y') }}</a>
            @endif
            @if($pt['nextPlan'])
                <br>Próximo planejamento:
                <a href="{{ route('macroplans.edit', [$pt['nextPlan'], 'bloco' => 'linha-do-tempo']) }}" class="font-semibold hover:underline" style="color:var(--purple)">{{ $pt['nextPlan']->statusLabel() }}</a>
            @endif
        </div>
    </div>

    @if($pt['needsNext'] || $pt['canCreateNext'])
        <div class="px-5 py-2.5 text-xs font-semibold flex items-center gap-3 flex-wrap" style="background:rgba(238,121,25,.08); color:var(--orange); border-bottom:1px solid var(--border2)">
            <x-icon name="triangle-alert" size="13" />
            {{ $pt['needsNext'] ? 'Ciclo em Renovação e a reunião do próximo ciclo ainda não está marcada.' : 'Ciclo em Renovação: o próximo planejamento já pode ser cadastrado.' }}
            @if($pt['canCreateNext'])
                <a href="{{ route('macroplans.create', ['client_id' => $macroplan->client_id]) }}" class="btn btn-ghost btn-xs">+ Cadastrar o próximo planejamento</a>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        <div style="min-width:760px">
            {{-- Régua --}}
            <div class="grid" style="grid-template-columns:230px minmax(0,1fr)">
                <div class="px-5 py-2 text-xs" style="color:var(--muted)">{{ $ptRange['from']->format('d/m') }} a {{ $ptRange['to']->format('d/m/y') }}</div>
                <div class="relative" style="height:28px; border-left:1px solid var(--border2)">
                    @foreach($ptRange['ticks'] as $tick)
                        <span class="absolute font-mono" style="left:{{ $tick['left'] }}%; top:6px; transform:translateX(-50%); color:var(--muted); font-size:10px">{{ $tick['label'] }}</span>
                    @endforeach
                    @if($ptRange['todayPct'] !== null)
                        <span class="absolute font-bold" style="left:{{ $ptRange['todayPct'] }}%; top:6px; transform:translateX(-50%); font-size:10px; color:var(--purple); background:var(--s1); padding:0 3px">Hoje</span>
                    @endif
                </div>
            </div>

            {{-- Etapas do ciclo --}}
            <div class="grid" style="grid-template-columns:230px minmax(0,1fr); border-top:1px solid var(--border2); background:var(--s2)">
                <div class="px-5 py-3">
                    <p class="text-sm font-bold" style="color:var(--text)">Ciclo do planejamento</p>
                    <p class="text-xs" style="color:var(--muted)">Briefing · Elaboração · Execução · Renovação</p>
                </div>
                <div class="relative" style="border-left:1px solid var(--border2); min-height:56px">
                    @foreach($pt['stages'] as $s)
                        <span class="absolute flex items-center justify-center overflow-hidden text-xs font-semibold whitespace-nowrap"
                              title="{{ $s['label'] }} — {{ $s['start']->format('d/m') }}{{ $s['end']->eq($s['start']) ? '' : ' a ' . $s['end']->format('d/m') }} · {{ $s['hint'] }}"
                              style="left:{{ $s['left'] }}%; width:{{ $s['width'] }}%; top:50%; height:26px; transform:translateY(-50%); border-radius:6px;
                                     background:{{ $stageHex[$s['state']] }}{{ $s['state'] === 'atual' ? '' : '33' }};
                                     color:{{ $s['state'] === 'atual' ? '#fff' : $stageHex[$s['state']] }};
                                     border:1px solid {{ $stageHex[$s['state']] }}; padding:0 4px">
                            {{ $s['width'] > 6 ? $s['label'] : '' }}
                        </span>
                    @endforeach
                    @if($ptRange['nextPct'] !== null)
                        <span class="absolute" title="Reunião do próximo ciclo — {{ $pt['nextMeeting']->scheduled_at->format('d/m/Y') }}"
                              style="left:{{ $ptRange['nextPct'] }}%; top:50%; width:13px; height:13px; transform:translate(-50%,-50%) rotate(45deg); background:var(--orange); border:2px solid var(--s1)"></span>
                    @endif
                    @if($ptRange['todayPct'] !== null)
                        <span class="absolute top-0 bottom-0" style="left:{{ $ptRange['todayPct'] }}%; width:2px; background:var(--purple); opacity:.6"></span>
                    @endif
                </div>
            </div>

            {{-- Campanhas e projetos --}}
            @foreach($pt['projects'] as $row)
                @php
                    $p   = $row['project'];
                    $url = route('macroplans.projects.show', [$macroplan, $p]);
                    $typeHex = \App\Models\Task::colorHex($p->typeColor());
                @endphp
                <div class="grid" style="grid-template-columns:230px minmax(0,1fr); border-top:1px solid var(--border2)">
                    <div class="px-5 py-2.5 min-w-0">
                        <a href="{{ $url }}" class="text-sm font-semibold truncate flex items-center gap-1.5 hover:underline" style="color:var(--text)" title="{{ $p->title }}">
                            <x-icon :name="$p->typeIcon()" size="12" style="color:{{ $typeHex }}; flex-shrink:0" /> {{ $p->title }}
                        </a>
                        <p class="text-xs mt-0.5 truncate" style="color:var(--muted)">
                            @if($p->status === 'concluido')
                                <span style="color:var(--green)">Concluído</span>
                            @elseif($row['current'])
                                Agora: <span style="color:{{ $phaseHex($row['current']['status']) }}">{{ $row['current']['phase']->name }}</span>
                            @elseif($row['segments']->isEmpty())
                                sem fases — só as datas {{ $p->type === 'campanha' ? 'da campanha' : 'do projeto' }}
                            @endif
                            @if($row['late'] > 0)
                                · <span style="color:var(--red)">{{ $row['late'] }} atrasada{{ $row['late'] !== 1 ? 's' : '' }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="relative" style="border-left:1px solid var(--border2); min-height:50px">
                        @if($ptRange['todayPct'] !== null)
                            <span class="absolute top-0 bottom-0" style="left:{{ $ptRange['todayPct'] }}%; width:2px; background:var(--purple); opacity:.35"></span>
                        @endif
                        {{-- Fundo: do primeiro ao último dia do item --}}
                        <span class="absolute" style="left:{{ $row['span']['left'] }}%; width:{{ $row['span']['width'] }}%; top:50%; height:6px; transform:translateY(-50%); border-radius:3px; background:{{ $typeHex }}33"></span>
                        @foreach($row['segments'] as $seg)
                            <span class="absolute" title="{{ $seg['name'] }} — {{ \App\Models\ProjectPhase::statusLabel($seg['status']) }} ({{ $seg['start']->format('d/m') }} a {{ $seg['end']->format('d/m') }})"
                                  style="left:{{ $seg['left'] }}%; width:{{ $seg['width'] }}%; top:50%; height:16px; transform:translateY(-50%); border-radius:4px;
                                         background:{{ $phaseHex($seg['status']) }}{{ $seg['status'] === 'concluida' ? '88' : 'CC' }}; border:1px solid var(--s1)"></span>
                        @endforeach
                        @foreach($row['milestones'] as $m)
                            <span class="absolute" title="{{ $m['label'] }} — {{ $m['date']->format('d/m/Y') }}"
                                  style="left:{{ $m['left'] }}%; top:50%; width:12px; height:12px; transform:translate(-50%,-50%) rotate(45deg);
                                         background:{{ $m['status'] ? $phaseHex($m['status']) : 'var(--orange)' }}; border:2px solid var(--s1)"></span>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if($pt['projects']->isEmpty())
                <p class="px-5 py-4 text-xs" style="border-top:1px solid var(--border2); color:var(--muted)">
                    Nenhuma campanha/projeto com datas ainda.
                </p>
            @endif
        </div>
    </div>

    @if($pt['undated']->isNotEmpty())
        <div class="px-5 py-3 text-xs" style="border-top:1px solid var(--border2); background:rgba(238,121,25,.05); color:var(--muted2)">
            <span class="font-semibold" style="color:var(--orange)">Sem datas ({{ $pt['undated']->count() }}):</span>
            @foreach($pt['undated'] as $p)
                <a href="{{ route('macroplans.projects.show', [$macroplan, $p]) }}" class="hover:underline" style="color:var(--text)">{{ $p->title }}</a>{{ $loop->last ? '' : ' · ' }}
            @endforeach
            <span> — preencha Início/Término ou monte a linha do tempo pra aparecer aqui.</span>
        </div>
    @endif

    {{-- Legenda --}}
    <div class="px-5 py-2.5 flex items-center gap-4 flex-wrap" style="border-top:1px solid var(--border2); font-size:11px; color:var(--muted)">
        @foreach(['a_comecar', 'em_andamento', 'atrasada', 'concluida'] as $st)
            <span class="flex items-center gap-1.5"><span style="width:10px; height:10px; border-radius:2px; background:{{ $phaseHex($st) }}"></span>{{ \App\Models\ProjectPhase::statusLabel($st) }}</span>
        @endforeach
        <span class="flex items-center gap-1.5"><span style="width:9px; height:9px; transform:rotate(45deg); background:var(--orange)"></span>Marco</span>
        <span class="flex items-center gap-1.5"><span style="width:2px; height:12px; background:var(--purple)"></span>Hoje</span>
    </div>
</div>
