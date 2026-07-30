@php
    $metrics = [
        'service_score' => ['label' => 'Índice de Atendimento', 'suffix' => '', 'direction' => 'up'],
        'conversion_rate' => ['label' => 'Taxa de conversão', 'suffix' => '%', 'direction' => 'up'],
        'avg_first_response_minutes' => ['label' => '1ª resposta (min)', 'suffix' => 'min', 'direction' => 'down'],
        'avg_sentiment_score' => ['label' => 'Sentimento', 'suffix' => '', 'direction' => 'up'],
        'sales_confirmed' => ['label' => 'Vendas confirmadas', 'suffix' => '', 'direction' => 'up'],
        'estimated_lost_revenue' => ['label' => 'R$ perdido no período', 'suffix' => '', 'direction' => 'down'],
    ];

    $series = [];
    foreach ($metrics as $key => $meta) {
        $values = $diagnostics->pluck($key)->map(fn ($v) => (float) $v)->values();
        $min = $values->min();
        $max = $values->max();
        $range = ($max - $min) ?: 1;
        $count = $values->count();

        $points = $values->values()->map(function ($v, $i) use ($count, $min, $range) {
            $x = $count > 1 ? ($i / ($count - 1)) * 100 : 50;
            $y = 26 - (($v - $min) / $range) * 22;
            return round($x, 1) . ',' . round($y, 1);
        })->implode(' ');

        $current = $values->last();
        $previous = $count > 1 ? $values->get($count - 2) : null;
        $delta = $previous !== null ? $current - $previous : null;

        $isGood = null;
        if ($delta !== null && $meta['direction']) {
            $isGood = $meta['direction'] === 'up' ? $delta >= 0 : $delta <= 0;
        }

        $series[$key] = compact('points', 'current', 'delta', 'isGood') + $meta;
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">
            <a href="{{ route('service-diagnostics.index') }}" style="color:var(--muted)">Atendimento Assistido</a>
            <span style="color:var(--muted)">/</span>
            {{ $integration->client->company_name }} · {{ $integration->label }}
        </span>
    </x-slot>

    <div style="max-width:960px">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded text-sm font-medium"
                 style="background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.25)">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 rounded text-sm font-medium"
                 style="background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.25)">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                {{ $integration->providerLabel() }} · {{ $integration->statusLabel() }} ·
                cadência de {{ $integration->diagnosticFrequencyDays() }} dias
            </p>
            @if($readiness['pending_conversations'] > 0)
                <form method="POST" action="{{ route('service-diagnostics.generate', $integration) }}"
                      @submit.prevent="if (await $store.confirmDialog.ask('Gerar um novo diagnóstico agora, analisando as conversas desde a última rodada? Isso consome créditos de IA.')) $el.submit()">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background:var(--purple)">
                        Gerar diagnóstico agora
                    </button>
                </form>
            @else
                <button type="button" disabled
                        class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest opacity-50 cursor-not-allowed"
                        style="background:var(--s3); color:var(--muted)">
                    Gerar diagnóstico agora
                </button>
            @endif
        </div>

        {{-- Prontidão pro próximo diagnóstico --}}
        <div class="card mb-8">
            <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text)">Prontidão para o Próximo Diagnóstico</h3></div>
            <div class="card-body">
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="stat-card">
                        <p class="stat-label">Conversas (total)</p>
                        <p class="stat-value">{{ $readiness['total_conversations'] }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Mensagens (total)</p>
                        <p class="stat-value">{{ $readiness['total_messages'] }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Última mensagem recebida</p>
                        <p class="stat-value" style="font-size:16px">{{ $readiness['last_message_at'] ? \Illuminate\Support\Carbon::parse($readiness['last_message_at'])->diffForHumans() : '—' }}</p>
                    </div>
                </div>

                <div class="px-4 py-3 rounded mb-3" style="background:var(--s3); border-left:3px solid {{ $readiness['pending_conversations'] > 0 ? 'var(--purple)' : 'var(--muted)' }}">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:{{ $readiness['pending_conversations'] > 0 ? 'var(--purple)' : 'var(--muted)' }}">
                        Próximo período a analisar
                    </p>
                    <p class="text-sm" style="color:var(--text)">
                        {{ $readiness['period_start']->format('d/m/Y') }} até {{ $readiness['period_end']->format('d/m/Y H:i') }}
                    </p>
                    @if($readiness['pending_conversations'] > 0)
                        <p class="text-xs mt-1" style="color:var(--muted)">
                            {{ $readiness['pending_conversations'] }} conversa{{ $readiness['pending_conversations'] !== 1 ? 's' : '' }}
                            · {{ $readiness['pending_messages'] }} mensage{{ $readiness['pending_messages'] !== 1 ? 'ns' : 'm' }} nesse período
                        </p>
                    @else
                        <p class="text-xs mt-1" style="color:var(--muted)">Nenhuma conversa nova nesse período ainda — o botão fica desabilitado até chegar mensagem.</p>
                    @endif
                </div>
            </div>
        </div>

        @if($diagnostics->isEmpty())
            <div class="text-center py-16" style="border:1px dashed var(--border2); border-radius:8px">
                <div class="text-4xl mb-3">🕐</div>
                <div class="text-sm font-semibold mb-1" style="color:var(--text)">Nenhum diagnóstico gerado ainda</div>
                <div class="text-xs" style="color:var(--muted)">Assim que a primeira rodada rodar (agendada ou manual), o histórico aparece aqui.</div>
            </div>
        @else
            {{-- Evolução de KPIs --}}
            <h3 class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Evolução</h3>
            <div class="grid grid-cols-3 gap-3 mb-8">
                @foreach($series as $key => $s)
                    <div class="stat-card">
                        <p class="stat-label">{{ $s['label'] }}</p>
                        <p class="stat-value">{{ $key === 'estimated_lost_revenue' ? 'R$ ' . number_format($s['current'], 0, ',', '.') : rtrim(rtrim(number_format($s['current'], 1, ',', '.'), '0'), ',') . $s['suffix'] }}</p>

                        @if($s['delta'] !== null)
                            <p class="text-xs font-mono mt-1"
                               style="color: {{ $s['isGood'] === null ? 'var(--muted)' : ($s['isGood'] ? 'var(--green)' : 'var(--red)') }}">
                                {{ $s['delta'] >= 0 ? '+' : '' }}{{ number_format($s['delta'], 1, ',', '.') }}{{ $s['suffix'] }} vs anterior
                            </p>
                        @endif

                        @if($diagnostics->count() > 1)
                            <svg viewBox="0 0 100 28" class="mt-2" style="width:100%; height:28px" preserveAspectRatio="none">
                                <polyline points="{{ $s['points'] }}" fill="none"
                                          stroke="var(--purple)" stroke-width="2"
                                          stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Lista de versões --}}
            <h3 class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Histórico</h3>
            <div class="flex flex-col gap-3">
                @foreach($diagnostics->sortByDesc('version') as $diagnostic)
                    <a href="{{ route('service-diagnostics.show', [$integration, $diagnostic]) }}"
                       class="flex items-center justify-between px-4 py-3 transition-colors"
                       style="border:1px solid var(--border2); border-radius:8px;">
                        <div>
                            <div class="text-sm font-semibold" style="color:var(--text)">
                                Versão {{ $diagnostic->version }}
                                <span class="text-xs font-mono ml-2 px-2 py-0.5 rounded"
                                      style="background:var(--s3); color: {{ $diagnostic->isPublished() ? 'var(--green)' : 'var(--muted)' }}">
                                    {{ $diagnostic->statusLabel() }}
                                </span>
                            </div>
                            <div class="text-xs font-mono" style="color:var(--muted)">
                                {{ $diagnostic->period_start->format('d/m/Y') }} – {{ $diagnostic->period_end->format('d/m/Y') }}
                                · {{ $diagnostic->total_conversations }} conversas
                                · conversão {{ number_format($diagnostic->conversion_rate, 1, ',', '.') }}%
                            </div>
                        </div>
                        <span class="text-xs font-mono" style="color:var(--muted)">Ver →</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
