<x-portal-layout>
    <x-slot name="title">{{ $campaign->name }}</x-slot>

    @php
        $statusMap = [
            'active'   => ['label' => 'Ativa',     'bg' => 'rgba(5,150,105,.1)', 'text' => 'var(--green)'],
            'paused'   => ['label' => 'Pausada',   'bg' => 'rgba(238, 121, 25,.1)', 'text' => 'var(--orange)'],
            'deleted'  => ['label' => 'Removida',  'bg' => 'rgba(220,38,38,.1)', 'text' => 'var(--red)'],
            'archived' => ['label' => 'Arquivada', 'bg' => 'var(--s3)',          'text' => 'var(--muted)'],
        ];
        $st = $statusMap[$campaign->status] ?? $statusMap['paused'];
        $platformLabels = ['meta' => 'Meta Ads', 'google' => 'Google Ads', 'tiktok' => 'TikTok Ads'];
    @endphp

    <div class="mb-6">
        <a href="{{ route('portal.campaigns.index') }}" class="text-xs font-semibold" style="color: var(--muted)">← Campanhas</a>
    </div>

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black" style="color: var(--text)">{{ $campaign->name }}</h1>
            <p class="text-sm mt-1" style="color: var(--muted)">
                {{ $platformLabels[$campaign->platform] ?? $campaign->platform }}
                · {{ $campaign->adAccount->account_name ?? $campaign->adAccount->account_id ?? '—' }}
                @if($campaign->start_date)
                    · desde {{ $campaign->start_date->format('d/m/Y') }}
                @endif
            </p>
        </div>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0" style="background: {{ $st['bg'] }}; color: {{ $st['text'] }}">
            {{ $st['label'] }}
        </span>
    </div>

    @if($campaign->description)
        <div class="card p-5 mb-6">
            <p class="text-sm whitespace-pre-wrap" style="color: var(--muted2); line-height: 1.6">{{ $campaign->description }}</p>
        </div>
    @endif

    {{-- Filtro de período --}}
    <form method="GET" action="{{ route('portal.campaigns.show', $campaign) }}" class="mb-6">
        <select name="period" onchange="this.form.submit()"
                style="background: var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px; border-radius:6px; outline:none; cursor:pointer">
            @foreach(\App\Http\Controllers\Portal\CampaignController::$periods as $key => $label)
                <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    {{-- Métricas --}}
    @php
        $metrics = [
            ['key' => 'spend',       'label' => 'Investimento', 'fmt' => fn($v) => 'R$ '.number_format($v, 0, ',', '.')],
            ['key' => 'roas',        'label' => 'ROAS',         'fmt' => fn($v) => number_format($v ?? 0, 2, ',', '.').'x', 'color' => 'var(--purple)'],
            ['key' => 'conversions', 'label' => 'Conversões',   'fmt' => fn($v) => number_format($v, 0, ',', '.')],
            ['key' => 'cpa',         'label' => 'CPA',          'fmt' => fn($v) => $v !== null ? 'R$ '.number_format($v, 2, ',', '.') : '—'],
            ['key' => 'clicks',      'label' => 'Cliques',      'fmt' => fn($v) => number_format($v, 0, ',', '.')],
            ['key' => 'cpc',         'label' => 'CPC',          'fmt' => fn($v) => $v !== null ? 'R$ '.number_format($v, 2, ',', '.') : '—'],
            ['key' => 'ctr',         'label' => 'CTR',          'fmt' => fn($v) => $v !== null ? number_format($v, 2, ',', '.').'%' : '—'],
            ['key' => 'impressions', 'label' => 'Impressões',   'fmt' => fn($v) => number_format($v, 0, ',', '.')],
            ['key' => 'reach',       'label' => 'Alcance',      'fmt' => fn($v) => number_format($v, 0, ',', '.')],
        ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        @foreach($metrics as $m)
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">{{ $m['label'] }}</p>
                <p class="text-2xl font-black leading-none" style="color: {{ $m['color'] ?? 'var(--text)' }}">
                    {{ ($m['fmt'])($stats[$m['key']]) }}
                </p>
                @if($deltas[$m['key']] !== null)
                    <p class="text-xs font-semibold mt-2" style="color: {{ $deltas[$m['key']] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                        {{ $deltas[$m['key']] >= 0 ? '▲' : '▼' }} {{ number_format(abs($deltas[$m['key']]), 1, ',', '.') }}% vs período anterior
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Gráficos diários --}}
    @if($dailyStats->count() > 1)
        @php
            $maxSpend = $dailyStats->max('spend');
            $maxConv  = $dailyStats->max('conversions');
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color: var(--muted)">Investimento por Dia</p>
                <div class="flex items-end gap-1" style="height: 80px">
                    @foreach($dailyStats as $day)
                        @php
                            $pct = $maxSpend > 0 ? round(($day->spend / $maxSpend) * 100) : 2;
                            $dt  = \Carbon\Carbon::parse($day->snapshot_date);
                        @endphp
                        <div class="flex-1 flex flex-col items-center gap-1.5" style="height: 100%">
                            <div class="w-full flex items-end" style="height: calc(100% - 18px)">
                                <div class="w-full rounded-t cursor-default transition-opacity"
                                     title="{{ $dt->format('d/m') }}: R$ {{ number_format($day->spend, 0, ',', '.') }}"
                                     style="background: var(--grad); height: {{ max($pct, 3) }}%; opacity: 0.7"
                                     onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'"></div>
                            </div>
                            <span class="text-[9px] font-semibold" style="color: var(--muted)">{{ $dt->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color: var(--muted)">Conversões por Dia</p>
                <div class="flex items-end gap-1" style="height: 80px">
                    @foreach($dailyStats as $day)
                        @php
                            $pct = $maxConv > 0 ? round(($day->conversions / $maxConv) * 100) : 2;
                            $dt  = \Carbon\Carbon::parse($day->snapshot_date);
                        @endphp
                        <div class="flex-1 flex flex-col items-center gap-1.5" style="height: 100%">
                            <div class="w-full flex items-end" style="height: calc(100% - 18px)">
                                <div class="w-full rounded-t cursor-default transition-opacity"
                                     title="{{ $dt->format('d/m') }}: {{ number_format($day->conversions, 0, ',', '.') }}"
                                     style="background: var(--purple); height: {{ max($pct, 3) }}%; opacity: 0.7"
                                     onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'"></div>
                            </div>
                            <span class="text-[9px] font-semibold" style="color: var(--muted)">{{ $dt->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Como está indo --}}
    @if($insights->isNotEmpty())
        <div class="card p-5 mb-8">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Como está indo</p>
            <div class="flex flex-col gap-3">
                @foreach($insights as $insight)
                    <p class="text-sm" style="color: var(--muted2); line-height: 1.6">{{ $insight->client_summary }}</p>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Conjuntos de anúncios --}}
    <div class="card p-5 mb-8">
        <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color: var(--muted)">
            Conjuntos de Anúncios · {{ $periodLabel }}
        </p>

        @if($campaign->adsets->isEmpty())
            <p class="text-sm" style="color: var(--muted)">Nenhum conjunto sincronizado ainda.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($campaign->adsets as $adset)
                    @php($row = $adsetStats->get($adset->external_id))
                    <div x-data="{ open: false }" class="rounded-lg" style="border:1px solid var(--border2)">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                            <div class="flex items-center gap-2 min-w-0">
                                <span :class="open ? 'rotate-90' : ''" class="transition-transform text-xs flex-shrink-0" style="color: var(--muted)">▶</span>
                                <span class="text-sm font-semibold truncate" style="color: var(--text)">{{ $adset->name }}</span>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0 text-xs" style="color: var(--muted2)">
                                <span>R$ {{ number_format($row['spend'] ?? 0, 2, ',', '.') }}</span>
                                <span>{{ ($row['roas'] ?? null) !== null ? number_format($row['roas'], 2, ',', '.').'x' : '—' }}</span>
                            </div>
                        </button>
                        <div x-show="open" x-cloak class="px-4 pb-4">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3 text-xs" style="color: var(--muted2)">
                                <span>CPA: {{ ($row['cpa'] ?? null) !== null ? 'R$ '.number_format($row['cpa'], 2, ',', '.') : '—' }}</span>
                                <span>CTR: {{ ($row['ctr'] ?? null) !== null ? number_format($row['ctr'], 2, ',', '.').'%' : '—' }}</span>
                                <span>Cliques: {{ number_format($row['clicks'] ?? 0, 0, ',', '.') }}</span>
                                <span>Impressões: {{ number_format($row['impressions'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            @if($adset->ads->isEmpty())
                                <p class="text-xs" style="color: var(--muted)">Nenhum anúncio sincronizado neste conjunto.</p>
                            @else
                                <div class="flex flex-col gap-2">
                                    @foreach($adset->ads as $ad)
                                        @php($adRow = $adStats->get($ad->external_id))
                                        <div class="flex items-center gap-3 py-2" style="border-top:1px solid var(--border2)">
                                            @if($ad->creative_url)
                                                <img src="{{ $ad->creative_url }}" alt="" class="rounded" style="width:36px;height:36px;object-fit:cover">
                                            @else
                                                <div class="rounded flex items-center justify-center flex-shrink-0" style="width:36px;height:36px;background:var(--s3);color:var(--muted);font-size:10px">
                                                    {{ strtoupper(substr($ad->creative_type ?? '?', 0, 1)) }}
                                                </div>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold truncate" style="color: var(--text)">{{ $ad->name }}</p>
                                                <p class="text-xs" style="color: var(--muted)">
                                                    R$ {{ number_format($adRow['spend'] ?? 0, 2, ',', '.') }}
                                                    · {{ ($adRow['roas'] ?? null) !== null ? number_format($adRow['roas'], 2, ',', '.').'x' : '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Histórico de otimizações --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color: var(--muted)">
            Histórico de Otimizações
            @if($optimizationLogs->count() > 0)
                <span class="ml-1">({{ $optimizationLogs->count() }})</span>
            @endif
        </p>

        @if($optimizationLogs->isEmpty())
            <p class="text-sm" style="color: var(--muted)">Nenhuma otimização registrada ainda neste período.</p>
        @else
            <div class="flex flex-col gap-4">
                @foreach($optimizationLogs as $log)
                    <div class="{{ !$loop->last ? 'pb-4' : '' }}" style="{{ !$loop->last ? 'border-bottom:1px solid var(--border2)' : '' }}">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold" style="color: var(--text)">{{ $log->user->name ?? 'Equipe Nonna' }}</span>
                            <span class="text-xs" style="color: var(--muted)">{{ $log->created_at->format('d/m/Y') }}</span>
                        </div>
                        <p class="text-sm whitespace-pre-wrap" style="color: var(--muted2); line-height: 1.6">{{ $log->description }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</x-portal-layout>
