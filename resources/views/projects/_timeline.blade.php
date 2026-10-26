{{-- LINHA DO TEMPO — fases e marcos da campanha/projeto (ProjectPhase, 2026-10-26).
     $timeline vem de Services\Projects\ProjectTimeline. Fase se conclui sozinha quando a
     última tarefa dela termina — não tem botão de concluir. Tarefa sem fase aparece em
     "Sem fase" pro gestor resolver. --}}
@php
    $tlRows    = $timeline['rows'];
    $tlRange   = $timeline['range'];
    $tlFixed   = $timeline['fixed'];
    $tlCurrent = $timeline['current'];
    $tlPhaseOptions = $project->phases->where('is_milestone', false)->values();
    $tlEditorRows = $project->phases->map(fn ($p) => [
        'id'           => $p->id,
        'name'         => $p->name,
        'is_milestone' => $p->is_milestone,
        'start_date'   => $p->start_date?->format('Y-m-d'),
        'end_date'     => $p->end_date?->format('Y-m-d'),
    ])->values();
    $tlPresets = collect(\App\Models\ProjectPhase::$presets)->map(fn ($p) => ['label' => $p['label'], 'phases' => $p['phases']]);
    $tlHex = fn (string $status) => \App\Models\Task::colorHex(\App\Models\ProjectPhase::statusColor($status));
    // Erro ao salvar volta com o editor aberto (e as linhas como estavam no banco).
    $tlErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'phases'))->flatten();
@endphp

