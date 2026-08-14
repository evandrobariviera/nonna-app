<x-portal-layout>
    <x-slot name="title">{{ $meeting->title }}</x-slot>

    @php
        $statusColors = [
            'para_agendar' => ['bg' => 'var(--s3)',              'text' => 'var(--muted)',  'label' => 'Para Agendar'],
            'agendada'     => ['bg' => 'rgba(100, 59, 142,.08)',   'text' => 'var(--purple)', 'label' => 'Agendada'],
            'pos_reuniao'  => ['bg' => 'rgba(238, 121, 25,.08)',    'text' => 'var(--orange)', 'label' => 'Pós-Reunião'],
            'realizada'    => ['bg' => 'rgba(5,150,105,.1)',     'text' => 'var(--green)',  'label' => 'Realizada'],
            'cancelada'    => ['bg' => 'rgba(220,38,38,.08)',    'text' => 'var(--red)',    'label' => 'Cancelada'],
        ];
        $sc = $statusColors[$meeting->status] ?? $statusColors['para_agendar'];
    @endphp

    <div class="mb-6">
        <a href="{{ route('portal.meetings.index') }}" class="text-xs font-semibold" style="color: var(--muted)">← Reuniões</a>
    </div>

    <div class="card p-6 mb-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h1 class="text-xl font-black mb-1" style="color: var(--text)">{{ $meeting->title }}</h1>
                <p class="text-xs" style="color: var(--muted)">{{ $meeting->typeLabel() }}</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0"
                  style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                {{ $sc['label'] }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Data / Hora</p>
                <p style="color: var(--text)">{{ $meeting->scheduled_at?->format('d/m/Y \à\s H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Modalidade</p>
                <p style="color: var(--text)">{{ $meeting->modalityLabel() }}</p>
            </div>
            @if($meeting->duration_minutes)
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Duração</p>
                    <p style="color: var(--text)">{{ $meeting->duration_minutes }} min</p>
                </div>
            @endif
            @if($meeting->location)
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Local</p>
                    <p style="color: var(--text)">{{ $meeting->location }}</p>
                </div>
            @endif
            @if($meeting->online_link)
                <div class="col-span-2">
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Link da Reunião</p>
                    <a href="{{ $meeting->online_link }}" target="_blank" class="break-all" style="color: var(--purple)">{{ $meeting->online_link }}</a>
                </div>
            @endif
        </div>
    </div>

    @if($meeting->agenda)
        <div class="card p-6 mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-3" style="color: var(--muted)">Pauta</h2>
            <p class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.7">{{ $meeting->agenda }}</p>
        </div>
    @endif

    @if($meeting->hasAta())
        <div class="card p-6 mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-1 flex items-center gap-1.5" style="color: var(--purple)">
                <x-icon name="file-text" size="14" />
                Ata da Reunião
            </h2>
            @if($meeting->ata_recorded_at)
                <p class="text-xs mb-3" style="color: var(--muted)">Registrada em {{ $meeting->ata_recorded_at->format('d/m/Y') }}</p>
            @endif
            <p class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.7">{{ $meeting->ata }}</p>
        </div>
    @endif

    @if($meeting->next_steps)
        <div class="card p-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-3" style="color: var(--muted)">Próximos Passos</h2>
            <p class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.7">{{ $meeting->next_steps }}</p>
        </div>
    @endif

</x-portal-layout>
