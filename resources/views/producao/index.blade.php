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

    <div class="flex items-start justify-between mb-5 flex-wrap gap-3">
        <p class="text-sm" style="color:var(--muted)">
            Toda a produção aberta da agência: onde está, com quem está e o que já passou do prazo.
        </p>
        <a href="{{ route('production-panel.index', ['inativos' => $incluirInativos ? null : 1]) }}"
           class="px-3 py-1.5 text-xs font-semibold transition-colors"
           style="background:{{ $incluirInativos ? 'var(--purple)' : 'var(--s3)' }};
                  color:{{ $incluirInativos ? '#fff' : 'var(--muted)' }};
                  border:1px solid {{ $incluirInativos ? 'var(--purple)' : 'var(--border2)' }}">
            Incluir clientes inativos
        </a>
    </div>

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

    {{-- ── Carteira: uma linha por cliente ── --}}
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
