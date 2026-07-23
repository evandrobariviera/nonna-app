{{--
  Componente: <x-status-dropdown-cell>
  Uma <td> inteira, preenchida com a cor do status atual (estilo "Monday"),
  clicável — abre um dropdown com as opções, cada uma é um form que salva
  na hora (PATCH). Mesmo padrão já usado em partials/_task-tr.blade.php,
  generalizado pra qualquer model com um array $statuses (label+color).

  Props:
    options — array ['key' => ['label' => '...', 'color' => '...'], ...]
    current — chave do status atual
    action  — URL do PATCH
    field   — nome do campo enviado (default 'status')
    width   — largura da célula em px (default 140)
--}}
@props(['options', 'current', 'action', 'field' => 'status', 'width' => 140])

@php
    $currentOpt = $options[$current] ?? null;
@endphp

<td class="monday-fill-td relative" style="width:{{ $width }}px" x-data="{ open: false, style: '' }">
    <button @click="open = !open; style = dropdownStyle($el, 'bottom-left')" type="button"
            style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                   gap:4px; background:{{ \App\Models\Task::colorHex($currentOpt['color'] ?? 'muted') }}; color:#fff;
                   font-size:11px; font-weight:700; cursor:pointer; border:none; overflow:hidden">
        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:calc(100% - 20px)">
            {{ $currentOpt['label'] ?? ($current ?: '—') }}
        </span>
        <span style="opacity:.8; flex-shrink:0">▾</span>
    </button>

    <template x-teleport="body">
        <div x-show="open" @click.outside="open = false" x-close-on-scroll="open" x-cloak
             class="rounded shadow-lg py-1"
             :style="style + 'background:var(--s1); border:1px solid var(--border2); min-width:190px'">
            @foreach($options as $key => $opt)
                <form method="POST" action="{{ $action }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="{{ $field }}" value="{{ $key }}">
                    <button type="submit"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        style="color:{{ $current === $key ? 'var(--purple)' : 'var(--text)' }}"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style="background:{{ \App\Models\Task::colorHex($opt['color']) }}"></span>
                        {{ $opt['label'] }}
                    </button>
                </form>
            @endforeach
        </div>
    </template>
</td>
