{{-- Fragmento reaproveitado no load inicial (campaigns.index) e na busca dinâmica via
     AJAX (CampaignController::results(), fetch disparado por live-filter.js). Cards de
     stats + insights + tabela mudam todos juntos com o filtro (mesmo escopo). --}}

{{-- ── STATS ── --}}
<div class="grid gap-3 mb-6" style="grid-template-columns: repeat(4, 1fr)">
    <div class="card px-4 py-4 text-left">
        <div class="text-2xl font-black mb-1" style="color:var(--text)">
            R$ {{ number_format($stats['period_spend'], 2, ',', '.') }}
        </div>
        <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Gasto ({{ $periodLabel }})</div>
        @if($stats['period_spend_delta'] !== null)
            <div class="text-xs font-mono mt-1" style="color:{{ $stats['period_spend_delta'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                {{ $stats['period_spend_delta'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($stats['period_spend_delta']), 1, ',', '.') }}% vs período anterior
            </div>
        @endif
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

{{-- ── INSIGHTS (colapsado por padrão) ── --}}
@include('campaigns.partials._insights-panel', ['insights' => $openInsights])

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
                    <th>Última Otimização</th>
                    <th></th>
                </tr>
            </thead>
            @if($groupBy)
                @foreach($campaignsGrouped as $groupKey => $groupCampaigns)
                    @include('partials._campaign-group-tbody', ['groupBy' => $groupBy, 'groupKey' => $groupKey, 'groupCampaigns' => $groupCampaigns])
                @endforeach
            @else
                <tbody x-data="{ groupOpen: true }">
                    @foreach($campaigns as $campaign)
                        @include('partials._campaign-tr', ['campaign' => $campaign])
                    @endforeach
                </tbody>
            @endif
        </table>
    @endif
</div>
