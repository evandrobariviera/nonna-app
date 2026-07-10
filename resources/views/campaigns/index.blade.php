<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Rotina</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Campanhas</h1>
            </div>
        </div>
    </x-slot>

    {{-- ── STATS ── --}}
    <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(4, 1fr)">
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--text)">
                R$ {{ number_format($stats['period_spend'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Gasto ({{ $periodLabel }})</div>
        </div>

        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--purple)">
                R$ {{ number_format($stats['total_budget'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Orçamento combinado (mês)</div>
        </div>

        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:{{ $stats['over_budget_count'] > 0 ? 'var(--red)' : 'var(--text)' }}">
                {{ $stats['over_budget_count'] }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Clientes acima do orçado (mês)</div>
        </div>

        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--green)">{{ $stats['active_campaigns'] }}</div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Campanhas ativas</div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── INSIGHTS (colapsado por padrão) ── --}}
    <div class="mb-6" x-data="{ open: false }">
        <button @click="open = !open" type="button"
            class="text-xs font-mono uppercase tracking-widest transition-colors flex items-center gap-2 mb-3"
            style="color:var(--muted)"
            onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
            <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
            Insights ({{ $openInsights->count() }})
        </button>

        <div x-show="open" x-cloak>
            @if($openInsights->isEmpty())
                <div class="card px-6 py-8 text-center" style="color:var(--muted)">
                    <p class="text-sm">Nenhum insight em aberto no período/filtro selecionado.</p>
                </div>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($openInsights as $insight)
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
                                            <button type="submit" class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                                onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                                                Marcar como lido
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('campaign-insights.update-status', $insight) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="resolvido">
                                        <button type="submit" class="text-xs font-mono transition-colors" style="color:var(--green)">
                                            Resolver
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('campaign-insights.update-status', $insight) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="descartado">
                                        <button type="submit" class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
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

    {{-- Filtros --}}
    <form method="GET" action="{{ route('campaigns.index') }}" class="flex flex-wrap items-center gap-3 mb-5">
        <select name="client_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ $clientId === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <select name="ad_campaign_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todas as campanhas</option>
            @foreach($campaignOptions as $opt)
                <option value="{{ $opt->id }}" {{ $campaignId === $opt->id ? 'selected' : '' }}>
                    {{ $clientId ? $opt->name : ($opt->adAccount?->client?->company_name . ' — ' . $opt->name) }}
                </option>
            @endforeach
        </select>

        <select name="platform"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todas as plataformas</option>
            <option value="meta" {{ $platform === 'meta' ? 'selected' : '' }}>Meta Ads</option>
            <option value="google" {{ $platform === 'google' ? 'selected' : '' }}>Google Ads</option>
        </select>

        <select name="status"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="" {{ $statusFilter === '' ? 'selected' : '' }}>Todos os status</option>
            @foreach(\App\Http\Controllers\CampaignController::$campaignStatuses as $key => $label)
                <option value="{{ $key }}" {{ $statusFilter === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="period"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            @foreach(\App\Http\Controllers\CampaignController::$periods as $key => $label)
                <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-ghost btn-sm">Filtrar</button>

        @if(request()->hasAny(['client_id', 'ad_campaign_id', 'platform', 'status', 'period']))
            <a href="{{ route('campaigns.index') }}"
               class="text-xs font-mono transition-colors" style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                Limpar
            </a>
        @endif
    </form>

    <div class="card">
        @if($campaigns->isEmpty())
            <div class="px-6 py-16 text-center" style="color:var(--muted)">
                <p class="text-sm">Nenhuma campanha encontrada.</p>
            </div>
        @else
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Campanha</th>
                        <th>Plataforma</th>
                        <th>Status</th>
                        <th>Gasto ({{ $periodLabel }})</th>
                        <th>CPA</th>
                        <th>CTR</th>
                        <th>ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campaigns as $campaign)
                        <tr>
                            <td class="font-semibold text-[var(--text)]">
                                {{ $campaign->adAccount?->client?->company_name ?? '—' }}
                            </td>
                            <td class="text-[var(--text)]">{{ $campaign->name }}</td>
                            <td class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $campaign->platform }}</td>
                            <td>
                                <span class="badge">{{ \App\Http\Controllers\CampaignController::$campaignStatuses[$campaign->status] ?? $campaign->status }}</span>
                            </td>
                            <td class="text-xs font-mono text-[var(--muted2)]">R$ {{ number_format($campaign->stats->spend, 2, ',', '.') }}</td>
                            <td class="text-xs font-mono text-[var(--muted2)]">
                                {{ $campaign->stats->cpa !== null ? 'R$ ' . number_format($campaign->stats->cpa, 2, ',', '.') : '—' }}
                            </td>
                            <td class="text-xs font-mono text-[var(--muted2)]">
                                {{ $campaign->stats->ctr !== null ? number_format($campaign->stats->ctr, 2, ',', '.') . '%' : '—' }}
                            </td>
                            <td class="text-xs font-mono text-[var(--muted2)]">
                                {{ $campaign->stats->roas !== null ? number_format($campaign->stats->roas, 2, ',', '.') . 'x' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
