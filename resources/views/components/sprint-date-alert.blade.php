{{-- Aviso de divergência entre a data de aprovação e a sprint da tarefa
     (ver Task::sprintDateCheck() pro porquê de cada caso ter uma sugestão diferente).

     Nunca bloqueia nada: é um aviso com atalho. Some sozinho quando a tarefa fica
     coerente, sem precisar de "marcar como lido". --}}
@props(['task'])

@php $check = $task->sprintDateCheck(); @endphp

@if($check)
    @php
        // match() só avalia o ramo escolhido — importa porque 'sem_sprint' não tem
        // $check['sprint'], e um array literal avaliaria os 4 textos sempre, quebrando
        // nesse caso ao tentar ler ->title de null nos outros ramos.
        $dataFmt = $task->approval_date->format('d/m/Y');
        $mapa = match ($check['kind']) {
            'mover' => [
                'cor'    => 'var(--orange)',
                'rgb'    => '238, 121, 25',
                'icone'  => 'calendar-range',
                'titulo' => 'A data de aprovação é de outra quinzena',
                'texto'  => "Aprovação em {$dataFmt}, que cai na {$check['sprint']->title}"
                            . " — mas a tarefa está na {$check['current']->title}.",
            ],
            'data_velha' => [
                'cor'    => 'var(--red)',
                'rgb'    => '239, 68, 68',
                'icone'  => 'history',
                'titulo' => 'A data de aprovação parece desatualizada',
                'texto'  => "Aprovação em {$dataFmt}, de uma quinzena já passada ({$check['sprint']->title}),"
                            . " mas a tarefa está na {$check['current']->title}. Normalmente isso é tarefa que"
                            . ' atrasou e foi arrastada — o que precisa de correção costuma ser a data, não a sprint.',
            ],
            'sem_sprint' => [
                'cor'    => 'var(--muted2)',
                'rgb'    => '148, 163, 184',
                'icone'  => 'calendar',
                'titulo' => 'Nenhuma sprint cobre essa data',
                'texto'  => "Aprovação em {$dataFmt} — não existe sprint criada para essa quinzena ainda.",
            ],
            'orfa' => [
                'cor'    => 'var(--purple)',
                'rgb'    => '100, 59, 142',
                'icone'  => 'calendar-plus',
                'titulo' => 'Essa data já tem sprint',
                'texto'  => "Aprovação em {$dataFmt} cai na {$check['sprint']->title}, e a tarefa ainda está fora de sprint.",
            ],
        };
    @endphp

    <div class="px-4 py-3 flex items-start gap-3 mb-4"
         style="background:rgba({{ $mapa['rgb'] }},.07); border:1px solid rgba({{ $mapa['rgb'] }},.28); border-radius:8px">

        <span class="flex-shrink-0 mt-0.5" style="color:{{ $mapa['cor'] }}">
            <x-icon :name="$mapa['icone']" size="16" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold" style="color:{{ $mapa['cor'] }}">{{ $mapa['titulo'] }}</p>
            <p class="text-xs mt-1" style="color:var(--muted2); line-height:1.6">{{ $mapa['texto'] }}</p>

            @if(in_array($check['kind'], ['mover', 'orfa'], true) && $check['sprint']->status !== 'closed')
                <form method="POST" action="{{ route('tasks.update-sprint', $task) }}" class="mt-2.5">
                    @csrf @method('PATCH')
                    <input type="hidden" name="sprint_id" value="{{ $check['sprint']->id }}">
                    <button type="submit" class="btn btn-xs"
                            style="border:1px solid {{ $mapa['cor'] }}; color:{{ $mapa['cor'] }}">
                        {{ $check['kind'] === 'orfa' ? 'Enviar para' : 'Mover para' }} {{ $check['sprint']->title }}
                    </button>
                </form>
            @elseif(in_array($check['kind'], ['mover', 'orfa'], true))
                {{-- Sprint que cobre a data já está encerrada — mover pra lá falharia
                     (SprintController::updateSprint rejeita sprint closed). Só informa. --}}
                <p class="text-xs mt-1.5" style="color:var(--muted)">
                    Essa sprint já está encerrada — ajuste a data de aprovação manualmente.
                </p>
            @elseif($check['kind'] === 'sem_sprint')
                <a href="{{ route('sprints.create') }}" class="btn btn-xs mt-2.5 inline-block"
                   style="border:1px solid {{ $mapa['cor'] }}; color:{{ $mapa['cor'] }}">
                    Criar a próxima sprint
                </a>
            @endif
        </div>
    </div>
@endif
