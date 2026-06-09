<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Atendimento</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Agenda</h1>
            </div>
            <a href="{{ route('meetings.create') }}"
               class="flex items-center gap-2 px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
               style="background:var(--purple)">
                + Nova Reunião
            </a>
        </div>
    </x-slot>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('meetings.index') }}" class="flex flex-wrap items-center gap-3 mb-5">
        <select name="status"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Meeting::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="type"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os tipos</option>
            @foreach(\App\Models\Meeting::$types as $key => $label)
                <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="client_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <button type="submit"
            class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
            style="border:1px solid var(--border2); color:var(--muted2)"
            onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
            onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
            Filtrar
        </button>

        @if(request()->hasAny(['status','type','client_id']))
            <a href="{{ route('meetings.index') }}"
               class="text-xs font-mono transition-colors" style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                Limpar
            </a>
        @endif
    </form>

    {{-- Tabela --}}
    <div class="card">
        @if($meetings->isEmpty())
            <div class="px-6 py-16 text-center" style="color:var(--muted)">
                <p class="text-sm mb-3">Nenhuma reunião encontrada.</p>
                <a href="{{ route('meetings.create') }}" class="text-sm font-semibold" style="color:var(--purple)">
                    Criar primeira reunião →
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    <thead>
                        <tr>
                            <th>Data / Hora</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Modalidade</th>
                            <th>Cliente</th>
                            <th>Organizador</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($meetings as $meeting)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <span class="font-semibold text-sm" style="color:var(--text)">{{ $meeting->scheduled_at->format('d/m/Y') }}</span>
                                    <div class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->scheduled_at->format('H:i') }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('meetings.show', $meeting) }}"
                                       class="font-semibold text-sm hover:underline" style="color:var(--text)">
                                        {{ $meeting->title }}
                                    </a>
                                    @if($meeting->hasAta())
                                        <span class="badge badge-green ml-1">ATA</span>
                                    @endif
                                </td>
                                <td class="text-xs" style="color:var(--muted2)">{{ $meeting->typeLabel() }}</td>
                                <td class="text-xs" style="color:var(--muted2)">{{ $meeting->modalityLabel() }}</td>
                                <td>
                                    @if($meeting->client)
                                        <a href="{{ route('clients.show', $meeting->client) }}"
                                           class="text-xs font-semibold hover:underline" style="color:var(--purple)">
                                            {{ $meeting->client->company_name }}
                                        </a>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                <td class="text-xs" style="color:var(--muted2)">{{ $meeting->organizer->name ?? '—' }}</td>
                                <td>
                                    <span class="badge badge-{{ $meeting->statusColor() }}">{{ $meeting->statusLabel() }}</span>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('meetings.show', $meeting) }}"
                                           class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                            Ver →
                                        </a>
                                        <a href="{{ route('meetings.edit', $meeting) }}"
                                           class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                            Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($meetings->hasPages())
                <div class="px-6 py-4 flex items-center justify-between" style="border-top:1px solid var(--border)">
                    <span class="text-xs font-mono" style="color:var(--muted)">
                        {{ $meetings->firstItem() }}–{{ $meetings->lastItem() }} de {{ $meetings->total() }}
                    </span>
                    <div class="flex gap-2">
                        @if($meetings->onFirstPage())
                            <span class="badge opacity-30">← Anterior</span>
                        @else
                            <a href="{{ $meetings->previousPageUrl() }}" class="badge">← Anterior</a>
                        @endif
                        @if($meetings->hasMorePages())
                            <a href="{{ $meetings->nextPageUrl() }}" class="badge">Próxima →</a>
                        @else
                            <span class="badge opacity-30">Próxima →</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

</x-app-layout>
