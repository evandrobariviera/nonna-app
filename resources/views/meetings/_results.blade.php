{{-- Fragmento reaproveitado no load inicial (meetings.index) e na busca dinâmica via
     AJAX (MeetingController::results(), fetch disparado por live-filter.js). --}}
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
                                        {{ $meeting->client->displayName() }}
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
                                    <a href="{{ route('meetings.show', $meeting) }}" class="btn btn-ghost btn-xs">
                                        Ver →
                                    </a>
                                    <a href="{{ route('meetings.edit', $meeting) }}" class="btn btn-ghost btn-xs">
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
