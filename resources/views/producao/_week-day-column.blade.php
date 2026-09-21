{{-- Uma coluna do quadro (um dia) — isolado num partial próprio porque também é usado sozinho
     pelo endpoint de extensão dia-a-dia (ProductionPanelController::diaResultados()), chamado
     pelo JS quando a régua horizontal chega na borda (ver producao-week-scroll.js).

     @props não dá aqui porque quem inclui passa $dia (['data' => Carbon, 'tasks' => Collection])
     e precisa ficar disponível tal como está pros dois chamadores (@include e a view do
     endpoint) sem reformatar nada. --}}
@php
    $data = $dia['data'];
    $colTasks = $dia['tasks'];
    $hoje = $data->isToday();
    $dataStatus = $data->toDateString();

    // Cor de status → rgb, pra dar 20% de opacidade no fundo do card inteiro (não só na tag).
    // Mesma paleta de .badge-{cor} em app.css, convertida de hex pra rgb (compatibilidade
    // maior que color-mix(), que só entrou nos navegadores em 2023).
    $corRgb = [
        'green' => '16,185,129', 'purple' => '100,59,142', 'orange' => '238,121,25',
        'red' => '220,38,38', 'muted' => '152,161,178', 'blue' => '46,144,250',
    ];

    $capa = 30; // ver nota em baixo, na coluna
@endphp

<div class="flex flex-col gap-2 flex-shrink-0" style="width:270px{{ $hoje ? '; padding:6px; border-radius:10px; border:2px solid var(--green); background:rgba(52,211,153,.05)' : '' }}"
     data-kanban-column data-status="{{ $dataStatus }}">
    <div class="flex items-center justify-between px-3 py-2"
         style="{{ $hoje ? 'background:rgba(52,211,153,.12); border:1px solid var(--green)' : 'background:var(--s2); border:1px solid var(--border2)' }}">
        <span class="text-xs font-bold font-mono uppercase tracking-widest" style="color:{{ $hoje ? 'var(--green)' : 'var(--purple)' }}">
            {{ ucfirst($data->translatedFormat('D')) }} · {{ $data->format('d/m') }}{{ $hoje ? ' · Hoje' : '' }}
        </span>
        <span class="text-xs font-mono font-bold" data-kanban-count style="color:var(--muted)">{{ $colTasks->count() }}</span>
    </div>

    {{-- Coluna com muito card trava o navegador se renderizar tudo de cara: cada card tem
         avatares e o Alpine tem que hidratar cada um. Mostra só os primeiros e revela o resto
         sob pedido. --}}
    <div class="flex flex-col gap-2" style="min-height:40px; max-height:70vh; overflow-y:auto"
         data-kanban-list @if($colTasks->count() > $capa) x-data="{ mostrarMais: false }" @endif>
    @forelse($colTasks as $i => $task)
        @php
            $execList = $task->executors->filter(fn($u) => $u->pivot->role === 'executor');
            if ($execList->isEmpty() && $task->executor) {
                $execList = collect([$task->executor]);
            }
            $respList = $task->executors->filter(fn($u) => $u->pivot->role === 'responsavel');
            $approvalUrl = route('tasks.update-approval-date-direct', $task);
            $rgb = $corRgb[$task->statusColor()] ?? $corRgb['muted'];
        @endphp
        {{-- Card reduzido de propósito: sem miniatura nem ícone de tipo, sem botão "Mover" (o
             arrastar já cobre isso) — com ~400+ tarefas na tela, cada elemento extra por card
             pesava o navegador. Clique abre a tarefa num popup, não navega pra fora do painel. --}}
        <div class="px-3.5 py-3 relative"
             @if($i >= $capa) x-show="mostrarMais" x-cloak @endif
             data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $approvalUrl }}"
             style="background:rgba({{ $rgb }}, .2);
                    border:1px solid var(--border2);
                    {{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}; cursor:pointer"
             @click="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">

            @if(! $task->sprint_id)
                <p class="text-xs font-mono truncate mb-0.5" style="color:var(--orange)">Fila</p>
            @endif

            <p class="text-xs font-mono truncate" style="color:var(--purple)">
                {{ $task->client?->displayName() ?? '—' }}
            </p>

            <p class="font-semibold leading-snug mt-1.5" style="color:var(--text); font-size:14px">
                {{ $task->title }}
            </p>

            <div class="flex items-center gap-1 mt-2 flex-wrap">
                <span class="badge badge-{{ $task->statusColor() }}" style="font-size:8px; padding:1px 5px">{{ $task->statusLabel() }}</span>
                @if($task->situation)
                    <span class="badge" style="font-size:8px; padding:1px 5px; background:{{ $task->situationColor() }}; color:#fff; border-color:transparent">
                        {{ $task->situationLabel() }}
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-1.5 mt-2.5">
                @forelse($respList as $resp)
                    <x-user-avatar :user="$resp" size="5" color="var(--orange)" title="{{ $resp->name }} (Responsável)" />
                @empty
                    @if($execList->isEmpty())
                        <span class="text-xs" style="color:var(--muted2)">—</span>
                    @endif
                @endforelse
                @foreach($execList as $exec)
                    <x-user-avatar :user="$exec" size="5" color="var(--purple)" title="{{ $exec->name }} (Executor)" />
                @endforeach
            </div>
        </div>
    @empty
        <div class="px-4 py-5 text-center text-xs"
             style="border:1px dashed var(--border2); color:var(--muted)">
            Sem tarefas
        </div>
    @endforelse

    @if($colTasks->count() > $capa)
        <button type="button" x-show="!mostrarMais" @click="mostrarMais = true" x-cloak
                class="text-xs font-semibold py-2 text-center"
                style="color:var(--purple); border:1px dashed var(--border2); background:var(--s2)">
            + mostrar mais {{ $colTasks->count() - $capa }} tarefa{{ ($colTasks->count() - $capa) !== 1 ? 's' : '' }}
        </button>
    @endif
    </div>
</div>
