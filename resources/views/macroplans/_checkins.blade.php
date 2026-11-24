{{-- Aba Acompanhamento do PLANEJAMENTO (24/11): as metas do ciclo são o centro. Para cada meta,
     os números (App\Services\Projects\PlanCheckinSystem) e, embaixo, as campanhas/projetos que
     ajudam nela com a última conferência de cada um. O responsável do planejamento avalia;
     no fechamento, o resultado de cada meta vai para o documento. Sem IA. Recebe: $macroplan. --}}
@php
    use App\Models\ProjectCheckin;
    use App\Models\MacroPlanCheckin;
    $sys      = app(\App\Services\Projects\PlanCheckinSystem::class)->build($macroplan);
    $checkins = $macroplan->checkins()->with('user:id,name')->get();
    $resp     = $macroplan->responsible_id ? \App\Models\User::withoutGlobalScopes()->find($macroplan->responsible_id, ['id', 'name']) : null;
    $proxima  = $sys['proxima'] ? \Carbon\Carbon::parse($sys['proxima']) : null;
    $dt       = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
    $lights   = ['verde' => ['#16a34a', 'No ritmo'], 'amarelo' => ['#EE7919', 'Abaixo do ritmo'], 'vermelho' => ['#dc2626', 'Bem abaixo do ritmo']];
    $closeDue = $sys['momento'] === 'fechamento' && $proxima && $proxima->lte(today());
    $initial  = [
        'metas'    => collect($sys['metas'])->mapWithKeys(fn ($m) => [$m['key'] => [
            'atual' => old("metas.{$m['key']}.atual", $m['ultimo']), 'avaliacao' => old("metas.{$m['key']}.avaliacao"),
        ]])->all(),
        'cuidados' => (object) [],
        'situacao' => old('situacao'),
        'fechar'   => (bool) old('fechar', $closeDue),
        'ref'      => collect($sys['metas'])->mapWithKeys(fn ($m) => [$m['key'] => [$m['partida'], $m['meta'], $m['ritmo']]])->all(),
    ];
@endphp

<style>
    .ck-grid { display: grid; gap: 1rem; }
    .ck-row { border: 1px solid var(--border2); border-radius: 10px; padding: .75rem .9rem; background: var(--s1); }
    .ck-sys { background: var(--s2); border-radius: 8px; padding: .5rem .7rem; font-size: 12px; color: var(--muted); }
    .ck-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .7rem; border-radius: 999px; font-size: 12px; font-weight: 600;
               border: 1px solid var(--border2); color: var(--muted); background: var(--s3); cursor: pointer; }
    .ck-pill input { position: absolute; opacity: 0; pointer-events: none; }
    .ck-dot { width: 9px; height: 9px; border-radius: 999px; display: inline-block; flex-shrink: 0; }
    .ck-who { display: flex; align-items: flex-start; gap: .5rem; padding: .4rem 0; border-top: 1px dashed var(--border2); font-size: 12px; }
</style>

@if(!$sys['has_doc'])
    <div class="card px-5 py-4 text-sm" style="color:var(--muted)">O acompanhamento das metas usa o documento em campos do planejamento. Este planejamento ainda está no formato antigo.</div>
@else

{{-- Cabeçalho --}}
<div class="card px-5 py-4 mb-4">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Quem confere</span>
            @if($resp)
                <p class="font-semibold" style="color:var(--text)">{{ $resp->name }}</p>
            @else
                <p class="font-semibold" style="color:var(--orange)">Sem responsável: escolha em Configurações</p>
            @endif
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Conferir</span>
            <p style="color:var(--text)">A cada {{ MacroPlanCheckin::DAYS }} dias</p>
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Próxima conferência</span>
            <p style="color:{{ $proxima && $proxima->lt(today()) ? 'var(--orange)' : 'var(--text)' }}">
                @if($proxima)
                    {{ $proxima->format('d/m/Y') }}{{ $proxima->lt(today()) ? ' (atrasada)' : ($proxima->isToday() ? ' (hoje)' : '') }}
                    · {{ ['mensal' => 'do mês', 'meio' => 'revisão do meio', 'fechamento' => 'fechamento'][$sys['momento']] ?? '' }}
                @else
                    {{ $sys['fechada'] ? 'Ciclo fechado' : '—' }}
                @endif
            </p>
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Revisão do meio</span>
            <p style="color:var(--text)">{{ $dt($sys['revisao']) }}</p>
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Fechamento</span>
            <p style="color:var(--text)">{{ $dt($sys['fechamento']) }}@if($sys['fechada']) <b style="color:#16a34a">· registrado</b>@endif</p>
        </div>
        @if($sys['ritmo'] !== null)
            <div class="flex-1 min-w-[10rem]">
                <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Tempo do ciclo que já passou</span>
                <div class="flex items-center gap-2 mt-1">
                    <div class="flex-1 h-2 rounded-full" style="background:var(--s3)"><div class="h-2 rounded-full" style="width:{{ $sys['ritmo'] }}%; background:var(--purple)"></div></div>
                    <span class="text-xs font-semibold" style="color:var(--text)">{{ $sys['ritmo'] }}%</span>
                </div>
            </div>
        @endif
    </div>
