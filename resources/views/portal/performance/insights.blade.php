{{-- Aba Insights do Portal: só os resumos em linguagem de cliente
     (client_summary) dos últimos 60 dias — nunca o diagnóstico interno. --}}
<x-portal-layout>
    <x-slot name="title">Insights</x-slot>

    @include('portal.performance._tabs')

    <div class="mb-4">
        <h1 class="text-2xl font-black" style="color: var(--text)">Insights</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">O que a análise das suas campanhas mostrou e o que a nossa equipe está fazendo a respeito.</p>
    </div>

    <div class="flex flex-col gap-3">
        @forelse($insights as $insight)
            <div class="card px-5 py-4">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="text-xs font-semibold" style="color:var(--text)">{{ $insight->campaign?->name ?? 'Seus anúncios' }}</span>
                    <span class="text-xs" style="color:var(--muted)">· {{ $insight->generated_at->format('d/m/Y') }}</span>
                    @if($insight->status === 'resolvido')
                        <span class="badge tag-green" style="font-size:10px">Tratado pela equipe</span>
                    @endif
                </div>
                <p class="text-sm" style="color:var(--muted2); line-height:1.6">{{ $insight->client_summary }}</p>
                @if($insight->ad_campaign_id && app('currentPortalClient')->portalModuleActive('trafego'))
                    <a href="{{ route('portal.campaigns.show', $insight->ad_campaign_id) }}" class="inline-block text-xs font-semibold mt-2 hover:underline" style="color:var(--purple)">Ver campanha →</a>
                @endif
            </div>
        @empty
            <div class="card px-6 py-10 text-center">
                <p class="text-sm" style="color:var(--muted)">Ainda não há análises dos últimos 60 dias. Elas aparecem aqui a cada ciclo de otimização das campanhas.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $insights->links() }}</div>
</x-portal-layout>
