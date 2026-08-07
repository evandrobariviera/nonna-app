{{-- Menu único compartilhado pro dropdown de Responsável/Executor (ver
     resources/js/monday-fill.js, $store.personFill). Renderizar UMA VEZ por página
     (fora da área que o live-filter substitui via AJAX), com $users no escopo.
     Antes cada linha duplicava a lista inteira de usuários (2x por linha —
     Responsável e Executor); com centenas de tarefas isso sozinho já passava de
     7MB de HTML nunca aberto. --}}
@props(['users'])
<template x-teleport="body">
    <div x-show="$store.personFill.open" @click.outside="$store.personFill.close()" x-close-on-scroll="$store.personFill.open" x-cloak
         class="rounded shadow-lg py-1"
         :style="$store.personFill.style + 'background:var(--s1); border:1px solid var(--border2); min-width:190px; max-height:280px; overflow-y:auto'">
        <button type="button"
            @click="$store.personFill.select(null, null, null, null)"
            class="w-full text-left px-3 py-1.5 text-xs"
            style="color:var(--muted)"
            onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
            — Remover —
        </button>
        @foreach($users as $u)
            @php $initials = strtoupper(substr($u->name, 0, 2)); @endphp
            <button type="button"
                @click="$store.personFill.select('{{ $u->id }}', '{{ addslashes($u->name) }}', {{ $u->avatarUrl() ? "'" . $u->avatarUrl() . "'" : 'null' }}, '{{ $initials }}')"
                class="w-full text-left px-3 py-1.5 text-xs"
                :style="$store.personFill.currentName === '{{ addslashes($u->name) }}' ? 'color:var(--purple)' : 'color:var(--text)'"
                onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                {{ $u->name }}
            </button>
        @endforeach
    </div>
</template>
