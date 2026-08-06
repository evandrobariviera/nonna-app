{{-- Preview leve pro painel lateral (canvas). Não inclui layout — só o fragmento. --}}
<div class="p-6 flex flex-col gap-6">

    {{-- Cabeçalho --}}
    <div>
        <p class="text-base font-bold" style="color:var(--text)">{{ $meeting->title }}</p>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="badge badge-{{ $meeting->statusColor() }}">{{ $meeting->statusLabel() }}</span>
            <span class="badge">{{ $meeting->typeLabel() }}</span>
            @if($meeting->client)
                <span class="text-xs" style="color:var(--muted)">{{ $meeting->client->displayName() }}</span>
            @endif
        </div>
    </div>

    {{-- Data e organizador --}}
    <div class="card card-body">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Data</p>
                <p class="text-sm" style="color:var(--text)">{{ $meeting->scheduled_at->format('d/m/Y H:i') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Organizador</p>
                <p class="text-sm" style="color:var(--text)">{{ $meeting->organizer?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Planejamento vinculado --}}
    @if($meeting->macroPlan)
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Planejamento</p>
            <a href="{{ route('macroplans.edit', $meeting->macroPlan) }}"
               class="card card-body flex items-center justify-between gap-3 transition-colors"
               onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                <span class="text-sm font-medium truncate" style="color:var(--text)">{{ $meeting->macroPlan->title }}</span>
                <span class="badge badge-{{ $meeting->macroPlan->statusColor() }} flex-shrink-0">{{ $meeting->macroPlan->statusLabel() }}</span>
            </a>
        </div>
    @endif

    {{-- Link pra página completa --}}
    <a href="{{ route('meetings.show', $meeting) }}"
       class="text-center px-4 py-2.5 text-xs font-bold transition-colors"
       style="border:1px solid var(--border2); color:var(--muted2)"
       onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
       onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
        Ver página completa da reunião →
    </a>
</div>
