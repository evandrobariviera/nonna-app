{{-- Seção "Heads" do dashboard — Direção Criativa e Head de Tecnologia compartilham a
     mesma seção (não faz sentido separar por setor aqui): tickets e tarefas em revisão
     interna sob a responsabilidade (papel "responsável", não "executor") do usuário logado. --}}
<div class="grid gap-4" style="grid-template-columns: repeat(2, 1fr)">

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
                    <a href="{{ route('tasks.show', $ticket) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid {{ $ticket->isOverdue() ? 'var(--red)' : 'var(--purple)' }}"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <div class="flex items-start gap-1.5">
                            <x-icon :name="$ticket->typeIcon()" size="12" class="flex-shrink-0" style="color:var(--muted); margin-top:1px" />
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $ticket->title }}</p>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $ticket->client?->displayName() ?? '—' }}</span>
                            @if($ticket->due_date)
                                <span class="text-xs font-mono" style="color:{{ $ticket->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">{{ $ticket->due_date->format('d/m') }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Revisão interna sob responsabilidade --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="search" size="14" />
            Revisão Interna ({{ $headsRevisaoInterna->count() }})
        </h4>
        @if($headsRevisaoInterna->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nada aguardando sua revisão no momento.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($headsRevisaoInterna as $task)
                    <a href="{{ route('tasks.show', $task) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid {{ $task->isOverdue() ? 'var(--red)' : 'var(--purple)' }}"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <div class="flex items-start gap-1.5">
                            <x-icon :name="$task->typeIcon()" size="12" class="flex-shrink-0" style="color:var(--muted); margin-top:1px" />
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->displayName() ?? '—' }}</span>
                            @if($task->due_date)
                                <span class="text-xs font-mono" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">{{ $task->due_date->format('d/m') }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>
