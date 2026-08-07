{{-- Avatar clicável + dropdown pra trocar Responsável/Executor direto da lista (mesmo padrão
     "Monday fill" de _status-fill.blade.php/_situacao-fill.blade.php, só que preenche com foto
     em vez de texto). Espera no escopo: $task, $role ('responsavel'|'executor'), $currentUser
     (User|null), $users (lista pra popular o dropdown — precisa id+name+avatar_path+avatar_disk,
     pra avatarUrl() funcionar direito assim que uma pessoa nova é escolhida).
     Sempre aplica via PATCH em background (window.applyFill), sem recarregar — diferente de
     _status-fill/_situacao-fill, esse aqui não é usado na Central de Aprovações, não precisa de
     opt-in. Um componente Blade (<x-user-avatar>) não é "hidratável", então replica aqui em
     Alpine puro o mesmo if/else dele (foto vs. iniciais) pra poder atualizar sem reload.
     Precisa de um ancestral com x-data contendo respOpen/respStyle/execOpen/execStyle e
     respName/respAvatarUrl/respInitials/execName/execAvatarUrl/execInitials (ver
     _task-tr.blade.php/_fila-task-tr.blade.php) e de um container (<td>) com position:relative
     + tamanho definido. --}}
@php
    $isResp    = $role === 'responsavel';
    $personUrl = $isResp ? route('tasks.update-responsavel', $task) : route('tasks.update-executor', $task);
    $fieldName = $isResp ? 'responsavel_id' : 'executor_id';
    $color     = $isResp ? 'var(--orange)' : 'var(--purple)';
    $openVar   = $isResp ? 'respOpen' : 'execOpen';
    $styleVar  = $isResp ? 'respStyle' : 'execStyle';
    $nameVar   = $isResp ? 'respName' : 'execName';
    $avatarVar = $isResp ? 'respAvatarUrl' : 'execAvatarUrl';
    $initialsVar = $isResp ? 'respInitials' : 'execInitials';
@endphp
<button type="button"
        @click="{{ $openVar }} = !{{ $openVar }}; {{ $isResp ? 'execOpen' : 'respOpen' }} = false; statusOpen = false; situacaoOpen = false; {{ $styleVar }} = dropdownStyle($el, 'bottom-left')"
        style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:none; border:none; cursor:pointer; padding:0">
    <img x-show="{{ $nameVar }} && {{ $avatarVar }}" x-cloak :src="{{ $avatarVar }}" :title="{{ $nameVar }}"
         class="rounded-full object-cover flex-shrink-0" style="height:28px; width:28px">
    <div x-show="{{ $nameVar }} && !{{ $avatarVar }}" x-cloak x-text="{{ $initialsVar }}" :title="{{ $nameVar }}"
         class="rounded-full flex items-center justify-center font-black text-white flex-shrink-0"
         style="height:28px; width:28px; font-size:12px" :style="'background:{{ $color }}'"></div>
    <span class="text-xs" x-show="!{{ $nameVar }}" style="color:var(--muted)">—</span>
</button>

<template x-teleport="body">
    <div x-show="{{ $openVar }}" @click.outside="{{ $openVar }} = false" x-close-on-scroll="{{ $openVar }}" x-cloak
         class="rounded shadow-lg py-1"
         :style="{{ $styleVar }} + 'background:var(--s1); border:1px solid var(--border2); min-width:190px; max-height:280px; overflow-y:auto'">
        <button type="button"
            @click="applyFill('{{ $personUrl }}', {{ '{' . $fieldName . ': null}' }}, () => { {{ $nameVar }} = null; {{ $avatarVar }} = null; {{ $initialsVar }} = null; {{ $openVar }} = false })"
            class="w-full text-left px-3 py-1.5 text-xs"
            style="color:var(--muted)"
            onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
            — Remover —
        </button>
        @foreach($users as $u)
            @php $initials = strtoupper(substr($u->name, 0, 2)); @endphp
            <button type="button"
                @click="applyFill('{{ $personUrl }}', {{ '{' . $fieldName . ": '{$u->id}'}" }}, () => { {{ $nameVar }} = '{{ addslashes($u->name) }}'; {{ $avatarVar }} = {{ $u->avatarUrl() ? "'" . $u->avatarUrl() . "'" : 'null' }}; {{ $initialsVar }} = '{{ $initials }}'; {{ $openVar }} = false })"
                class="w-full text-left px-3 py-1.5 text-xs"
                :style="{{ $nameVar }} === '{{ addslashes($u->name) }}' ? 'color:var(--purple)' : 'color:var(--text)'"
                onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">
                {{ $u->name }}
            </button>
        @endforeach
    </div>
</template>
