<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <a href="{{ route('campaigns.index') }}" class="text-xs font-mono uppercase tracking-widest mb-0.5 transition-colors" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    ← Campanhas
                </a>
                <h1 class="text-xl font-black" style="color:var(--text)">{{ $campaign->name }}</h1>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex gap-5" style="align-items: start;">

        {{-- ══ COLUNA PRINCIPAL ══ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- CABEÇALHO --}}
            <div class="card card-body-lg">
                <div class="flex items-center gap-2 mb-3 flex-wrap">
                    <span class="badge">{{ \App\Http\Controllers\CampaignController::$campaignStatuses[$campaign->status] ?? $campaign->status }}</span>
                    <span class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $campaign->platform }}</span>
                </div>
                <p class="text-sm font-semibold" style="color:var(--text)">
                    {{ $campaign->adAccount?->client?->company_name ?? '—' }}
                </p>
                @if($campaign->objective)
                    <p class="text-xs mt-1" style="color:var(--muted)">Objetivo: {{ $campaign->objective }}</p>
                @endif
            </div>

            {{-- STATS 7 DIAS --}}
            <div class="grid gap-3" style="grid-template-columns: repeat(4, 1fr)">
                <div class="card px-4 py-4 text-left">
                    <div class="text-2xl font-black mb-1" style="color:var(--text)">R$ {{ number_format($stats->spend, 2, ',', '.') }}</div>
                    <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Gasto (7 dias)</div>
                </div>
                <div class="card px-4 py-4 text-left">
                    <div class="text-2xl font-black mb-1" style="color:var(--text)">
                        {{ $stats->cpa !== null ? 'R$ ' . number_format($stats->cpa, 2, ',', '.') : '—' }}
                    </div>
                    <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">CPA</div>
                </div>
                <div class="card px-4 py-4 text-left">
                    <div class="text-2xl font-black mb-1" style="color:var(--text)">
                        {{ $stats->ctr !== null ? number_format($stats->ctr, 2, ',', '.') . '%' : '—' }}
                    </div>
                    <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">CTR</div>
                </div>
                <div class="card px-4 py-4 text-left">
                    <div class="text-2xl font-black mb-1" style="color:var(--text)">
                        {{ $stats->roas !== null ? number_format($stats->roas, 2, ',', '.') . 'x' : '—' }}
                    </div>
                    <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">ROAS</div>
                </div>
            </div>

            {{-- ══ HISTÓRICO ══ --}}
            <div class="card card-body-lg" id="historico">
                <p class="text-xs font-semibold uppercase tracking-widest mb-5" style="color:var(--muted); letter-spacing:.1em">
                    Histórico
                    @if($logs->count() > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border2); color:var(--muted2)">{{ $logs->count() }}</span>
                    @endif
                </p>

                @if($logs->count() > 0)
                    <div class="flex flex-col mb-6">
                        @foreach($logs as $log)
                            <div class="flex gap-4 py-4" style="{{ !$loop->last ? 'border-bottom:1px solid var(--border2)' : '' }}">
                                <x-user-avatar :user="$log->user" size="8" class="mt-0.5" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline gap-3 mb-1.5 flex-wrap">
                                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $log->user->name }}</span>
                                        <span class="badge badge-{{ \App\Models\CampaignLog::$types[$log->type]['color'] ?? 'muted' }}">
                                            {{ \App\Models\CampaignLog::$types[$log->type]['label'] ?? $log->type }}
                                        </span>
                                        <span class="text-xs" style="color:var(--muted)">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                                        <span class="text-xs" style="color:var(--muted2)">{{ $log->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.65">{{ $log->description }}</p>
                                </div>
                                @if($log->logged_by === auth()->id())
                                    <form method="POST"
                                          action="{{ route('campaign-logs.destroy', [$campaign, $log]) }}"
                                          onsubmit="return confirm('Remover registro?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs mt-1 flex-shrink-0 transition-colors" style="color:var(--muted)"
                                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-5 py-7 text-center" style="border:1px dashed var(--border2)">
                        <p class="text-sm" style="color:var(--muted)">Nenhum registro ainda.</p>
                    </div>
                @endif

                {{-- Novo registro --}}
                <form method="POST" action="{{ route('campaign-logs.store', $campaign) }}"
                      x-data="{ description: '', rows: 2 }"
                      @submit="if (!description.trim()) { $event.preventDefault(); }">
                    @csrf
                    <div class="flex gap-3">
                        <x-user-avatar :user="auth()->user()" size="8" class="mt-0.5" />
                        <div class="flex-1">
                            <select name="type" class="w-full mb-2 px-3 py-2 text-sm" style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                @foreach(\App\Models\CampaignLog::$types as $key => $t)
                                    @continue($key === 'otimizacao')
                                    <option value="{{ $key }}">{{ $t['label'] }}</option>
                                @endforeach
                            </select>
                            <textarea
                                name="description"
                                x-model="description"
                                :rows="rows"
                                @focus="rows = 4"
                                placeholder="Descreva a atualização..."
                                class="w-full px-4 py-3 text-sm focus:outline-none resize-none transition-all"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text); line-height:1.65"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'"
                            ></textarea>
                            <div class="flex justify-end mt-2" x-show="description.trim().length > 0" x-cloak>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-semibold text-white"
                                    style="background:var(--purple)"
                                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                    Registrar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>{{-- /coluna principal --}}

        {{-- ══ SIDEBAR ══ --}}
        <div class="flex flex-col gap-4" style="width:270px; flex-shrink:0">

            {{-- STATUS --}}
            <div class="card card-body" x-data="{ open: false }">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Status</p>
                <div class="relative">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--{{ $campaign->managementStatusColor() }})"></span>
                            {{ $campaign->managementStatusLabel() }}
                        </span>
                        <span style="color:var(--muted); font-size:10px">▾</span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute left-0 right-0 mt-1 z-20 py-1"
                         style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1)">
                        @foreach(\App\Models\AdCampaign::$managementStatuses as $key => $s)
                            <form method="POST" action="{{ route('campaigns.update-status', $campaign) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="management_status" value="{{ $key }}">
                                <button type="submit" @click="open = false"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-left transition-colors"
                                    style="color:{{ $campaign->management_status === $key ? 'var(--purple)' : 'var(--muted2)' }}; font-weight:{{ $campaign->management_status === $key ? '600' : '400' }}"
                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                    <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--{{ $s['color'] }})"></span>
                                    {{ $s['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- SITUAÇÃO --}}
            <div class="card card-body" x-data="{ open: false }">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Situação</p>
                <div class="relative">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <span class="flex items-center gap-2">
                            @if($campaign->management_situation)
                                <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--{{ $campaign->managementSituationColor() }})"></span>
                            @endif
                            {{ $campaign->managementSituationLabel() !== '—' ? $campaign->managementSituationLabel() : 'Sem situação' }}
                        </span>
                        <span style="color:var(--muted); font-size:10px">▾</span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute left-0 right-0 mt-1 z-20 py-1"
                         style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1)">
                        @foreach(\App\Models\AdCampaign::$managementSituations as $key => $label)
                            <form method="POST" action="{{ route('campaigns.update-situation', $campaign) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="management_situation" value="{{ $key }}">
                                <button type="submit" @click="open = false"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-left transition-colors"
                                    style="color:{{ ($campaign->management_situation ?? '') === $key ? 'var(--purple)' : 'var(--muted2)' }}; font-weight:{{ ($campaign->management_situation ?? '') === $key ? '600' : '400' }}"
                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                    @if($key)
                                        <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--{{ \App\Models\AdCampaign::$managementSituationColors[$key] ?? 'muted' }})"></span>
                                    @endif
                                    {{ $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- OTIMIZAÇÃO --}}
            <div class="card card-body" id="otimizacao" x-data="{ open: false }">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Otimização</p>

                <div class="relative mb-3">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--{{ $campaign->optimizationTierColor() }})"></span>
                            {{ $campaign->optimizationTierLabel() }}
                        </span>
                        <span style="color:var(--muted); font-size:10px">▾</span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute left-0 right-0 mt-1 z-20 py-1"
                         style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1)">
                        @foreach(\App\Models\AdCampaign::$optimizationTiers as $key => $t)
                            <form method="POST" action="{{ route('campaigns.update-tier', $campaign) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="optimization_tier" value="{{ $key }}">
                                <button type="submit" @click="open = false"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-left transition-colors"
                                    style="color:{{ $campaign->optimization_tier === $key ? 'var(--purple)' : 'var(--muted2)' }}; font-weight:{{ $campaign->optimization_tier === $key ? '600' : '400' }}"
                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                    <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--{{ $t['color'] }})"></span>
                                    {{ $t['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2 mb-3">
                    @if($campaign->isOptimizationOverdue())
                        <span class="badge badge-red">Atrasada</span>
                    @else
                        <span class="badge badge-green">Em dia</span>
                    @endif
                    <span class="text-xs" style="color:var(--muted)">
                        {{ $campaign->last_optimized_at ? 'há ' . $campaign->last_optimized_at->diffForHumans(null, true) : 'nunca otimizada' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('campaigns.mark-optimized', $campaign) }}" x-data="{ comment: '' }">
                    @csrf
                    <textarea name="comment" x-model="comment" rows="2"
                        placeholder="O que foi otimizado? (opcional)"
                        class="w-full px-3 py-2 text-sm mb-2 focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm w-full justify-center">
                        Marcar otimização feita
                    </button>
                </form>
            </div>

        </div>{{-- /sidebar --}}

    </div>
</x-app-layout>
