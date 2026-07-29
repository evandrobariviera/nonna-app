<x-portal-layout>
    <x-slot name="title">Planejamentos</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Planejamentos</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Histórico de ciclos estratégicos de {{ $client->company_name }}</p>
    </div>

    @forelse($macroplans as $plan)
        @php
            $statusColors = [
                'em_planejamento' => ['bg' => 'var(--s3)',             'text' => 'var(--muted)',  'label' => 'Em Planejamento'],
                'revisao_interna' => ['bg' => 'rgba(106,90,205,.08)',  'text' => 'var(--purple)', 'label' => 'Revisão Interna'],
                'aprovacao'       => ['bg' => 'rgba(255,140,0,.08)',   'text' => 'var(--orange)', 'label' => 'Aprovação'],
                'em_execucao'     => ['bg' => 'rgba(37,99,235,.1)',    'text' => '#2563eb',       'label' => 'Em Execução'],
                'concluido'       => ['bg' => 'rgba(5,150,105,.1)',    'text' => 'var(--green)',  'label' => 'Concluído'],
            ];
            $sc = $statusColors[$plan->status] ?? $statusColors['em_planejamento'];
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
            <a href="{{ route('portal.projects.show', $plan) }}" class="btn btn-ghost btn-sm">
                Ver →
            </a>
        </div>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm font-semibold" style="color: var(--muted)">Nenhum planejamento encontrado.</p>
        </div>
    @endforelse

</x-portal-layout>
