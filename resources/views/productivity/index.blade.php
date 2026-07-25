<x-app-layout>
    <x-slot name="header">Painel de Produtividade</x-slot>

    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <p class="text-sm" style="color:var(--muted)">Números de entrega, gargalos e volume por cliente.</p>
            @if($instrumentationStartedAt)
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    Histórico de status sendo coletado desde {{ $instrumentationStartedAt->format('d/m/Y') }} — os painéis de tempo médio ficam mais confiáveis com o passar das semanas.
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
                                    {{ $row->client->company_name }}
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
    </div>

    {{-- TEMPO MÉDIO EM CADA STATUS --}}
    <div class="card card-body-lg">
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
</x-app-layout>
