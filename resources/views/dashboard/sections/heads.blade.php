{{-- Seção "Heads" do dashboard — Direção Criativa e Head de Tecnologia compartilham a
     mesma seção (não faz sentido separar por setor aqui): tickets sob a responsabilidade (papel
     "responsável", não "executor") do usuário logado. A Revisão Interna saiu daqui e virou
     coluna dinâmica no quadro da Execução (revisar é executar, não distribuir). --}}
<div class="auto-grid">

    {{-- Tickets sob responsabilidade --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="ticket" size="14" />
            Meus Tickets ({{ $headsTickets->count() }})
        </h4>
        @if($headsTickets->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum ticket sob sua responsabilidade no momento.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($headsTickets as $ticket)
                    <x-item-card kind="tarefa" :icon="$ticket->typeIcon()" :title="$ticket->title" :state="$ticket->isOverdue() ? 'late' : null"
                                 :href="route('tasks.show', $ticket)">
                        <x-slot:meta>
                            <span>{{ $ticket->client?->displayName() ?? '—' }}</span>
                            @if($ticket->due_date)
                            <span style="color:{{ $ticket->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">{{ $ticket->due_date->format('d/m') }}</span>
                            @endif
                        </x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

</div>
