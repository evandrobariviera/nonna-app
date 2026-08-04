<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
        $firstName = explode(' ', Auth::user()->name)[0];
    @endphp

    {{-- ── LINHA 1: boas-vindas + sprint (principal) | pendências de cadastro (cardo) ── --}}
    <div class="grid gap-4 mb-6" style="grid-template-columns: 7fr 3fr; align-items: start">

        {{-- Coluna 1 --}}
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-xl font-black" style="color:var(--text)">{{ $greeting }}, {{ $firstName }} 👋</h1>
                <p class="text-sm mt-1" style="color:var(--muted)">Aqui está o resumo do que precisa da sua atenção hoje.</p>
            </div>

            <div class="card px-5 py-4">
                @if($activeSprint)
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-bold" style="color:var(--text)">
                            🏃 Sprint atual · {{ $activeSprint->title }}
                        </span>
                        <a href="{{ route('sprints.show', $activeSprint) }}" class="text-xs font-mono" style="color:var(--purple)">
                            Ver Sprint →
                        </a>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <div class="w-full h-2 rounded-full overflow-hidden" style="background:var(--border2)">
                                <div class="h-2 rounded-full transition-all"
                                     style="width:{{ $sprintProgress }}%; background:{{ $sprintProgress >= 100 ? 'var(--green)' : 'var(--grad)' }}"></div>
                            </div>
                        </div>
                        <span class="text-sm font-black flex-shrink-0" style="color:var(--text)">{{ $sprintProgress }}%</span>
                        <span class="text-xs font-mono flex-shrink-0" style="color:var(--muted)">{{ $sprintDone }} / {{ $sprintTotal }} concluídas</span>
                    </div>
                @else
                    <p class="text-sm" style="color:var(--muted)">🏃 Nenhuma sprint ativa no momento.</p>
                @endif
            </div>
        </div>

        {{-- Coluna 2: Pendências de Cadastro — só o número, sem listagem --}}
        <a href="{{ route('fila.index', ['pendencia' => 1]) }}"
           class="card px-5 py-4 flex flex-col items-center justify-center text-center transition-colors"
           style="{{ $pendingTasksCount > 0 ? 'border-color:rgba(239,68,68,.3)' : '' }}"
           onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
            <span class="text-3xl font-black" style="color:{{ $pendingTasksCount > 0 ? 'var(--red)' : 'var(--text)' }}">
                {{ $pendingTasksCount }}
            </span>
            <span class="text-xs font-mono mt-1" style="color:var(--muted)">
                ⚠️ {{ $pendingTasksCount === 1 ? 'tarefa com cadastro incompleto' : 'tarefas com cadastro incompleto' }}
            </span>
        </a>
    </div>

    {{-- ── LINHA 1.5: Agenda — quadro por status (para_agendar/agendada/pos_reuniao) ──
         Estático (sem drag-and-drop) — mudar status é só na própria página da reunião. --}}
    @php
        $agendaStatuses = ['para_agendar', 'agendada', 'pos_reuniao'];
    @endphp
    <div class="mb-6" x-data="{ filterType: '' }">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold" style="color:var(--text)">📅 Agenda</h3>
            <select x-model="filterType"
                class="px-3 py-1.5 text-xs font-mono focus:outline-none"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2)">
                <option value="">Todos os tipos</option>
                @foreach(\App\Models\Meeting::$types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-4" style="grid-template-columns: repeat(3, 1fr)">
            @foreach($agendaStatuses as $status)
                @php $statusMeetings = $myMeetingsByStatus->get($status, collect()); @endphp
                <div class="card px-5 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-bold" style="color:var(--text)">
                            {{ \App\Models\Meeting::$statuses[$status]['label'] }} (<span>{{ $statusMeetings->count() }}</span>)
                        </h4>
                        <a href="{{ route('meetings.index', ['status' => $status]) }}"
                           class="text-xs font-mono" style="color:var(--purple)">Ver agenda</a>
                    </div>
                    <div class="flex flex-col gap-2" style="min-height:40px">
                        @forelse($statusMeetings as $meeting)
                            @php $isToday = $meeting->scheduled_at->isToday(); @endphp
                            <a href="{{ route('meetings.show', $meeting) }}"
                               x-show="!filterType || filterType === '{{ $meeting->type }}'"
                               class="block px-3 py-2 transition-colors"
                               style="background:{{ $isToday ? 'rgba(52,211,153,.10)' : 'var(--s2)' }}; {{ $isToday ? 'border-left:2px solid var(--green)' : '' }}"
                               onmouseover="this.style.background='var(--s3)'"
                               onmouseout="this.style.background='{{ $isToday ? 'rgba(52,211,153,.10)' : 'var(--s2)' }}'">
                                <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $meeting->title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->client?->displayName() ?? '—' }}</span>
                                    <span class="text-xs font-mono" style="color:var(--purple)">
                                        {{ $meeting->scheduled_at->format('d/m H:i') }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs" style="color:var(--muted)">Nada por aqui. 🎉</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── LINHA 2: camada operacional da sprint (minhas tarefas por etapa) ──
         Kanban de verdade (arrastar-e-soltar entre colunas, ver resources/js/kanban-dnd.js).
         "Pronto para Produção" não é um status próprio no modelo — é status=backlog com
         situation="Pronto para produção" — por isso carrega data-extra além de data-status. --}}
    @php
        $quadros = [
            ['emoji' => '🔧', 'label' => 'Ajuste / Alteração',   'tasks' => $myAdjustmentTasks,         'status' => 'ajuste_alteracao', 'extra' => null],
            ['emoji' => '⚙️', 'label' => 'Em Produção',           'tasks' => $myProductionTasks,         'status' => 'em_producao',      'extra' => null],
            ['emoji' => '📋', 'label' => 'Pronto para Produção',  'tasks' => $myReadyForProductionTasks, 'status' => 'backlog',           'extra' => ['situation' => 'Pronto para produção']],
        ];
    @endphp
    <div id="dashboard-board" class="grid gap-4 mb-6" style="grid-template-columns: repeat(3, 1fr)"
         data-kanban-board data-status-field="status">
        @foreach($quadros as $q)
            <div class="card px-5 py-4"
                 data-kanban-column data-status="{{ $q['status'] }}"
                 @if($q['extra']) data-extra='{{ json_encode($q['extra']) }}' @endif>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold" style="color:var(--text)">
                        {{ $q['emoji'] }} {{ $q['label'] }} (<span data-kanban-count>{{ $q['tasks']->count() }}</span>)
                    </h3>
                    <a href="{{ route('tasks.index', ['executor_id' => auth()->id(), 'status' => $q['status']]) }}"
                       class="text-xs font-mono" style="color:var(--purple)">Ver todas</a>
                </div>
                <div class="flex flex-col gap-2" style="min-height:40px" data-kanban-list>
                    @forelse($q['tasks'] as $task)
                        @php
                            $statusUrl = $task->is_ticket ? route('tickets.update-status', $task) : route('tasks.update-status-direct', $task);
                        @endphp
                        <div data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $statusUrl }}"
                             class="px-3 py-2 transition-colors" style="cursor:pointer;
                                    background:var(--s2); border-left:2px solid {{ $task->isOverdue() ? 'var(--red)' : 'var(--purple)' }}"
                             onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'"
                             onclick="window.location='{{ route('tasks.show', $task) }}'">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->displayName() ?? '—' }}</span>
                                @if($task->due_date)
                                    <span class="text-xs font-mono" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                        {{ $task->due_date->format('d/m') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs" style="color:var(--muted)">Nada por aqui. 🎉</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('dashboard-board')) {
                initKanbanDnd('#dashboard-board');
            }
        });
    </script>
    @endpush

    {{-- ── SEÇÕES POR FUNÇÃO ── --}}
    {{-- Administrador vê todas as funções, não só as que tem atribuídas — mesma regra já usada em /visoes/{role}. --}}
    @php
        $dashboardRoles = ($isOrgAdmin ?? false)
            ? array_keys(\App\Models\OrganizationUser::$functionRoles)
            : ($userFunctionRoles ?? []);
        // Direção Criativa e Head de Tecnologia compartilham uma única seção
        // "Heads" — não faz sentido separar por setor pro que eles precisam ver aqui.
        $headsRoles = ['head_criativa', 'head_tech'];
        $showHeadsSection = !empty(array_intersect($dashboardRoles, $headsRoles));
    @endphp
    @if(!empty($dashboardRoles))
        <div class="flex flex-col gap-6">
            @foreach($dashboardRoles as $role)
                @continue(in_array($role, $headsRoles))
                @if(isset(\App\Models\OrganizationUser::$functionRoles[$role]))
                    <div>
                        <h2 class="text-base font-bold mb-3" style="color:var(--text)">
                            {{ \App\Models\OrganizationUser::$functionRoles[$role] }}
                        </h2>
                        @if($role === 'estrategia')
                            @include('dashboard.sections.estrategia')
                        @elseif($role === 'atendimento')
                            @include('dashboard.sections.atendimento')
                        @else
                            <div class="card px-5 py-4">
                                <p class="text-xs" style="color:var(--muted)">
                                    Painel de <strong>{{ \App\Models\OrganizationUser::$functionRoles[$role] }}</strong> em construção — em breve.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach

            @if($showHeadsSection)
                <div>
                    <h2 class="text-base font-bold mb-3" style="color:var(--text)">Heads</h2>
                    @include('dashboard.sections.heads')
                </div>
            @endif
        </div>
    @endif

</x-app-layout>
