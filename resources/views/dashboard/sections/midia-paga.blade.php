{{-- Seção "Mídia Paga" (papel Tráfego) — fila compartilhada de criativos prontos, orçamentos
     precisando de adição e campanhas com otimização atrasada. --}}

{{-- Investimento por dia nos últimos 30 dias (todas as contas de clientes ativos) --}}
@if(($spendDaily ?? collect())->sum('y') > 0)
    <div class="card card-body mb-4">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-1">
            <p class="card-title">Investimento por dia · últimos 30 dias</p>
            <span class="text-sm" style="color:var(--muted)">
                total <strong style="color:var(--text)">R$ {{ number_format($spendDaily->sum('y'), 2, ',', '.') }}</strong>
            </span>
        </div>
        <x-chart type="area" :height="200" :colors="['var(--purple)']" format="currency" axisFormat="currencyShort"
                 :categories="$spendDaily->pluck('x')->all()"
                 :series="[['name' => 'Investimento', 'data' => $spendDaily->pluck('y')->all()]]" />
    </div>
@endif

<div class="auto-grid">

    {{-- Criativos prontos pra campanha (fila compartilhada — gerada pela automação, ver /automacoes) --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="palette" size="14" />
            Criativos Prontos pra Campanha ({{ $creativosProntos->count() }})
        </h4>
        @if($creativosProntos->isEmpty())
            <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                <x-icon name="party-popper" size="13" />
                Nenhum criativo pendente.
            </p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($creativosProntos as $notification)
                    @php $task = $creativosProntosTasks->get($notification->source_id); @endphp
                    @continue(!$task)
                    <div>
                        <x-item-card kind="tarefa" :icon="$task->typeIcon()" :title="$task->title" :href="route('tasks.show', $task)">
                            <x-slot:meta>
                                <span class="truncate">{{ $task->client?->displayName() ?? '—' }}</span>
                                <span class="badge" style="font-size:10px">{{ $task->statusLabel() }}</span>
                            </x-slot:meta>
                        </x-item-card>
                        <form method="POST" action="{{ route('dashboard.midia-paga.resolve-criativo', $task) }}" class="mt-1">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-xs">✓ Resolver</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Orçamentos com adição necessária --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="credit-card" size="14" />
            Orçamentos — Adição Necessária ({{ $budgetsNeedingAddition->count() }})
        </h4>
        @if($budgetsNeedingAddition->isEmpty())
            <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                <x-icon name="party-popper" size="13" />
                Nenhum orçamento pendente.
            </p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($budgetsNeedingAddition as $account)
                    <x-item-card kind="cliente" icon="credit-card" :title="$account->client?->displayName() ?? '—'" state="soon"
                                 :href="route('clients.show', [$account->client_id, 'tab' => 'contas'])">
                        <x-slot:meta><span>{{ ucfirst($account->platform) }}</span></x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Campanhas precisando de otimização --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="settings" size="14" />
            Campanhas Precisando de Otimização ({{ $campaignsNeedingOptimization->count() }})
        </h4>
        @if($campaignsNeedingOptimization->isEmpty())
            <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                <x-icon name="party-popper" size="13" />
                Nenhuma campanha atrasada.
            </p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($campaignsNeedingOptimization as $campaign)
                    <x-item-card kind="campanha" :icon="$campaign->platformIcon()" :title="$campaign->name" state="late"
                                 :href="route('campaigns.show', $campaign)">
                        <x-slot:meta>
                            <span class="truncate">{{ $campaign->adAccount?->client?->displayName() ?? '—' }}</span>
                            <span style="color:var(--red)">{{ $campaign->last_optimized_at ? 'sem otimizar há ' . (int) $campaign->last_optimized_at->diffInDays(now()) . 'd' : 'nunca otimizada' }}</span>
                        </x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

</div>

<div class="mt-4 flex items-center gap-3">
    <a href="{{ route('orcamentos.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todos os orçamentos →</a>
    <a href="{{ route('campaigns.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todas as campanhas →</a>
</div>
