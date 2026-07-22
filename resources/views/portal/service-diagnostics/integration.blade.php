@php
    $metrics = [
        'service_score' => ['label' => 'Índice de Atendimento', 'suffix' => ''],
        'conversion_rate' => ['label' => 'Taxa de conversão', 'suffix' => '%'],
        'avg_first_response_minutes' => ['label' => '1ª resposta (min)', 'suffix' => 'min'],
        'avg_sentiment_score' => ['label' => 'Sentimento', 'suffix' => ''],
        'sales_confirmed' => ['label' => 'Vendas confirmadas', 'suffix' => ''],
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

        $series[$key] = compact('points', 'current', 'delta') + $meta;
    }
@endphp

<x-portal-layout>
    <x-slot name="title">Diagnóstico de Atendimento · {{ $integration->label }}</x-slot>

    <div class="mb-6">
        <a href="{{ route('portal.service-diagnostics.index') }}" class="text-xs" style="color:var(--muted)">← Voltar</a>
        <h1 class="text-2xl font-black mt-2" style="color: var(--text)">{{ $integration->label }}</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">{{ $client->company_name }}</p>
    </div>

    @if($diagnostics->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-2xl mb-2">📊</p>
            <p class="text-sm font-semibold" style="color: var(--text)">Nenhum diagnóstico publicado ainda</p>
            <p class="text-xs mt-1" style="color: var(--muted)">Assim que a primeira análise for concluída, ela aparece aqui.</p>
        </div>
    @else
        {{-- Evolução --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-8">
            @foreach($series as $key => $s)
                <div class="card p-5">
                    <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color: var(--muted)">{{ $s['label'] }}</p>
                    <p class="text-2xl font-black leading-none" style="color: var(--text)">
                        {{ rtrim(rtrim(number_format($s['current'], 1, ',', '.'), '0'), ',') }}{{ $s['suffix'] }}
                    </p>
                    @if($s['delta'] !== null)
                        <p class="text-xs font-semibold mt-1" style="color: {{ $s['delta'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                            {{ $s['delta'] >= 0 ? '+' : '' }}{{ number_format($s['delta'], 1, ',', '.') }}{{ $s['suffix'] }} vs anterior
                        </p>
                    @endif
                    @if($diagnostics->count() > 1)
                        <svg viewBox="0 0 100 28" class="mt-2" style="width:100%; height:24px" preserveAspectRatio="none">
                            <polyline points="{{ $s['points'] }}" fill="none" stroke="var(--purple)" stroke-width="2"
                                      stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Histórico --}}
        <div class="card">
            <div class="p-5" style="border-bottom:1px solid var(--border2)">
                <h3 class="text-sm font-bold" style="color:var(--text)">Histórico de Diagnósticos</h3>
            </div>
            <div>
                @foreach($diagnostics->sortByDesc('version') as $d)
                    <a href="{{ route('portal.service-diagnostics.show', [$integration, $d]) }}"
                       class="flex items-center justify-between p-5 transition-colors"
                       style="border-bottom:1px solid var(--border2)">
                        <div>
                            <p class="text-sm font-semibold" style="color:var(--text)">Versão {{ $d->version }}</p>
                            <p class="text-xs font-mono mt-1" style="color:var(--muted)">
                                {{ $d->period_start->format('d/m/Y') }} – {{ $d->period_end->format('d/m/Y') }}
                                · {{ $d->total_conversations }} conversas · Índice {{ $d->service_score ?? '—' }}
                            </p>
                        </div>
                        <span class="text-xs" style="color:var(--muted)">Ver →</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-portal-layout>
