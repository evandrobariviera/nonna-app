<x-portal-layout>
    <x-slot name="title">Reuniões</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Reuniões</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Agenda e histórico de reuniões com {{ $client->company_name }}</p>
    </div>

    @php
        $statusColors = [
            'para_agendar' => ['bg' => 'var(--s3)',              'text' => 'var(--muted)',  'label' => 'Para Agendar'],
            'agendada'     => ['bg' => 'rgba(106,90,205,.08)',   'text' => 'var(--purple)', 'label' => 'Agendada'],
            'pos_reuniao'  => ['bg' => 'rgba(255,140,0,.08)',    'text' => 'var(--orange)', 'label' => 'Pós-Reunião'],
            'realizada'    => ['bg' => 'rgba(5,150,105,.1)',     'text' => 'var(--green)',  'label' => 'Realizada'],
            'cancelada'    => ['bg' => 'rgba(220,38,38,.08)',    'text' => 'var(--red)',    'label' => 'Cancelada'],
        ];
    @endphp

    <div class="mb-3">
        <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">Próximas ({{ $upcoming->count() }})</h2>
    </div>
    @forelse($upcoming as $meeting)
        @php $sc = $statusColors[$meeting->status] ?? $statusColors['para_agendar']; @endphp
        <a href="{{ route('portal.meetings.show', $meeting) }}" class="card p-5 mb-4 flex items-center justify-between transition-colors"
           style="text-decoration:none">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <p class="font-bold" style="color: var(--text)">{{ $meeting->title }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs" style="color: var(--muted)">
                    {{ $meeting->scheduled_at?->format('d/m/Y \à\s H:i') }} · {{ $meeting->modalityLabel() }}
                </p>
            </div>
            <span class="text-xs font-semibold px-3 py-2 rounded-lg" style="background: var(--s3); color: var(--muted)">Ver →</span>
        </a>
    @empty
        <div class="card p-8 text-center mb-6">
            <p class="text-sm" style="color: var(--muted)">Nenhuma reunião agendada no momento.</p>
        </div>
    @endforelse

    <div class="mb-3 mt-8">
        <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">Realizadas ({{ $past->count() }})</h2>
    </div>
    @forelse($past as $meeting)
        @php $sc = $statusColors[$meeting->status] ?? $statusColors['realizada']; @endphp
        <a href="{{ route('portal.meetings.show', $meeting) }}" class="card p-5 mb-4 flex items-center justify-between transition-colors"
           style="text-decoration:none">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <p class="font-bold" style="color: var(--text)">{{ $meeting->title }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                        {{ $sc['label'] }}
                    </span>
                    @if($meeting->hasAta())
                        <span class="text-xs font-semibold" style="color: var(--purple)">📝 ata disponível</span>
                    @endif
                </div>
                <p class="text-xs" style="color: var(--muted)">
                    {{ $meeting->scheduled_at?->format('d/m/Y \à\s H:i') }} · {{ $meeting->modalityLabel() }}
                </p>
            </div>
            <span class="text-xs font-semibold px-3 py-2 rounded-lg" style="background: var(--s3); color: var(--muted)">Ver →</span>
        </a>
    @empty
        <div class="card p-8 text-center">
            <p class="text-sm" style="color: var(--muted)">Nenhuma reunião realizada ainda.</p>
        </div>
    @endforelse

</x-portal-layout>
