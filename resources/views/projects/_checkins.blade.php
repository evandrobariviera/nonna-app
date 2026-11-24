{{-- Aba Acompanhamento da campanha/projeto (etapa E.2, 24/11). Três blocos — Metas, Pontos de
     cuidado, Andamento — com o pilar Sistema (App\Services\Projects\CheckinSystem) ao lado de cada
     um; o coordenador só avalia. Embaixo, o histórico das conferências. Sem IA.
     Recebe: $project. --}}
@php
    use App\Models\ProjectCheckin;
    $sys      = app(\App\Services\Projects\CheckinSystem::class)->build($project);
    $checkins = $project->checkins()->with('user:id,name')->get();
    $coord    = $project->coordinator_id ? \App\Models\User::withoutGlobalScopes()->find($project->coordinator_id, ['id', 'name']) : null;
    $and      = $sys['andamento'];
    $ads      = $sys['anuncios'];
    $proxima  = $sys['proxima'] ? \Carbon\Carbon::parse($sys['proxima']) : null;
    $lights   = ['verde' => ['#16a34a', 'No ritmo'], 'amarelo' => ['#EE7919', 'Abaixo do ritmo'], 'vermelho' => ['#dc2626', 'Bem abaixo do ritmo']];
    $money    = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $secNum   = $sys['kind'] === 'campanha' ? '09' : '06';
    $isCampaign = $sys['kind'] === 'campanha';
    $initial = [
        'metas'    => collect($sys['metas'])->mapWithKeys(fn ($m) => [$m['key'] => [
            'atual' => old("metas.{$m['key']}.atual", $m['ultimo']), 'avaliacao' => old("metas.{$m['key']}.avaliacao"),
        ]])->all(),
        'cuidados' => collect($sys['cuidados'])->mapWithKeys(fn ($c) => [$c['id'] => ['aconteceu' => old("cuidados.{$c['id']}.aconteceu")]])->all(),
        'situacao' => old('situacao'),
        'fechar'   => (bool) old('fechar', $sys['hora_fechar'] && $proxima && $proxima->lte(today())),
        'ref'      => collect($sys['metas'])->mapWithKeys(fn ($m) => [$m['key'] => [$m['partida'], $m['meta']]])->all(),
    ];
@endphp

<style>
    .ck-grid { display: grid; gap: 1rem; }
    @media (min-width: 64rem) { .ck-grid { grid-template-columns: minmax(0, 1fr) 300px; align-items: start; } }
    .ck-row { border: 1px solid var(--border2); border-radius: 10px; padding: .75rem .9rem; background: var(--s1); }
    .ck-sys { background: var(--s2); border-radius: 8px; padding: .5rem .7rem; font-size: 12px; color: var(--muted); }
    .ck-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .7rem; border-radius: 999px; font-size: 12px; font-weight: 600;
               border: 1px solid var(--border2); color: var(--muted); background: var(--s3); cursor: pointer; }
    .ck-pill input { position: absolute; opacity: 0; pointer-events: none; }
    .ck-dot { width: 9px; height: 9px; border-radius: 999px; display: inline-block; flex-shrink: 0; }
    .ck-stat { display: flex; justify-content: space-between; gap: .5rem; font-size: 13px; padding: .3rem 0; border-bottom: 1px solid var(--border2); }
    .ck-stat:last-child { border-bottom: 0; }
</style>

{{-- Cabeçalho: coordenador e ritmo --}}
<div class="card px-5 py-4 mb-4">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Coordenador</span>
            @if($coord)
                <p class="font-semibold" style="color:var(--text)">{{ $coord->name }}</p>
            @else
                <p class="font-semibold" style="color:var(--orange)">Sem coordenador: escolha na seção {{ $secNum }} do documento</p>
            @endif
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Conferir</span>
            <p style="color:var(--text)">{{ $sys['checkin_dias'] ? 'A cada ' . $sys['checkin_dias'] . ' dias' : 'Prazo não definido no documento' }}</p>
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Próxima conferência</span>
            <p style="color:{{ $proxima && $proxima->lt(today()) ? 'var(--orange)' : 'var(--text)' }}">
                {{ $proxima ? $proxima->format('d/m/Y') . ($proxima->lt(today()) ? ' (atrasada)' : ($proxima->isToday() ? ' (hoje)' : '')) . ($sys['hora_fechar'] ? ' · fechamento' : '') : ($sys['fechada'] ? 'Encerrado' : '—') }}</p>
        </div>
        <div>
            <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Fechamento</span>
            <p style="color:var(--text)">{{ $sys['fechamento'] ? \Carbon\Carbon::parse($sys['fechamento'])->format('d/m/Y') : 'Sem data no documento' }}@if($sys['fechada']) <b style="color:#16a34a">· registrado</b>@endif</p>
        </div>
        @if($sys['ritmo'] !== null)
            <div class="flex-1 min-w-[10rem]">
                <span class="text-[11px] font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em">Tempo que já passou</span>
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

