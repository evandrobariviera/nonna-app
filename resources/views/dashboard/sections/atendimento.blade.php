{{-- Seção "Operação" (papel Atendimento) — tickets e funil de aprovações.
     "Agenda de Hoje · Equipe" foi retirada daqui: o quadro Agenda global,
     logo acima na dashboard, já cobre isso. --}}
<div class="auto-grid">

    {{-- Tickets abertos --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="ticket" size="14" />
            Tickets Abertos ({{ $openTickets->count() }})
        </h4>
        @if($openTickets->isEmpty())
            <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                <x-icon name="party-popper" size="13" />
                Nenhum ticket em aberto.
            </p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($openTickets as $ticket)
                    <a href="{{ route('tasks.show', $ticket) }}"
                       class="flex items-center gap-2.5 px-3 py-2 transition-colors" style="background:var(--s2); {{ $ticket->isOverdue() ? 'border-left:2px solid var(--red)' : '' }}"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip :icon="$ticket->typeIcon()" :color="$ticket->statusColor()" size="32" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $ticket->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $ticket->client?->displayName() ?? '—' }}</span>
                                @if($ticket->due_date)
                                    <span class="text-xs font-mono" style="color:{{ $ticket->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">{{ $ticket->due_date->format('d/m') }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Voltou (mudanças solicitadas) --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="refresh-cw" size="14" />
            Voltou com Ajustes ({{ $roundsChangesRequested->count() }})
        </h4>
        @if($roundsChangesRequested->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma tarefa voltou pra ajustes recentemente.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($roundsChangesRequested as $round)
                    <a href="{{ route('tasks.show', $round->task) }}"
                       class="flex items-center gap-2.5 px-3 py-2 transition-colors" style="background:var(--s2)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip :icon="$round->task->typeIcon()" :color="$round->task->statusColor()" size="32" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $round->task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task->client?->displayName() ?? '—' }}</span>
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->resolved_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Em aprovação (aguardando resposta do cliente) --}}
    <div class="card px-5 py-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                <x-icon name="send" size="14" />
                Em Aprovação ({{ $roundsPending->count() }})
            </h4>
            @if($roundsAwaitingSendCount > 0)
                <a href="{{ route('approvals.index') }}" class="text-xs font-mono" style="color:var(--orange)">
                    ⚠ {{ $roundsAwaitingSendCount }} aguardando envio →
                </a>
            @endif
        </div>
        @if($roundsPending->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma aprovação pendente no momento.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($roundsPending as $round)
                    <a href="{{ route('tasks.show', $round->task) }}"
                       class="flex items-center gap-2.5 px-3 py-2 transition-colors" style="background:var(--s2)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip :icon="$round->task->typeIcon()" :color="$round->task->statusColor()" size="32" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $round->task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task->client?->displayName() ?? '—' }}</span>
                                <span class="text-xs font-mono" style="color:var(--muted)">rodada {{ $round->round_number }} · {{ $round->submitted_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pronto para despacho (aprovado pelo cliente) --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="circle-check" size="14" />
            Pronto para Despacho ({{ $roundsApproved->count() }})
        </h4>
        @if($roundsApproved->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma aprovação concluída recentemente.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($roundsApproved as $round)
                    <a href="{{ route('tasks.show', $round->task) }}"
                       class="flex items-center gap-2.5 px-3 py-2 transition-colors" style="background:var(--s2)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip :icon="$round->task->typeIcon()" :color="$round->task->statusColor()" size="32" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $round->task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task->client?->displayName() ?? '—' }}</span>
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->resolved_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>

<div class="mt-4 flex items-center gap-3">
    <a href="{{ route('approvals.index', ['status' => 'pending']) }}" class="text-xs font-mono" style="color:var(--purple)">Ver todas as aprovações →</a>
    <a href="{{ route('tickets.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todos os tickets →</a>
</div>
