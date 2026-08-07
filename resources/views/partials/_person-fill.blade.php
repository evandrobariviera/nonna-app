{{-- Avatar clicável + dropdown pra trocar Responsável/Executor direto da lista (mesmo padrão
     "Monday fill" de _status-fill.blade.php/_situacao-fill.blade.php, só que preenche com foto
     em vez de texto). Espera no escopo: $task, $role ('responsavel'|'executor'), $currentUser
     (User|null), $users (lista pra popular o dropdown — só precisa id+name).
     Precisa de um ancestral com x-data contendo respOpen/respStyle/execOpen/execStyle (ver
     _task-tr.blade.php/_fila-task-tr.blade.php) e de um container (<td>) com position:relative
     + tamanho definido. --}}
@php
    $isResp    = $role === 'responsavel';
    $personUrl = $isResp ? route('tasks.update-responsavel', $task) : route('tasks.update-executor', $task);
    $fieldName = $isResp ? 'responsavel_id' : 'executor_id';
    $color     = $isResp ? 'var(--orange)' : 'var(--purple)';
    $openVar   = $isResp ? 'respOpen' : 'execOpen';
    $styleVar  = $isResp ? 'respStyle' : 'execStyle';
@endphp
<button type="button"
        @click="{{ $openVar }} = !{{ $openVar }}; {{ $isResp ? 'execOpen' : 'respOpen' }} = false; statusOpen = false; situacaoOpen = false; {{ $styleVar }} = dropdownStyle($el, 'bottom-left')"
        style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:none; border:none; cursor:pointer; padding:0">
    @if($currentUser)
        <x-user-avatar :user="$currentUser" size="7" color="{{ $color }}" title="{{ $currentUser->name }}" />
    @else
        <span class="text-xs" style="color:var(--muted)">—</span>
    @endif
</button>

<template x-teleport="body">
    <div x-show="{{ $openVar }}" @click.outside="{{ $openVar }} = false" x-close-on-scroll="{{ $openVar }}" x-cloak
         class="rounded shadow-lg py-1"
         :style="{{ $styleVar }} + 'background:var(--s1); border:1px solid var(--border2); min-width:190px; max-height:280px; overflow-y:auto'">
        <form method="POST" action="{{ $personUrl }}">
            @csrf @method('PATCH')
            <input type="hidden" name="{{ $fieldName }}" value="">
            <button type="submit"
                class="w-full text-left px-3 py-1.5 text-xs"
                style="color:var(--muted)"
                onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                — Remover —
            </button>
        </form>
        @foreach($users as $u)
            <form method="POST" action="{{ $personUrl }}">
                @csrf @method('PATCH')
                <input type="hidden" name="{{ $fieldName }}" value="{{ $u->id }}">
                <button type="submit"
                    class="w-full text-left px-3 py-1.5 text-xs"
                    style="color:{{ $currentUser?->id === $u->id ? 'var(--purple)' : 'var(--text)' }}"
                    onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                    {{ $u->name }}
                </button>
            </form>
        @endforeach
    </div>
</template>
