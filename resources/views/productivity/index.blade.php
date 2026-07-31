<x-app-layout>
    <x-slot name="header">Painel de Produtividade</x-slot>

    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <p class="text-sm" style="color:var(--muted)">Números de entrega, gargalos e volume por cliente.</p>
            @if($instrumentationStartedAt)
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    Histórico de status sendo coletado desde {{ $instrumentationStartedAt->format('d/m/Y') }} — os painéis de tempo médio e tendência ficam mais confiáveis com o passar das semanas.
                </p>
            @else
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    Ainda não há histórico de status suficiente — os painéis de tempo médio aparecem assim que houver dado.
                </p>
            @endif
        </div>

        <div class="flex items-center gap-1.5">
            @foreach(\App\Http\Controllers\ProductivityDashboardController::$periods as $key => $label)
                <a href="{{ route('productivity.index', ['period' => $key]) }}"
                   class="px-3 py-1.5 text-xs font-semibold transition-colors"
                   style="background:{{ $period === $key ? 'var(--purple)' : 'var(--s3)' }};
                          color:{{ $period === $key ? '#fff' : 'var(--muted)' }};
                          border:1px solid {{ $period === $key ? 'var(--purple)' : 'var(--border2)' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- KPIs DE TOPO --}}
    <div class="grid grid-cols-4 gap-4 mb-4">
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Taxa de Retrabalho</p>
            <p class="text-2xl font-black" style="color:{{ ($reworkRate['overall_rate'] ?? 0) > 20 ? 'var(--red)' : 'var(--text)' }}">
                {{ $reworkRate['overall_rate'] !== null ? $reworkRate['overall_rate'] . '%' : '—' }}
            </p>
            <p class="text-xs mt-1" style="color:var(--muted2)">{{ $reworkRate['total'] }} tarefas concluídas passaram por Ajuste/Alteração</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Cumprimento de Prazo</p>
            <p class="text-2xl font-black" style="color:{{ ($onTimeRate['rate'] ?? 100) < 70 ? 'var(--red)' : 'var(--text)' }}">
                {{ $onTimeRate['rate'] !== null ? $onTimeRate['rate'] . '%' : '—' }}
            </p>
            <p class="text-xs mt-1" style="color:var(--muted2)">{{ $onTimeRate['on_time'] }} de {{ $onTimeRate['total'] }} com prazo definido</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Aprovação de Primeira</p>
            <p class="text-2xl font-black" style="color:var(--text)">
                {{ $approvalOverall->first_time_rate !== null ? $approvalOverall->first_time_rate . '%' : '—' }}
            </p>
            <p class="text-xs mt-1" style="color:var(--muted2)">{{ $approvalOverall->total }} rodadas resolvidas no período</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Cliente Responde Em</p>
            <p class="text-2xl font-black" style="color:var(--text)">
                {{ $approvalOverall->avg_response ?? '—' }}
            </p>
            <p class="text-xs mt-1" style="color:var(--muted2)">tempo médio até aprovar/pedir ajuste (dias úteis)</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">

        {{-- QUEM ENTREGA MAIS --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Quem Entrega Mais <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· tarefas concluídas, por executor</span>
            </p>
            @if($byExecutor->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa concluída nesse período.</p>
            @else
                @php $maxExec = $byExecutor->max('count'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($byExecutor as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ $row->name }}</span>
                                <span class="text-sm font-bold" style="color:var(--purple)">{{ $row->count }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $maxExec > 0 ? round($row->count / $maxExec * 100) : 0 }}%; background:var(--grad)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- CARGA DE TRABALHO ATUAL (WIP) --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Carga de Trabalho Atual (WIP) <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· tarefas em aberto agora, por executor</span>
            </p>
            @if($wipByExecutor->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Ninguém com tarefa em aberto agora.</p>
            @else
                @php $maxWip = $wipByExecutor->max('count'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($wipByExecutor as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ $row->name }}</span>
                                <span class="text-sm font-bold" style="color:var(--orange)">{{ $row->count }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $maxWip > 0 ? round($row->count / $maxWip * 100) : 0 }}%; background:var(--orange)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TIPO DE TAREFA MAIS ENTREGUE --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Tipo de Tarefa Mais Entregue
            </p>
            @if($byType->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa concluída nesse período.</p>
            @else
                @php $maxType = $byType->max('count'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($byType as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ $row->label }}</span>
                                <span class="text-sm font-bold" style="color:var(--orange)">{{ $row->count }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $maxType > 0 ? round($row->count / $maxType * 100) : 0 }}%; background:var(--orange)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- MIX DE ORIGEM --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Mix de Origem <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· planejado vs. avulso, entregues no período</span>
            </p>
            @if($originMix->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa concluída nesse período.</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($originMix as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ $row->label }}</span>
                                <span class="text-sm font-bold" style="color:var(--purple)">{{ $row->count }} <span class="text-xs font-normal" style="color:var(--muted2)">({{ $row->pct }}%)</span></span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $row->pct }}%; background:var(--grad)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- CLIENTE QUE MAIS CONSOME --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Cliente Que Mais Consome a Agência <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· tarefas abertas no período</span>
            </p>
            @if($byClient->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa criada nesse período.</p>
            @else
                @php $maxClient = $byClient->max('count'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($byClient as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <a href="{{ route('clients.show', $row->client) }}" class="text-sm font-medium hover:underline" style="color:var(--text)">
                                    {{ $row->client->displayName() }}
                                </a>
                                <span class="text-sm font-bold" style="color:var(--purple)">{{ $row->count }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $maxClient > 0 ? round($row->count / $maxClient * 100) : 0 }}%; background:var(--grad)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ATRASADAS POR EXECUTOR --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Tarefas Atrasadas, Por Executor <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· estado atual</span>
            </p>
            @if($overdueByExecutor->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa atrasada agora. 🎉</p>
            @else
                @php $maxOverdue = $overdueByExecutor->max('count'); @endphp
                <div class="flex flex-col gap-3">
                    @foreach($overdueByExecutor as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ $row->name }}</span>
                                <span class="text-sm font-bold" style="color:var(--red)">{{ $row->count }}</span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $maxOverdue > 0 ? round($row->count / $maxOverdue * 100) : 0 }}%; background:var(--red)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        {{-- RETRABALHO POR EXECUTOR --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Retrabalho, Por Executor <span style="color:var(--muted2); text-transform:none; letter-spacing:normal">· % que passou por Ajuste/Alteração</span>
            </p>
            @if($reworkRate['by_executor']->isEmpty())
                <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa concluída nesse período.</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($reworkRate['by_executor'] as $row)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" style="color:var(--text)">{{ $row->name }}</span>
                                <span class="text-sm font-bold" style="color:{{ $row->rework_rate > 20 ? 'var(--red)' : 'var(--muted)' }}">
                                    {{ $row->rework_rate }}% <span class="text-xs font-normal" style="color:var(--muted2)">({{ $row->total }} concluídas)</span>
                                </span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full" style="width:{{ $row->rework_rate }}%; background:{{ $row->rework_rate > 20 ? 'var(--red)' : 'var(--muted2)' }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- TENDÊNCIA SEMANAL --}}
    <div class="card card-body-lg mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
            Tendência Semanal de Entregas
        </p>
        <p class="text-xs mb-4" style="color:var(--muted2)">Tarefas concluídas por semana, últimas 8 semanas — janela fixa, independente do filtro de período acima.</p>

        @php $maxWeek = max($weeklyTrend->max('count'), 1); @endphp
        <div class="flex items-end gap-2" style="height:120px">
            @foreach($weeklyTrend as $week)
                <div class="flex-1 flex flex-col items-center justify-end h-full">
                    <span class="text-xs font-bold mb-1" style="color:var(--text)">{{ $week->count > 0 ? $week->count : '' }}</span>
                    <div class="w-full rounded-t" style="height:{{ round($week->count / $maxWeek * 100) }}%; min-height:{{ $week->count > 0 ? '4px' : '0' }}; background:var(--grad)"></div>
                    <span class="text-xs mt-1.5" style="color:var(--muted2)">{{ $week->label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- TEMPO MÉDIO EM CADA STATUS --}}
    <div class="card card-body-lg mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
            Tempo Médio em Cada Status
        </p>
        <p class="text-xs mb-4" style="color:var(--muted2)">Dias úteis (fins de semana descontados) — só conta tarefas que já saíram do status, todo o histórico disponível.</p>

        @if($avgByStatus->isEmpty())
            <p class="text-sm py-6 text-center" style="color:var(--muted)">Ainda não há transições de status suficientes pra calcular uma média.</p>
        @else
            @php $maxAvg = $avgByStatus->max('avg_seconds'); @endphp
            <div class="flex flex-col gap-3">
                @foreach($avgByStatus as $row)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium" style="color:var(--text)">{{ $row->label }}</span>
                            <span class="text-sm font-bold" style="color:{{ \App\Models\Task::colorHex($row->color) }}">
                                {{ $row->avg_human }}
                                <span class="text-xs font-normal" style="color:var(--muted2)">({{ $row->sample_size }} {{ $row->sample_size === 1 ? 'amostra' : 'amostras' }})</span>
                            </span>
                        </div>
                        <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                            <div class="h-2 rounded-full" style="width:{{ $maxAvg > 0 ? round($row->avg_seconds / $maxAvg * 100) : 0 }}%; background:{{ \App\Models\Task::colorHex($row->color) }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- SAÚDE DA APROVAÇÃO POR CLIENTE --}}
    <div class="card card-body-lg mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
            Saúde da Aprovação, Por Cliente
        </p>
        <p class="text-xs mb-4" style="color:var(--muted2)">Rodadas resolvidas no período — % aprovada de primeira (sem pedido de ajuste) e tempo médio até o cliente responder.</p>

        @if($approvalByClient->isEmpty())
            <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma rodada de aprovação resolvida nesse período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border2)">
                            <th class="text-left py-2 font-semibold" style="color:var(--muted)">Cliente</th>
                            <th class="text-right py-2 font-semibold" style="color:var(--muted)">Rodadas</th>
                            <th class="text-right py-2 font-semibold" style="color:var(--muted)">Aprovação de Primeira</th>
                            <th class="text-right py-2 font-semibold" style="color:var(--muted)">Tempo Médio de Resposta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvalByClient as $row)
                            <tr style="border-bottom:1px solid var(--border2)">
                                <td class="py-2">
                                    <a href="{{ route('clients.show', $row->client) }}" class="font-medium hover:underline" style="color:var(--text)">
                                        {{ $row->client->displayName() }}
                                    </a>
                                </td>
                                <td class="text-right py-2" style="color:var(--text)">{{ $row->total }}</td>
                                <td class="text-right py-2 font-semibold" style="color:{{ $row->first_time_rate < 50 ? 'var(--red)' : 'var(--text)' }}">{{ $row->first_time_rate }}%</td>
                                <td class="text-right py-2" style="color:var(--muted)">{{ $row->avg_response ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- MATRIZ TIPO DE TAREFA × EXECUTOR --}}
    <div class="card card-body-lg">
        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.1em">
            Tipo de Tarefa × Executor
        </p>
        <p class="text-xs mb-4" style="color:var(--muted2)">Tarefas concluídas no período — quem cobre cada tipo (até 8 executores de maior volume).</p>

        @if($typeExecutorMatrix['rows']->isEmpty())
            <p class="text-sm py-6 text-center" style="color:var(--muted)">Nenhuma tarefa concluída nesse período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border2)">
                            <th class="text-left py-2 font-semibold" style="color:var(--muted)">Tipo</th>
                            @foreach($typeExecutorMatrix['executor_names'] as $name)
                                <th class="text-right py-2 font-semibold whitespace-nowrap px-2" style="color:var(--muted)">{{ $name }}</th>
                            @endforeach
                            <th class="text-right py-2 font-semibold" style="color:var(--muted)">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($typeExecutorMatrix['rows'] as $row)
                            <tr style="border-bottom:1px solid var(--border2)">
                                <td class="py-2 font-medium" style="color:var(--text)">{{ $row->label }}</td>
                                @foreach($typeExecutorMatrix['executor_keys'] as $key)
                                    <td class="text-right py-2 px-2" style="color:var(--muted)">{{ $row->cells[$key] ?: '·' }}</td>
                                @endforeach
                                <td class="text-right py-2 font-bold" style="color:var(--text)">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
