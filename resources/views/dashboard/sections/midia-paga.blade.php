{{-- Seção "Mídia Paga" (papel Tráfego) — fila compartilhada de criativos prontos, orçamentos
     precisando de adição e campanhas com otimização atrasada. --}}
<div class="grid gap-4" style="grid-template-columns: repeat(3, 1fr)">

    {{-- Criativos prontos pra campanha (fila compartilhada — gerada pela automação, ver /automacoes) --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            🎨 Criativos Prontos pra Campanha ({{ $creativosProntos->count() }})
        </h4>
        @if($creativosProntos->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum criativo pendente. 🎉</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($creativosProntos as $notification)
                    @php $task = $creativosProntosTasks->get($notification->source_id); @endphp
                    @continue(!$task)
                    <div class="px-3 py-2" style="background:var(--s2); border-left:2px solid var(--purple)">
                        <a href="{{ route('tasks.show', $task) }}" class="block">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->displayName() ?? '—' }}</span>
                                <span class="badge" style="font-size:10px">{{ $task->statusLabel() }}</span>
                            </div>
                        </a>
                        <form method="POST" action="{{ route('dashboard.midia-paga.resolve-criativo', $task) }}" class="mt-1.5">
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
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            💳 Orçamentos — Adição Necessária ({{ $budgetsNeedingAddition->count() }})
        </h4>
        @if($budgetsNeedingAddition->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum orçamento pendente. 🎉</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($budgetsNeedingAddition as $account)
                    <a href="{{ route('clients.show', [$account->client_id, 'tab' => 'contas']) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--orange)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $account->client?->displayName() ?? '—' }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ ucfirst($account->platform) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Campanhas precisando de otimização --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            ⚙️ Campanhas Precisando de Otimização ({{ $campaignsNeedingOptimization->count() }})
        </h4>
        @if($campaignsNeedingOptimization->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma campanha atrasada. 🎉</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($campaignsNeedingOptimization as $campaign)
                    <a href="{{ route('campaigns.show', $campaign) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--red)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $campaign->name }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $campaign->adAccount?->client?->displayName() ?? '—' }}</span>
                            <span class="text-xs font-mono" style="color:var(--red)">
                                {{ $campaign->last_optimized_at ? 'sem otimizar há ' . $campaign->last_optimized_at->diffInDays(now()) . 'd' : 'nunca otimizada' }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>

<div class="mt-4 flex items-center gap-3">
    <a href="{{ route('orcamentos.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todos os orçamentos →</a>
    <a href="{{ route('campaigns.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todas as campanhas →</a>
</div>
