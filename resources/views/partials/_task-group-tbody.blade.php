{{-- Grupo de tarefas (cabeçalho colapsável + linhas) — usado em Filas e na aba Lista da Sprint.
     Espera: $groupBy, $groupKey, $groupTasks, $activeSprint, $sprints --}}
@php
    $overdueCount = $groupTasks->filter(fn($t) => $t->isOverdue())->count();

    if ($groupBy === 'cliente') {
        $firstTask = $groupTasks->first();
        $groupLabel = $firstTask->client?->displayName() ?? 'Sem cliente';
        $groupInitials = strtoupper(substr($groupLabel, 0, 2));
        $groupSubLabel = null;
        $groupColor = 'var(--grad)';
        $groupTextColor = '#fff';
    } elseif ($groupBy === 'status') {
        $statusInfo = \App\Models\Task::$statuses[$groupKey] ?? null;
        $groupLabel = $statusInfo ? $statusInfo['label'] : $groupKey;
        $groupInitials = strtoupper(substr($groupLabel, 0, 2));
        $groupColor = \App\Models\Task::colorHex($statusInfo['color'] ?? '');
        $groupTextColor = '#fff';
        $groupSubLabel = null;
    } elseif ($groupBy === 'situacao') {
        $groupLabel = \App\Models\Task::$situations[$groupKey] ?? ($groupKey ?: '—');
        $groupInitials = strtoupper(substr($groupLabel, 0, 2));
        $groupColor = \App\Models\Task::$situationColors[$groupKey] ?? '#94a3b8';
        $groupTextColor = '#fff';
        $groupSubLabel = null;
    } else {
        // executor ou responsavel: key = "id|nome" ou "__xxx__|Sem ..."
        $parts = explode('|', $groupKey, 2);
        $groupLabel = $parts[1] ?? $groupKey;
        $groupInitials = strtoupper(substr($groupLabel, 0, 2));
        $groupColor = 'var(--purple)';
        $groupTextColor = '#fff';
        $groupSubLabel = $groupBy === 'executor' ? 'Executor' : 'Responsável';
    }

    // Chave estável do grupo (independe da ordem/posição do <tbody> na tabela) usada pra
    // lembrar se o usuário abriu/fechou esse grupo através de um refresh de live-filter —
    // ver resources/js/group-collapse.js.
    $groupStoreKey = addslashes($groupBy . '::' . $groupKey);
@endphp

<tbody x-data="{
        get groupOpen() { return $store.groupCollapse.isOpen('{{ $groupStoreKey }}', {{ ($defaultOpen ?? true) ? 'true' : 'false' }}) },
        set groupOpen(v) { $store.groupCollapse.setOpen('{{ $groupStoreKey }}', v) }
    }">
    {{-- Cabeçalho do grupo --}}
    <tr class="group-header-row" style="background:var(--s2)">
        <td colspan="12" style="padding:10px 16px">
            <button @click="groupOpen = !groupOpen"
                    type="button"
                    class="flex items-center gap-3 w-full text-left">

                <x-icon name="chevron-right" size="12" stroke="2.5" class="flex-shrink-0 transition-transform duration-200"
                     :class="groupOpen ? 'rotate-90' : ''"
                     style="color:var(--muted)" />

                <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                     style="background:{{ $groupColor }}; color:{{ $groupTextColor }}; font-size:9px; font-weight:700">
                    {{ $groupInitials }}
                </div>

                <span class="text-sm font-semibold" style="color:var(--text)">{{ $groupLabel }}</span>

                @if($groupSubLabel)
                    <span class="text-xs" style="color:var(--muted)">· {{ $groupSubLabel }}</span>
                @endif

                <span class="badge" style="background:rgba(100, 59, 142,.08); border-color:rgba(100, 59, 142,.2); color:var(--purple); font-size:11px">
                    {{ $groupTasks->count() }} tarefa{{ $groupTasks->count() !== 1 ? 's' : '' }}
                </span>

                @if($overdueCount > 0)
                    <span class="badge" style="background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.2); color:var(--red); font-size:11px">
                        {{ $overdueCount }} atrasada{{ $overdueCount > 1 ? 's' : '' }}
                    </span>
                @endif
            </button>
        </td>
    </tr>

    {{-- Linhas de tarefas --}}
    @foreach($groupTasks as $task)
        @include('partials._fila-task-tr', ['task' => $task, 'activeSprint' => $activeSprint, 'sprints' => $sprints])
    @endforeach
</tbody>
