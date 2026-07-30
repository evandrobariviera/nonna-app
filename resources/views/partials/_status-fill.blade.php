{{-- Botão de preenchimento colorido + dropdown de Status (padrão "Monday").
     Espera no escopo: $task, $statusUrl.
     Precisa de um ancestral com x-data contendo statusOpen/statusStyle (e
     situacaoOpen, se usado ao lado de _situacao-fill, pra fechar um ao abrir
     o outro) e de um container (<td>/<div>) com position:relative + tamanho
     definido — funciona em tabela ou fora dela, o botão preenche 100% do pai. --}}
<button @click="statusOpen = !statusOpen; situacaoOpen = false; statusStyle = dropdownStyle($el, 'bottom-left')" type="button"
        style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
               gap:4px; background:{{ $task->statusHex() }}; color:#fff; font-size:11px;
               font-weight:700; cursor:pointer; border:none; overflow:hidden">
    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">{{ $task->statusLabel() }}</span>
    <span style="opacity:.8; flex-shrink:0">▾</span>
</button>

<template x-teleport="body">
    <div x-show="statusOpen" @click.outside="statusOpen = false" x-close-on-scroll="statusOpen" x-cloak
         class="rounded shadow-lg py-1"
         :style="statusStyle + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
        @foreach(\App\Models\Task::$statuses as $key => $s)
            <form method="POST" action="{{ $statusUrl }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $key }}">
                <button type="submit"
                    class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                    style="color:{{ $task->status === $key ? 'var(--purple)' : 'var(--text)' }}"
                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                          style="background:{{ \App\Models\Task::colorHex($s['color']) }}"></span>
                    {{ $s['label'] }}
                </button>
            </form>
        @endforeach
    </div>
</template>