<div class="card mb-6" id="linha-do-tempo" style="scroll-margin-top:16px"
     x-data="phaseTimeline(@js($tlEditorRows), @js($tlPresets), @js($tlErrors->isNotEmpty()))">
    <div class="px-5 py-3 flex items-center justify-between gap-3 flex-wrap" style="border-bottom:1px solid var(--border2)">
        <div class="flex items-center gap-2 flex-wrap min-w-0">
            <x-icon name="chart-gantt" size="14" style="color:var(--purple)" />
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Linha do tempo</span>
            @if($timeline['phases'] > 0)
                <span class="text-xs" style="color:var(--muted2)">
                    {{ $timeline['done'] }} de {{ $timeline['phases'] }} fase{{ $timeline['phases'] !== 1 ? 's' : '' }} concluída{{ $timeline['done'] !== 1 ? 's' : '' }}
                </span>
                @if($tlCurrent)
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="color:{{ $tlHex($tlCurrent['status']) }}; background:{{ $tlHex($tlCurrent['status']) }}1A">
                        Agora: {{ $tlCurrent['phase']->name }}
                    </span>
                @endif
                @if($timeline['late'] > 0)
                    <span class="text-xs font-semibold flex items-center gap-1" style="color:var(--red)">
                        <x-icon name="triangle-alert" size="12" /> {{ $timeline['late'] }} atrasada{{ $timeline['late'] !== 1 ? 's' : '' }}
                    </span>
                @endif
            @endif
        </div>
        <button type="button" class="btn btn-ghost btn-xs flex items-center gap-1" x-show="!editing" @click="editing = true">
            <x-icon name="pencil" size="12" /> {{ $project->phases->isEmpty() ? 'Montar linha do tempo' : 'Editar fases' }}
        </button>
    </div>

    {{-- ── VISUALIZAÇÃO ── --}}
    <div x-show="!editing">
        @if($project->phases->isEmpty())
            <div class="px-5 py-4">
                <p class="text-sm" style="color:var(--muted2)">
                    Ainda não tem fases. Divida {{ $project->type === 'campanha' ? 'a campanha' : 'o projeto' }} em etapas
                    (ex.: Conceito → Produção → Aprovação → Veiculação) pra todo mundo saber em que pé está e o que vem depois.
                    Aplicando um <strong>Playbook</strong> com fases (botão no topo da página), as fases e as tarefas de cada uma já vêm prontas, com datas.
                </p>
                <div class="flex items-center gap-2 flex-wrap mt-3">
                    <span class="text-xs" style="color:var(--muted)">Começar de um modelo:</span>
                    <template x-for="(preset, key) in presets" :key="key">
                        <button type="button" class="btn btn-ghost btn-xs" @click="usePreset(key); editing = true" x-text="preset.label"></button>
                    </template>
                    <button type="button" class="btn btn-ghost btn-xs" @click="addRow(false); editing = true">Do zero</button>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <div style="min-width:680px">
                    {{-- Régua: datas + marcos fixos do projeto --}}
                    <div class="grid" style="grid-template-columns:240px minmax(0,1fr)">
                        <div class="px-5 py-2 text-xs" style="color:var(--muted)">
                            @if($tlRange)
                                {{ $tlRange['from']->format('d/m') }} a {{ $tlRange['to']->format('d/m/y') }}
                            @else
                                Sem datas ainda
                            @endif
                        </div>
                        <div class="relative" style="height:40px; border-left:1px solid var(--border2)">
                            @if($tlRange)
                                @foreach($tlRange['ticks'] as $tick)
                                    <span class="absolute text-xs font-mono" style="left:{{ $tick['left'] }}%; top:4px; transform:translateX(-50%); color:var(--muted); font-size:10px">{{ $tick['label'] }}</span>
                                @endforeach
                                @foreach($tlFixed as $f)
                                    <span class="absolute flex flex-col items-center" style="left:{{ $f['left'] }}%; top:18px; transform:translateX(-50%)"
                                          title="{{ $f['label'] }} — {{ $f['date']->format('d/m/Y') }}">
                                        <span style="width:9px; height:9px; transform:rotate(45deg); background:var(--orange)"></span>
                                        <span class="whitespace-nowrap font-semibold" style="font-size:10px; color:var(--orange)">{{ $f['label'] }}</span>
                                    </span>
                                @endforeach
                                @if($tlRange['todayPct'] !== null)
                                    <span class="absolute font-bold" style="left:{{ $tlRange['todayPct'] }}%; top:4px; transform:translateX(-50%); font-size:10px; color:var(--purple); background:var(--s1); padding:0 3px">Hoje</span>
                                @endif
                            @endif
                        </div>
                    </div>

                    @foreach($tlRows as $row)
                        @php
                            $phase = $row['phase'];
                            $hex   = $tlHex($row['status']);
                            $pct   = $row['total'] > 0 ? round($row['done'] / $row['total'] * 100) : 0;
                        @endphp
                        <div x-data="{ open: false }" style="border-top:1px solid var(--border2)">
                            <div class="grid items-stretch {{ $phase->is_milestone ? '' : 'cursor-pointer' }}" style="grid-template-columns:240px minmax(0,1fr)"
                                 @if(!$phase->is_milestone) @click="open = !open" @endif>
                                <div class="px-5 py-2.5 min-w-0">
                                    <p class="text-sm font-semibold truncate flex items-center gap-1.5" style="color:var(--text)" title="{{ $phase->name }}">
                                        @if($phase->is_milestone)
                                            <span class="flex-shrink-0" style="width:8px; height:8px; transform:rotate(45deg); background:{{ $hex }}"></span>
                                        @else
                                            <x-icon name="chevron-right" size="12" class="flex-shrink-0 transition-transform" ::style="open ? 'transform:rotate(90deg)' : ''" style="color:var(--muted)" />
                                        @endif
                                        {{ $phase->name }}
                                    </p>
                                    <p class="text-xs mt-0.5" style="color:var(--muted)">
                                        <span class="font-semibold" style="color:{{ $hex }}">{{ \App\Models\ProjectPhase::statusLabel($row['status']) }}</span>
                                        @if($phase->is_milestone)
                                            · {{ $phase->end_date?->format('d/m') }}
                                        @else
                                            · {{ $row['done'] }}/{{ $row['total'] }} tarefa{{ $row['total'] !== 1 ? 's' : '' }}
                                            @if($row['start'])
                                                · {{ $row['start']->format('d/m') }}{{ $row['end'] && !$row['end']->eq($row['start']) ? ' a ' . $row['end']->format('d/m') : '' }}
                                            @endif
                                        @endif
                                    </p>
                                </div>
                                <div class="relative" style="border-left:1px solid var(--border2); min-height:52px">
                                    @if($tlRange)
                                        @foreach($tlFixed as $f)
                                            <span class="absolute top-0 bottom-0" style="left:{{ $f['left'] }}%; border-left:1px dashed var(--orange); opacity:.35"></span>
                                        @endforeach
                                        @if($tlRange['todayPct'] !== null)
                                            <span class="absolute top-0 bottom-0" style="left:{{ $tlRange['todayPct'] }}%; width:2px; background:var(--purple); opacity:.6"></span>
                                        @endif
                                    @endif
                                    @if($tlRange && isset($row['left']))
                                        @if($phase->is_milestone)
                                            <span class="absolute" title="{{ $phase->name }} — {{ $phase->end_date->format('d/m/Y') }}"
                                                  style="left:{{ $row['left'] }}%; top:50%; width:14px; height:14px; transform:translate(-50%,-50%) rotate(45deg); background:{{ $hex }}; border:2px solid var(--s1)"></span>
                                        @else
                                            <span class="absolute overflow-hidden" title="{{ $phase->name }} — {{ $pct }}% das tarefas concluídas"
                                                  style="left:{{ $row['left'] }}%; width:{{ $row['width'] }}%; top:50%; height:18px; transform:translateY(-50%); border-radius:6px; background:{{ $hex }}33; border:1px solid {{ $hex }}">
                                                <span class="block h-full" style="width:{{ $pct }}%; background:{{ $hex }}; opacity:.75"></span>
                                            </span>
                                        @endif
                                    @elseif(!$phase->is_milestone)
                                        <span class="absolute text-xs" style="left:12px; top:50%; transform:translateY(-50%); color:var(--muted)">sem datas</span>
                                    @endif
                                </div>
                            </div>

                            @unless($phase->is_milestone)
                                <div x-show="open" x-cloak class="px-5 pb-3">
                                    @include('projects._timeline-tasks', ['tasks' => $row['tasks'], 'currentPhaseId' => $phase->id, 'phaseOptions' => $tlPhaseOptions])
                                </div>
                            @endunless
                        </div>
                    @endforeach

                    {{-- Tarefas sem fase — toda tarefa deve pertencer a uma fase --}}
                    @if($timeline['unphased']->isNotEmpty())
                        <div x-data="{ open: false }" style="border-top:1px solid var(--border2); background:rgba(238,121,25,.05)">
                            <button type="button" @click="open = !open" class="w-full px-5 py-2.5 text-left flex items-center gap-2">
                                <x-icon name="triangle-alert" size="13" style="color:var(--orange)" />
                                <span class="text-sm font-semibold" style="color:var(--text)">Sem fase</span>
                                <span class="text-xs" style="color:var(--muted2)">
                                    {{ $timeline['unphased']->count() }} tarefa{{ $timeline['unphased']->count() !== 1 ? 's' : '' }} — escolha a fase de cada uma
                                </span>
                                <span class="text-xs ml-auto" style="color:var(--muted)" x-text="open ? '▴' : '▾'"></span>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-3">
                                @include('projects._timeline-tasks', ['tasks' => $timeline['unphased'], 'currentPhaseId' => null, 'phaseOptions' => $tlPhaseOptions])
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ── EDIÇÃO (lista inteira, salva de uma vez) ── --}}
    <form x-show="editing" x-cloak method="POST" action="{{ route('projects.phases.sync', $project) }}" class="px-5 py-4 flex flex-col gap-3">
        @csrf @method('PUT')

        @if($tlErrors->isNotEmpty())
            <div class="px-3 py-2 text-xs font-semibold" style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                @foreach($tlErrors as $msg)
                    <div>{{ $msg }}</div>
                @endforeach
            </div>
        @endif

        <p class="text-xs" style="color:var(--muted2)">
            <strong>Fase</strong> = um trecho com começo e fim (tem tarefas). <strong>Marco</strong> = um dia que não pode escorregar (sem tarefas).
            A fase se conclui sozinha quando a última tarefa dela termina. Remover uma fase deixa as tarefas dela em "Sem fase".
        </p>

        <template x-for="(row, i) in rows" :key="row.key">
            <div class="flex items-end gap-2 flex-wrap p-2" style="background:var(--s2); border:1px solid var(--border2); border-radius:8px">
                <input type="hidden" :name="`phases[${i}][id]`" :value="row.id || ''">
                <input type="hidden" :name="`phases[${i}][is_milestone]`" :value="row.is_milestone ? 1 : 0">
                <div class="flex flex-col gap-0.5">
                    <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 4px" @click="move(i, -1)" :disabled="i === 0" title="Subir"><x-icon name="arrow-up" size="11" /></button>
                    <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 4px" @click="move(i, 1)" :disabled="i === rows.length - 1" title="Descer"><x-icon name="arrow-down" size="11" /></button>
                </div>
                <div class="flex-1" style="min-width:200px">
                    <label class="block text-xs mb-1" style="color:var(--muted)" x-text="row.is_milestone ? 'Marco' : 'Fase'"></label>
                    <input type="text" :name="`phases[${i}][name]`" x-model="row.name" required maxlength="150"
                           class="field w-full px-3 py-2 text-sm focus:outline-none" :placeholder="row.is_milestone ? 'Ex.: Apresentação ao cliente' : 'Ex.: Produção das peças'">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color:var(--muted)">Tipo</label>
                    <select class="field px-2 py-2 text-sm focus:outline-none"
                            @change="row.is_milestone = $event.target.value === '1'">
                        <option value="0" :selected="!row.is_milestone">Fase</option>
                        <option value="1" :selected="row.is_milestone">Marco</option>
                    </select>
                </div>
                <div x-show="!row.is_milestone">
                    <label class="block text-xs mb-1" style="color:var(--muted)">Começa</label>
                    <input type="date" :name="`phases[${i}][start_date]`" x-model="row.start_date" :disabled="row.is_milestone" class="field px-2 py-2 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color:var(--muted)" x-text="row.is_milestone ? 'Data' : 'Termina'"></label>
                    <input type="date" :name="`phases[${i}][end_date]`" x-model="row.end_date" :required="row.is_milestone" class="field px-2 py-2 text-sm focus:outline-none">
                </div>
                <button type="button" class="btn btn-ghost btn-xs" @click="rows.splice(i, 1)" title="Remover" style="color:var(--red)"><x-icon name="trash-2" size="13" /></button>
            </div>
        </template>

        <p x-show="rows.length === 0" class="text-xs" style="color:var(--muted)">Nenhuma fase. Adicione abaixo ou use um modelo.</p>

        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-ghost btn-xs flex items-center gap-1" @click="addRow(false)"><x-icon name="plus" size="12" /> Fase</button>
            <button type="button" class="btn btn-ghost btn-xs flex items-center gap-1" @click="addRow(true)"><x-icon name="plus" size="12" /> Marco</button>
            <span class="text-xs ml-2" style="color:var(--muted)">Acrescentar modelo:</span>
            <template x-for="(preset, key) in presets" :key="key">
                <button type="button" class="btn btn-ghost btn-xs" @click="usePreset(key)" x-text="preset.label"></button>
            </template>
        </div>

        <div class="flex items-center gap-2 pt-2" style="border-top:1px solid var(--border2)">
            <button type="submit" class="btn btn-primary btn-sm">Salvar linha do tempo</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="reset()">Cancelar</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Editor da linha do tempo (projects/_timeline) — lista inteira em memória, salva de uma vez
