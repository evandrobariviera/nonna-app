<x-portal-layout>
    <x-slot name="title">Campanhas</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-black" style="color: var(--text)">Campanhas</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            Performance de tráfego pago — {{ $client->company_name }}.
        </p>
    </div>

    @if(!$stats)
        <div class="card p-10 text-center">
            <p class="text-2xl mb-2">📊</p>
            <p class="text-sm font-semibold" style="color: var(--text)">Nenhuma conta de anúncios configurada</p>
            <p class="text-xs mt-1" style="color: var(--muted)">Assim que suas campanhas forem ativadas, os dados aparecerão aqui.</p>
        </div>
    @else

        {{-- Filtros --}}
        <form method="GET" action="{{ route('portal.campaigns.index') }}" class="flex flex-wrap items-center gap-3 mb-6">
            <select name="period" onchange="this.form.submit()"
                    style="background: var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px; border-radius:6px; outline:none; cursor:pointer">
                @foreach(\App\Http\Controllers\Portal\CampaignController::$periods as $key => $label)
                    <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select name="status" onchange="this.form.submit()"
                    style="background: var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px; border-radius:6px; outline:none; cursor:pointer">
                <option value="" {{ $statusFilter === '' ? 'selected' : '' }}>Todos os status</option>
                @foreach(\App\Http\Controllers\Portal\CampaignController::$statuses as $key => $label)
                    <option value="{{ $key }}" {{ $statusFilter === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        {{-- Cards de métricas --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Investimento</p>
                <p class="text-3xl font-black leading-none" style="color: var(--text)">
                    R$ {{ number_format($stats['spend'], 0, ',', '.') }}
                </p>
                <p class="text-xs mt-2" style="color: var(--muted)">{{ $periodLabel }}</p>
                @if($deltas['spend'] !== null)
                    <p class="text-xs font-semibold mt-1" style="color: {{ $deltas['spend'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                        {{ $deltas['spend'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($deltas['spend']), 1, ',', '.') }}% vs período anterior
                    </p>
                @endif
            </div>

            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">ROAS</p>
                <p class="text-3xl font-black leading-none" style="color: var(--purple)">
                    {{ number_format($stats['roas'], 2, ',', '.') }}x
                </p>
                <p class="text-xs mt-2" style="color: var(--muted)">
                    retorno sobre investimento
                    @if($stats['roas'] >= 4.0)
                        <span style="color: var(--green)">✓ meta atingida</span>
                    @endif
                </p>
                @if($deltas['roas'] !== null)
                    <p class="text-xs font-semibold mt-1" style="color: {{ $deltas['roas'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                        {{ $deltas['roas'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($deltas['roas']), 1, ',', '.') }}% vs período anterior
                    </p>
                @endif
            </div>

            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Conversões</p>
                <p class="text-3xl font-black leading-none" style="color: var(--text)">{{ number_format($stats['conversions'], 0, ',', '.') }}</p>
                <p class="text-xs mt-2" style="color: var(--muted)">
                    CPA médio R$ {{ number_format($stats['cpa'], 0, ',', '.') }}
                </p>
                @if($deltas['conversions'] !== null)
                    <p class="text-xs font-semibold mt-1" style="color: {{ $deltas['conversions'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                        {{ $deltas['conversions'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($deltas['conversions']), 1, ',', '.') }}% vs período anterior
                    </p>
                @endif
            </div>

            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">CPC Médio</p>
                <p class="text-3xl font-black leading-none" style="color: var(--text)">
                    R$ {{ number_format($stats['cpc'], 2, ',', '.') }}
                </p>
                <p class="text-xs mt-2" style="color: var(--muted)">
                    {{ number_format($stats['clicks'], 0, ',', '.') }} cliques · {{ number_format($stats['impressions'], 0, ',', '.') }} impressões
                </p>
                @if($deltas['cpc'] !== null)
                    <p class="text-xs font-semibold mt-1" style="color: {{ $deltas['cpc'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                        {{ $deltas['cpc'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($deltas['cpc']), 1, ',', '.') }}% vs período anterior
                    </p>
                @endif
            </div>
        </div>

        {{-- Gráfico de gasto diário --}}
        @if($dailySpend->isNotEmpty())
            @php $maxSpend = $dailySpend->max('total_spend'); @endphp
            <div class="card p-5 mb-8">
                <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color: var(--muted)">Investimento Diário — Últimos 14 Dias</p>
                <div class="flex items-end gap-1" style="height: 80px">
                    @foreach($dailySpend as $day)
                        @php
                            $pct = $maxSpend > 0 ? round(($day->total_spend / $maxSpend) * 100) : 2;
                            $dt  = \Carbon\Carbon::parse($day->snapshot_date);
                        @endphp
                        <div class="flex-1 flex flex-col items-center gap-1.5" style="height: 100%">
                            <div class="w-full flex items-end" style="height: calc(100% - 18px)">
                                <div class="w-full rounded-t cursor-default transition-opacity"
                                     title="{{ $dt->format('d/m') }}: R$ {{ number_format($day->total_spend, 0, ',', '.') }}"
                                     style="background: var(--grad);
                                            height: {{ max($pct, 3) }}%;
                                            opacity: 0.7"
                                     onmouseover="this.style.opacity='1'"
                                     onmouseout="this.style.opacity='0.7'">
                                </div>
                            </div>
                            <span class="text-[9px] font-semibold" style="color: var(--muted)">{{ $dt->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Lista de campanhas --}}
        <div class="mb-4">
            <h2 class="text-xs font-bold uppercase tracking-widest" style="color: var(--muted)">
                Campanhas com veiculação · {{ $periodLabel }}
            </h2>
        </div>

        @if($campaigns->isEmpty())
            <div class="card p-10 text-center">
                <p class="text-sm font-semibold" style="color: var(--text)">Nenhuma campanha com veiculação neste período</p>
                <p class="text-xs mt-1" style="color: var(--muted)">Tente um período maior ou outro status no filtro acima.</p>
            </div>
        @else
            @foreach($campaigns as $campaign)
                @php
                    $s = $campaign->period_stats;
                    $statusMap = [
                        'active'   => ['label' => 'Ativa',    'bg' => 'rgba(5,150,105,.1)',   'text' => 'var(--green)'],
                        'paused'   => ['label' => 'Pausada',  'bg' => 'rgba(255,140,0,.1)',   'text' => 'var(--orange)'],
                        'deleted'  => ['label' => 'Removida', 'bg' => 'rgba(220,38,38,.1)',   'text' => 'var(--red)'],
                        'archived' => ['label' => 'Arquivada','bg' => 'var(--s3)',             'text' => 'var(--muted)'],
                    ];
                    $st = $statusMap[$campaign->status] ?? $statusMap['paused'];
                    $platformIcons = [
                        'meta'   => 'Meta Ads',
                        'google' => 'Google Ads',
                        'tiktok' => 'TikTok Ads',
                    ];
                @endphp
                <div class="card mb-4">
                    <div class="px-6 py-4 border-b flex items-center justify-between" style="border-color: var(--border2)">
                        <div class="flex items-center gap-3">
                            <div>
                                <p class="text-sm font-bold" style="color: var(--text)">{{ $campaign->name }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--muted)">
                                    {{ $platformIcons[$campaign->platform] ?? $campaign->platform }}
                                    · {{ $campaign->adAccount->account_name ?? $campaign->adAccount->account_id ?? '—' }}
                                    @if($campaign->start_date)
                                        · desde {{ $campaign->start_date->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                              style="background: {{ $st['bg'] }}; color: {{ $st['text'] }}">
                            {{ $st['label'] }}
                        </span>
                    </div>

                    @if($campaign->description)
                        <div class="px-6 py-3" style="background: var(--s3); border-bottom: 1px solid var(--border2)">
                            <p class="text-sm whitespace-pre-wrap" style="color: var(--muted2); line-height: 1.6">{{ $campaign->description }}</p>
                        </div>
                    @endif

                    @if($campaign->client_insights->isNotEmpty())
                        <div class="px-6 py-3 flex flex-col gap-2" style="border-bottom: 1px solid var(--border2)">
                            <p class="text-xs font-bold uppercase tracking-widest" style="color: var(--muted)">Como está indo</p>
                            @foreach($campaign->client_insights as $insight)
                                <p class="text-sm" style="color: var(--muted2); line-height: 1.6">{{ $insight->client_summary }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">Investimento</p>
                            <p class="text-lg font-black" style="color: var(--text)">R$ {{ number_format($s->spend, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">ROAS</p>
                            <p class="text-lg font-black" style="color: var(--purple)">{{ number_format($s->roas, 2, ',', '.') }}x</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">Conversões</p>
                            <p class="text-lg font-black" style="color: var(--text)">{{ number_format($s->conversions, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">CPA</p>
                            <p class="text-lg font-black" style="color: var(--text)">R$ {{ number_format($s->cpa, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">Cliques</p>
                            <p class="text-lg font-black" style="color: var(--text)">{{ number_format($s->clicks, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    @if($s->spend > 0)
                        @php
                            $roasColor = $s->roas >= 4.0 ? 'var(--green)' : ($s->roas >= 2.5 ? 'var(--orange)' : 'var(--red)');
                            $roasPct   = min(round(($s->roas / 6) * 100), 100);
                        @endphp
                        <div class="px-6 pb-4">
                            <div class="flex items-center justify-between text-xs mb-1.5" style="color: var(--muted)">
                                <span>ROAS</span>
                                <span style="color: {{ $roasColor }}">{{ number_format($s->roas, 2, ',', '.') }}x (meta: 4.0x)</span>
                            </div>
                            <div class="h-1.5 rounded-full" style="background: var(--s3)">
                                <div class="h-1.5 rounded-full" style="background: {{ $roasColor }}; width: {{ $roasPct }}%"></div>
                            </div>
                        </div>
                    @else
                        <div class="px-6 pb-4">
                            <p class="text-xs" style="color: var(--muted)">Sem dados no período selecionado.</p>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

    @endif

</x-portal-layout>
