{{-- Avatar clicável que abre o menu único compartilhado pra trocar Responsável/Executor
     direto da lista (mesmo padrão "Monday fill" de _status-fill/_situacao-fill, só que
     preenche com foto em vez de texto). Espera no escopo: $task, $role
     ('responsavel'|'executor'). Sempre aplica via PATCH em background através de
     $store.personFill (ver resources/js/monday-fill.js) — precisa de um
     <x-person-fill-menu :users="$users" /> em algum lugar da página, fora da área
     trocada por live-filter. Esse componente não é usado na Central de Aprovações,
     não precisa de opt-in como os outros dois.
     Precisa de um ancestral com x-data contendo respName/respAvatarUrl/respInitials/
     execName/execAvatarUrl/execInitials (ver _task-tr.blade.php/_fila-task-tr.blade.php,
     que também escutam os eventos person-fill-applied/badge-fill-applied pra atualizar
     esses valores depois de um PATCH bem-sucedido) e de um container (<td>) com
     position:relative + tamanho definido. --}}
@php
    $isResp    = $role === 'responsavel';
    $personUrl = $isResp ? route('tasks.update-responsavel', $task) : route('tasks.update-executor', $task);
    $fieldName = $isResp ? 'responsavel_id' : 'executor_id';
    $color     = $isResp ? 'var(--orange)' : 'var(--purple)';
    $nameVar   = $isResp ? 'respName' : 'execName';
    $avatarVar = $isResp ? 'respAvatarUrl' : 'execAvatarUrl';
    $initialsVar = $isResp ? 'respInitials' : 'execInitials';
@endphp
<button type="button"
        @click="$store.personFill.openFor($el, { role: '{{ $role }}', fieldName: '{{ $fieldName }}', taskId: '{{ $task->id }}', url: '{{ $personUrl }}', currentName: {{ $nameVar }} })"
        style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:none; border:none; cursor:pointer; padding:0">
    <img x-show="{{ $nameVar }} && {{ $avatarVar }}" x-cloak :src="{{ $avatarVar }}" :title="{{ $nameVar }}"
         class="rounded-full object-cover flex-shrink-0" style="height:28px; width:28px">
    <div x-show="{{ $nameVar }} && !{{ $avatarVar }}" x-cloak x-text="{{ $initialsVar }}" :title="{{ $nameVar }}"
         class="rounded-full flex items-center justify-center font-black text-white flex-shrink-0"
         style="height:28px; width:28px; font-size:12px" :style="'background:{{ $color }}'"></div>
    <span class="text-xs" x-show="!{{ $nameVar }}" style="color:var(--muted)">—</span>
</button>
