{{-- Versão mobile (md:hidden) de _task-group-tbody: mesmo cabeçalho de grupo
     colapsável, mas renderiza cards (_task-card) em vez de <tr>. A lógica de
     rótulo/cor/iniciais é a mesma de _task-group-tbody — mantida em sincronia.
     Espera: $groupBy, $groupKey, $groupTasks, $activeSprint, $sprints --}}
@php
    $overdueCount = $groupTasks->filter(fn ($t) => $t->isOverdue())->count();

    if ($groupBy === 'cliente') {
        $groupLabel = $groupTasks->first()->client?->displayName() ?? 'Sem cliente';
        $groupColor = 'var(--grad)';
        $groupSubLabel = null;
    } elseif ($groupBy === 'status') {
        $statusInfo = \App\Models\Task::$statuses[$groupKey] ?? null;
        $groupLabel = $statusInfo ? $statusInfo['label'] : $groupKey;
        $groupColor = \App\Models\Task::colorHex($statusInfo['color'] ?? '');
        $groupSubLabel = null;
    } elseif ($groupBy === 'situacao') {
        $groupLabel = \App\Models\Task::$situations[$groupKey] ?? ($groupKey ?: '—');
        $groupColor = \App\Models\Task::$situationColors[$groupKey] ?? '#94a3b8';
        $groupSubLabel = null;
    } else {
        $parts = explode('|', $groupKey, 2);
        $groupLabel = $parts[1] ?? $groupKey;
        $groupColor = 'var(--purple)';
        $groupSubLabel = $groupBy === 'executor' ? 'Executor' : 'Responsável';
    }
    $groupInitials = strtoupper(substr($groupLabel, 0, 2));
    $groupStoreKey = addslashes($groupBy . '::' . $groupKey);
@endphp

<div x-data="{
        get groupOpen() { return $store.groupCollapse.isOpen('{{ $groupStoreKey }}', {{ ($defaultOpen ?? true) ? 'true' : 'false' }}) },
        set groupOpen(v) { $store.groupCollapse.setOpen('{{ $groupStoreKey }}', v) }
    }">
    <button @click="groupOpen = !groupOpen" type="button"
            class="flex items-center gap-2.5 w-full text-left px-4 py-2.5"
            style="background:var(--s2); border-bottom:1px solid var(--border2)">
        <x-icon name="chevron-right" size="12" stroke="2.5"
             class="flex-shrink-0 transition-transform duration-200" x-bind:class="groupOpen ? 'rotate-90' : ''"
             style="color:var(--muted)" />
        <span class="flex h-5 w-5 items-center justify-center rounded-full text-white flex-shrink-0"
              style="background:{{ $groupColor }}; font-size:9px; font-weight:700">{{ $groupInitials }}</span>
        <span class="text-sm font-semibold min-w-0 truncate" style="color:var(--text)">{{ $groupLabel }}</span>
        @if($groupSubLabel)
            <span class="text-xs flex-shrink-0" style="color:var(--muted)">· {{ $groupSubLabel }}</span>
        @endif
        <span class="badge flex-shrink-0 ml-auto" style="background:rgba(100,59,142,.08); border-color:rgba(100,59,142,.2); color:var(--purple)">
            {{ $groupTasks->count() }}
        </span>
        @if($overdueCount > 0)
            <span class="badge flex-shrink-0" style="background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.2); color:var(--red)">
                {{ $overdueCount }} atras.
            </span>
        @endif
    </button>

    <div x-show="groupOpen">
        @foreach($groupTasks as $task)
            @include('partials._task-card', ['task' => $task, 'context' => 'fila'])
        @endforeach
    </div>
</div>
