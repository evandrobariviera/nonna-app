<x-portal-layout>
    <x-slot name="title">Chamados</x-slot>

    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black" style="color: var(--text)">Chamados</h1>
            <p class="text-sm mt-1" style="color: var(--muted)">Tarefas avulsas e solicitações de {{ $client->company_name }}</p>
        </div>
        @if(!request()->boolean('mostrar_fechados'))
            <a href="{{ route('portal.tickets.index', ['mostrar_fechados' => 1]) }}" class="text-xs font-semibold" style="color: var(--purple)">
                Mostrar concluídos →
            </a>
        @else
            <a href="{{ route('portal.tickets.index') }}" class="text-xs font-semibold" style="color: var(--muted)">
                ← Ocultar concluídos
            </a>
        @endif
    </div>

    @php
        $statusColors = [
            'backlog'              => ['bg' => 'var(--s3)',              'text' => 'var(--muted)',  'label' => 'Backlog / A Fazer'],
            'em_producao'          => ['bg' => 'rgba(37,99,235,.1)',     'text' => '#2563eb',       'label' => 'Em Produção'],
            'revisao_interna'      => ['bg' => 'rgba(106,90,205,.08)',   'text' => 'var(--purple)', 'label' => 'Revisão Interna'],
            'ajuste_alteracao'     => ['bg' => 'rgba(37,99,235,.1)',     'text' => '#2563eb',       'label' => 'Ajuste / Alteração'],
            'aprovacao'            => ['bg' => 'rgba(255,140,0,.08)',    'text' => 'var(--orange)', 'label' => 'Aprovação'],
            'despacho_agendamento' => ['bg' => 'rgba(37,99,235,.1)',     'text' => '#2563eb',       'label' => 'Despacho / Agendamento'],
            'concluido'            => ['bg' => 'rgba(5,150,105,.1)',     'text' => 'var(--green)',  'label' => 'Concluído'],
            'cancelado'            => ['bg' => 'rgba(220,38,38,.08)',    'text' => 'var(--red)',    'label' => 'Cancelado'],
        ];
    @endphp

    @forelse($tickets as $ticket)
        @php $sc = $statusColors[$ticket->status] ?? $statusColors['backlog']; @endphp
        <a href="{{ route('portal.tickets.show', $ticket) }}" class="card p-5 mb-4 flex items-center justify-between transition-colors"
           style="text-decoration:none">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <p class="font-bold" style="color: var(--text)">{{ $ticket->title }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs" style="color: var(--muted)">
                    {{ $ticket->created_at->format('d/m/Y') }}
                    @if($ticket->due_date)
                        · prazo {{ $ticket->due_date->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            <span class="text-xs font-semibold px-3 py-2 rounded-lg" style="background: var(--s3); color: var(--muted)">Ver →</span>
        </a>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm font-semibold" style="color: var(--muted)">Nenhum chamado encontrado.</p>
        </div>
    @endforelse

</x-portal-layout>