</div>

@if($errors->any())
    <div class="card px-4 py-3 mb-4 text-sm" style="border-color:var(--orange); color:var(--orange)">{{ $errors->first() }}</div>
@endif

@if($sys['fechada'])
    <div class="card px-5 py-4 mb-4">
        <p class="card-title mb-1">Ciclo fechado</p>
        <p class="text-sm" style="color:var(--muted)">O resultado de cada meta está na seção Fechamento do documento e é a primeira coisa que o próximo planejamento lê.</p>
    </div>
@else
<form method="POST" action="{{ route('macroplan-checkins.store', $macroplan) }}" class="card px-5 py-4 mb-4"
      x-data="checkinForm(@js($initial), {{ $sys['ritmo'] ?? 'null' }})">
    @csrf
    <p class="card-title mb-1">Conferir as metas do ciclo</p>
    <p class="text-xs mb-4" style="color:var(--muted)">As metas do planejamento são as mais importantes. Para cada uma, veja como estão as campanhas e projetos que ajudam nela e avalie. Só o próximo passo é obrigatório.</p>

    <p class="text-[11px] font-semibold uppercase mb-2" style="color:var(--purple); letter-spacing:.06em">1 · Metas do ciclo</p>
    <div class="flex flex-col gap-3 mb-5">
        @forelse($sys['metas'] as $m)
            @php $k = $m['key']; @endphp
            <div class="ck-row">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <p class="text-sm font-semibold" style="color:var(--text)">{{ $m['nome'] }}</p>
                    <template x-if="light('{{ $k }}')">
                        <span class="flex items-center gap-1 text-xs font-semibold flex-shrink-0" :style="'color:' + lightColor('{{ $k }}')">
                            <span class="ck-dot" :style="'background:' + lightColor('{{ $k }}')"></span><span x-text="lightLabel('{{ $k }}')"></span>
                        </span>
                    </template>
                </div>
                <div class="ck-sys mb-2 flex flex-wrap gap-x-4 gap-y-1">
                    <span>Ponto de partida: <b style="color:var(--text)">{{ $m['partida'] ?: '—' }}</b></span>
                    <span>Quanto queremos: <b style="color:var(--text)">{{ $m['meta'] ?: '—' }}</b></span>
                    <span>Até: <b style="color:var(--text)">{{ $dt($m['ate']) }}</b></span>
                    @if($m['fonte'])<span>Onde vemos: <b style="color:var(--text)">{{ $m['fonte'] }}</b></span>@endif
                    @if($m['ultimo'])<span>Última conferência: <b style="color:var(--text)">{{ $m['ultimo'] }}</b></span>@endif
                </div>

                {{-- Quem ajuda a bater esta meta --}}
                <div class="mb-2">
                    <p class="text-[11px] font-semibold uppercase mb-0.5" style="color:var(--muted); letter-spacing:.06em">Quem ajuda a bater esta meta</p>
                    @forelse($m['quem'] as $q)
                        @php $s = $q['conferencia'] ? (ProjectCheckin::SITUACOES[$q['conferencia']['situacao']] ?? null) : null; @endphp
                        <div class="ck-who">
                            <span class="ck-dot mt-1" style="background:{{ $s['color'] ?? 'var(--border2)' }}"></span>
                            <div class="min-w-0 flex-1">
                                @if($q['url'])
                                    <a href="{{ $q['url'] }}" class="font-semibold hover:underline" style="color:var(--text)">{{ $q['nome'] }}</a>
                                @else
                                    <span class="font-semibold" style="color:var(--text)">{{ $q['nome'] }}</span>
                                @endif
                                <span style="color:var(--muted)">· {{ $q['tipo'] === 'projeto' ? 'Projeto' : 'Campanha' }}{{ $q['status'] ? ' · ' . $q['status'] : '' }}</span>
                                @if(!$q['criada'])
                                    <p style="color:var(--muted2)">Ainda não criada a partir do planejamento.</p>
                                @elseif($q['conferencia'])
                                    @php $c = $q['conferencia']; @endphp
                                    <p style="color:var(--muted)">
                                        Conferida em {{ $c['data'] }}: <b style="color:{{ $s['color'] ?? 'var(--text)' }}">{{ $s['label'] ?? '' }}</b>
                                        @if($c['atual']) · número principal em {{ $c['atual'] }}@endif
                                        @if($c['avaliacao']) · {{ mb_strtolower(ProjectCheckin::AVALIACOES[$c['avaliacao']] ?? '') }}@endif
                                        @if($c['fechada']) · <b style="color:#16a34a">fechada</b>@endif
                                    </p>
                                    <p class="truncate" style="color:var(--muted)">Próximo passo: {{ $c['proximo'] }}</p>
                                @else
                                    <p style="color:var(--muted2)">Sem conferência ainda.</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs" style="color:var(--orange)">Nenhuma campanha ou projeto marcado para ajudar nesta meta.</p>
                    @endforelse
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" name="metas[{{ $k }}][atual]" x-model="metas['{{ $k }}'].atual" maxlength="120"
                           class="field px-3 py-1.5 text-sm w-40" placeholder="Quanto está hoje">
                    @foreach(ProjectCheckin::AVALIACOES as $val => $label)
                        <label class="ck-pill relative" :style="metas['{{ $k }}'].avaliacao === '{{ $val }}' ? 'background:rgba(100,59,142,.14); border-color:rgba(100,59,142,.45); color:var(--purple)' : ''">
                            <input type="radio" name="metas[{{ $k }}][avaliacao]" value="{{ $val }}" x-model="metas['{{ $k }}'].avaliacao">{{ $label }}
                        </label>
                    @endforeach
                </div>
                <input type="text" name="metas[{{ $k }}][comentario]" value="{{ old("metas.$k.comentario") }}" maxlength="1000"
                       class="field w-full px-3 py-1.5 text-sm mt-2" placeholder="Por quê? (opcional)">
                <div x-show="fechar" x-cloak class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="text-xs font-semibold" style="color:var(--text)">Resultado final:</span>
                    <select name="metas[{{ $k }}][resultado]" class="field px-3 py-1.5 text-sm">
                        <option value="">Escolha…</option>
                        @foreach(MacroPlanCheckin::RESULTADOS as $val => $label)
                            <option value="{{ $val }}" @selected(old("metas.$k.resultado") === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @empty
            <p class="text-sm" style="color:var(--muted)">O documento ainda não tem metas (seção 02).</p>
        @endforelse
    </div>

    <p class="text-[11px] font-semibold uppercase mb-2" style="color:var(--purple); letter-spacing:.06em">2 · O ciclo como um todo</p>
    <div class="ck-row mb-4">
        <p class="text-xs mb-2" style="color:var(--muted)">Situação geral</p>
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach(ProjectCheckin::SITUACOES as $val => $s)
                <label class="ck-pill relative" :style="situacao === '{{ $val }}' ? 'background:{{ $s['color'] }}1f; border-color:{{ $s['color'] }}; color:{{ $s['color'] }}' : ''">
                    <input type="radio" name="situacao" value="{{ $val }}" x-model="situacao" required>
                    <span class="ck-dot" style="background:{{ $s['color'] }}"></span>{{ $s['label'] }}
                </label>
            @endforeach
        </div>
        <label class="block text-xs mb-1" style="color:var(--muted)">O que travou (opcional)</label>
        <textarea name="travou" rows="2" maxlength="2000" class="field w-full px-3 py-2 text-sm mb-2" style="resize:vertical">{{ old('travou') }}</textarea>
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="text-xs" style="color:var(--muted)">Depende de quem?</span>
            @foreach(ProjectCheckin::DEPENDE as $val => $label)
                <label class="ck-pill relative"><input type="radio" name="depende" value="{{ $val }}" @checked(old('depende') === $val)
                    onchange="this.closest('div').querySelectorAll('.ck-pill').forEach(p => p.style.cssText=''); this.parentElement.style.cssText='background:rgba(100,59,142,.14); border-color:rgba(100,59,142,.45); color:var(--purple)'">{{ $label }}</label>
            @endforeach
        </div>
        <label class="block text-xs mb-1 font-semibold" style="color:var(--text)">Próximo passo</label>
        <textarea name="proximo_passo" rows="2" maxlength="2000" required class="field w-full px-3 py-2 text-sm" style="resize:vertical"
                  placeholder="O que muda no ciclo até a próxima conferência, e quem faz">{{ old('proximo_passo') }}</textarea>
    </div>

    <div class="ck-row mb-4" :style="fechar ? 'border-color:var(--purple)' : ''">
        <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer" style="color:var(--text)">
            <input type="checkbox" name="fechar" value="1" x-model="fechar">
            Esta é a conferência de fechamento do ciclo
        </label>
        <p class="text-xs mt-1" style="color:var(--muted)">
            @if($closeDue)Chegou a data do fechamento ({{ $dt($sys['fechamento']) }}).@else Marque só no fim do ciclo.@endif
            Cada meta ganha o campo "Resultado final", e tudo vai para a seção Fechamento do documento, que o próximo planejamento lê.</p>
        <div x-show="fechar" x-cloak class="flex flex-col gap-3 mt-3">
            @foreach($sys['fech_fields'] as $f)
                <div>
                    <label class="block text-xs mb-1 font-semibold" style="color:var(--text)">{{ $f['label'] }}</label>
                    <textarea name="fech[{{ $f['key'] }}]" rows="2" maxlength="8000" class="field w-full px-3 py-2 text-sm" style="resize:vertical">{{ old("fech.{$f['key']}", $f['value']) }}</textarea>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary" x-text="fechar ? 'Registrar fechamento do ciclo' : 'Registrar conferência'">Registrar conferência</button>
    </div>
</form>
@endif

{{-- Histórico --}}
<div class="card px-5 py-4 mb-6">
    <p class="card-title mb-3">Conferências anteriores ({{ $checkins->count() }})</p>
    @forelse($checkins as $ck)
        @php $s = ProjectCheckin::SITUACOES[$ck->situacao] ?? null; @endphp
        <div class="py-3" style="border-top:1px solid var(--border2)">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="ck-dot" style="background:{{ $s['color'] ?? 'var(--muted)' }}"></span>
                <b class="text-sm" style="color:var(--text)">{{ $ck->checked_on->format('d/m/Y') }} · {{ $s['label'] ?? $ck->situacao }}</b>
                <span class="text-[10px] font-semibold px-1.5 rounded" style="background:{{ $ck->is_closing ? 'rgba(22,163,74,.12); color:#16a34a' : 'var(--s3); color:var(--muted)' }}">{{ MacroPlanCheckin::MOMENTS[$ck->moment] ?? $ck->moment }}</span>
                <span class="text-xs" style="color:var(--muted)">por {{ $ck->user?->name ?? '—' }}</span>
                @if($ck->user_id === auth()->id() && $ck->created_at->gt(now()->subDay()))
                    <form method="POST" action="{{ route('macroplan-checkins.destroy', [$macroplan, $ck]) }}" class="ml-auto"
                          x-data @submit.prevent="if (await $store.confirmDialog.ask('Apagar esta conferência?')) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs" style="color:var(--muted2)">Apagar</button>
                    </form>
                @endif
            </div>
            @foreach($ck->metas ?? [] as $m)
                @php $l = $lights[$m['semaforo'] ?? ''] ?? null; @endphp
                <p class="text-xs py-0.5" style="color:var(--muted)">
                    @if($l)<span class="ck-dot mr-1" style="background:{{ $l[0] }}"></span>@endif
                    {{ $m['nome'] }}@if($m['atual']): <b style="color:var(--text)">{{ $m['atual'] }}</b>@endif
                    @if($m['meta']) (queremos {{ $m['meta'] }})@endif
                    @if($m['avaliacao']) · {{ ProjectCheckin::AVALIACOES[$m['avaliacao']] ?? '' }}@endif
                    @if(!empty($m['resultado'])) · <b style="color:var(--text)">resultado: {{ MacroPlanCheckin::RESULTADOS[$m['resultado']] ?? '' }}</b>@endif
                    @if($m['comentario']) · {{ $m['comentario'] }}@endif
                </p>
            @endforeach
            @if($ck->travou)
                <p class="text-xs py-0.5" style="color:var(--muted)">Travou: {{ $ck->travou }}@if($ck->depende) ({{ \Illuminate\Support\Str::lower(ProjectCheckin::DEPENDE[$ck->depende] ?? '') }})@endif</p>
            @endif
            <p class="text-sm mt-1" style="color:var(--text)"><b>Próximo passo:</b> {{ $ck->proximo_passo }}</p>
        </div>
    @empty
        <p class="text-sm" style="color:var(--muted)">Nenhuma conferência ainda.</p>
    @endforelse
</div>
@endif
