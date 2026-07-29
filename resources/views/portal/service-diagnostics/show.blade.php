@php
    $breakdown = $diagnostic->service_score_breakdown ?? [];
    $breakdownLabels = ['velocidade' => 'Velocidade', 'conversao' => 'Conversão', 'consistencia' => 'Consistência', 'sentimento' => 'Sentimento'];
    $scoreColor = ($diagnostic->service_score ?? 0) >= 70 ? 'var(--green)' : (($diagnostic->service_score ?? 0) >= 45 ? 'var(--orange)' : 'var(--red)');

    $avgTicket = $diagnostic->integration?->avgTicketValue();
    $quickWins = $diagnostic->recommendations->where('category', 'quick_win');
    $estruturais = $diagnostic->recommendations->where('category', 'estrutural');
@endphp

<x-portal-layout>
    <x-slot name="title">Atendimento Assistido · Versão {{ $diagnostic->version }}</x-slot>

    <div class="mb-6">
        <a href="{{ route('portal.service-diagnostics.integration', $integration) }}" class="text-xs" style="color:var(--muted)">← Voltar ao histórico</a>
        <h1 class="text-2xl font-black mt-2" style="color: var(--text)">Diagnóstico · Versão {{ $diagnostic->version }}</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            {{ $diagnostic->period_start->format('d/m/Y') }} – {{ $diagnostic->period_end->format('d/m/Y') }}
        </p>
    </div>

    {{-- Índice de Atendimento --}}
    @if($diagnostic->service_score !== null)
        <div class="card p-5 mb-6 flex items-center gap-6">
            <div class="flex-shrink-0 text-center" style="width:100px">
                <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Índice</p>
                <p class="text-4xl font-black" style="color:{{ $scoreColor }}">{{ $diagnostic->service_score }}</p>
                <p class="text-xs" style="color:var(--muted)">de 100</p>
            </div>
            <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach($breakdown as $key => $value)
                    <div>
                        <p class="text-xs mb-1" style="color:var(--muted)">{{ $breakdownLabels[$key] ?? $key }}</p>
                        <div style="height:6px; background:var(--s3); border-radius:3px; overflow:hidden">
                            <div style="height:100%; width:{{ $value }}%; background:var(--purple); border-radius:3px"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Conversas</p>
            <p class="text-3xl font-black leading-none" style="color: var(--text)">{{ $diagnostic->total_conversations }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Taxa de conversão</p>
            <p class="text-3xl font-black leading-none" style="color: var(--green)">{{ number_format($diagnostic->conversion_rate, 1, ',', '.') }}%</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">1ª resposta</p>
            <p class="text-3xl font-black leading-none" style="color: var(--text)">{{ $diagnostic->avg_first_response_minutes ?? '—' }} min</p>
        </div>
        @if($diagnostic->estimated_lost_revenue)
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Oportunidade a recuperar</p>
                <p class="text-3xl font-black leading-none" style="color: var(--red)">R$ {{ number_format($diagnostic->estimated_lost_revenue, 0, ',', '.') }}</p>
                <p class="text-xs mt-2" style="color: var(--muted)">estimado neste período</p>
            </div>
        @endif
    </div>

    {{-- Panorama & Funil --}}
    @if($funnel = $diagnostic->funnel_summary)
        <div class="card mb-6">
            <div class="p-5" style="border-bottom:1px solid var(--border2)"><h3 class="text-sm font-bold" style="color:var(--text)">Panorama &amp; Funil</h3></div>
            <div class="p-5">
                @if(!empty($funnel['achado_central']))
                    <div class="p-4 mb-4 rounded" style="background:var(--s3); border-left:3px solid var(--orange)">
                        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:var(--orange)">O achado central</p>
                        <p class="text-sm" style="color:var(--text)">{{ $funnel['achado_central'] }}</p>
                    </div>
                @endif
                @if(!empty($funnel['como_entra']))
                    <p class="text-sm mb-4" style="color:var(--muted)">{{ $funnel['como_entra'] }}</p>
                @endif
                @if(!empty($funnel['estagios']))
                    <div class="flex flex-col gap-3">
                        @foreach($funnel['estagios'] as $estagio)
                            <div class="p-3 rounded" style="background:var(--s3)">
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $estagio['estagio'] }}</p>
                                <p class="text-xs mt-1" style="color:var(--muted)"><strong style="color:var(--text)">Observado:</strong> {{ $estagio['observado'] }}</p>
                                <p class="text-xs mt-1" style="color:var(--muted)"><strong style="color:var(--text)">Onde perde:</strong> {{ $estagio['onde_perde'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Personas --}}
    @if($diagnostic->personas->isNotEmpty())
        <div class="card mb-6">
            <div class="p-5" style="border-bottom:1px solid var(--border2)"><h3 class="text-sm font-bold" style="color:var(--text)">Personas Identificadas</h3></div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($diagnostic->personas as $persona)
                    <div class="p-4 rounded" style="background:var(--s3); border-top:2px solid var(--purple)">
                        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:var(--purple)">{{ $persona->tag }}</p>
                        <p class="text-sm font-bold mb-2" style="color:var(--text)">{{ $persona->name }}</p>
                        @if($persona->profile)<p class="text-xs mb-1" style="color:var(--muted)"><strong style="color:var(--text)">Perfil:</strong> {{ $persona->profile }}</p>@endif
                        @if($persona->behavior)<p class="text-xs mb-1" style="color:var(--muted)"><strong style="color:var(--text)">Comportamento:</strong> {{ $persona->behavior }}</p>@endif
                        @if($persona->evidence)<p class="text-xs italic" style="color:var(--muted)">{{ $persona->evidence }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Gaps --}}
    @if(!empty($diagnostic->gaps))
        <div class="card mb-6">
            <div class="p-5" style="border-bottom:1px solid var(--border2)"><h3 class="text-sm font-bold" style="color:var(--text)">Pontos de Atenção</h3></div>
            <div class="p-5 flex flex-col gap-3">
                @foreach($diagnostic->gaps as $gap)
                    <div class="p-4 rounded" style="background:var(--s3); border-left:3px solid var(--red)">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-bold" style="color:var(--text)">{{ $gap['titulo'] }}</p>
                            @if(!empty($gap['estimated_leads_lost']) && $avgTicket)
                                <span class="text-xs font-mono flex-shrink-0" style="color:var(--red)">~R$ {{ number_format($gap['estimated_leads_lost'] * $avgTicket, 0, ',', '.') }}</span>
                            @endif
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted)">{{ $gap['descricao'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- O que funciona --}}
    @if(!empty($diagnostic->strengths))
        <div class="card mb-6">
            <div class="p-5" style="border-bottom:1px solid var(--border2)"><h3 class="text-sm font-bold" style="color:var(--text)">O que Já Funciona</h3></div>
            <div class="p-5 flex flex-col gap-3">
                @foreach($diagnostic->strengths as $strength)
                    <div class="p-4 rounded" style="background:var(--s3); border-left:3px solid var(--green)">
                        <p class="text-sm font-bold" style="color:var(--text)">{{ $strength['titulo'] }}</p>
                        <p class="text-xs mt-1" style="color:var(--muted)">{{ $strength['descricao'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Insights de campanha --}}
    @if(!empty($diagnostic->campaign_insights))
        <div class="card mb-6">
            <div class="p-5" style="border-bottom:1px solid var(--border2)"><h3 class="text-sm font-bold" style="color:var(--text)">Insights de Campanha</h3></div>
            <div class="p-5 flex flex-col gap-3">
                @foreach($diagnostic->campaign_insights as $insight)
                    <div class="p-4 rounded" style="background:var(--s3); border-left:3px solid var(--purple)">
                        <p class="text-sm font-bold" style="color:var(--text)">{{ $insight['titulo'] }}</p>
                        <p class="text-xs mt-1" style="color:var(--muted)">{{ $insight['descricao'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recomendações --}}
    @if($quickWins->isNotEmpty() || $estruturais->isNotEmpty())
        <div class="card mb-6">
            <div class="p-5" style="border-bottom:1px solid var(--border2)"><h3 class="text-sm font-bold" style="color:var(--text)">Recomendações</h3></div>
            <div class="p-5">
                @foreach(['quick_win' => ['label' => 'Quick wins', 'color' => 'var(--orange)', 'items' => $quickWins], 'estrutural' => ['label' => 'Estruturais', 'color' => 'var(--purple)', 'items' => $estruturais]] as $group)
                    @if($group['items']->isNotEmpty())
                        <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:{{ $group['color'] }}">{{ $group['label'] }}</p>
                        <div class="flex flex-col gap-3 mb-4">
                            @foreach($group['items'] as $item)
                                <div class="p-4 rounded" style="background:var(--s3); border-left:3px solid {{ $group['color'] }}">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <p class="text-sm font-bold" style="color:var(--text)">{{ $item->title }}</p>
                                        <span class="text-xs font-mono px-2 py-0.5 rounded flex-shrink-0"
                                              style="background:var(--s2); color:var(--{{ $item->statusColor() }})">
                                            {{ $item->statusLabel() }}
                                        </span>
                                    </div>
                                    <p class="text-xs" style="color:var(--muted)">{{ $item->description }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Metodologia --}}
    <div class="card" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-5 text-left">
            <h3 class="text-sm font-bold" style="color:var(--text)">Como calculamos este diagnóstico</h3>
            <span class="text-xs transition-transform" :style="open ? 'transform:rotate(180deg)' : ''" style="color:var(--muted)">▾</span>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-5" style="border-top:1px solid var(--border2)">
            <p class="text-sm mt-4 mb-3" style="color:var(--muted2); line-height:1.7">
                Este diagnóstico é uma metodologia proprietária da Nonna, construída sobre conceitos consolidados de gestão comercial e ciência de dados — não é uma opinião solta, cada peça tem uma base:
            </p>
            <ul class="flex flex-col gap-3">
                <li class="text-sm" style="color:var(--muted2); line-height:1.6">
                    <strong style="color:var(--text)">Tempo de resposta e conversão</strong> — pesquisas de gestão comercial (como o estudo de Lead Response Management, do MIT/InsideSales.com) mostram que a velocidade da primeira resposta impacta diretamente a chance de fechar uma venda. Por isso monitoramos isso de perto.
                </li>
                <li class="text-sm" style="color:var(--muted2); line-height:1.6">
                    <strong style="color:var(--text)">Análise de funil</strong> — mapeamos cada etapa da jornada (entrada, qualificação, proposta, fechamento) pra identificar exatamente onde as oportunidades se perdem, seguindo o modelo clássico de funil de vendas.
                </li>
                <li class="text-sm" style="color:var(--muted2); line-height:1.6">
                    <strong style="color:var(--text)">Índice de Atendimento</strong> — um índice único (0 a 100) que combina velocidade, conversão, consistência e sentimento, inspirado em técnicas de análise multicritério — o mesmo princípio por trás de índices conhecidos como o IDH.
                </li>
                <li class="text-sm" style="color:var(--muted2); line-height:1.6">
                    <strong style="color:var(--text)">Inteligência Artificial</strong> — as conversas são analisadas por IA especializada em processamento de linguagem natural, que identifica padrões, objeções, pontos fortes e oportunidades reais de melhoria.
                </li>
                <li class="text-sm" style="color:var(--muted2); line-height:1.6">
                    <strong style="color:var(--text)">Melhoria contínua</strong> — cada diagnóstico compara o período atual com o anterior e acompanha se as recomendações foram implementadas, seguindo o princípio dos ciclos de melhoria contínua (PDCA) usados em gestão da qualidade.
                </li>
            </ul>
            <p class="text-xs mt-4" style="color:var(--muted)">
                Os parâmetros usados no cálculo são definidos pela Nonna com base em boas práticas de mercado, e evoluem conforme acumulamos mais dados reais da sua operação.
            </p>
        </div>
    </div>
</x-portal-layout>
