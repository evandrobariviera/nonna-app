{{-- Botão de preenchimento colorido + dropdown de Situação (padrão "Monday").
     Espera no escopo: $task, $situacaoUrl.
     Precisa de um ancestral com x-data contendo situacaoOpen/situacaoStyle (e
     statusOpen, se usado ao lado de _status-fill) e de um container (<td>/<div>)
     com position:relative + tamanho definido. --}}
@php $hasSituation = $task->situation && $task->situation !== ''; @endphp
<button @click="situacaoOpen = !situacaoOpen; statusOpen = false; situacaoStyle = dropdownStyle($el, 'bottom-left')" type="button"
        style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
               gap:4px; {{ $hasSituation ? 'background:' . $task->situationColor() . '; color:#fff;' : 'background:transparent; color:var(--muted);' }}
               font-size:11px; font-weight:{{ $hasSituation ? '700' : '400' }};
               cursor:pointer; border:none; overflow:hidden; width:100%">
    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">
        {{ $hasSituation ? $task->situationLabel() : '—' }}
    </span>
    <span style="opacity:.6; flex-shrink:0">▾</span>
</button>

<template x-teleport="body">
    <div x-show="situacaoOpen" @click.outside="situacaoOpen = false" x-close-on-scroll="situacaoOpen" x-cloak
         class="rounded shadow-lg py-1"
         :style="situacaoStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:210px'">
        @foreach(\App\Models\Task::$situations as $key => $label)
            <form method="POST" action="{{ $situacaoUrl }}">
                @csrf @method('PATCH')
                <input type="hidden" name="situation" value="{{ $key }}">
                <button type="submit"
                    class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                    style="color:{{ ($task->situation ?? '') === $key ? 'var(--purple)' : 'var(--text)' }}"
                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                    @if($key !== '')
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style="background:{{ \App\Models\Task::$situationColors[$key] ?? '#94a3b8' }}"></span>
                    @else
                        <span class="inline-block w-2 h-2 flex-shrink-0"></span>
                    @endif
                    {{ $label ?: '— Limpar —' }}
                </button>
            </form>
        @endforeach
    </div>
</template>