<div class="ck-grid mb-6">
    {{-- Formulário da conferência (some depois do fechamento) --}}
    @if($sys['fechada'])
    <div class="card px-5 py-4">
        <p class="card-title mb-1">Fechamento registrado</p>
        <p class="text-sm" style="color:var(--muted)">O resultado está na seção Fechamento do documento e entra no próximo planejamento. Não abrimos mais conferências {{ $isCampaign ? 'desta campanha' : 'deste projeto' }}.</p>
    </div>
    @else
    <form method="POST" action="{{ route('project-checkins.store', $project) }}" class="card px-5 py-4"
          x-data="checkinForm(@js($initial), {{ $sys['ritmo'] ?? 'null' }})">
        @csrf
        <p class="card-title mb-1">Nova conferência</p>
        <p class="text-xs mb-4" style="color:var(--muted)">O App mostra o que sabe sozinho; você avalia. Só o próximo passo é obrigatório.</p>

        {{-- 1. Metas --}}
        <p class="text-[11px] font-semibold uppercase mb-2" style="color:var(--purple); letter-spacing:.06em">1 · Metas</p>
        <div class="flex flex-col gap-2 mb-5">
            @forelse($sys['metas'] as $m)
                @php $k = $m['key']; @endphp
                <div class="ck-row">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <p class="text-sm font-semibold" style="color:var(--text)">{{ $m['nome'] }}
                            @if($m['principal'] && $isCampaign)<span class="text-[10px] font-semibold ml-1 px-1.5 rounded" style="background:rgba(100,59,142,.1); color:var(--purple)">principal</span>@endif</p>
                        <template x-if="light('{{ $k }}')">
                            <span class="flex items-center gap-1 text-xs font-semibold flex-shrink-0" :style="'color:' + lightColor('{{ $k }}')">
                                <span class="ck-dot" :style="'background:' + lightColor('{{ $k }}')"></span><span x-text="lightLabel('{{ $k }}')"></span>
                            </span>
                        </template>
                    </div>
                    @if($m['partida'] !== null || $m['meta'] !== null)
                        <div class="ck-sys mb-2 flex flex-wrap gap-x-4 gap-y-1">
                            <span>Ponto de partida: <b style="color:var(--text)">{{ $m['partida'] ?: '—' }}</b></span>
                            <span>Quanto queremos: <b style="color:var(--text)">{{ $m['meta'] ?: '—' }}</b></span>
                            @if($m['ultimo'])<span>Última conferência: <b style="color:var(--text)">{{ $m['ultimo'] }}</b></span>@endif
                        </div>
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        @if($m['key'] !== 'entrega')
                            <input type="text" name="metas[{{ $k }}][atual]" x-model="metas['{{ $k }}'].atual" maxlength="120"
                                   class="field px-3 py-1.5 text-sm w-40" placeholder="Quanto está hoje">
                        @endif
                        @foreach(ProjectCheckin::AVALIACOES as $val => $label)
                            <label class="ck-pill relative" :style="metas['{{ $k }}'].avaliacao === '{{ $val }}' ? 'background:rgba(100,59,142,.14); border-color:rgba(100,59,142,.45); color:var(--purple)' : ''">
                                <input type="radio" name="metas[{{ $k }}][avaliacao]" value="{{ $val }}" x-model="metas['{{ $k }}'].avaliacao">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <input type="text" name="metas[{{ $k }}][comentario]" value="{{ old("metas.$k.comentario") }}" maxlength="1000"
                           class="field w-full px-3 py-1.5 text-sm mt-2" placeholder="Por quê? (opcional)">
                </div>
            @empty
                <p class="text-sm" style="color:var(--muted)">O documento ainda não tem {{ $isCampaign ? 'número principal (seção 01)' : '"está pronto quando" nem "o que melhora" (seção 01)' }}.</p>
            @endforelse
        </div>

        {{-- 2. Pontos de cuidado --}}
        <p class="text-[11px] font-semibold uppercase mb-2" style="color:var(--purple); letter-spacing:.06em">2 · Pontos de cuidado</p>
        <div class="flex flex-col gap-2 mb-5">
            @forelse($sys['cuidados'] as $c)
                @php $id = $c['id']; @endphp
                <div class="ck-row">
                    <p class="text-sm font-semibold" style="color:var(--text)">Se {{ \Illuminate\Support\Str::lcfirst($c['se']) }}</p>
                    @if($c['fazemos'])<p class="text-xs mb-2" style="color:var(--muted)">Combinado: {{ $c['fazemos'] }}@if($c['quem']) ({{ $c['quem'] }})@endif</p>@endif
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs" style="color:var(--muted)">Aconteceu?</span>
                        @foreach(['nao' => 'Não', 'sim' => 'Sim'] as $val => $label)
                            <label class="ck-pill relative" :style="cuidados['{{ $id }}'].aconteceu === '{{ $val }}' ? ('{{ $val }}' === 'sim' ? 'background:rgba(238,121,25,.12); border-color:var(--orange); color:var(--orange)' : 'background:rgba(22,163,74,.1); border-color:#16a34a; color:#16a34a') : ''">
                                <input type="radio" name="cuidados[{{ $id }}][aconteceu]" value="{{ $val }}" x-model="cuidados['{{ $id }}'].aconteceu">{{ $label }}
                            </label>
                        @endforeach
                        <template x-if="cuidados['{{ $id }}'].aconteceu === 'sim'">
                            <span class="flex flex-wrap items-center gap-2 ml-2">
                                <span class="text-xs" style="color:var(--muted)">Fizemos o combinado?</span>
                                @foreach(ProjectCheckin::COMBINADO as $val => $label)
                                    <label class="ck-pill relative"><input type="radio" name="cuidados[{{ $id }}][combinado]" value="{{ $val }}" @checked(old("cuidados.$id.combinado") === $val)
                                        onchange="this.closest('span').querySelectorAll('.ck-pill').forEach(p => p.style.cssText=''); this.parentElement.style.cssText='background:rgba(100,59,142,.14); border-color:rgba(100,59,142,.45); color:var(--purple)'">{{ $label }}</label>
                                @endforeach
                            </span>
                        </template>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color:var(--muted)">O documento não tem sinais de alerta.</p>
            @endforelse
        </div>

        {{-- 3. Andamento --}}
        <p class="text-[11px] font-semibold uppercase mb-2" style="color:var(--purple); letter-spacing:.06em">3 · Andamento</p>
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
                      placeholder="O que vamos fazer até a próxima conferência, e quem faz">{{ old('proximo_passo') }}</textarea>
        </div>

        {{-- Fechamento: a última conferência preenche a seção Fechamento do documento. --}}
        @if($sys['fech_fields'])
        <div class="ck-row mb-4" :style="fechar ? 'border-color:var(--purple)' : ''">
            <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer" style="color:var(--text)">
                <input type="checkbox" name="fechar" value="1" x-model="fechar">
                Esta é a conferência de fechamento
            </label>
            <p class="text-xs mt-1" style="color:var(--muted)">
                @if($sys['hora_fechar'])Chegou a data do fechamento ({{ \Carbon\Carbon::parse($sys['fechamento'])->format('d/m/Y') }}).@else Marque só se {{ $isCampaign ? 'a campanha terminou' : 'o projeto terminou e já dá para conferir o ganho' }}.@endif
                O que você escrever aqui vai para a seção Fechamento do documento, que o próximo planejamento lê.</p>
            <div x-show="fechar" x-cloak class="flex flex-col gap-3 mt-3">
                @foreach($sys['fech_fields'] as $f)
                    <div>
                        <label class="block text-xs mb-1 font-semibold" style="color:var(--text)">{{ $f['label'] }}</label>
                        @if($f['input'] === 'select')
                            <select name="fech[{{ $f['key'] }}]" class="field px-3 py-2 text-sm">
                                <option value="">Escolha…</option>
                                @foreach($f['options'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old("fech.{$f['key']}", $f['value']) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <textarea name="fech[{{ $f['key'] }}]" rows="2" maxlength="8000" class="field w-full px-3 py-2 text-sm" style="resize:vertical"
                                      placeholder="{{ $f['placeholder'] ?? '' }}">{{ old("fech.{$f['key']}", $f['value']) }}</textarea>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary" x-text="fechar ? 'Registrar fechamento' : 'Registrar conferência'">Registrar conferência</button>
        </div>
    </form>
    @endif

    {{-- O que o Sistema vê --}}
    <div class="card px-5 py-4">
        <p class="card-title mb-3">O que o App vê hoje</p>
        <div class="ck-stat"><span style="color:var(--muted)">Tarefas concluídas</span><b>{{ $and['tarefas_concluidas'] }} de {{ $and['tarefas_total'] }}</b></div>
        <div class="ck-stat"><span style="color:var(--muted)">Tarefas atrasadas</span><b style="color:{{ $and['tarefas_atrasadas'] ? 'var(--orange)' : 'var(--text)' }}">{{ $and['tarefas_atrasadas'] }}</b></div>
        @foreach($and['atrasadas'] as $t)
            <p class="text-xs pl-2 py-0.5 truncate" style="color:var(--muted)">· {{ $t['title'] }} ({{ $t['due'] }})</p>
        @endforeach
        <div class="ck-stat"><span style="color:var(--muted)">Ajustes pedidos pelo cliente</span><b>{{ $and['ajustes_pedidos'] }}</b></div>
        <div class="ck-stat"><span style="color:var(--muted)">Pendências do cliente</span><b style="color:{{ $and['pendencias_cliente'] ? 'var(--orange)' : 'var(--text)' }}">{{ $and['pendencias_cliente'] }}</b></div>
        @foreach($and['pend_cliente_lista'] as $p)
            <p class="text-xs pl-2 py-0.5 truncate" style="color:var(--muted)">· {{ $p['title'] }}@if($p['due']) (até {{ $p['due'] }})@endif</p>
        @endforeach
        <div class="ck-stat"><span style="color:var(--muted)">Pendências da equipe</span><b>{{ $and['pendencias_equipe'] }}</b></div>

        @if($ads)
            <p class="text-[11px] font-semibold uppercase mt-4 mb-1" style="color:var(--muted); letter-spacing:.06em">Anúncios desde {{ $ads['desde'] }}</p>
            <div class="ck-stat"><span style="color:var(--muted)">Gasto</span><b>{{ $money($ads['gasto']) }}</b></div>
            <div class="ck-stat"><span style="color:var(--muted)">Resultados</span><b>{{ $ads['resultados'] }}</b></div>
            <div class="ck-stat"><span style="color:var(--muted)">Custo por resultado</span><b>{{ $ads['custo'] !== null ? $money($ads['custo']) : '—' }}</b></div>
            <div class="ck-stat"><span style="color:var(--muted)">CTR</span><b>{{ $ads['ctr'] !== null ? number_format($ads['ctr'], 2, ',', '.') . '%' : '—' }}</b></div>
        @elseif($isCampaign)
            <p class="text-xs mt-4" style="color:var(--muted2)">Sem anúncio ligado a esta campanha na Central de Campanhas.</p>
        @endif

        @if($and['alertas_central'])
            <p class="text-[11px] font-semibold uppercase mt-4 mb-1" style="color:var(--muted); letter-spacing:.06em">Alertas da Central em aberto</p>
            @foreach($and['alertas_central'] as $a)
                <p class="text-xs py-0.5" style="color:{{ $a['severity'] === 'critico' ? '#dc2626' : 'var(--orange)' }}">· {{ $a['title'] }}</p>
            @endforeach
        @endif
    </div>
</div>

{{-- Histórico --}}
<div class="card px-5 py-4 mb-6">
    <p class="card-title mb-3">Conferências anteriores ({{ $checkins->count() }})</p>
    @forelse($checkins as $ck)
        @php $s = ProjectCheckin::SITUACOES[$ck->situacao] ?? null; @endphp
        <div class="py-3" style="border-top:1px solid var(--border2)">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="ck-dot" style="background:{{ $s['color'] ?? 'var(--muted)' }}"></span>
                <b class="text-sm" style="color:var(--text)">{{ $ck->checked_on->format('d/m/Y') }} · {{ $s['label'] ?? $ck->situacao }}</b>
                @if($ck->is_closing)<span class="text-[10px] font-semibold px-1.5 rounded" style="background:rgba(22,163,74,.12); color:#16a34a">Fechamento</span>@endif
                <span class="text-xs" style="color:var(--muted)">por {{ $ck->user?->name ?? '—' }}</span>
                @if($ck->user_id === auth()->id() && $ck->created_at->gt(now()->subDay()))
                    <form method="POST" action="{{ route('project-checkins.destroy', [$project, $ck]) }}" class="ml-auto"
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
                    @if($m['comentario']) · {{ $m['comentario'] }}@endif
                </p>
            @endforeach
            @foreach(collect($ck->cuidados ?? [])->where('aconteceu', 'sim') as $c)
                <p class="text-xs py-0.5" style="color:var(--orange)">Aconteceu: {{ $c['se'] }}@if($c['combinado']) · combinado: {{ ProjectCheckin::COMBINADO[$c['combinado']] ?? '' }}@endif</p>
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
