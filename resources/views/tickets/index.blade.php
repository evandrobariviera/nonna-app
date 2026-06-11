<x-app-layout>
    <x-slot name="header">Tickets / Suporte</x-slot>

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <form method="GET" action="{{ route('tickets.index') }}" class="flex items-center gap-2 flex-wrap">
            <select name="status" onchange="this.form.submit()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Task::$statuses as $key => $s)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
            <select name="client_id" onchange="this.form.submit()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                @endforeach
            </select>
            <select name="executor_id" onchange="this.form.submit()"
                class="px-3 py-2 text-xs focus:outline-none"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os executores</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('tickets.create') }}"
           class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
           style="background:var(--purple)">
            + Novo Ticket
        </a>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABELA --}}
    @forelse($tickets as $ticket)
        <div class="card px-5 py-3 mb-2 flex items-start justify-between gap-4 flex-wrap"
             style="{{ $ticket->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="badge badge-{{ $ticket->statusColor() }}" style="font-size:10px">
                        {{ $ticket->statusLabel() }}
                    </span>
                    <span class="text-xs font-mono font-bold" style="color:var(--purple)">
                        {{ $ticket->client?->company_name ?? '—' }}
                    </span>
                    <span class="badge" style="font-size:10px">{{ $ticket->typeLabel() }}</span>
                    @if($ticket->destination)
                        <span class="text-xs font-mono" style="color:var(--muted)">{{ $ticket->destinationLabel() }}</span>
                    @endif
                </div>

                <p class="text-sm font-semibold leading-snug" style="color:var(--text)">{{ $ticket->title }}</p>

                <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                    @if($ticket->executors->count() > 0)
                        @foreach($ticket->executors as $exec)
                            <span class="text-xs font-mono" style="color:var(--orange)">
                                {{ explode(' ', $exec->name)[0] }}
                            </span>
                        @endforeach
                    @elseif($ticket->executor)
                        <span class="text-xs font-mono" style="color:var(--muted)">{{ explode(' ', $ticket->executor->name)[0] }}</span>
                    @endif

                    @if($ticket->requester_name)
                        <span class="text-xs font-mono" style="color:var(--muted)">
                            Solicitante: {{ $ticket->requester_name }}
                            @if($ticket->requester_channel)
                                ({{ \App\Models\Task::$requesterChannels[$ticket->requester_channel] ?? '' }})
                            @endif
                        </span>
                    @endif

                    @if($ticket->due_date)
                        <span class="text-xs font-mono"
                              style="color:{{ $ticket->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                            Venc: {{ $ticket->due_date->format('d/m/Y') }}
                        </span>
                    @endif
                    @if($ticket->sprint)
                        <span class="text-xs font-mono" style="color:var(--purple)">
                            Sprint: {{ $ticket->sprint->title }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Ações rápidas --}}
            <div class="flex items-center gap-2 flex-shrink-0" x-data="{ open: false }">
                <div class="relative">
                    <button @click="open = !open"
                        class="px-3 py-1.5 text-xs font-mono"
                        style="border:1px solid var(--border2); color:var(--muted)">
                        Status ▾
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute right-0 mt-1 z-10 w-44"
                         style="background:var(--s2); border:1px solid var(--border2)">
                        @foreach(\App\Models\Task::$statuses as $key => $s)
                            <form method="POST" action="{{ route('tickets.update-status', $ticket) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $key }}">
                                <button type="submit"
                                    class="w-full text-left px-3 py-2 text-xs font-mono transition-colors"
                                    style="color:{{ $ticket->status === $key ? 'var(--purple)' : 'var(--muted)' }}"
                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                    {{ $s['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}"
                      onsubmit="return confirm('Cancelar este ticket?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="px-3 py-1.5 text-xs font-mono transition-colors"
                        style="border:1px solid var(--border2); color:var(--muted)"
                        onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                        Cancelar
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="px-6 py-12 text-center" style="border:1px dashed var(--border2)">
            <p class="text-sm font-mono" style="color:var(--muted)">Nenhum ticket encontrado.</p>
            <a href="{{ route('tickets.create') }}" class="text-xs mt-2 inline-block" style="color:var(--purple)">
                Criar primeiro ticket →
            </a>
        </div>
    @endforelse

    {{ $tickets->links() }}

</x-app-layout>
