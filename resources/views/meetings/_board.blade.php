{{-- Visão em quadros (Kanban) — uma coluna por status de reunião. Reaproveita o
     drag-and-drop genérico de resources/js/kanban-dnd.js (mesmo contrato usado
     em Oportunidades/Sprint/Projeto) e a rota meetings.update-status já existente.
     Visual no padrão Mirbal (classes .kb-* em app.css). --}}
<div id="meetings-board" class="kb-board snap-x" style="min-height:60vh"
     data-kanban-board data-status-field="status">

    @foreach(\App\Models\Meeting::$statuses as $key => $s)
        @php($columnMeetings = $board->get($key, collect()))
        <div class="kb-col snap-start" style="--kb-bar: var(--{{ $s['color'] }}); width:270px"
             data-kanban-column data-status="{{ $key }}">

            <div class="kb-col-head">
                <h5 class="card-title truncate">{{ $s['label'] }}</h5>
                <span class="kb-count" data-kanban-count>{{ $columnMeetings->count() }}</span>
            </div>

            <div class="kb-list" data-kanban-list>
                @forelse($columnMeetings as $meeting)
                    <div class="kb-card" data-kanban-card data-id="{{ $meeting->id }}"
                         data-update-url="{{ route('meetings.update-status', $meeting) }}">
                        <div class="kb-card-top">
                            <x-icon-chip :icon="$meeting->typeIcon()" :color="$meeting->statusColor()" size="22" />
                            <span class="badge tag-muted truncate" style="max-width:170px">{{ $meeting->typeLabel() }}</span>
                        </div>

                        <a href="{{ route('meetings.show', $meeting) }}"
                           @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open('{{ route('meetings.preview', $meeting) }}') }"
                           class="kb-card-title hover:underline">
                            {{ $meeting->title }}
                        </a>
                        @if($meeting->client)
                            <p class="kb-card-desc" style="color:var(--purple)">{{ $meeting->client->displayName() }}</p>
                        @endif

                        <div class="kb-card-foot">
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="calendar" size="13" /> {{ $meeting->scheduled_at->format('d/m/Y H:i') }}
                            </span>
                            <span class="inline-flex gap-1"><x-meeting-checks :meeting="$meeting" /></span>
                        </div>
                    </div>
                @empty
                    <div class="kb-empty">Nenhuma reunião</div>
                @endforelse
            </div>
        </div>
    @endforeach

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Arraste só no desktop — no toque conflita com o scroll horizontal das
    // colunas. No mobile o card abre o preview / a reunião no toque.
    if (document.getElementById('meetings-board') && window.matchMedia('(min-width: 768px)').matches) {
        initKanbanDnd('#meetings-board');
    }
});
</script>
