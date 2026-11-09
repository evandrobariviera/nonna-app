<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Campanhas</h1>
        </div>
    </x-slot>

    @php
        $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
        $clientName = fn ($c) => $c?->displayName() ?? 'Sem cliente';
        $checked = collect($check?->items ?? []);
        $optAgo = function ($c) {
            if (!$c->last_optimized_at) return 'nunca otimizada';
            $d = (int) $c->last_optimized_at->startOfDay()->diffInDays(today());
            return 'última otimização ' . ($d === 0 ? 'hoje' : ($d === 1 ? 'ontem' : "há {$d} dias"));
        };
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        <form method="GET" action="{{ route('campaign-central.today') }}" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                <option value="">Todos os clientes</option>
                <option value="meus" @selected($mine)>Só os clientes em que sou responsável</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
            @if($clientId || $mine)
                <a href="{{ route('campaign-central.today') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
            @endif
        </form>

        {{-- Números do dia --}}
        <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(170px, 1fr))">
            @foreach([
                ['Precisam de você agora', $kpis['urgent'], 'parada, reprovado, sem dados ou saldo', $kpis['urgent'] > 0],
                ['Investido ontem', $money($kpis['spend_yesterday']), 'em todas as campanhas do filtro', false],
                ['Leads bons ontem', $kpis['good_yesterday'], $kpis['leads_yesterday'] ? 'de ' . $kpis['leads_yesterday'] . ' leads que chegaram' : 'nenhum lead ontem', false],
                ['Artes aprovadas', $arts['total'], 'para patrocinado, nos últimos 14 dias', false],
            ] as [$label, $value, $hint, $alert])
                <div class="card px-4 py-3">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
                    <p class="text-xl font-black mt-1" style="color:{{ $alert ? 'var(--red)' : 'var(--text)' }}">{{ $value }}</p>
                    <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-5 min-w-0">

                {{-- Resolver agora --}}
                <section class="card p-5">
                    <h2 class="card-title">Resolver agora <small class="font-normal text-xs" style="color:var(--muted)">está custando dinheiro ou parado</small></h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @foreach($stale as $a)
                            <x-item-card :href="route('campaigns.index', ['client_id' => $a->client_id])" kind="campanha" icon="wifi-off" state="late"
                                         :title="'Conta sem dados novos: ' . $clientName($a->client)">
                                <x-slot:sub>A sincronização não trouxe nada desde {{ \Illuminate\Support\Carbon::parse($a->last_synced_at)->format('d/m') }}. Geralmente é acesso do BM da Nonna à conta.</x-slot:sub>
                                <x-slot:meta>{{ str_starts_with($a->platform, 'meta') ? 'Meta' : 'Google' }} · conta {{ $a->account_id }}</x-slot:meta>
                            </x-item-card>
                        @endforeach
                        @foreach($stopped as $c)
                            <x-item-card :href="route('campaigns.show', $c)" kind="campanha" icon="circle-pause" state="late"
                                         :title="$clientName($c->adAccount?->client) . ' parou de veicular'">
                                <x-slot:sub>{{ $c->name }} · {{ $c->stop_reason }}</x-slot:sub>
                            </x-item-card>
                        @endforeach
                        @foreach($problemAds as $ad)
                            <x-item-card :href="route('campaigns.show', $ad->campaign_id)" kind="campanha" icon="ban" state="late"
                                         :title="($ad->effective_status === 'DISAPPROVED' ? 'Anúncio reprovado' : 'Anúncio com problema') . ' · ' . $clientName($ad->client)">
                                <x-slot:sub>{{ $ad->name }} · {{ $ad->campaign_name }}</x-slot:sub>
                            </x-item-card>
                        @endforeach
                        @foreach($balance as $a)
                            <x-item-card :href="route('orcamentos.index')" kind="campanha" icon="wallet"
                                         :state="($a->days_left !== null && $a->days_left <= 1) || $a->budget_status === 'aguardando_pagto' ? 'late' : 'soon'"
                                         :title="($a->days_left !== null ? 'Saldo de ' . $clientName($a->client) . ' acaba em ~' . max(1, $a->days_left) . ($a->days_left <= 1 ? ' dia' : ' dias') : $clientName($a->client) . ': ' . mb_strtolower($a->budgetStatusLabel()))">
                                <x-slot:sub>
                                    {{ $a->balance !== null ? $money((float) $a->balance) . ' de saldo' : 'sem saldo informado' }}
                                    @if($a->daily_spend > 0) · gasta {{ $money($a->daily_spend) }} por dia @endif
                                    · {{ $a->budgetStatusLabel() }}
                                </x-slot:sub>
                            </x-item-card>
                        @endforeach
                        @if($kpis['urgent'] === 0)
                            <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Nada urgente hoje.</p>
                        @endif
                    </div>
                </section>

                {{-- Alertas --}}
                <section class="card p-5">
                    <h2 class="card-title flex items-center justify-between gap-2">
                        <span>Alertas <small class="font-normal text-xs" style="color:var(--muted)">vêm das análises automáticas</small></span>
                        @if($alerts['total'] > $alerts['items']->count())
                            <a href="{{ route('performance.insights', array_filter(['client_id' => $clientId])) }}" class="btn btn-ghost btn-sm">Ver os {{ $alerts['total'] }}</a>
                        @endif
                    </h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($alerts['items'] as $insight)
                            <x-item-card :href="$insight->campaign ? route('campaigns.show', $insight->campaign) : route('performance.insights', ['client_id' => $insight->client_id])"
                                         kind="campanha" icon="triangle-alert" :state="$insight->severity === 'critico' ? 'late' : ($insight->severity === 'atencao' ? 'soon' : null)"
                                         :title="$insight->title">
                                <x-slot:sub>{{ $clientName($insight->client) }} · {{ $insight->kindLabel() }}@if($insight->campaign) · {{ $insight->campaign->name }}@endif</x-slot:sub>
                                <x-slot:meta>{{ $insight->generated_at?->diffForHumans() }}</x-slot:meta>
                            </x-item-card>
                        @empty
                            <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Sem alertas abertos.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Otimização da semana --}}
                <section class="card p-5">
                    <h2 class="card-title flex items-center justify-between gap-2">
                        <span>Otimização atrasada
                            <small class="font-normal text-xs" style="color:var(--muted)">{{ $overdue['total'] }} de {{ $overdue['active'] }} campanhas gastando passaram do prazo do seu ritmo</small></span>
                        @if($overdue['total'] > $overdue['items']->count())
                            <a href="{{ route('campaigns.index', array_filter(['client_id' => $clientId && !$mine ? $clientId : null])) }}" class="btn btn-ghost btn-sm">Ver todas</a>
                        @endif
                    </h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($overdue['items'] as $c)
                            <div class="flex items-stretch gap-2">
                                <x-item-card :href="route('campaigns.show', $c)" kind="campanha" class="flex-1 min-w-0"
                                             :state="$c->optimization_locked_reason ? null : ($c->last_optimized_at ? 'soon' : 'late')" :title="$c->name">
                                    <x-slot:sub>{{ $clientName($c->adAccount?->client) }} · {{ $optAgo($c) }} · ritmo {{ mb_strtolower($c->optimizationTierLabel()) }}</x-slot:sub>
                                    @if($c->optimization_locked_reason)
                                        <x-slot:meta>Com trava: {{ $c->optimization_locked_reason }}</x-slot:meta>
                                    @endif
                                </x-item-card>
                                <button type="button" class="btn btn-sm self-center" @click="$dispatch('open-ladder', { id: '{{ $c->id }}' })">
                                    <x-icon name="sliders-horizontal" size="14" />Otimizar
                                </button>
                            </div>
                        @empty
                            <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Tudo otimizado no prazo.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Prestação de contas do mês anterior --}}
                @if($billing['total'] > 0)
                    @php
                        $bState = \App\Services\Billing\AccountStatement::deadlineState();
                        $bMonth = \App\Services\Billing\StatementDeliveryService::monthLabel($billing['month']);
                    @endphp
                    <section class="card p-5">
                        <h2 class="card-title flex items-center justify-between gap-2">
                            <span>Prestação de contas de {{ $bMonth }}
                                <small class="font-normal text-xs" style="color:var(--muted)">{{ $billing['done'] }} de {{ $billing['total'] }} contas com nota · prazo dia {{ \App\Services\Billing\AccountStatement::DEADLINE_DAY }}</small></span>
                            <a href="{{ route('billing-statements.index', array_filter(['client_id' => request('client_id')])) }}" class="btn btn-ghost btn-sm">Abrir</a>
                        </h2>
                        <div class="flex flex-col gap-2 mt-3">
                            @forelse($billing['items']->take(8) as $r)
                                <x-item-card :href="route('billing-statements.show', [$r->client, 'mes' => $billing['month']->format('Y-m')])" kind="cliente" icon="receipt" :state="$bState"
                                             :title="'Falta a nota de ' . $bMonth . ': ' . $clientName($r->client)">
                                    <x-slot:sub>{{ $r->account->platformLabel() }} · consumiu {{ $money($r->consumed) }} no mês</x-slot:sub>
                                </x-item-card>
                            @empty
                                <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Todas as notas de {{ $bMonth }} já subiram.</p>
                            @endforelse
                            @if($billing['items']->count() > 8)
                                <a href="{{ route('billing-statements.index') }}" class="text-xs" style="color:var(--purple)">e mais {{ $billing['items']->count() - 8 }}…</a>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Artes aprovadas --}}
                <section class="card p-5">
                    <h2 class="card-title">Artes aprovadas para anúncio
                        <small class="font-normal text-xs" style="color:var(--muted)">{{ $arts['total'] }} nos últimos 14 dias</small></h2>
                    <p class="text-xs mt-1" style="color:var(--muted)">Tarefas com destino "Campanhas patrocinadas" que o cliente aprovou. Confira se já subiram: em breve o anúncio vai poder ser criado daqui.</p>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($arts['items'] as $task)
                            <x-item-card :href="route('tasks.show', $task)" kind="tarefa" icon="image" :popup="true" :title="$task->title">
                                <x-slot:sub>{{ $clientName($task->client) }} · aprovada em {{ ($task->latestApprovalRound?->resolved_at ?? $task->latestApprovalRound?->updated_at)?->format('d/m') }}</x-slot:sub>
                                @if($task->latestApprovalRound?->caption)
                                    <x-slot:meta>"{{ \Illuminate\Support\Str::limit($task->latestApprovalRound->caption, 110) }}"</x-slot:meta>
                                @endif
                            </x-item-card>
                        @empty
                            <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Nenhuma arte aprovada para anúncio nos últimos 14 dias.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Planejadas sem campanha --}}
                <section class="card p-5">
                    <h2 class="card-title">Planejadas e ainda não criadas
                        <small class="font-normal text-xs" style="color:var(--muted)">campanhas do planejamento que começam em até 15 dias</small></h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($planned as $p)
                            <x-item-card :href="$p->macro_plan_id ? route('macroplans.projects.show', [$p->macro_plan_id, $p]) : route('projects.showDirect', $p)"
                                         kind="planejamento" icon="layers" :state="$p->start_date->isPast() ? 'late' : ($p->start_date->lte(now()->addDays(5)) ? 'soon' : null)"
                                         :title="$p->title">
                                <x-slot:sub>{{ $clientName($p->client ?? $p->macroPlan?->client) }} · {{ $p->start_date->isPast() ? 'devia ter começado em ' : 'começa em ' }}{{ $p->start_date->format('d/m') }}</x-slot:sub>
                                <x-slot:meta>Quando a campanha existir na plataforma, ligue ela a este projeto na página da campanha.</x-slot:meta>
                            </x-item-card>
                        @empty
                            <p class="text-sm px-3 py-4 text-center rounded" style="background:var(--s2); color:var(--muted)">Nenhuma campanha planejada esperando para ser criada.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            {{-- Coluna da direita --}}
            <div class="flex flex-col gap-5 min-w-0">
                @include('campaign-central._daily-check')

                <section class="card p-5">
                    <h2 class="card-title">Ritmo</h2>
                    <div class="flex flex-col gap-2 mt-3 text-sm">
                        <div class="rounded px-3 py-2" style="background:var(--s2)"><b>Conferência diária</b>
                            <span style="color:var(--muted2)">· {{ $checked->count() === count(\App\Models\CampaignDailyCheck::$items) ? 'feita hoje' : 'falta fazer hoje' }}</span></div>
                        <div class="rounded px-3 py-2" style="background:var(--s2)"><b>Otimização</b>
                            <span style="color:var(--muted2)">· {{ $overdue['active'] - $overdue['total'] }} de {{ $overdue['active'] }} campanhas no prazo</span></div>
                        <a href="{{ route('campaign-central.optimizations', array_filter(['client_id' => request('client_id')])) }}" class="btn btn-ghost btn-sm self-start">Ver o diário de otimizações</a>
                    </div>
                </section>

                <p class="text-xs px-1" style="color:var(--muted)">
                    Os <b>alertas</b> ficam aqui porque são trabalho do gestor. As <b>análises</b> ("como está indo", "do anúncio à venda") continuam na Central de Performance.
                </p>
            </div>
        </div>
    </div>

    @include('campaign-central._ladder')
</x-app-layout>
