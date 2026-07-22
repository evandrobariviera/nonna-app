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
            <a href="{{ route('service-diagnostics.index') }}" style="color:var(--muted)">Diagnóstico de Atendimento</a>
            <span style="color:var(--muted)">/</span>
            {{ $integration->client->company_name }} · {{ $integration->label }}
        </span>
    </x-slot>

    <div style="max-width:960px">

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                {{ $integration->providerLabel() }} · {{ $integration->statusLabel() }} ·
                cadência de {{ $integration->diagnosticFrequencyDays() }} dias
            </p>
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
