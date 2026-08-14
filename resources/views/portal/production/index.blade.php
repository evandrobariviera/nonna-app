<x-portal-layout>
    <x-slot name="title">Produção</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-black" style="color: var(--text)">Produção</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            Tudo o que está ativo pra {{ $client->company_name }} agora — fila, sprint e chamados, organizado por prazo.
        </p>
    </div>

    {{-- Stats + filtro --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-6">
            <div>
                <p class="text-2xl font-black leading-none" style="color: var(--text)">{{ $stats['total'] }}</p>
                <p class="text-xs mt-1" style="color: var(--muted)">demanda{{ $stats['total'] !== 1 ? 's' : '' }} ativa{{ $stats['total'] !== 1 ? 's' : '' }}</p>
            </div>
            @if($stats['atrasadas'] > 0)
                <div>
                    <p class="text-2xl font-black leading-none" style="color: var(--red)">{{ $stats['atrasadas'] }}</p>
                    <p class="text-xs mt-1" style="color: var(--muted)">atrasada{{ $stats['atrasadas'] !== 1 ? 's' : '' }}</p>
                </div>
            @endif
        </div>

        <form method="GET" action="{{ route('portal.production.index') }}">
            <label class="flex items-center gap-2 text-sm cursor-pointer" style="color: var(--muted2)">
                <input type="checkbox" name="mostrar_concluidas" value="1" onchange="this.form.submit()" {{ $mostrarConcluidas ? 'checked' : '' }}>
                Mostrar concluídas
            </label>
        </form>
    </div>

    @if($stats['total'] === 0)
        <div class="card p-10 text-center">
            <p class="mb-2 flex justify-center" style="color:var(--muted)"><x-icon name="party-popper" size="24" /></p>
            <p class="text-sm font-semibold" style="color: var(--text)">Nada ativo no momento</p>
            <p class="text-xs mt-1" style="color: var(--muted)">
                @if(!$mostrarConcluidas)
                    Tente marcar "Mostrar concluídas" acima pra ver o histórico.
                @else
                    Assim que uma nova demanda entrar em produção, ela aparece aqui.
                @endif
            </p>
        </div>
    @else
        @foreach($groups as $key => $group)
            @continue($group['tasks']->isEmpty())
            <div class="mb-8">
                <h2 class="text-xs font-bold uppercase tracking-widest mb-3" style="color: {{ $key === 'atrasadas' ? 'var(--red)' : 'var(--muted)' }}">
                    {{ $group['label'] }} ({{ $group['tasks']->count() }})
                </h2>
                <div class="flex flex-col gap-2">
                    @foreach($group['tasks'] as $task)
                        @php
                            $showRoute = $task->is_ticket ? route('portal.tickets.show', $task) : route('portal.tasks.show', $task);
                            $statusColorMap = ['muted'=>'#98A1B2','blue'=>'#2E90FA','purple'=>'#643B8E','orange'=>'#EE7919','green'=>'#10B981','red'=>'#DC2626'];
                            $sColor = $statusColorMap[$task->statusColor()] ?? 'var(--muted)';
                        @endphp
                        <a href="{{ $showRoute }}" class="card p-4 flex items-center justify-between gap-4 transition-colors" style="text-decoration:none">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="h-2 w-2 rounded-full flex-shrink-0" style="background: {{ $sColor }}"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold truncate" style="color: var(--text)">{{ $task->title }}</p>
                                    <p class="text-xs mt-0.5" style="color: var(--muted)">
                                        {{ $task->typeLabel() }}
                                        @if($task->is_ticket)
                                            · Chamado
                                        @elseif($task->sprint)
                                            · {{ $task->sprint->title }}
                                        @else
                                            · Fila
                                        @endif
                                        @if($task->situation)
                                            · {{ $task->situationLabel() }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                      style="background: {{ $sColor }}1a; color: {{ $sColor }}">
                                    {{ $task->statusLabel() }}
                                </span>
                                <span class="text-xs font-mono" style="color: {{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                    {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

</x-portal-layout>
