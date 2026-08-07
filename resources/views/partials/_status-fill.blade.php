{{-- Botão de preenchimento colorido + dropdown de Status (padrão "Monday").
     Espera no escopo: $task, $statusUrl.
     Opcional: $showCurrent (default true) — false mostra o botão "vazio"
     (neutro, sem o valor atual preenchido) mesmo a tarefa já tendo um status;
     usado quando o widget funciona como uma AÇÃO a escolher (ex: roteamento
     rápido na Central de Aprovações), não como exibição do estado atual —
     mostrar o valor já preenchido ali dava a entender que já tinha sido
     escolhido/tratado.
     Opcional: $ajax (default false) — true aplica a mudança via PATCH em
     background (window.applyFill, ver resources/js/inline-patch.js), sem
     recarregar a página; espera reativo no x-data do ancestral:
     statusKey/statusLabel/statusColor (ver _task-tr.blade.php). Fica
     desligado por padrão pra não mexer no uso na Central de Aprovações
     (showCurrent=false), que não precisa disso.
     Precisa de um ancestral com x-data contendo statusOpen/statusStyle (e
     situacaoOpen, se usado ao lado de _situacao-fill, pra fechar um ao abrir
     o outro) e de um container (<td>/<div>) com position:relative + tamanho
     definido — funciona em tabela ou fora dela, o botão preenche 100% do pai. --}}
@php
    $showCurrent = $showCurrent ?? true;
    $ajax        = $ajax ?? false;
@endphp

@if($ajax)
    <button @click="statusOpen = !statusOpen; situacaoOpen = false; respOpen = false; execOpen = false; statusStyle = dropdownStyle($el, 'bottom-left')" type="button"
            :style="'position:absolute; inset:0; display:flex; align-items:center; justify-content:center; gap:4px; font-size:11px; font-weight:700; cursor:pointer; border:none; overflow:hidden; background:' + statusColor + '; color:#fff;'">
        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)" x-text="statusLabel"></span>
        <span style="opacity:.8; flex-shrink:0">▾</span>
    </button>
@else
    <button @click="statusOpen = !statusOpen; situacaoOpen = false; statusStyle = dropdownStyle($el, 'bottom-left')" type="button"
            style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                   gap:4px; background:{{ $showCurrent ? $task->statusHex() : 'var(--s2)' }};
                   color:{{ $showCurrent ? '#fff' : 'var(--muted)' }}; font-size:11px;
                   font-weight:{{ $showCurrent ? '700' : '400' }}; cursor:pointer;
                   border:{{ $showCurrent ? 'none' : '1px dashed var(--border2)' }}; overflow:hidden">
        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">
            {{ $showCurrent ? $task->statusLabel() : 'Selecionar status' }}
        </span>
        <span style="opacity:.8; flex-shrink:0">▾</span>
    </button>
@endif

<template x-teleport="body">
    <div x-show="statusOpen" @click.outside="statusOpen = false" x-close-on-scroll="statusOpen" x-cloak
         class="rounded shadow-lg py-1"
         :style="statusStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
        @foreach(\App\Models\Task::$statuses as $key => $s)
            @php $hex = \App\Models\Task::colorHex($s['color']); @endphp
            @if($ajax)
                <button type="button"
                    @click="applyFill('{{ $statusUrl }}', {status: '{{ $key }}'}, () => { statusKey = '{{ $key }}'; statusLabel = '{{ addslashes($s['label']) }}'; statusColor = '{{ $hex }}'; statusOpen = false })"
                    class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                    :style="statusKey === '{{ $key }}' ? 'color:var(--purple)' : 'color:var(--text)'"
                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $hex }}"></span>
                    {{ $s['label'] }}
                </button>
            @else
                <form method="POST" action="{{ $statusUrl }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button type="submit"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:{{ $showCurrent && $task->status === $key ? 'var(--purple)' : 'var(--text)' }}"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $hex }}"></span>
                        {{ $s['label'] }}
                    </button>
                </form>
            @endif
        @endforeach
    </div>
</template>
