{{-- Painel de insights — usado em campaigns/index.blade.php (colapsado por
     padrão, lista com vários clientes) e campaigns/show.blade.php (aberto por
     padrão, já que ali é sobre uma única campanha). Recebe $insights
     (Collection<CampaignInsight>, com campaign/adset/ad eager-loaded) e,
     opcionalmente, $openByDefault (bool, default false). --}}
@php($openByDefault = $openByDefault ?? false)
<div class="mb-6" x-data="{ open: {{ $openByDefault ? 'true' : 'false' }} }">
    <button @click="open = !open" type="button"
        class="text-xs font-mono uppercase tracking-widest transition-colors flex items-center gap-2 mb-3"
        style="color:var(--muted)"
        onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
        <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
        Insights ({{ $insights->count() }})
    </button>

    <div x-show="open" x-cloak>
        @if($insights->isEmpty())
            <div class="card px-6 py-8 text-center" style="color:var(--muted)">
                <p class="text-sm">Nenhum insight em aberto no período/filtro selecionado.</p>
            </div>
        @else
            <div class="flex flex-col gap-2">
                @foreach($insights as $insight)
                    <div class="card px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="badge badge-{{ $insight->severityColor() }}">{{ $insight->severityLabel() }}</span>
                                    <span class="badge badge-{{ $insight->statusColor() }}">{{ $insight->statusLabel() }}</span>
                                    <span class="font-bold text-sm" style="color:var(--text)">{{ $insight->title }}</span>
                                </div>
                                <p class="text-xs font-mono mb-1" style="color:var(--muted)">
                                    {{ $insight->client->company_name }}
                                    @if($insight->campaign) · {{ $insight->campaign->name }} @endif
                                    @if($insight->adset) → {{ $insight->adset->name }} @endif
                                    @if($insight->ad) → {{ $insight->ad->name }} @endif
                                    · {{ $insight->generated_at->format('d/m/Y H:i') }}
                                </p>
                                @if($insight->summary)
                                    <p class="text-sm mt-2" style="color:var(--muted2)">{{ $insight->summary }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($insight->status === 'novo')
                                    <form method="POST" action="{{ route('campaign-insights.update-status', $insight) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="lido">
                                        <button type="submit" class="btn btn-ghost btn-xs">
                                            Marcar como lido
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('campaign-insights.update-status', $insight) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="resolvido">
                                    <button type="submit" class="btn btn-success btn-xs">
                                        Resolver
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('campaign-insights.update-status', $insight) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="descartado">
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        Descartar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
