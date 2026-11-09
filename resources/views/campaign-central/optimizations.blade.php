<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Campanhas</h1>
        </div>
    </x-slot>

    @php
        $logTypes = \App\Models\CampaignLog::$types;
        $steps = \App\Services\CampaignCentral\OptimizationLadder::$steps;
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        <form method="GET" action="{{ route('campaign-central.optimizations') }}" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                <option value="">Todos os clientes</option>
                <option value="meus" @selected($mine)>Só os clientes em que sou responsável</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
            @if($clientId || $mine)
                <a href="{{ route('campaign-central.optimizations') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
            @endif
        </form>

        {{-- Ritmo --}}
        <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr))">
            <div class="card px-4 py-3">
                <p class="font-bold text-sm" style="color:var(--text)">Conferência diária</p>
                <p class="text-xs mt-0.5 mb-2" style="color:var(--muted2)">5 minutos, só olhar. Fica na aba Hoje.</p>
                @php $done = count($check?->items ?? []) === count(\App\Models\CampaignDailyCheck::$items); @endphp
                <span class="badge {{ $done ? 'badge-green' : 'badge-orange' }}">{{ $done ? 'Feita hoje' : 'Falta fazer hoje' }}</span>
            </div>
            <div class="card px-4 py-3">
                <p class="font-bold text-sm" style="color:var(--text)">Otimização</p>
                <p class="text-xs mt-0.5 mb-2" style="color:var(--muted2)">Cada campanha passa pela escada no ritmo dela: normal 5 dias, alta 3, diária 1.</p>
                <span class="badge {{ $overdueCount ? 'badge-orange' : 'badge-green' }}">{{ $overdueCount ? $overdueCount . ' atrasadas' : 'Tudo no prazo' }}</span>
            </div>
            <div class="card px-4 py-3">
                <p class="font-bold text-sm" style="color:var(--text)">Avaliação do ciclo</p>
                <p class="text-xs mt-0.5 mb-2" style="color:var(--muted2)">Uma vez por mês, olhando o funil inteiro com o analista de IA.</p>
                <a href="{{ route('performance.insights', array_filter(['client_id' => $clientId])) }}" class="badge badge-muted">Abrir Insights</a>
            </div>
        </div>

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            {{-- Diário de bordo --}}
            <section class="card p-5 min-w-0" x-data="{ pick: false, q: '' }">
                <h2 class="card-title flex items-center justify-between gap-2">
                    <span>Diário de bordo <small class="font-normal text-xs" style="color:var(--muted)">tudo que foi feito nas campanhas</small></span>
                    <button type="button" class="btn btn-primary btn-sm" @click="pick = !pick"><x-icon name="sliders-horizontal" size="14" />Nova otimização</button>
                </h2>

                <div x-show="pick" x-cloak class="mt-3 rounded p-3" style="background:var(--s2)">
                    <input type="text" x-model="q" placeholder="Buscar campanha ou cliente…" class="w-full text-sm mb-2" aria-label="Buscar campanha">
                    <div class="flex flex-col gap-1 max-h-72 overflow-y-auto">
                        @forelse($campaigns as $c)
                            @php $label = ($c->adAccount?->client?->displayName() ?? '') . ' · ' . $c->name; @endphp
                            <button type="button" class="text-left text-sm px-2 py-1.5 rounded hover:bg-[var(--s3)] flex justify-between gap-3"
                                    x-show="!q || @js(mb_strtolower($label)).includes(q.toLowerCase())"
                                    @click="$dispatch('open-ladder', { id: '{{ $c->id }}' })">
                                <span class="min-w-0 truncate">{{ $label }}</span>
                                <span class="flex-shrink-0 text-xs" style="color:{{ $c->isOptimizationOverdue() ? 'var(--orange)' : 'var(--muted)' }}">
                                    {{ $c->last_optimized_at ? $c->last_optimized_at->format('d/m') : 'nunca' }}
                                </span>
                            </button>
                        @empty
                            <p class="text-sm" style="color:var(--muted)">Nenhuma campanha ativa neste filtro.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-3">
                    @forelse($logs as $log)
                        <div class="grid gap-3 py-3" style="grid-template-columns:78px minmax(0,1fr); {{ !$loop->first ? 'border-top:1px solid var(--border)' : '' }}">
                            <div class="text-xs" style="color:var(--muted)">
                                {{ $log->created_at->format('d/m') }}<br>{{ $log->user?->name ? \Illuminate\Support\Str::of($log->user->name)->before(' ') : '' }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm" style="color:var(--text)">{{ $log->description }}</p>
                                <p class="text-xs mt-1" style="color:var(--muted2)">{{ $log->adAccount?->client?->displayName() }} · {{ $log->entity_name }}</p>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    <span class="badge badge-{{ $logTypes[$log->type]['color'] ?? 'muted' }}">{{ $logTypes[$log->type]['label'] ?? $log->type }}</span>
                                    @if($label = data_get($log->metadata, 'escada.label'))
                                        <span class="badge badge-muted">{{ $label }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Nada registrado neste filtro.</p>
                    @endforelse
                </div>
                <div class="mt-3">{{ $logs->links() }}</div>
            </section>

            {{-- A escada --}}
            <section class="card p-5 min-w-0">
                <h2 class="card-title">A escada das 5 perguntas</h2>
                <p class="text-xs mt-1 mb-3" style="color:var(--muted2)">Toda otimização segue a mesma ordem e para na primeira resposta "não". O diário guarda as respostas.</p>
                <div class="flex flex-col gap-2">
                    @foreach(array_values($steps) as $i => [$question, $hint, $owner])
                        <div class="flex gap-3 rounded px-3 py-2" style="border:1px solid var(--border)">
                            <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-xs font-bold flex-shrink-0" style="background:var(--s3); color:var(--muted2)">{{ $i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $question }}
                                    <span class="ml-1 text-[11px] font-semibold uppercase tracking-wide" style="color:var(--muted)">{{ $owner === 'gestor' ? 'gestor decide' : 'estratégia decide' }}</span></p>
                                <p class="text-xs" style="color:var(--muted2)">{{ $hint }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    @include('campaign-central._ladder')
</x-app-layout>
