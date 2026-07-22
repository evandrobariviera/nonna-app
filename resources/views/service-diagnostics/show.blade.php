@php
    $breakdown = $diagnostic->service_score_breakdown ?? [];
    $breakdownLabels = ['velocidade' => 'Velocidade', 'conversao' => 'Conversão', 'consistencia' => 'Consistência', 'sentimento' => 'Sentimento'];
    $scoreColor = $diagnostic->service_score >= 70 ? 'var(--green)' : ($diagnostic->service_score >= 45 ? 'var(--orange)' : 'var(--red)');

    $avgTicket = $integration->avgTicketValue();
    $quickWins = $diagnostic->recommendations->where('category', 'quick_win');
    $estruturais = $diagnostic->recommendations->where('category', 'estrutural');
@endphp

<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">
            <a href="{{ route('service-diagnostics.index') }}" style="color:var(--muted)">Diagnóstico de Atendimento</a>
            <span style="color:var(--muted)">/</span>
            <a href="{{ route('service-diagnostics.integration', $integration) }}" style="color:var(--muted)">{{ $integration->client->company_name }} · {{ $integration->label }}</a>
            <span style="color:var(--muted)">/</span>
            Versão {{ $diagnostic->version }}
        </span>
    </x-slot>

    <div style="max-width:960px">

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                {{ $diagnostic->period_start->format('d/m/Y') }} – {{ $diagnostic->period_end->format('d/m/Y') }}
                · gerado {{ $diagnostic->generated_by === 'scheduled' ? 'automaticamente' : 'manualmente' }}
                @if($diagnostic->aiAgent) · agente {{ $diagnostic->aiAgent->name }} @endif
            </p>
            <span class="text-xs font-mono px-3 py-1 rounded"
                  style="background:var(--s3); color: {{ $diagnostic->isPublished() ? 'var(--green)' : 'var(--muted)' }}">
                {{ $diagnostic->statusLabel() }}
            </span>
        </div>

        {{-- Índice de Atendimento --}}
        @if($diagnostic->service_score !== null)
            <div class="card mb-6">
                <div class="card-body flex items-center gap-6">
                    <div class="flex-shrink-0 text-center" style="width:100px">
                        <p class="stat-label mb-1">Índice de Atendimento</p>
                        <p class="text-4xl font-black" style="color:{{ $scoreColor }}">{{ $diagnostic->service_score }}</p>
                        <p class="text-xs font-mono" style="color:var(--muted)">de 100</p>
                    </div>
                    <div class="flex-1 grid grid-cols-4 gap-4">
                        @foreach($breakdown as $key => $value)
                            <div>
                                <p class="text-xs mb-1" style="color:var(--muted)">{{ $breakdownLabels[$key] ?? $key }}</p>
                                <div style="height:6px; background:var(--s3); border-radius:3px; overflow:hidden">
                                    <div style="height:100%; width:{{ $value }}%; background:var(--purple); border-radius:3px"></div>
                                </div>
                                <p class="text-xs font-mono mt-1" style="color:var(--text)">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- KPIs --}}
        <div class="grid grid-cols-3 gap-3 mb-8">
            <div class="stat-card">
                <p class="stat-label">Conversas</p>
                <p class="stat-value">{{ $diagnostic->total_conversations }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Taxa de conversão</p>
                <p class="stat-value" style="color:var(--green)">{{ number_format($diagnostic->conversion_rate, 1, ',', '.') }}%</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">1ª resposta</p>
                <p class="stat-value">{{ $diagnostic->avg_first_response_minutes }} min</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Vendas confirmadas</p>
                <p class="stat-value">{{ $diagnostic->sales_confirmed }}
                    <span class="text-xs font-mono" style="color:var(--muted)">(+{{ $diagnostic->sales_in_negotiation }})</span>
                </p>
            </div>
            @if($diagnostic->avg_sentiment_score !== null)
                <div class="stat-card">
                    <p class="stat-label">Sentimento médio</p>
                    <p class="stat-value">{{ number_format($diagnostic->avg_sentiment_score, 0) }}<span class="text-xs font-mono" style="color:var(--muted)">/100</span></p>
                </div>
            @endif
            @if($diagnostic->estimated_lost_revenue !== null)
                <div class="stat-card">
                    <p class="stat-label">Oportunidade perdida no período</p>
                    <p class="stat-value" style="color:var(--red)">R$ {{ number_format($diagnostic->estimated_lost_revenue, 0, ',', '.') }}</p>
                </div>
            @endif
        </div>

        {{-- Panorama & Funil --}}
        @if($funnel = $diagnostic->funnel_summary)
            <div class="card mb-6">
                <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">Panorama &amp; Funil</h3></div>
                <div class="card-body">
                    @if(!empty($funnel['achado_central']))
                        <div class="px-4 py-3 mb-4 rounded" style="background:var(--s3); border-left:3px solid var(--orange)">
                            <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--orange)">O achado central</p>
                            <p class="text-sm" style="color:var(--text)">{{ $funnel['achado_central'] }}</p>
                        </div>
                    @endif
                    @if(!empty($funnel['como_entra']))
                        <p class="text-sm mb-4" style="color:var(--muted2)">{{ $funnel['como_entra'] }}</p>
                    @endif
                    @if(!empty($funnel['estagios']))
                        <table class="nonna-table">
                            <thead>
                                <tr><th>Estágio</th><th>O que observamos</th><th>Onde perde</th></tr>
                            </thead>
                            <tbody>
                                @foreach($funnel['estagios'] as $estagio)
                                    <tr>
                                        <td class="font-semibold" style="color:var(--text)">{{ $estagio['estagio'] }}</td>
                                        <td class="text-sm" style="color:var(--muted)">{{ $estagio['observado'] }}</td>
                                        <td class="text-sm" style="color:var(--muted)">{{ $estagio['onde_perde'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        {{-- Personas --}}
        @if($diagnostic->personas->isNotEmpty())
            <div class="card mb-6">
                <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">Personas Identificadas</h3></div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($diagnostic->personas as $persona)
                            <div class="p-4 rounded" style="background:var(--s3); border-top:2px solid var(--purple)">
                                <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">{{ $persona->tag }}</p>
                                <p class="text-sm font-bold mb-2" style="color:var(--text)">{{ $persona->name }}</p>
                                @if($persona->profile)
                                    <p class="text-xs mb-1" style="color:var(--muted)"><strong style="color:var(--text)">Perfil:</strong> {{ $persona->profile }}</p>
                                @endif
                                @if($persona->behavior)
                                    <p class="text-xs mb-1" style="color:var(--muted)"><strong style="color:var(--text)">Comportamento:</strong> {{ $persona->behavior }}</p>
                                @endif
                                @if($persona->evidence)
                                    <p class="text-xs italic" style="color:var(--muted)">{{ $persona->evidence }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Gaps operacionais --}}
        @if(!empty($diagnostic->gaps))
            <div class="card mb-6">
                <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">Gaps Operacionais</h3></div>
                <div class="card-body flex flex-col gap-3">
                    @foreach($diagnostic->gaps as $gap)
                        <div class="px-4 py-3 rounded" style="background:var(--s3); border-left:3px solid var(--red)">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold mb-1" style="color:var(--text)">{{ $gap['titulo'] }}</p>
                                @if(!empty($gap['estimated_leads_lost']) && $avgTicket)
                                    <span class="text-xs font-mono flex-shrink-0" style="color:var(--red)">
                                        ~R$ {{ number_format($gap['estimated_leads_lost'] * $avgTicket, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs" style="color:var(--muted)">{{ $gap['descricao'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- O que funciona --}}
        @if(!empty($diagnostic->strengths))
            <div class="card mb-6">
                <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">O que Já Funciona</h3></div>
                <div class="card-body flex flex-col gap-3">
                    @foreach($diagnostic->strengths as $strength)
                        <div class="px-4 py-3 rounded" style="background:var(--s3); border-left:3px solid var(--green)">
                            <p class="text-sm font-bold mb-1" style="color:var(--text)">{{ $strength['titulo'] }}</p>
                            <p class="text-xs" style="color:var(--muted)">{{ $strength['descricao'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Insights de campanha --}}
        @if(!empty($diagnostic->campaign_insights))
            <div class="card mb-6">
                <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">Insights de Campanha</h3></div>
                <div class="card-body flex flex-col gap-3">
                    @foreach($diagnostic->campaign_insights as $insight)
                        <div class="px-4 py-3 rounded" style="background:var(--s3); border-left:3px solid var(--purple)">
                            <p class="text-sm font-bold mb-1" style="color:var(--text)">{{ $insight['titulo'] }}</p>
                            <p class="text-xs" style="color:var(--muted)">{{ $insight['descricao'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recomendações --}}
        @if($quickWins->isNotEmpty() || $estruturais->isNotEmpty())
            <div class="card mb-6">
                <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">Recomendações Priorizadas</h3></div>
                <div class="card-body">
                    @foreach(['quick_win' => ['label' => 'Quick wins', 'color' => 'var(--orange)', 'items' => $quickWins], 'estrutural' => ['label' => 'Estruturais', 'color' => 'var(--purple)', 'items' => $estruturais]] as $group)
                        @if($group['items']->isNotEmpty())
                            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:{{ $group['color'] }}">{{ $group['label'] }}</p>
                            <div class="flex flex-col gap-3 mb-4">
                                @foreach($group['items'] as $item)
                                    <div class="px-4 py-3 rounded" style="background:var(--s3); border-left:3px solid {{ $group['color'] }}">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <p class="text-sm font-bold" style="color:var(--text)">{{ $item->title }}</p>
                                            <span class="text-xs font-mono px-2 py-0.5 rounded flex-shrink-0"
                                                  style="background:var(--s2); color:var(--{{ $item->statusColor() }})">
                                                {{ $item->statusLabel() }}
                                            </span>
                                        </div>
                                        <p class="text-xs" style="color:var(--muted)">{{ $item->description }}</p>
                                        @if($item->previousRecommendation)
                                            <p class="text-xs italic mt-2" style="color:var(--muted)">
                                                Recomendado há {{ $item->streakLength() }} versões seguidas
                                                @if($item->status === 'implementada') — implementada nesta rodada. @else — ainda em aberto. @endif
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
