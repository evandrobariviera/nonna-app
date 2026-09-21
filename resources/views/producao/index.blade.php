{{-- Painel de Produção — visão geral da agência, igual pra todo mundo (não é painel por
     head). Leitura de cima pra baixo: o que precisa de olho hoje → onde o trabalho está
     (sprints) → com quem está (executores) → que tipo é → como cada cliente está sendo
     atendido. Tela só de leitura; cada linha leva pro lugar onde se resolve. --}}
<x-app-layout>
    <x-slot name="header">Painel de Produção</x-slot>

    @php
        $t = $termometro;
        // Quadradinhos do topo. "tom" só pinta quando o número pede ação.
        $tiles = [
            ['Tarefas abertas',  $t['abertas'],        'tudo que não está concluído nem cancelado', null],
            ['Vencem hoje',      $t['hoje'],           'data de entrega é hoje',                    $t['hoje'] > 0 ? 'var(--orange)' : null],
            ['Próximos 7 dias',  $t['semana'],         'entram na semana',                          null],
            ['Atrasadas',        $t['atrasadas'],      'data de entrega já passou',                 $t['atrasadas'] > 0 ? 'var(--red)' : null],
            ['Sem data',         $t['sem_data'],       'ninguém sabe quando entra',                 $t['sem_data'] > 0 ? 'var(--orange)' : null],
            ['Sem executor',     $t['sem_executor'],   'aberta e sem dono',                         $t['sem_executor'] > 0 ? 'var(--red)' : null],
        ];
    @endphp

    @php
        $comFiltro = $clienteSel || $executorSel || $direcaoSel || $statusFiltroAtivo || $sprintFila;
    @endphp

    <p class="text-sm mb-3" style="color:var(--muted)">
        Toda a produção aberta da agência: onde está, com quem está e o que já passou do prazo.
    </p>

    @if($comFiltro)
        @php
            // Monta "pelo cliente X, pelo executor Y e pela direção criativa Z" com vírgula
            // entre os itens do meio e "e" só antes do último — sem isso, com vários filtros
            // ativos ao mesmo tempo, a frase ficava ambígua sobre quantos "e" cabiam.
            $partes = array_filter([
                $clienteSel ? 'pelo cliente <strong style="color:var(--text)">' . e($clienteSel->displayName()) . '</strong>' : null,
                $executorSel ? 'pelo executor <strong style="color:var(--text)">' . e($executorSel->name) . '</strong>' : null,
                $direcaoSel ? 'pela direção criativa <strong style="color:var(--text)">' . e($direcaoSel->name) . '</strong>' : null,
                $sprintFila ? 'só o que está ' . ($sprintFila === 'sprint' ? 'em sprint' : 'na fila') : null,
                $statusFiltroAtivo
                    ? 'nos status <strong style="color:var(--text)">' . e(collect($statusSelecionadosRaw)
                        ->map(fn ($s) => \App\Models\Task::$statuses[$s]['label'] ?? $s)->implode(', ')) . '</strong>'
                    : null,
            ]);
            $ultima = array_pop($partes);
            $frase = $partes ? implode(', ', $partes) . ' e ' . $ultima : $ultima;
        @endphp
        <p class="text-xs mb-4" style="color:var(--muted2)">
            Toda a tela está recortada {!! $frase !!}.
        </p>
    @endif

    {{-- ── Termômetro ── --}}
    <div class="auto-grid-sm mb-4">
        @foreach($tiles as [$label, $valor, $ajuda, $tom])
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">{{ $label }}</p>
                <p class="text-2xl font-black" style="color:{{ $tom ?? 'var(--text)' }}">{{ $valor }}</p>
                <p class="text-xs mt-1" style="color:var(--muted2)">{{ $ajuda }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Volume do mês: o que foi combinado × o que já está de pé ── --}}
    <div class="card card-body-lg mb-4">
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">
                    Volume de {{ now()->locale('pt_BR')->translatedFormat('F') }}
                    @if($volume['cliente'])
                        · {{ $volume['cliente']->displayName() }}
                    @endif
                </p>
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    @if($volume['cliente'])
                        Quanto foi combinado com o cliente no mês e quanto já está de pé.
                    @else
                        Soma do volume combinado com todos os clientes que têm volume configurado.
                    @endif
                </p>
            </div>
            @if($volume['configurado'])
                <p class="text-xs" style="color:var(--muted2)">
                    faltam {{ $volume['dias_restantes'] }} dia(s) pro fim do mês
                </p>
            @endif
        </div>

        @if(! $volume['configurado'])
            {{-- Sem cota cadastrada não há o que comparar — a tela diz onde se resolve. --}}
            <div class="flex items-start gap-3">
                <x-icon name="package" size="20" style="color:var(--orange)" class="flex-shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold" style="color:var(--text)">Nenhum volume de produção configurado ainda</p>
                    <p class="text-xs mt-1" style="color:var(--muted2)">
                        {{ $volume['sem_cota'] }} cliente(s) sem volume mensal definido. Enquanto ninguém preencher
                        quanto cada cliente contratou por mês, não dá pra dizer se a agência está entregando o combinado.
                        Configura-se na ficha do cliente, aba Geral.
                        @if($volume['cliente'])
                            <br><a href="{{ route('clients.show', $volume['cliente']) }}" style="color:var(--purple)" class="font-semibold">
                                Configurar agora o volume de {{ $volume['cliente']->displayName() }} →
                            </a>
                        @endif
                    </p>
                </div>
            </div>
        @else
            @php
                $pctFalta = $volume['cota'] > 0 ? round($volume['falta'] / $volume['cota'] * 100) : 0;
            @endphp

            {{-- Barra única: entregue (verde) + planejado ainda em produção (roxo) + o que
                 nem foi pedido (vazio). O vazio é a leitura que interessa ao head. --}}
            <div class="mb-2" style="height:12px; background:var(--s3); border-radius:6px; overflow:hidden; display:flex">
                <div style="width:{{ $volume['pct_entr'] }}%; background:var(--green)"></div>
                <div style="width:{{ max(0, $volume['pct_plan'] - $volume['pct_entr']) }}%; background:var(--purple)"></div>
            </div>
            <div class="flex items-baseline gap-4 flex-wrap mb-5 text-xs">
                <span style="color:var(--green)"><strong class="text-sm">{{ $volume['entregue'] }}</strong> entregues</span>
                <span style="color:var(--purple)"><strong class="text-sm">{{ $volume['planejado'] - $volume['entregue'] }}</strong> em produção</span>
                <span style="color:{{ $volume['falta'] > 0 ? 'var(--orange)' : 'var(--muted2)' }}">
                    <strong class="text-sm">{{ $volume['falta'] }}</strong> ainda nem foram pedidos
                </span>
                <span style="color:var(--muted)">de <strong class="text-sm" style="color:var(--text)">{{ $volume['cota'] }}</strong> combinados no mês</span>
                @if($volume['excedente'] > 0)
                    <span style="color:var(--red)">· {{ $volume['excedente'] }} além do combinado em outros tipos</span>
                @endif
                @if(! $volume['cliente'] && $volume['sem_cota'] > 0)
                    <span style="color:var(--muted2)">· {{ $volume['sem_cota'] }} cliente(s) ainda sem volume configurado, fora desta conta</span>
                @endif
            </div>

            <div class="auto-grid-sm">
                @foreach($volume['linhas'] as $l)
                    @php
                        $pctE = $l['cota'] > 0 ? min(100, round($l['entregue'] / $l['cota'] * 100)) : 0;
                        $pctP = $l['cota'] > 0 ? min(100, round($l['planejado'] / $l['cota'] * 100)) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <x-icon name="{{ $l['icone'] }}" size="14" class="flex-shrink-0" style="color:var(--muted)" />
                            <span class="text-xs font-semibold truncate flex-1 min-w-0" style="color:var(--muted2)">{{ $l['label'] }}</span>
                            <span class="text-xs font-mono flex-shrink-0" style="color:var(--text)">{{ $l['planejado'] }}/{{ $l['cota'] }}</span>
                        </div>
                        <div style="height:6px; background:var(--s3); border-radius:3px; overflow:hidden; display:flex">
                            <div style="width:{{ $pctE }}%; background:var(--green)"></div>
                            <div style="width:{{ max(0, $pctP - $pctE) }}%; background:var(--purple)"></div>
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted2)">
                            @if($l['falta'] > 0)
                                <span style="color:var(--orange)">faltam {{ $l['falta'] }} pra fechar o mês</span>
                            @elseif($l['excedente'] > 0)
                                <span style="color:var(--red)">{{ $l['excedente'] }} além do combinado</span>
                            @else
                                volume do mês completo
                            @endif
                            @if(! $volume['cliente'])
                                · {{ $l['clientes'] }} cliente(s)
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Filtro global: molda a tela inteira, não só a Semana logo abaixo. Fica aqui — igual
         ao filtro dedicado que a aba Semana da Sprint tinha — porque é onde ele mais se usa:
         recortar por cliente/status pra organizar a produção da semana. ── --}}
    <form method="GET" action="{{ route('production-panel.index') }}"
          class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
            <select name="cliente" onchange="this.form.submit()"
                    class="text-sm px-3 py-1.5 w-full" style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os clientes</option>
                @foreach($opcoesClientes as $op)
                    <option value="{{ $op->id }}" @selected($clienteSel?->id === $op->id)>{{ $op->displayName() }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-44">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Executor</label>
            <select name="executor" onchange="this.form.submit()"
                    class="text-sm px-3 py-1.5 w-full" style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todo o time</option>
                @foreach($opcoesExecutores as $op)
                    <option value="{{ $op->id }}" @selected($executorSel?->id === $op->id)>{{ $op->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-44">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Direção criativa</label>
            <select name="direcao_criativa" onchange="this.form.submit()"
                    class="text-sm px-3 py-1.5 w-full" style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todas</option>
                @foreach($opcoesDirecaoCriativa as $op)
                    <option value="{{ $op->id }}" @selected($direcaoSel?->id === $op->id)>{{ $op->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-40">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Sprint ou Fila</label>
            <select name="sprint_fila" onchange="this.form.submit()"
                    class="text-sm px-3 py-1.5 w-full" style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="" @selected($sprintFila === '')>Tudo</option>
                <option value="sprint" @selected($sprintFila === 'sprint')>Só em sprint</option>
                <option value="fila" @selected($sprintFila === 'fila')>Só na fila</option>
            </select>
        </div>

        <label class="flex items-center gap-2 text-xs font-semibold pb-1.5" style="color:var(--muted)">
            <input type="checkbox" name="inativos" value="1" @checked($incluirInativos) onchange="this.form.submit()">
            Incluir clientes inativos
        </label>

        @if($comFiltro)
            <a href="{{ route('production-panel.index') }}" class="text-xs font-semibold pb-1.5" style="color:var(--purple)">
                ✕ Limpar filtros
            </a>
        @endif

        {{-- Status é cumulativo (pode marcar mais de um) — "Todos" é um checkbox próprio, não
             "nenhum marcado", senão não dá pra saber se o usuário desmarcou tudo de propósito
             ou simplesmente nunca mexeu (ver ProductionPanelController::resolverFiltrosGlobais()). --}}
        <div class="w-full">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Status (pode marcar mais de um)</label>
            <div class="flex flex-wrap gap-1.5">
                <label class="flex items-center gap-1.5 text-xs cursor-pointer px-2.5 py-1.5" style="border:1px solid var(--border2); border-radius:6px; color:var(--muted2)">
                    <input type="checkbox" name="status[]" value="todos" onchange="this.form.submit()"
                        {{ ! $statusFiltroAtivo ? 'checked' : '' }} style="accent-color:var(--purple)">
                    Todos
                </label>
                @foreach(\App\Http\Controllers\ProductionPanelController::$statusAbertosParaFiltro as $key)
                    <label class="flex items-center gap-1.5 text-xs cursor-pointer px-2.5 py-1.5" style="border:1px solid var(--border2); border-radius:6px; color:var(--muted2)">
                        <input type="checkbox" name="status[]" value="{{ $key }}" onchange="this.form.submit()"
                            {{ in_array($key, $statusSelecionadosRaw, true) ? 'checked' : '' }} style="accent-color:var(--purple)">
                        {{ \App\Models\Task::$statuses[$key]['label'] }}
                    </label>
                @endforeach
            </div>
        </div>
    </form>

    {{-- ── Semana de Produção: todas as tarefas abertas, arrastáveis entre dias ── --}}
    <div class="card card-body-lg mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
            Semana de Produção
        </p>
        <p class="text-xs mb-4" style="color:var(--muted2)">
            Toda tarefa aberta, na coluna do dia da sua data de aprovação. Arraste um card pra outro dia
            pra mudar a data direto, sem abrir a tarefa. As setas nas pontas do quadro andam um dia por vez,
            sempre mantendo 5 dias na tela.
        </p>

        {{-- Formulário oculto: carrega o filtro acima + a semana em exibição pro fetch AJAX do
             fragmento abaixo (só a navegação de semana/dia passa por aqui; mudar qualquer
             filtro recarrega a página inteira, porque ele afeta os blocos acima também). --}}
        <form method="GET" action="{{ route('production-panel.index') }}" id="producao-week-filter-form"
              data-live-filter data-results-url="{{ route('production-panel.week-results') }}" data-target="#producao-week-results"
              data-day-results-url="{{ route('production-panel.dia-results') }}"
              style="display:none">
            <input type="hidden" name="cliente" value="{{ $clienteSel?->id }}">
            <input type="hidden" name="executor" value="{{ $executorSel?->id }}">
            <input type="hidden" name="direcao_criativa" value="{{ $direcaoSel?->id }}">
            <input type="hidden" name="inativos" value="{{ $incluirInativos ? 1 : '' }}">
            <input type="hidden" name="sprint_fila" value="{{ $sprintFila }}">
            @foreach($statusSelecionadosRaw as $s)
                <input type="hidden" name="status[]" value="{{ $s }}">
            @endforeach
            <input type="hidden" name="week_offset" id="producao-week-offset-input" value="{{ $semana['weekOffset'] }}">
        </form>

        <div id="producao-week-results">
            @include('producao._week-results', $semana)
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function initWeekBoard() {
                if (document.getElementById('producao-week-board')) {
                    initKanbanDnd('#producao-week-board');
                    initProducaoWeekScroll();
                }
            }
            initWeekBoard();
            document.getElementById('producao-week-results').addEventListener('live-filter:updated', initWeekBoard);
        });
    </script>
    @endpush

    <div class="grid md:grid-cols-2 gap-4 mb-4">

        {{-- ── Distribuição entre as sprints ── --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
                Distribuição entre as sprints
            </p>
            <p class="text-xs mb-4" style="color:var(--muted2)">
                {{ $t['em_sprint'] }} tarefas abertas estão dentro de uma sprint e {{ $t['fora_de_sprint'] }} estão na Fila.
            </p>

            <div class="flex flex-col gap-3">
                @foreach($sprints['linhas'] as $linha)
                    @php
                        $sp  = $linha['sprint'];
                        $cor = $sp->status === 'active' ? 'var(--green)'
                             : ($sp->status === 'closed' ? 'var(--red)' : 'var(--purple)');
                    @endphp
                    <a href="{{ route('sprints.show', $sp) }}" class="block">
                        <div class="flex items-baseline justify-between gap-2 mb-1">
                            <span class="text-sm font-semibold truncate" style="color:var(--text)">{{ $sp->title }}</span>
                            <span class="text-xs font-mono flex-shrink-0" style="color:var(--muted)">
                                {{ $linha['abertas'] }} abertas
                                @if($linha['atrasadas'] > 0)
                                    · <span style="color:var(--red)">{{ $linha['atrasadas'] }} atrasadas</span>
                                @endif
                            </span>
                        </div>
                        <div style="height:6px; background:var(--s3); border-radius:3px; overflow:hidden">
                            <div style="height:6px; width:{{ round($linha['abertas'] / $sprints['maior'] * 100) }}%; background:{{ $cor }}; border-radius:3px"></div>
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted2)">
                            {{ $sp->starts_at?->format('d/m') }}–{{ $sp->ends_at?->format('d/m') }}
                            · {{ $linha['concluidas'] }} de {{ $linha['total'] }} concluídas
                            @if($sp->status === 'closed' && $linha['abertas'] > 0)
                                · <span style="color:var(--red)">sprint fechada com trabalho aberto dentro</span>
                            @endif
                        </p>
                    </a>
                @endforeach

                @if($sprints['vazias'] > 0)
                    <p class="text-xs" style="color:var(--muted2)">
                        + {{ $sprints['vazias'] }} sprint(s) futura(s) ainda sem nenhuma tarefa dentro.
                    </p>
                @endif

                {{-- A Fila não é sprint, mas é onde mora o trabalho que ainda não foi agendado --}}
                <a href="{{ route('fila.index') }}" class="block" style="border-top:1px solid var(--border2); padding-top:12px">
                    <div class="flex items-baseline justify-between gap-2 mb-1">
                        <span class="text-sm font-semibold" style="color:var(--text)">Fila (fora de sprint)</span>
                        <span class="text-xs font-mono" style="color:var(--muted)">
                            {{ $sprints['fila']['abertas'] }} abertas
                            @if($sprints['fila']['atrasadas'] > 0)
                                · <span style="color:var(--red)">{{ $sprints['fila']['atrasadas'] }} atrasadas</span>
                            @endif
                        </span>
                    </div>
                    <div style="height:6px; background:var(--s3); border-radius:3px; overflow:hidden">
                        <div style="height:6px; width:{{ round($sprints['fila']['abertas'] / $sprints['maior'] * 100) }}%; background:var(--orange); border-radius:3px"></div>
                    </div>
                </a>
            </div>
        </div>

        {{-- ── Carga por executor ── --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
                Carga por executor
            </p>
            <p class="text-xs mb-4" style="color:var(--muted2)">
                Tarefas abertas na mão de cada pessoa. A parte escura da barra é o que já está atrasado.
            </p>

            <div class="flex flex-col gap-3">
                @foreach($pessoas['linhas'] as $linha)
                    @php
                        $pct    = round($linha['abertas'] / $pessoas['maior'] * 100);
                        $pctAtr = $linha['abertas'] > 0 ? round($linha['atrasadas'] / $linha['abertas'] * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center gap-2.5 mb-1">
                            @if($linha['pessoa'])
                                <x-user-avatar :user="$linha['pessoa']" size="6" />
                                <span class="text-sm font-semibold truncate flex-1 min-w-0" style="color:var(--text)">{{ $linha['pessoa']->name }}</span>
                            @else
                                <span class="h-6 w-6 rounded-full flex-shrink-0" style="background:var(--s3); border:1px dashed var(--border2)"></span>
                                <span class="text-sm font-semibold truncate flex-1 min-w-0" style="color:var(--red)">Sem executor</span>
                            @endif
                            <span class="text-xs font-mono flex-shrink-0" style="color:var(--muted)">
                                {{ $linha['abertas'] }}
                                @if($linha['atrasadas'] > 0)
                                    · <span style="color:var(--red)">{{ $linha['atrasadas'] }} atrasadas</span>
                                @endif
                            </span>
                        </div>
                        <div style="height:6px; background:var(--s3); border-radius:3px; overflow:hidden">
                            <div style="height:6px; width:{{ $pct }}%; background:var(--purple); border-radius:3px; position:relative">
                                <div style="position:absolute; inset:0 auto 0 0; width:{{ $pctAtr }}%; background:var(--red); border-radius:3px"></div>
                            </div>
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted2)">
                            {{ $linha['hoje'] }} pra hoje · {{ $linha['semana'] }} nos próximos 7 dias · {{ $linha['em_producao'] }} em produção
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Mix de produção ── --}}
    <div class="card card-body-lg mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
            Que tipo de trabalho está aberto
        </p>
        <div class="auto-grid-sm">
            @foreach($tipos['linhas'] as $linha)
                <div class="flex items-center gap-2.5">
                    <x-icon name="{{ $linha['icone'] }}" size="16" class="flex-shrink-0" style="color:var(--purple)" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-xs font-semibold truncate" style="color:var(--muted2)">{{ $linha['label'] }}</span>
                            <span class="text-xs font-mono flex-shrink-0" style="color:var(--text)">{{ $linha['abertas'] }}</span>
                        </div>
                        <div style="height:4px; background:var(--s3); border-radius:2px; overflow:hidden; margin-top:4px">
                            <div style="height:4px; width:{{ round($linha['abertas'] / $tipos['maior'] * 100) }}%; background:var(--purple); border-radius:2px"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @php $semCarga = collect($clientes)->where('abertas', 0)->count(); @endphp
    <div class="card card-body-lg" x-data="{ busca: '', soAtrasados: false, mostrarSemCarga: false }">
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">
                    Produção por cliente
                </p>
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    Cota é o volume mensal combinado com o cliente. Cadastro é o quanto da ficha dele está preenchida.
                    @if($semCarga > 0)
                        <br>{{ $semCarga }} clientes estão sem nenhuma tarefa aberta agora e ficam escondidos.
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <input type="search" x-model="busca" placeholder="Buscar cliente…"
                       class="text-xs px-3 py-1.5" style="background:var(--s3); border:1px solid var(--border2); color:var(--text); min-width:180px">
                <button type="button" @click="soAtrasados = !soAtrasados"
                        class="px-3 py-1.5 text-xs font-semibold"
                        :style="soAtrasados
                            ? 'background:var(--red); color:#fff; border:1px solid var(--red)'
                            : 'background:var(--s3); color:var(--muted); border:1px solid var(--border2)'">
                    Só com atraso
                </button>
                @if($semCarga > 0)
                    <button type="button" @click="mostrarSemCarga = !mostrarSemCarga"
                            class="px-3 py-1.5 text-xs font-semibold"
                            :style="mostrarSemCarga
                                ? 'background:var(--purple); color:#fff; border:1px solid var(--purple)'
                                : 'background:var(--s3); color:var(--muted); border:1px solid var(--border2)'">
                        Mostrar sem produção
                    </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border2)">
                        <th class="text-left py-2 pr-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Cliente</th>
                        <th class="text-left py-2 px-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Direção criativa</th>
                        <th class="text-right py-2 px-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Abertas</th>
                        <th class="text-right py-2 px-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Atrasadas</th>
                        <th class="text-right py-2 px-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Na fila</th>
                        <th class="text-right py-2 px-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Cota do mês</th>
                        <th class="text-right py-2 pl-3 text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Cadastro</th>
                    </tr>
                </thead>
                <tbody style="font-variant-numeric:tabular-nums">
                    @foreach($clientes as $linha)
                        @php
                            $c        = $linha['client'];
                            $notaCor  = $linha['nota'] >= 100 ? 'var(--green)' : ($linha['nota'] >= 70 ? 'var(--orange)' : 'var(--red)');
                            $cotaFalta = $linha['cota_total'] - $linha['cota_usada'];
                        @endphp
                        <tr style="border-bottom:1px solid var(--border2)"
                            x-show="(busca === '' || {{ Js::from(mb_strtolower($c->displayName())) }}.includes(busca.toLowerCase()))
                                    && (!soAtrasados || {{ $linha['atrasadas'] }} > 0)
                                    && (mostrarSemCarga || busca !== '' || {{ $linha['abertas'] }} > 0)">
                            <td class="py-2 pr-3">
                                <a href="{{ route('clients.show', $c) }}" class="font-semibold hover:underline" style="color:var(--text)">
                                    {{ $c->displayName() }}
                                </a>
                            </td>
                            <td class="py-2 px-3">
                                @if($c->creativeLead)
                                    <span class="flex items-center gap-2">
                                        <x-user-avatar :user="$c->creativeLead" size="5" />
                                        <span class="text-xs truncate" style="color:var(--muted2)">{{ $c->creativeLead->name }}</span>
                                    </span>
                                @else
                                    <span class="text-xs" style="color:var(--muted2)">—</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-right font-mono text-xs" style="color:var(--text)">{{ $linha['abertas'] ?: '—' }}</td>
                            <td class="py-2 px-3 text-right font-mono text-xs" style="color:{{ $linha['atrasadas'] > 0 ? 'var(--red)' : 'var(--muted2)' }}">
                                {{ $linha['atrasadas'] ?: '—' }}
                            </td>
                            <td class="py-2 px-3 text-right font-mono text-xs" style="color:var(--muted2)">{{ $linha['na_fila'] ?: '—' }}</td>
                            <td class="py-2 px-3 text-right font-mono text-xs">
                                @if($linha['cota_total'] > 0)
                                    <span style="color:var(--text)">{{ $linha['cota_usada'] }}/{{ $linha['cota_total'] }}</span>
                                    @if($cotaFalta > 0)
                                        <span style="color:var(--orange)"> · faltam {{ $cotaFalta }}</span>
                                    @endif
                                @else
                                    <span style="color:var(--muted2)">não configurada</span>
                                @endif
                            </td>
                            <td class="py-2 pl-3 text-right font-mono text-xs" style="color:{{ $notaCor }}">{{ $linha['nota'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
