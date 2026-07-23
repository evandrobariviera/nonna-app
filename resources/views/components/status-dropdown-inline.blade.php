{{--
  Componente: <x-status-dropdown-inline>
  Mesma mecânica de <x-status-dropdown-cell>, mas o gatilho é um badge/pill
  normal (inline-flex), não um preenchimento de célula — pra usar fora de
  tabela (ex: cabeçalho de uma página de detalhe).

  Props: iguais a <x-status-dropdown-cell> (sem `width`).
--}}
@props(['options', 'current', 'action', 'field' => 'status'])

@php
    $currentOpt = $options[$current] ?? null;
@endphp

<span class="relative inline-block" x-data="{ open: false, style: '' }">
    <button @click="open = !open; style = dropdownStyle($el, 'bottom-left')" type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-opacity"
            style="background:{{ \App\Models\Task::colorHex($currentOpt['color'] ?? 'muted') }}; color:#fff; border:none; cursor:pointer"
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        {{ $currentOpt['label'] ?? ($current ?: '—') }}
        <span style="opacity:.8">▾</span>
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
</span>
