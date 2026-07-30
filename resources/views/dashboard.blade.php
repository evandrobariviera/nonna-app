<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
        $firstName = explode(' ', Auth::user()->name)[0];
    @endphp

    {{-- ── LINHA 1: boas-vindas + sprint (70%) | agenda (30%) ── --}}
    <div class="grid gap-4 mb-6" style="grid-template-columns: 7fr 3fr; align-items: stretch">

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

        {{-- Coluna 2: Agenda --}}
        <div class="card px-5 py-4 flex flex-col" style="height:100%">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold" style="color:var(--text)">
                    📅 Agenda de Hoje ({{ $myMeetings->count() }})
                </h3>
                <a href="{{ route('meetings.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver agenda</a>
            </div>
            @if($myMeetings->isEmpty())
                <p class="text-xs" style="color:var(--muted)">Nenhum compromisso agendado pra hoje.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($myMeetings as $meeting)
                        <a href="{{ route('meetings.show', $meeting) }}" class="block px-3 py-2 transition-colors"
                           style="background:var(--s2)"
                           onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $meeting->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->client?->company_name ?? '—' }}</span>
                                <span class="text-xs font-mono" style="color:var(--purple)">
                                    {{ $meeting->scheduled_at->format('H:i') }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ── Pendências de cadastro (transversal, sempre visível independente do papel) ── --}}
    <div class="card px-5 py-4 mb-6">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            ⚠️ Pendências de Cadastro ({{ $pendingTasksCount }})
        </h4>
        @if($pendingTasksSample->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma pendência de cadastro no momento. 🎉</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($pendingTasksSample as $task)
                    <a href="{{ route('tasks.show', $task) }}"
                       class="block px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--red)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <p class="text-xs font-semibold" style="color:var(--text)">{{ $task->title }}</p>
                        <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->company_name ?? '—' }}</span>
                    </a>
                @endforeach
            </div>
            <a href="{{ route('fila.index', ['pendencia' => 1]) }}" class="block mt-3 text-xs font-mono" style="color:var(--purple)">
                Ver todas na Fila →
            </a>
        @endif
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
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->company_name ?? '—' }}</span>
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
