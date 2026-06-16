<x-portal-layout>
    <x-slot name="title">Planejamentos</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Planejamentos</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Histórico de ciclos estratégicos de {{ $client->company_name }}</p>
    </div>

    @forelse($macroplans as $plan)
        @php
            $statusColors = [
                'active'  => ['bg' => 'rgba(5,150,105,.1)',   'text' => 'var(--green)',  'label' => 'Ativo'],
                'draft'   => ['bg' => 'var(--s3)',            'text' => 'var(--muted)',  'label' => 'Rascunho'],
                'closed'  => ['bg' => 'rgba(106,90,205,.08)', 'text' => 'var(--purple)', 'label' => 'Encerrado'],
            ];
            $sc = $statusColors[$plan->status] ?? $statusColors['draft'];
        @endphp
        <div class="card p-5 mb-4 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <p class="font-bold" style="color: var(--text)">{{ $plan->title }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs" style="color: var(--muted)">
                    {{ $plan->period_start?->format('d/m/Y') }} — {{ $plan->period_end?->format('d/m/Y') }}
                    · {{ $plan->projects->count() }} projeto(s)
                </p>
            </div>
            <a href="{{ route('portal.projects.show', $plan) }}"
               class="text-xs font-semibold px-3 py-2 rounded-lg transition-colors"
               style="background: var(--s3); color: var(--muted)"
               onmouseover="this.style.color='var(--purple)'"
               onmouseout="this.style.color='var(--muted)'">
                Ver →
            </a>
        </div>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm font-semibold" style="color: var(--muted)">Nenhum planejamento encontrado.</p>
        </div>
    @endforelse

</x-portal-layout>
