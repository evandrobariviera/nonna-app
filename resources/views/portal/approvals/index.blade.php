<x-portal-layout>
    <x-slot name="title">Aprovações</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Aprovações</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Materiais aguardando sua decisão e histórico de {{ $client->company_name }}</p>
    </div>

    @php
        $statusColors = [
            'pending'            => ['bg' => 'rgba(100, 59, 142,.08)', 'text' => 'var(--purple)', 'label' => 'Aguardando Decisão'],
            'approved'           => ['bg' => 'rgba(5,150,105,.1)',   'text' => 'var(--green)',  'label' => 'Aprovado'],
            'changes_requested'  => ['bg' => 'rgba(238, 121, 25,.08)',  'text' => 'var(--orange)', 'label' => 'Ajustes Solicitados'],
            'cancelled'          => ['bg' => 'var(--s3)',            'text' => 'var(--muted)',  'label' => 'Cancelado'],
        ];
    @endphp

    <div class="mb-3">
        <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">Aguardando Decisão ({{ $pending->count() }})</h2>
    </div>
    @forelse($pending as $round)
        @php $sc = $statusColors[$round->status] ?? $statusColors['pending']; @endphp
        <a href="{{ route('portal.approvals.show', $round) }}" class="card p-5 mb-4 flex items-center justify-between transition-colors"
           style="text-decoration:none">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <p class="font-bold" style="color: var(--text)">{{ $round->task?->title ?? '—' }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs" style="color: var(--muted)">
                    Rodada #{{ $round->round_number }} · enviado em {{ $round->submitted_at?->format('d/m/Y') }}
                </p>
            </div>
            <span class="text-xs font-semibold px-3 py-2 rounded-lg" style="background: var(--purple); color: #fff">Revisar →</span>
        </a>
    @empty
        <div class="card p-8 text-center mb-6">
            <p class="text-sm" style="color: var(--muted)">Nenhum material aguardando sua decisão no momento.</p>
        </div>
    @endforelse

    <div class="mb-3 mt-8">
        <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">Histórico ({{ $decided->count() }})</h2>
    </div>
    @forelse($decided as $round)
        @php $sc = $statusColors[$round->status] ?? $statusColors['approved']; @endphp
        <a href="{{ route('portal.approvals.show', $round) }}" class="card p-5 mb-4 flex items-center justify-between transition-colors"
           style="text-decoration:none">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <p class="font-bold" style="color: var(--text)">{{ $round->task?->title ?? '—' }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs" style="color: var(--muted)">
                    Rodada #{{ $round->round_number }} · decidido em {{ $round->resolved_at?->format('d/m/Y') }}
                </p>
            </div>
            <span class="text-xs font-semibold px-3 py-2 rounded-lg" style="background: var(--s3); color: var(--muted)">Ver →</span>
        </a>
    @empty
        <div class="card p-8 text-center">
            <p class="text-sm" style="color: var(--muted)">Nenhuma aprovação decidida ainda.</p>
        </div>
    @endforelse

</x-portal-layout>
