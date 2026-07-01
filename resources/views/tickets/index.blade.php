<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span class="text-base font-bold" style="color:var(--text)">Tickets / Suporte</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">+ Novo Ticket</a>
        </div>
    </x-slot>

    {{-- BARRA DE FILTROS --}}
    <form method="GET" action="{{ route('tickets.index') }}"
          class="flex items-center gap-2 flex-wrap mb-5 pb-4"
          style="border-bottom:1px solid var(--border2)">

        <select name="status" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="client_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <select name="executor_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>

        @if(request()->hasAny(['status','client_id','executor_id']))
            <a href="{{ route('tickets.index') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
        @endif

        <span class="ml-auto text-sm" style="color:var(--muted)">
            {{ $tickets->total() }} ticket{{ $tickets->total() !== 1 ? 's' : '' }}
        </span>
    </form>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 text-sm font-semibold rounded"
             style="background:rgba(5,150,105,.08); border:1px solid rgba(5,150,105,.2); color:#059669">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABELA --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th style="width:140px; padding-left:16px">Status</th>
                        <th>Ticket</th>
                        <th style="width:200px">Cliente</th>
                        <th style="width:170px">Tipo</th>
                        <th style="width:130px">Executor</th>
                        <th style="width:110px">Prazo</th>
                        <th style="width:110px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr class="{{ $ticket->isOverdue() ? 'row-overdue' : '' }}"
                        x-data="{ statusOpen: false }">

                        {{-- Status — clicável para alterar --}}
                        <td style="padding-left:16px" class="relative">
                            <button @click="statusOpen = !statusOpen"
                                    class="badge badge-{{ $ticket->statusColor() }} cursor-pointer hover:opacity-80 transition-opacity"
                                    type="button">
                                {{ $ticket->statusLabel() }} ▾
                            </button>

                            <div x-show="statusOpen" @click.outside="statusOpen = false" x-cloak
                                 class="absolute left-2 top-full mt-1 z-20 rounded shadow-lg py-1"
                                 style="background:var(--s1); border:1px solid var(--border2); min-width:180px">
                                @foreach(\App\Models\Task::$statuses as $key => $s)
                                    <form method="POST" action="{{ route('tickets.update-status', $ticket) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $key }}">
                                        <button type="submit"
                                            class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 transition-colors"
                                            style="color:{{ $ticket->status === $key ? 'var(--purple)' : 'var(--text)' }}"
                                            onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                                                  style="background: {{ match($s['color']) {
                                                      'green' => '#059669', 'blue' => '#2563eb',
                                                      'purple' => '#6A5ACD', 'orange' => '#FF8C00',
                                                      'red' => '#dc2626', default => '#94a3b8'
                                                  } }}"></span>
                                            {{ $s['label'] }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </td>

                        {{-- Ticket title + solicitante --}}
                        <td>
                            <div class="flex flex-col justify-center gap-0.5">
                                <a href="{{ route('tasks.show', $ticket) }}"
                                   class="font-semibold leading-snug hover:underline"
                                   style="color:var(--text); font-size:13.5px">
                                    {{ $ticket->title }}
                                </a>
                                @if($ticket->requester_name)
                                    <span style="font-size:11px; color:var(--muted)">
                                        {{ $ticket->requester_name }}
                                        @if($ticket->requester_channel)
                                            · {{ \App\Models\Task::$requesterChannels[$ticket->requester_channel] ?? '' }}
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- Cliente --}}
                        <td>
                            <span class="text-sm font-medium" style="color:var(--text)">
                                {{ $ticket->client?->company_name ?? '—' }}
                            </span>
                        </td>

                        {{-- Tipo --}}
                        <td>
                            <span class="text-sm" style="color:var(--muted2)">{{ $ticket->typeLabel() }}</span>
                        </td>

                        {{-- Executor --}}
                        <td>
                            @php
                                $execList = $ticket->executors->count() > 0
                                    ? $ticket->executors
                                    : ($ticket->executor ? collect([$ticket->executor]) : collect());
                            @endphp
                            @if($execList->isNotEmpty())
                                <div class="flex items-center gap-1.5">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                                         style="background:var(--purple); font-size:10px; font-weight:700">
                                        {{ strtoupper(substr($execList->first()->name, 0, 2)) }}
                                    </div>
                                    <span class="text-sm" style="color:var(--text)">
                                        {{ explode(' ', $execList->first()->name)[0] }}
                                    </span>
                                </div>
                            @else
                                <span class="text-sm" style="color:var(--muted)">—</span>
                            @endif
                        </td>

                        {{-- Prazo --}}
                        <td>
                            @if($ticket->due_date)
                                <span class="text-sm {{ $ticket->isOverdue() ? 'font-semibold' : '' }}"
                                      style="color:{{ $ticket->isOverdue() ? 'var(--red)' : 'var(--muted2)' }}">
                                    {{ $ticket->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-sm" style="color:var(--muted)">—</span>
                            @endif
                        </td>

                        {{-- Ações --}}
                        <td>
                            <div class="row-actions flex items-center gap-1.5">
                                <a href="{{ route('tasks.show', $ticket) }}" class="btn btn-primary btn-xs">
                                    Abrir
                                </a>
                                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}"
                                      onsubmit="return confirm('Cancelar este ticket?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs"
                                            style="color:var(--red); border-color:rgba(220,38,38,.2)"
                                            onmouseover="this.style.background='rgba(220,38,38,.06)'"
                                            onmouseout="this.style.background='transparent'">
                                        Cancelar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="tab-placeholder">
                                <div class="tab-placeholder-icon">🎫</div>
                                <div class="tab-placeholder-title">Nenhum ticket encontrado</div>
                                <div class="tab-placeholder-desc">Ajuste os filtros ou crie um novo ticket.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>

</x-app-layout>
