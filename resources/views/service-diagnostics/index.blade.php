<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Diagnóstico de Atendimento</span>
    </x-slot>

    <div style="max-width:960px">

        <p class="text-sm mb-6" style="color:var(--muted)">
            Selecione um cliente e um número de atendimento para ver os diagnósticos gerados a partir das conversas de WhatsApp.
        </p>

        @if($integrations->isEmpty())
            <div class="text-center py-16" style="border:1px dashed var(--border2); border-radius:8px">
                <div class="text-4xl mb-3">📊</div>
                <div class="text-sm font-semibold mb-1" style="color:var(--text)">Nenhum número de atendimento cadastrado ainda</div>
                <div class="text-xs mb-4" style="color:var(--muted)">Cadastre um número na aba "Atendimento" da ficha do cliente para começar.</div>
            </div>
        @else
            <div class="flex flex-col gap-6">
                @foreach($integrations as $clientName => $clientIntegrations)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-sm font-bold" style="color:var(--text)">{{ $clientName }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid gap-3">
                                @foreach($clientIntegrations as $integration)
                                    <a href="{{ route('service-diagnostics.integration', $integration) }}"
                                       class="flex items-center justify-between px-4 py-3 transition-colors"
                                       style="border:1px solid var(--border2); border-radius:8px;">
                                        <div class="flex items-center gap-3">
                                            <span class="h-8 w-8 rounded flex items-center justify-center text-xs font-black flex-shrink-0"
                                                  style="background:var(--s3); color:var(--purple)">
                                                📱
                                            </span>
                                            <div>
                                                <div class="text-sm font-semibold" style="color:var(--text)">{{ $integration->label }}</div>
                                                <div class="text-xs font-mono" style="color:var(--muted)">{{ $integration->providerLabel() }} · {{ $integration->statusLabel() }}</div>
                                            </div>
                                        </div>
                                        <div class="text-xs font-mono" style="color:var(--muted)">
                                            {{ $integration->diagnostics_count }} diagnóstico{{ $integration->diagnostics_count !== 1 ? 's' : '' }} →
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
