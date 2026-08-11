{{-- Visão em quadros (Kanban) — uma coluna por status de reunião. Reaproveita o
     drag-and-drop genérico de resources/js/kanban-dnd.js (mesmo contrato usado
     em Oportunidades/Sprint/Projeto) e a rota meetings.update-status já existente. --}}
<div id="meetings-board" class="flex gap-4 overflow-x-auto pb-4" style="min-height:60vh"
     data-kanban-board data-status-field="status">

    @foreach(\App\Models\Meeting::$statuses as $key => $s)
        @php($columnMeetings = $board->get($key, collect()))
        <div class="flex-shrink-0 w-64 flex flex-col gap-3"
             data-kanban-column data-status="{{ $key }}">

            {{-- Column Header --}}
            <div class="flex items-center justify-between px-3 py-2"
                 style="border-bottom:2px solid var(--{{ $s['color'] }})">
                <span class="text-xs font-bold font-mono uppercase tracking-wider"
                      style="color:var(--{{ $s['color'] }})">
                    {{ $s['label'] }}
                </span>
                <span class="text-xs font-mono px-1.5 py-0.5 rounded" data-kanban-count
                      style="background:var(--s3); color:var(--muted)">
                    {{ $columnMeetings->count() }}
                </span>
            </div>

            {{-- Cards --}}
            <div class="flex flex-col gap-2 flex-1" data-kanban-list>
                @forelse($columnMeetings as $meeting)
                    <div class="card p-3" data-kanban-card data-id="{{ $meeting->id }}"
                         data-update-url="{{ route('meetings.update-status', $meeting) }}">
                        <a href="{{ route('meetings.show', $meeting) }}"
                           @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open('{{ route('meetings.preview', $meeting) }}') }"
                           class="text-sm font-semibold hover:underline block mb-2" style="color:var(--text)">
                            {{ $meeting->title }}
                        </a>

                        <div class="text-xs font-mono mb-1" style="color:var(--muted2)">
                            {{ $meeting->scheduled_at->format('d/m/Y H:i') }}
                        </div>

                        @if($meeting->client)
                            <div class="text-xs font-semibold mb-1" style="color:var(--purple)">
                                {{ $meeting->client->displayName() }}
                            </div>
                        @endif

                        <div class="text-xs" style="color:var(--muted)">
                            {{ $meeting->typeLabel() }}
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-xs" style="border:1px dashed var(--border); color:var(--muted); opacity:.6">
                        Nenhuma reunião
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('meetings-board')) {
        initKanbanDnd('#meetings-board');
    }
});
</script>
