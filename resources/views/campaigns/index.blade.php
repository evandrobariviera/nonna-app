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

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── INSIGHTS (colapsado por padrão) ── --}}
    @include('campaigns.partials._insights-panel', ['insights' => $openInsights])

    {{-- Filtros --}}
    <form method="GET" action="{{ route('campaigns.index') }}" class="flex flex-wrap items-center gap-3 mb-5">
        <select name="client_id" onchange="this.form.ad_campaign_id.value=''; this.form.submit()"
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

        <select name="platform" onchange="this.form.ad_campaign_id.value=''; this.form.submit()"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todas as plataformas</option>
            <option value="meta" {{ $platform === 'meta' ? 'selected' : '' }}>Meta Ads</option>
            <option value="google" {{ $platform === 'google' ? 'selected' : '' }}>Google Ads</option>
        </select>

        <select name="status" onchange="this.form.ad_campaign_id.value=''; this.form.submit()"
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

        <select name="group_by" onchange="this.form.submit()"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="" {{ $groupBy === '' ? 'selected' : '' }}>Sem agrupamento</option>
            <option value="cliente" {{ $groupBy === 'cliente' ? 'selected' : '' }}>Agrupar por Cliente</option>
            <option value="situacao" {{ $groupBy === 'situacao' ? 'selected' : '' }}>Agrupar por Situação</option>
        </select>

        <button type="submit" class="btn btn-ghost btn-sm">Filtrar</button>

        @if(request()->hasAny(['client_id', 'ad_campaign_id', 'platform', 'status', 'period', 'group_by']))
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
</x-app-layout>
