<x-portal-layout>
    <x-slot name="title">Atendimento Assistido</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Atendimento Assistido</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            Análise periódica das conversas de atendimento via WhatsApp — {{ $client->company_name }}.
        </p>
    </div>

    @if($diagnostics->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-2xl mb-2">📊</p>
            <p class="text-sm font-semibold" style="color: var(--text)">Nenhum diagnóstico publicado ainda</p>
            <p class="text-xs mt-1" style="color: var(--muted)">Assim que a primeira análise for concluída, ela aparece aqui.</p>
        </div>
    @else
        @php $latest = $diagnostics->sortByDesc('version')->first(); @endphp

        {{-- Cards de métricas (versão mais recente) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Índice de Atendimento</p>
                <p class="text-3xl font-black leading-none" style="color: var(--purple)">{{ $latest->service_score ?? '—' }}</p>
                <p class="text-xs mt-2" style="color: var(--muted)">de 100</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Taxa de conversão</p>
                <p class="text-3xl font-black leading-none" style="color: var(--green)">{{ number_format($latest->conversion_rate, 1, ',', '.') }}%</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">1ª resposta</p>
                <p class="text-3xl font-black leading-none" style="color: var(--text)">{{ $latest->avg_first_response_minutes ?? '—' }} min</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Vendas confirmadas</p>
                <p class="text-3xl font-black leading-none" style="color: var(--text)">{{ $latest->sales_confirmed }}</p>
                <p class="text-xs mt-2" style="color: var(--muted)">+{{ $latest->sales_in_negotiation }} em negociação</p>
            </div>
        </div>

        {{-- Histórico --}}
        <div class="card mb-8">
            <div class="p-5" style="border-bottom:1px solid var(--border2)">
                <h3 class="text-sm font-bold" style="color:var(--text)">Histórico de Diagnósticos</h3>
            </div>
            <div>
                @foreach($diagnostics->sortByDesc('version') as $d)
                    <a href="{{ route('portal.service-diagnostics.show', [$d->integration, $d]) }}"
                       class="flex items-center justify-between p-5 transition-colors"
                       style="border-bottom:1px solid var(--border2)">
                        <div>
                            <p class="text-sm font-semibold" style="color:var(--text)">
                                Versão {{ $d->version }}
                                @if($integrations->count() > 1 && $d->integration)
                                    <span class="text-xs font-normal" style="color:var(--muted)">· {{ $d->integration->label }}</span>
                                @endif
                            </p>
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

    {{-- Números Conectados --}}
    @if($integrations->isNotEmpty())
        <div class="mb-4">
            <h2 class="text-xs font-bold uppercase tracking-widest" style="color: var(--muted)">Números Conectados</h2>
        </div>
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr))">
            @foreach($integrations as $integration)
                <a href="{{ route('portal.service-diagnostics.integration', $integration) }}"
                   class="card p-4 flex flex-col items-center justify-center text-center transition-colors"
                   style="aspect-ratio: 1">
                    <span class="text-2xl mb-2">📱</span>
                    <p class="text-sm font-bold" style="color: var(--text)">{{ $integration->label }}</p>
                    <p class="text-xs mt-1" style="color: var(--muted)">
                        {{ $integration->published_diagnostics_count }} diagnóstico{{ $integration->published_diagnostics_count !== 1 ? 's' : '' }}
                    </p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full mt-2"
                          style="background: {{ $integration->isConnected() ? 'rgba(5,150,105,.1)' : 'var(--s3)' }};
                                 color: {{ $integration->isConnected() ? 'var(--green)' : 'var(--muted)' }}">
                        {{ $integration->statusLabel() }}
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-portal-layout>
