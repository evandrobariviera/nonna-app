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

{{-- Coluna no padrão Mirbal (classes .kb-* em app.css); "hoje" com borda e risquinho verdes.
     O fundo tingido na cor do status nos cartões continua (esquema de cores do time). --}}
<div class="kb-col" style="--kb-bar: {{ $hoje ? 'var(--green)' : 'var(--purple)' }}; width:280px{{ $hoje ? '; border:2px solid var(--green)' : '' }}"
     data-kanban-column data-status="{{ $dataStatus }}">
    <div class="kb-col-head">
        <h5 class="card-title truncate" style="{{ $hoje ? 'color:var(--green)' : '' }}">
            {{ ucfirst($data->translatedFormat('D')) }} · {{ $data->format('d/m') }}{{ $hoje ? ' · Hoje' : '' }}
        </h5>
        <span class="kb-count" data-kanban-count>{{ $colTasks->count() }}</span>
    </div>

    {{-- Coluna com muito card trava o navegador se renderizar tudo de cara: cada card tem
         avatares e o Alpine tem que hidratar cada um. Mostra só os primeiros e revela o resto
         sob pedido. --}}
    <div class="kb-list" style="max-height:70vh; overflow-y:auto"
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
        <div class="kb-card {{ $task->isOverdue() ? 'kb-card-overdue' : '' }}"
             @if($i >= $capa) x-show="mostrarMais" x-cloak @endif
             data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $approvalUrl }}"
             style="background:rgba({{ $rgb }}, .16); cursor:pointer"
             @click="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">

            <div class="kb-card-top flex-wrap" style="margin-bottom:8px">
                <span class="badge tag-purple truncate" style="max-width:170px">{{ $task->client?->displayName() ?? '—' }}</span>
                @if(! $task->sprint_id)
                    <span class="badge tag-orange">Fila</span>
                @endif
            </div>

            <p class="kb-card-title">{{ $task->title }}</p>

            <div class="flex items-center gap-1 mt-2 flex-wrap">
                <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px; padding:1px 6px">{{ $task->statusLabel() }}</span>
                @if($task->situation)
                    <span class="badge" style="font-size:10px; padding:1px 6px; background:{{ $task->situationColor() }}; color:#fff">
                        {{ $task->situationLabel() }}
                    </span>
                @endif
            </div>

            <div class="kb-card-foot" style="justify-content:flex-end; margin-top:10px">
                <div class="kb-avatars">
                    @foreach($respList as $resp)
                        <x-user-avatar :user="$resp" size="6" color="var(--orange)" title="{{ $resp->name }} (Responsável)" />
                    @endforeach
                    @foreach($execList as $exec)
                        <x-user-avatar :user="$exec" size="6" color="var(--purple)" title="{{ $exec->name }} (Executor)" />
                    @endforeach
                </div>
                @if($respList->isEmpty() && $execList->isEmpty())
                    <span style="color:var(--muted2)">sem pessoa</span>
                @endif
            </div>
        </div>
    @empty
        <div class="kb-empty">Sem tarefas</div>
    @endforelse

    @if($colTasks->count() > $capa)
        <button type="button" x-show="!mostrarMais" @click="mostrarMais = true" x-cloak
                class="btn btn-soft-purple btn-xs justify-center">
            + mostrar mais {{ $colTasks->count() - $capa }} tarefa{{ ($colTasks->count() - $capa) !== 1 ? 's' : '' }}
        </button>
    @endif
    </div>
</div>
