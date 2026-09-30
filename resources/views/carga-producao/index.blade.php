{{-- Carga de Produção — quanto cada cliente consumiu da agência, por período, executor,
     responsável e tipo. Leitura: números do período → cliente × tipo → pessoas → as
     tarefas por trás dos números (com exportação). Lógica em ProductionLoadService. --}}
<x-app-layout>
    <x-slot name="header">Carga de Produção</x-slot>

    @php
        $qs = request()->query();
        $periodoTexto = $inicio->format('d/m/Y') . ' a ' . $fim->format('d/m/Y');
        $campo = 'text-xs px-3 py-1.5 rounded-lg';
        $campoStyle = 'background:var(--s2); border:1px solid var(--border2); color:var(--text)';
    @endphp

    <p class="text-sm mb-3" style="color:var(--muted)">
        Quanto foi executado e quanto está em execução — por cliente, período, pessoa e tipo de tarefa.
    </p>

    {{-- ── Filtros ── --}}
    <form method="GET" action="{{ route('production-load.index') }}" x-data="{ periodo: '{{ $f['periodo'] }}' }"
          class="card card-body mb-4 flex items-end gap-3 flex-wrap">
        <label class="flex flex-col gap-1">
            <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Período</span>
            <select name="periodo" x-model="periodo" @change="periodo !== 'custom' && $el.form.submit()" class="{{ $campo }}" style="{{ $campoStyle }}">
                @foreach(\App\Services\Production\ProductionLoadService::PERIODOS as $k => $label)
                    <option value="{{ $k }}" @selected($f['periodo'] === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <template x-if="periodo === 'custom'">
            <div class="flex items-end gap-2">
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">De</span>
                    <input type="date" name="inicio" value="{{ $inicio->toDateString() }}" class="{{ $campo }}" style="{{ $campoStyle }}">
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Até</span>
                    <input type="date" name="fim" value="{{ $fim->toDateString() }}" class="{{ $campo }}" style="{{ $campoStyle }}">
                </label>
            </div>
        </template>
        <label class="flex flex-col gap-1">
            <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Cliente</span>
            <select name="cliente" onchange="this.form.submit()" class="{{ $campo }}" style="{{ $campoStyle }}; max-width:220px">
                <option value="">Todos os clientes</option>
                @foreach($opcoesClientes as $c)
                    <option value="{{ $c->id }}" @selected($f['cliente'] === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Executor</span>
            <select name="executor" onchange="this.form.submit()" class="{{ $campo }}" style="{{ $campoStyle }}">
                <option value="">Todos</option>
                @foreach($opcoesPessoas as $u)
                    <option value="{{ $u->id }}" @selected($f['executor'] == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Responsável</span>
            <select name="responsavel" onchange="this.form.submit()" class="{{ $campo }}" style="{{ $campoStyle }}">
                <option value="">Todos</option>
                @foreach($opcoesPessoas as $u)
                    <option value="{{ $u->id }}" @selected($f['responsavel'] == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Tipo de tarefa</span>
            <select name="tipo" onchange="this.form.submit()" class="{{ $campo }}" style="{{ $campoStyle }}">
                <option value="">Todos</option>
                @foreach(\App\Models\Task::$types as $k => $label)
                    <option value="{{ $k }}" @selected($f['tipo'] === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-1.5 text-xs pb-1.5" style="color:var(--muted)">
            <input type="checkbox" name="inativos" value="1" onchange="this.form.submit()" @checked($f['inativos']) style="accent-color:var(--purple)">
            Incluir clientes inativos
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
        @if(count(array_filter(\Illuminate\Support\Arr::except($qs, ['periodo', 'inicio', 'fim']))))
            <a href="{{ route('production-load.index', \Illuminate\Support\Arr::only($qs, ['periodo', 'inicio', 'fim'])) }}" class="text-xs pb-1.5" style="color:var(--muted)">Limpar filtros</a>
        @endif
    </form>

    <p class="text-xs mb-4" style="color:var(--muted2)">
        Período: <strong style="color:var(--text)">{{ $periodoTexto }}</strong>.
        @if($inicio->toDateString() < $historicoDesde)
            <span style="color:var(--orange)">
                Atenção: o registro de data de conclusão só existe a partir de {{ \Carbon\Carbon::parse($historicoDesde)->format('d/m/Y') }}
                — tarefas concluídas antes disso (vindas do ClickUp) não aparecem como executadas.
            </span>
        @endif
    </p>

    {{-- ── Números do período ── --}}
    @php
        $tiles = [
            ['Executadas', $resumo['executadas'], 'concluídas dentro do período', 'var(--green)'],
            ['Em execução agora', $resumo['em_execucao'], 'abertas hoje (não depende do período)', 'var(--purple)'],
            ['Pedidas no período', $resumo['pedidas'], 'tarefas criadas entre as datas', 'var(--text)'],
            ['Entregues no prazo', $resumo['no_prazo_pct'] !== null ? $resumo['no_prazo_pct'] . '%' : '—',
                $resumo['com_prazo'] ? "das {$resumo['com_prazo']} executadas que tinham data de entrega" : 'nenhuma executada tinha data de entrega',
                $resumo['no_prazo_pct'] === null ? 'var(--muted)' : ($resumo['no_prazo_pct'] >= 80 ? 'var(--green)' : 'var(--orange)')],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        @foreach($tiles as [$label, $valor, $ajuda, $cor])
            <div class="card card-body min-w-0">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">{{ $label }}</p>
                <p class="text-2xl font-black" style="color:{{ $cor }}">{{ $valor }}</p>
                <p class="text-xs mt-1" style="color:var(--muted2)">{{ $ajuda }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Limites mensais (controle de produção da ficha do cliente) ── --}}
    @php
        $mesLimite = $limites['mes']->locale('pt_BR')->translatedFormat('F/Y');
        $notaMes = $limites['mes_do_periodo'] ? '' : ' (o período escolhido não é um mês inteiro, então vale o mês atual)';
    @endphp
    @if($clienteSel)
        @php $lc = $limites['clientes']->first(); @endphp
        <div class="card mb-4" x-data="{ editando: {{ $lc ? 'false' : 'true' }} }">
            <div class="px-5 pt-4 pb-3 flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">
                        Controle de produção · {{ $clienteSel->displayName() }} · {{ $mesLimite }}
                    </p>
                    <p class="text-xs mt-1" style="color:var(--muted2)">
                        Limite combinado × quanto já foi pedido no mês (mesma conta da ficha do cliente) e quanto disso já foi executado{{ $notaMes }}.
                    </p>
                </div>
                <button type="button" @click="editando = !editando" class="btn btn-ghost btn-sm" x-text="editando ? 'Cancelar' : 'Editar limites'"></button>
            </div>

            @if($lc)
                <div class="px-5 pb-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3" x-show="!editando">
                    @foreach($lc['tipos'] as $t)
                        @php
                            $cor = $t['acima'] > 0 ? 'var(--red)' : ($t['pedido'] >= $t['limite'] ? 'var(--orange)' : 'var(--purple)');
                            $pctPed = min(100, round($t['pedido'] / max(1, $t['limite']) * 100));
                            $pctExe = min(100, round($t['executado'] / max(1, $t['limite']) * 100));
                        @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-2 mb-1">
                                <span class="text-sm font-semibold" style="color:var(--text)">{{ $t['label'] }}</span>
                                <span class="text-xs font-mono" style="color:{{ $cor }}">
                                    <strong class="text-sm">{{ $t['pedido'] }}</strong> de {{ $t['limite'] }} pedidos
                                </span>
                            </div>
                            {{-- executado (verde) por cima do pedido (cor do estado) --}}
                            <div class="relative h-2 rounded-full overflow-hidden" style="background:var(--s3)">
                                <div class="absolute inset-y-0 left-0" style="width:{{ $pctPed }}%; background:{{ $cor }}; opacity:.45"></div>
                                <div class="absolute inset-y-0 left-0" style="width:{{ $pctExe }}%; background:var(--green)"></div>
                            </div>
                            <p class="text-xs mt-1" style="color:var(--muted2)">
                                <span style="color:var(--green)">{{ $t['executado'] }} executada(s)</span> ·
                                @if($t['acima'] > 0)
                                    <span style="color:var(--red); font-weight:600">{{ $t['acima'] }} acima do limite</span>
                                @elseif($t['resta'] === 0)
                                    <span style="color:var(--orange)">limite atingido</span>
                                @else
                                    ainda cabem <strong style="color:var(--text)">{{ $t['resta'] }}</strong>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-5 pb-4 text-sm" style="color:var(--muted2)" x-show="!editando">
                    Este cliente ainda não tem limite mensal definido.
                </p>
            @endif

            {{-- Mesmo endpoint da ficha do cliente. creative_lead_id vai junto porque a
                 rota grava os dois — sem ele, editar limite aqui apagaria a Direção criativa. --}}
            <form method="POST" action="{{ route('clients.update-production', $clienteSel) }}" x-show="editando" x-cloak class="px-5 pb-5">
                @csrf @method('PATCH')
                <input type="hidden" name="creative_lead_id" value="{{ $clienteSel->creative_lead_id }}">
                <p class="text-xs mb-3" style="color:var(--muted)">Quantas tarefas de cada tipo cabem no mês. Em branco = não se aplica. Altera a ficha do cliente.</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                    @foreach(\App\Models\Client::$productionQuotaTypes as $tipo)
                        <label class="flex flex-col gap-1">
                            <span class="text-xs" style="color:var(--muted2)">{{ \App\Models\Task::$types[$tipo] }}</span>
                            <input type="number" min="0" max="999" name="production_quota[{{ $tipo }}]"
                                   value="{{ $clienteSel->production_quota[$tipo] ?? '' }}" placeholder="—"
                                   class="px-2 py-1.5 text-sm text-center focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border); border-radius:6px; color:var(--text)">
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Salvar limites</button>
            </form>
        </div>
    @elseif($limites['clientes']->isNotEmpty())
        @php
            $acima = $limites['clientes']->where('acima', '>', 0);
            $resto = $limites['clientes']->where('acima', 0);
        @endphp
        <div class="card mb-4" x-data="{ todos: false }">
            <div class="px-5 pt-4 pb-3">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Limites de produção · {{ $mesLimite }}</p>
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    Pedido no mês × limite combinado de cada cliente{{ $notaMes }}.
                    <strong style="color:{{ $acima->count() ? 'var(--red)' : 'var(--green)' }}">{{ $acima->count() }} de {{ $limites['clientes']->count() }}</strong> passaram do limite em algum tipo.
                    Filtre um cliente pra ver o detalhe e editar os limites.
                </p>
            </div>
            <div class="px-5 pb-4 space-y-2">
                @foreach($limites['clientes'] as $lc)
                    <div class="flex items-center gap-3 flex-wrap" @if($lc['acima'] === 0) x-show="todos" x-cloak @endif>
                        <a href="{{ route('production-load.index', array_merge($qs, ['cliente' => $lc['id']])) }}"
                           class="text-sm font-semibold truncate hover:underline" style="color:var(--text); width:220px">{{ $lc['nome'] }}</a>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach($lc['tipos'] as $t)
                                <span class="text-[11px] font-mono px-1.5 py-0.5 rounded"
                                      style="background:{{ $t['acima'] ? 'rgba(239,68,68,.12)' : 'var(--s2)' }}; color:{{ $t['acima'] ? 'var(--red)' : ($t['pedido'] >= $t['limite'] ? 'var(--orange)' : 'var(--muted)') }}">
                                    {{ \Illuminate\Support\Str::before($t['label'], ' /') }} {{ $t['pedido'] }}/{{ $t['limite'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if($resto->count())
                    <button type="button" @click="todos = !todos" class="text-xs font-semibold" style="color:var(--purple)"
                            x-text="todos ? 'Mostrar só quem passou do limite' : 'Ver também os {{ $resto->count() }} dentro do limite'"></button>
                @endif
            </div>
        </div>
    @endif

    {{-- ── Cliente × tipo ── --}}
    <div class="card mb-4 overflow-hidden">
        <div class="px-5 pt-4 pb-3">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Cliente × tipo de tarefa</p>
            <p class="text-xs mt-1" style="color:var(--muted2)">
                Cada célula = tarefas executadas no período.
                @if($matriz['mes_inteiro'])
                    Quando o cliente tem volume mensal combinado, aparece <strong>executadas / combinado</strong> no mês.
                @else
                    Pra comparar com o volume mensal combinado, escolha um mês inteiro (Este mês / Mês passado).
                @endif
            </p>
        </div>
        @if($matriz['linhas']->isEmpty())
            <p class="text-sm px-5 pb-5" style="color:var(--muted2)">Nenhuma tarefa neste recorte.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background:var(--s2); border-top:1px solid var(--border); border-bottom:1px solid var(--border)">
                            <th class="text-left px-4 py-2 text-[11px] font-semibold uppercase tracking-wider sticky left-0" style="color:var(--muted); background:var(--s2)">Cliente</th>
                            @foreach($matriz['colunas'] as $tipo => $label)
                                <th class="px-3 py-2 text-[11px] font-semibold text-center whitespace-nowrap" style="color:var(--muted)">{{ \Illuminate\Support\Str::before($label, ' /') }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-[11px] font-semibold uppercase text-center" style="color:var(--green)">Executadas</th>
                            <th class="px-3 py-2 text-[11px] font-semibold uppercase text-center" style="color:var(--purple)">Em execução</th>
                            <th class="px-3 py-2 text-[11px] font-semibold uppercase text-center" style="color:var(--muted)">Pedidas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matriz['linhas'] as $l)
                            <tr style="border-bottom:1px solid var(--border)">
                                <td class="px-4 py-2 font-semibold sticky left-0" style="color:var(--text); background:var(--s1)" title="{{ $l['nome'] }}">
                                    {{-- truncate não age em <td>; o corte tem que estar num bloco com largura máxima --}}
                                    @if($l['id'])
                                        <a href="{{ route('production-load.index', array_merge($qs, ['cliente' => $l['id']])) }}" class="block truncate hover:underline" style="max-width:220px">{{ $l['nome'] }}</a>
                                    @else
                                        <span class="block truncate" style="max-width:220px">{{ $l['nome'] }}</span>
                                    @endif
                                </td>
                                @foreach($matriz['colunas'] as $tipo => $label)
                                    @php $c = $l['celulas'][$tipo]; @endphp
                                    <td class="px-3 py-2 text-center font-mono">
                                        @if($c['cota'])
                                            <span style="color:{{ $c['n'] >= $c['cota'] ? 'var(--green)' : 'var(--orange)' }}">{{ $c['n'] }}<span style="color:var(--muted2)">/{{ $c['cota'] }}</span></span>
                                        @elseif($c['n'])
                                            <span style="color:var(--text)">{{ $c['n'] }}</span>
                                        @else
                                            <span style="color:var(--muted2)">·</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-center font-mono font-bold" style="color:var(--green)">
                                    {{ $l['executadas'] }}@if($l['cota_total'])<span class="font-normal" style="color:var(--muted2)">/{{ $l['cota_total'] }}</span>@endif
                                </td>
                                <td class="px-3 py-2 text-center font-mono" style="color:var(--purple)">{{ $l['em_execucao'] }}</td>
                                <td class="px-3 py-2 text-center font-mono" style="color:var(--muted)">{{ $l['pedidas'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--s2)">
                            <td class="px-4 py-2 text-xs font-bold uppercase sticky left-0" style="color:var(--muted); background:var(--s2)">Total</td>
                            @foreach($matriz['colunas'] as $tipo => $label)
                                <td class="px-3 py-2 text-center font-mono font-bold" style="color:var(--text)">{{ $matriz['linhas']->sum(fn ($l) => $l['celulas'][$tipo]['n']) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-center font-mono font-bold" style="color:var(--green)">{{ $resumo['executadas'] }}</td>
                            <td class="px-3 py-2 text-center font-mono font-bold" style="color:var(--purple)">{{ $resumo['em_execucao'] }}</td>
                            <td class="px-3 py-2 text-center font-mono font-bold" style="color:var(--muted)">{{ $resumo['pedidas'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Pessoas ── --}}
    <div class="card mb-4 overflow-hidden">
        <div class="px-5 pt-4 pb-3">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Carga por pessoa</p>
            <p class="text-xs mt-1" style="color:var(--muted2)">
                Tarefa feita por duas pessoas conta pras duas — por isso a soma das pessoas pode passar do total. Clique numa pessoa pra ver por cliente.
            </p>
        </div>
        @if($pessoas->isEmpty())
            <p class="text-sm px-5 pb-5" style="color:var(--muted2)">Ninguém neste recorte.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background:var(--s2); border-top:1px solid var(--border); border-bottom:1px solid var(--border)">
                            <th class="text-left px-4 py-2 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Pessoa</th>
                            <th class="px-3 py-2 text-[11px] font-semibold uppercase text-center" style="color:var(--green)">Executou</th>
                            <th class="px-3 py-2 text-[11px] font-semibold uppercase text-center" style="color:var(--muted)">Foi responsável</th>
                            <th class="px-3 py-2 text-[11px] font-semibold uppercase text-center" style="color:var(--purple)">Executando agora</th>
                        </tr>
                    </thead>
                    @foreach($pessoas as $p)
                        <tbody x-data="{ aberto: false }">
                            <tr @click="aberto = !aberto" class="cursor-pointer" style="border-bottom:1px solid var(--border)">
                                <td class="px-4 py-2 font-semibold whitespace-nowrap" style="color:var(--text)">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="chevron-right" size="12" class="transition-transform" x-bind:class="aberto ? 'rotate-90' : ''" style="color:var(--muted)" />
                                        {{ $p['nome'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center font-mono font-bold" style="color:var(--green)">{{ $p['exec'] }}</td>
                                <td class="px-3 py-2 text-center font-mono" style="color:var(--muted)">{{ $p['resp'] }}</td>
                                <td class="px-3 py-2 text-center font-mono" style="color:var(--purple)">{{ $p['abertas'] }}</td>
                            </tr>
                            @foreach($p['clientes'] as $c)
                                <tr x-show="aberto" x-cloak style="border-bottom:1px solid var(--border); background:var(--s2)">
                                    <td class="pl-10 pr-4 py-1.5 text-xs" style="color:var(--muted)">{{ $c['nome'] }}</td>
                                    <td class="px-3 py-1.5 text-center font-mono text-xs" style="color:var(--green)">{{ $c['exec'] ?: '·' }}</td>
                                    <td></td>
                                    <td class="px-3 py-1.5 text-center font-mono text-xs" style="color:var(--purple)">{{ $c['abertas'] ?: '·' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    {{-- ── As tarefas por trás dos números ── --}}
    <div class="card overflow-hidden" x-data="{ aba: 'executadas' }">
        <div class="px-5 pt-4 pb-3 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex rounded-lg overflow-hidden" style="border:1px solid var(--border2)">
                <button type="button" @click="aba = 'executadas'" class="text-xs px-3 py-1.5"
                        :style="aba === 'executadas' ? 'background:var(--purple); color:#fff' : 'color:var(--muted)'">Executadas ({{ $executadas->count() }})</button>
                <button type="button" @click="aba = 'abertas'" class="text-xs px-3 py-1.5"
                        :style="aba === 'abertas' ? 'background:var(--purple); color:#fff' : 'color:var(--muted)'">Em execução ({{ $abertas->count() }})</button>
            </div>
            <a :href="aba === 'abertas' ? '{{ route('production-load.export', array_merge($qs, ['lista' => 'abertas'])) }}' : '{{ route('production-load.export', array_merge($qs, ['lista' => 'executadas'])) }}'"
               class="btn btn-ghost btn-sm inline-flex items-center gap-1.5">
                <x-icon name="download" size="14" /> Exportar planilha
            </a>
        </div>

        @foreach(['executadas' => $executadas, 'abertas' => $abertas] as $aba => $lista)
            <div x-show="aba === '{{ $aba }}'" @if($aba !== 'executadas') x-cloak @endif class="overflow-x-auto">
                @if($lista->isEmpty())
                    <p class="text-sm px-5 pb-5" style="color:var(--muted2)">Nenhuma tarefa.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background:var(--s2); border-top:1px solid var(--border); border-bottom:1px solid var(--border)">
                                <th class="text-left px-4 py-2 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Tarefa</th>
                                <th class="text-left px-3 py-2 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Cliente</th>
                                <th class="text-left px-3 py-2 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Tipo</th>
                                <th class="text-left px-3 py-2 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">Executor · Responsável</th>
                                <th class="text-left px-3 py-2 text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap" style="color:var(--muted)">Entrega</th>
                                <th class="text-left px-3 py-2 text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap" style="color:var(--muted)">{{ $aba === 'executadas' ? 'Concluída' : 'Status' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lista->take(300) as $t)
                                @php
                                    $concl = $t->concluida_em ? \Carbon\Carbon::parse($t->concluida_em) : null;
                                    $fora = $aba === 'executadas'
                                        ? ($concl && $t->due_date && $concl->toDateString() > $t->due_date->toDateString())
                                        : ($t->due_date && $t->due_date->lt(today()));
                                    $execs = \App\Services\Production\ProductionLoadService::executoresDe($t)->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->implode(', ');
                                    $resps = \App\Services\Production\ProductionLoadService::responsaveisDe($t)->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->implode(', ');
                                @endphp
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td class="px-4 py-2"><a href="{{ route('tasks.show', $t->id) }}" class="hover:underline" style="color:var(--text)">{{ \Illuminate\Support\Str::limit($t->title, 70) }}</a></td>
                                    <td class="px-3 py-2 text-xs whitespace-nowrap" style="color:var(--muted)">{{ $t->client?->displayName() ?? 'Interno' }}</td>
                                    <td class="px-3 py-2 text-xs whitespace-nowrap" style="color:var(--muted)">{{ \Illuminate\Support\Str::before(\App\Models\Task::$types[$t->task_type] ?? 'Sem tipo', ' /') }}</td>
                                    <td class="px-3 py-2 text-xs whitespace-nowrap" style="color:var(--muted)">{{ $execs ?: '—' }}@if($resps) <span style="color:var(--muted2)">· {{ $resps }}</span>@endif</td>
                                    <td class="px-3 py-2 text-xs font-mono whitespace-nowrap" style="color:{{ $fora ? 'var(--red)' : 'var(--muted)' }}">{{ $t->due_date?->format('d/m') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs whitespace-nowrap" style="color:var(--muted)">
                                        {{ $aba === 'executadas' ? $concl?->format('d/m') : (\App\Models\Task::$statuses[$t->status]['label'] ?? $t->status) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($lista->count() > 300)
                        <p class="text-xs px-5 py-3" style="color:var(--muted2)">Mostrando 300 de {{ $lista->count() }} — a planilha exportada traz todas.</p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
