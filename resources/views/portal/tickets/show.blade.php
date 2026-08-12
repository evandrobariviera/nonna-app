<x-portal-layout>
    <x-slot name="title">{{ $task->title }}</x-slot>

    @php
        $statusColors = [
            'backlog'              => ['bg' => 'var(--s3)',              'text' => 'var(--muted)',  'label' => 'Backlog / A Fazer'],
            'em_producao'          => ['bg' => 'rgba(37,99,235,.1)',     'text' => '#2563eb',       'label' => 'Em Produção'],
            'revisao_interna'      => ['bg' => 'rgba(100, 59, 142,.08)',   'text' => 'var(--purple)', 'label' => 'Revisão Interna'],
            'ajuste_alteracao'     => ['bg' => 'rgba(37,99,235,.1)',     'text' => '#2563eb',       'label' => 'Ajuste / Alteração'],
            'aprovacao'            => ['bg' => 'rgba(238, 121, 25,.08)',    'text' => 'var(--orange)', 'label' => 'Aprovação'],
            'despacho_agendamento' => ['bg' => 'rgba(37,99,235,.1)',     'text' => '#2563eb',       'label' => 'Despacho / Agendamento'],
            'concluido'            => ['bg' => 'rgba(5,150,105,.1)',     'text' => 'var(--green)',  'label' => 'Concluído'],
            'cancelado'            => ['bg' => 'rgba(220,38,38,.08)',    'text' => 'var(--red)',    'label' => 'Cancelado'],
        ];
        $sc = $statusColors[$task->status] ?? $statusColors['backlog'];
    @endphp

    <div class="mb-6">
        <a href="{{ route('portal.tickets.index') }}" class="text-xs font-semibold" style="color: var(--muted)">← Chamados</a>
    </div>

    <div class="card p-6 mb-6">
        <div class="flex items-start justify-between mb-4">
            <h1 class="text-xl font-black" style="color: var(--text)">{{ $task->title }}</h1>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0"
                  style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                {{ $sc['label'] }}
            </span>
        </div>

        @if($task->situation)
            <p class="text-xs font-semibold mb-4" style="color: var(--purple)">{{ $task->situationLabel() }}</p>
        @endif

        @if($task->description)
            <p class="text-sm whitespace-pre-wrap mb-4" style="color: var(--text); line-height: 1.7">{{ $task->description }}</p>
        @endif

        <div class="grid grid-cols-2 gap-4 text-sm pt-2" style="border-top: 1px solid var(--border2)">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Aberto em</p>
                <p style="color: var(--text)">{{ $task->created_at->format('d/m/Y') }}</p>
            </div>
            @if($task->due_date)
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Prazo</p>
                    <p style="color: var(--text)">{{ $task->due_date->format('d/m/Y') }}</p>
                </div>
            @endif
        </div>
    </div>

    @if($deliverables->isNotEmpty())
        <div class="card p-6 mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-4" style="color: var(--muted)">Arquivos Entregues</h2>
            <div class="flex flex-col gap-2">
                @foreach($deliverables as $file)
                    <a href="{{ $file->url() }}" target="_blank"
                       class="flex items-center gap-3 p-3 rounded-lg transition-colors"
                       style="background: var(--s2)">
                        <span>{{ $file->icon() }}</span>
                        <span class="text-sm flex-1" style="color: var(--text)">{{ $file->filename }}</span>
                        <span class="text-xs" style="color: var(--muted)">{{ $file->sizeForHumans() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @include('portal.partials._comments', ['task' => $task])

</x-portal-layout>
