{{-- Menu único compartilhado pros dropdowns de Status/Situação (ver
     resources/js/monday-fill.js, $store.badgeFill). Renderizar UMA VEZ por página
     (fora da área que o live-filter substitui via AJAX) — qualquer linha da tabela
     abre este mesmo menu chamando $store.badgeFill.openFor(...). Antes cada linha
     duplicava essas ~14 opções (status+situação); com centenas de tarefas isso
     inflava o HTML à toa. --}}
<template x-teleport="body">
    <div x-show="$store.badgeFill.open" @click.outside="$store.badgeFill.close()" x-close-on-scroll="$store.badgeFill.open" x-cloak
         class="rounded shadow-lg py-1"
         :style="$store.badgeFill.style + 'background:var(--s1); border:1px solid var(--border2); min-width:210px'">
        <template x-if="$store.badgeFill.field === 'status'">
            <div>
                @foreach(\App\Models\Task::$statuses as $key => $s)
                    @php $hex = \App\Models\Task::colorHex($s['color']); @endphp
                    <button type="button"
                        @click="$store.badgeFill.select('{{ $key }}', '{{ addslashes($s['label']) }}', '{{ $hex }}')"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        :style="$store.badgeFill.currentKey === '{{ $key }}' ? 'color:var(--purple)' : 'color:var(--text)'"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $hex }}"></span>
                        {{ $s['label'] }}
                    </button>
                @endforeach
            </div>
        </template>
        <template x-if="$store.badgeFill.field === 'situation'">
            <div>
                @foreach(\App\Models\Task::$situations as $key => $label)
                    @php $situacaoHex = \App\Models\Task::$situationColors[$key] ?? '#94a3b8'; @endphp
                    <button type="button"
                        @click="$store.badgeFill.select('{{ $key }}', '{{ addslashes($label ?: '— Limpar —') }}', '{{ $situacaoHex }}')"
                        class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors"
                        :style="$store.badgeFill.currentKey === '{{ $key }}' ? 'color:var(--purple)' : 'color:var(--text)'"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                        @if($key !== '')
                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $situacaoHex }}"></span>
                        @else
                            <span class="inline-block w-2 h-2 flex-shrink-0"></span>
                        @endif
                        {{ $label ?: '— Limpar —' }}
                    </button>
                @endforeach
            </div>
        </template>
    </div>
</template>
