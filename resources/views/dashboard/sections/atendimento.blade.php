{{-- Seção "Atendimento" do dashboard — agenda da equipe, tickets e funil de aprovações --}}
<div class="grid gap-4 mb-4" style="grid-template-columns: repeat(2, 1fr)">

    {{-- Agenda de hoje (equipe toda) --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            📅 Agenda de Hoje · Equipe ({{ $teamMeetingsToday->count() }})
        </h4>
        @if($teamMeetingsToday->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum compromisso agendado pra hoje.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($teamMeetingsToday as $meeting)
                    <a href="{{ route('meetings.show', $meeting) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $meeting->title }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->client?->company_name ?? '—' }}</span>
                            <span class="text-xs font-mono" style="color:var(--purple)">{{ $meeting->scheduled_at->format('H:i') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tickets abertos --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            🎫 Tickets Abertos ({{ $openTickets->count() }})
        </h4>
        @if($openTickets->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum ticket em aberto. 🎉</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($openTickets as $ticket)
                    <a href="{{ route('tasks.show', $ticket) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid {{ $ticket->isOverdue() ? 'var(--red)' : 'var(--purple)' }}"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $ticket->title }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $ticket->client?->company_name ?? '—' }}</span>
                            @if($ticket->due_date)
                                <span class="text-xs font-mono" style="color:{{ $ticket->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">{{ $ticket->due_date->format('d/m') }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>

{{-- Funil de aprovações --}}
<div class="grid gap-4" style="grid-template-columns: repeat(3, 1fr)">

    {{-- Enviado / Aguardando resposta --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            📤 Aguardando resposta ({{ $roundsPending->count() }})
        </h4>
        @if($roundsPending->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma aprovação pendente no momento.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($roundsPending as $round)
                    <a href="{{ route('tasks.show', $round->task) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--orange)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $round->task->title }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task->client?->company_name ?? '—' }}</span>
                            <span class="text-xs font-mono" style="color:var(--muted)">rodada {{ $round->round_number }} · {{ $round->submitted_at->diffForHumans() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Aprovado --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            ✅ Aprovado ({{ $roundsApproved->count() }})
        </h4>
        @if($roundsApproved->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma aprovação concluída recentemente.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($roundsApproved as $round)
                    <a href="{{ route('tasks.show', $round->task) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--green)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $round->task->title }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task->client?->company_name ?? '—' }}</span>
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->resolved_at?->diffForHumans() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Voltou (mudanças solicitadas) --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            🔁 Voltou com Ajustes ({{ $roundsChangesRequested->count() }})
        </h4>
        @if($roundsChangesRequested->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma tarefa voltou pra ajustes recentemente.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($roundsChangesRequested as $round)
                    <a href="{{ route('tasks.show', $round->task) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--red)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $round->task->title }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task->client?->company_name ?? '—' }}</span>
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->resolved_at?->diffForHumans() }}</span>
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
    <a href="{{ route('meetings.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todas as reuniões →</a>
</div>
