{{-- Botão de preenchimento colorido + dropdown de Situação (padrão "Monday").
     Espera no escopo: $task, $situacaoUrl.
     Opcional: $showCurrent (default true) — false mostra sempre o prompt neutro
     "Selecionar situação", ignorando o valor atual da tarefa. Ver _status-fill.blade.php.
     Opcional: $ajax (default false) — true aplica via PATCH em background
     (window.applyFill), sem recarregar; espera situacaoKey/situacaoLabel/situacaoColor
     reativos no x-data do ancestral (ver _task-tr.blade.php). Desligado por padrão pra
     não mexer no uso na Central de Aprovações (showCurrent=false).
     Precisa de um ancestral com x-data contendo situacaoOpen/situacaoStyle (e
     statusOpen, se usado ao lado de _status-fill) e de um container (<td>/<div>)
     com position:relative + tamanho definido. --}}
@php
    $showCurrent  = $showCurrent ?? true;
    $ajax         = $ajax ?? false;
    $hasSituation = $showCurrent && $task->situation && $task->situation !== '';
@endphp

@if($ajax)
    <button @click="situacaoOpen = !situacaoOpen; statusOpen = false; respOpen = false; execOpen = false; situacaoStyle = dropdownStyle($el, 'bottom-left')" type="button"
            :style="'position:absolute; inset:0; display:flex; align-items:center; justify-content:center; gap:4px; font-size:11px; cursor:pointer; border:none; overflow:hidden; width:100%; ' + (situacaoKey ? ('background:' + situacaoColor + '; color:#fff; font-weight:700;') : 'background:transparent; color:var(--muted); font-weight:400;')">
        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)" x-text="situacaoLabel"></span>
        <span style="opacity:.6; flex-shrink:0">▾</span>
    </button>
@else
    <button @click="situacaoOpen = !situacaoOpen; statusOpen = false; situacaoStyle = dropdownStyle($el, 'bottom-left')" type="button"
            style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                   gap:4px; {{ $hasSituation ? 'background:' . $task->situationColor() . '; color:#fff;' : 'background:transparent; color:var(--muted);' }}
                   font-size:11px; font-weight:{{ $hasSituation ? '700' : '400' }};
                   cursor:pointer; border:{{ $showCurrent ? 'none' : '1px dashed var(--border2)' }}; overflow:hidden; width:100%">
        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">
            {{ $hasSituation ? $task->situationLabel() : ($showCurrent ? '—' : 'Selecionar situação') }}
        </span>
        <span style="opacity:.6; flex-shrink:0">▾</span>
    </button>
@endif

<template x-teleport="body">
    <div x-show="situacaoOpen" @click.outside="situacaoOpen = false" x-close-on-scroll="situacaoOpen" x-cloak
         class="rounded shadow-lg py-1"
         :style="situacaoStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:210px'">
        @foreach(\App\Models\Task::$situations as $key => $label)
            @php $situacaoHex = \App\Models\Task::$situationColors[$key] ?? '#94a3b8'; @endphp
            @if($ajax)
                <button type="button"
                    @click="applyFill('{{ $situacaoUrl }}', {situation: '{{ $key }}'}, () => { situacaoKey = '{{ $key }}'; situacaoLabel = '{{ addslashes($label ?: '— Limpar —') }}'; situacaoColor = '{{ $situacaoHex }}'; situacaoOpen = false })"
                    class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                    :style="situacaoKey === '{{ $key }}' ? 'color:var(--purple)' : 'color:var(--text)'"
                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                    @if($key !== '')
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $situacaoHex }}"></span>
                    @else
                        <span class="inline-block w-2 h-2 flex-shrink-0"></span>
                    @endif
                    {{ $label ?: '— Limpar —' }}
                </button>
            @else
                <form method="POST" action="{{ $situacaoUrl }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="situation" value="{{ $key }}">
                    <button type="submit"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:{{ $showCurrent && ($task->situation ?? '') === $key ? 'var(--purple)' : 'var(--text)' }}"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        @if($key !== '')
                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $situacaoHex }}"></span>
                        @else
                            <span class="inline-block w-2 h-2 flex-shrink-0"></span>
                        @endif
                        {{ $label ?: '— Limpar —' }}
                    </button>
                </form>
            @endif
        @endforeach
    </div>
</template>