// (ProjectPhaseController::sync). `key` só identifica a linha no x-for (id some em linha nova).
function phaseTimeline(initial, presets, startEditing) {
    let seq = 0;
    const clone = (rows) => rows.map(r => ({ ...r, key: ++seq }));
    return {
        rows: clone(initial),
        presets,
        editing: startEditing,
        addRow(milestone) {
            this.rows.push({ id: null, name: '', is_milestone: milestone, start_date: null, end_date: null, key: ++seq });
        },
        usePreset(key) {
            this.presets[key].phases.forEach(p => this.rows.push({
                id: null, name: p.name, is_milestone: !!p.is_milestone, start_date: null, end_date: null, key: ++seq,
            }));
        },
        move(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.rows.length) return;
            [this.rows[i], this.rows[j]] = [this.rows[j], this.rows[i]];
        },
        reset() {
            this.rows = clone(initial);
            this.editing = false;
        },
    };
}

// Troca a fase de uma tarefa direto da linha do tempo — recarrega pra recalcular status das fases.
async function setTaskPhase(url, phaseId) {
    const { ok, message } = await window.inlinePatch(url, { project_phase_id: phaseId || null });
    if (ok) {
        window.location.hash = 'linha-do-tempo';
        window.location.reload();
    } else {
        alert(message || 'Falha ao trocar a fase. Tente de novo.');
    }
}
</script>
@endpush
