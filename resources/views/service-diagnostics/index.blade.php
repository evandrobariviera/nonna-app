<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Atendimento Assistido</span>
    </x-slot>

    <div style="max-width:960px">

        <p class="text-sm mb-6" style="color:var(--muted)">
            Selecione um cliente e um número de atendimento para ver os diagnósticos gerados a partir das conversas de WhatsApp.
        </p>

        @if($integrations->isEmpty())
            <div class="text-center py-16" style="border:1px dashed var(--border2); border-radius:8px">
                <div class="mb-3 flex justify-center" style="color:var(--muted)"><x-icon name="bar-chart-3" size="36" /></div>
                <div class="text-sm font-semibold mb-1" style="color:var(--text)">Nenhum número de atendimento cadastrado ainda</div>
                <div class="text-xs mb-4" style="color:var(--muted)">Cadastre um número na aba "Atendimento" da ficha do cliente para começar.</div>
            </div>
        @else
            <div class="flex flex-col gap-6">
                @foreach($integrations as $clientName => $clientIntegrations)
                    <div>
                        <h3 class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">{{ $clientName }}</h3>
                        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr))">
                            @foreach($clientIntegrations as $integration)
                                <a href="{{ route('service-diagnostics.integration', $integration) }}"
                                   class="card p-4 flex flex-col items-center justify-center text-center transition-colors"
                                   style="aspect-ratio: 1">
                                    <span class="mb-2" style="color:var(--muted)"><x-icon name="smartphone" size="24" /></span>
                                    <p class="text-sm font-bold" style="color:var(--text)">{{ $integration->label }}</p>
                                    <p class="text-xs mt-1" style="color:var(--muted)">
                                        {{ $integration->diagnostics_count }} diagnóstico{{ $integration->diagnostics_count !== 1 ? 's' : '' }}
                                    </p>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full mt-2"
                                          style="background: {{ $integration->isConnected() ? 'rgba(5,150,105,.1)' : 'var(--s3)' }};
                                                 color: {{ $integration->isConnected() ? 'var(--green)' : 'var(--muted)' }}">
                                        {{ $integration->statusLabel() }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
