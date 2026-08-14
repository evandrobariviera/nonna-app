<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
        $firstName = explode(' ', Auth::user()->name)[0];

        // Administrador vê todas as funções, não só as que tem atribuídas — mesma
        // regra já usada em /visoes/{role}. Calculado aqui em cima porque a seção
        // "Atendimento" é destacada logo abaixo da Agenda, fora do loop por função
        // mais abaixo — precisa saber se o usuário tem o papel antes disso.
        $orgFunctionalRoles = \App\Models\FunctionalRole::where('organization_id', $currentOrg?->id)
            ->orderBy('name')
            ->get();
        $dashboardRoles = ($isOrgAdmin ?? false)
            ? $orgFunctionalRoles->pluck('key')->all()
            : ($userFunctionRoles ?? []);
        // Direção Criativa e Head de Tecnologia compartilham uma única seção
        // "Heads"; Atendimento vira uma seção destacada — nenhum dos dois passa
        // pelo loop genérico por função mais abaixo.
        $headsRoles = ['head_criativa', 'head_tech'];
        $showHeadsSection = !empty(array_intersect($dashboardRoles, $headsRoles));
        $showAtendimentoSection = in_array('atendimento', $dashboardRoles);
        $showTrafegoSection = in_array('trafego', $dashboardRoles);
        $functionRoleLabels = $orgFunctionalRoles->pluck('name', 'key');
    @endphp

    {{-- ── LINHA 1: boas-vindas (full width) + sprint (principal) | pendências de cadastro (cardo) ── --}}
    <div class="mb-4">
        <h1 class="text-xl font-black flex items-center gap-2" style="color:var(--text)">{{ $greeting }}, {{ $firstName }} <x-icon name="hand" size="20" /></h1>
        <p class="text-sm mt-1" style="color:var(--muted)">Aqui está o resumo do que precisa da sua atenção hoje.</p>
    </div>

    {{-- ── CITAÇÃO LITERÁRIA DO DIA — fixa pra Organização inteira o dia todo
         (ver DashboardController::index(), rotação determinística pelo acervo
         literary_quotes). Ideia: estimular cultura/leitura na agência, com o
         trecho sempre justificado (autor, obra, por que faz sentido). ── --}}
    @if($literaryQuote)
        <div class="card px-6 py-5 mb-4" style="background:linear-gradient(135deg, rgba(100, 59, 142,.05), rgba(238, 121, 25,.03)); border:1px solid rgba(100, 59, 142,.18)">
            <div class="flex items-start gap-4">
                <span class="text-3xl flex-shrink-0 leading-none select-none" style="color:var(--purple); opacity:.35">❝</span>
                <div class="min-w-0">
                    <p class="text-sm italic" style="color:var(--text); line-height:1.7">{{ $literaryQuote->excerpt }}</p>
                    <p class="text-xs font-semibold mt-2.5" style="color:var(--purple)">
                        — {{ $literaryQuote->author }}, <span style="font-style:italic">{{ $literaryQuote->book }}</span>
                    </p>
                    <p class="text-xs mt-2 flex items-start gap-1.5" style="color:var(--muted2); line-height:1.6">
                        <x-icon name="book-open" size="13" class="flex-shrink-0" style="margin-top:2px" />
                        {{ $literaryQuote->justification }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 mb-6" style="grid-template-columns: 7fr 3fr; align-items: stretch">

        {{-- Coluna 1: Sprint --}}
        <div class="card px-5 py-4">
            @if($activeSprint)
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                        <x-icon name="activity" size="14" />
                        Sprint atual · {{ $activeSprint->title }}
                    </span>
                    <a href="{{ route('sprints.show', $activeSprint) }}" class="text-xs font-mono" style="color:var(--purple)">
                        Ver Sprint →
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <x-status-distribution-bar :counts="$sprintByStatus" :total="$sprintTotal" class="rounded-full" />
                    </div>
                    <span class="text-sm font-black flex-shrink-0" style="color:var(--text)">{{ $sprintProgress }}%</span>
                    <span class="text-xs font-mono flex-shrink-0" style="color:var(--muted)">{{ $sprintDone }} / {{ $sprintTotal }} concluídas</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2">
                    @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
                        @continue($statusKey === 'cancelado')
                        @php $cnt = $sprintByStatus[$statusKey] ?? 0; @endphp
                        @if($cnt > 0)
                            <span class="flex items-center gap-1 text-xs font-mono" style="color:var(--muted)">
                                <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--{{ $meta['color'] === 'muted' ? 'muted' : $meta['color'] }})"></span>
                                {{ $meta['label'] }} {{ $cnt }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="text-sm flex items-center gap-1.5" style="color:var(--muted)">
                    <x-icon name="activity" size="14" />
                    Nenhuma sprint ativa no momento.
                </p>
            @endif
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
            <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                <x-icon name="calendar" size="15" />
                Agenda
            </h3>
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
                               class="flex items-center gap-2.5 px-3 py-2 transition-colors"
                               style="background:{{ $isToday ? 'rgba(52,211,153,.10)' : 'var(--s2)' }}; {{ $isToday ? 'border-left:2px solid var(--green)' : '' }}"
                               onmouseover="this.style.background='var(--s3)'"
                               onmouseout="this.style.background='{{ $isToday ? 'rgba(52,211,153,.10)' : 'var(--s2)' }}'">
                                <x-icon-chip :icon="$meeting->typeIcon()" :color="$meeting->statusColor()" size="32" />
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $meeting->title }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->client?->displayName() ?? '—' }}</span>
                                        <span class="text-xs font-mono" style="color:var(--purple)">
                                            {{ $meeting->scheduled_at->format('d/m H:i') }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                            <x-icon name="party-popper" size="13" />
                            Nada por aqui.
                        </p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Atendimento (seção destacada logo abaixo da Agenda) ── --}}
    @if($showAtendimentoSection)
        <div class="mb-6">
            <h2 class="text-base font-bold mb-3" style="color:var(--text)">Atendimento</h2>
            @include('dashboard.sections.atendimento')
        </div>
    @endif

    {{-- ── Heads (entre Atendimento e Operação) ── --}}
    @if($showHeadsSection)
        <div class="mb-6">
            <h2 class="text-base font-bold mb-3" style="color:var(--text)">Heads</h2>
            @include('dashboard.sections.heads')
        </div>
    @endif

    {{-- ── Mídia Paga (papel Tráfego) ── --}}
    @if($showTrafegoSection)
        <div class="mb-6">
            <h2 class="text-base font-bold mb-3 flex items-center gap-2" style="color:var(--text)">
                <x-icon name="megaphone" size="17" />
                Mídia Paga
            </h2>
            @include('dashboard.sections.midia-paga')
        </div>
    @endif

    {{-- ── LINHA 2: "Operação" — camada operacional da sprint (minhas tarefas por etapa) ──
         Kanban de verdade (arrastar-e-soltar entre colunas, ver resources/js/kanban-dnd.js).
         "Pronto para Produção" não é um status próprio no modelo — é status=backlog com
         situation="Pronto para produção" — por isso carrega data-extra além de data-status. --}}
    <h2 class="text-base font-bold mb-3" style="color:var(--text)">Operação</h2>

    {{-- MEUS NÚMEROS NA SPRINT — recorte pessoal (executor) dentro da sprint ativa,
         antes dos 3 quadros abaixo. Cores reaproveitam a paleta de status já usada
         em badges/dropdowns em todo o App (Task::colorHex), sem inventar cor nova. --}}
    @if($activeSprint)
        <div class="card px-5 py-4 mb-4">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <span class="text-sm font-bold flex items-center gap-2" style="color:var(--text)">
                    <x-icon name="bar-chart-3" size="15" />
                    Meus Números na Sprint
                    <span class="text-xs font-mono font-normal" style="color:var(--muted)">— como Executor</span>
                </span>
                <span class="text-xs font-mono" style="color:var(--muted)">{{ $activeSprint->title }}</span>
            </div>

            @if($myExecutorSprintTotal > 0)
                <div class="grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr))">
                    {{-- Tile "hero": total --}}
                    <div class="px-3 py-3 text-center" style="background:var(--s2); border-top:3px solid var(--purple)">
                        <p class="text-2xl font-black" style="color:var(--text)">{{ $myExecutorSprintTotal }}</p>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">Total na Sprint</p>
                    </div>
                    @foreach($myExecutorSprintByStatus as $row)
                        <div class="px-3 py-3 text-center" style="background:var(--s2); border-top:3px solid {{ $row['hex'] }}">
                            <p class="text-2xl font-black" style="color:var(--text)">{{ $row['count'] }}</p>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">{{ $row['label'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Barra segmentada — distribuição visual das mesmas tarefas por status,
                     com gap de 2px entre segmentos (mesma cor de cada tile acima). --}}
                <div class="flex h-2.5 rounded-full overflow-hidden gap-0.5" style="background:var(--border2)">
                    @foreach($myExecutorSprintByStatus as $row)
                        <div style="width:{{ round($row['count'] / $myExecutorSprintTotal * 100, 2) }}%; background:{{ $row['hex'] }}"
                             title="{{ $row['label'] }}: {{ $row['count'] }} ({{ round($row['count'] / $myExecutorSprintTotal * 100) }}%)"></div>
                    @endforeach
                </div>
            @else
                <p class="text-sm flex items-center gap-1.5" style="color:var(--muted)">
                    <x-icon name="party-popper" size="14" />
                    Nenhuma tarefa sua como executor nessa sprint.
                </p>
            @endif
        </div>
    @endif

    @php
        $quadros = [
            ['icon' => 'wrench',         'label' => 'Ajuste / Alteração',   'tasks' => $myAdjustmentTasks,         'status' => 'ajuste_alteracao', 'extra' => null],
            ['icon' => 'settings',       'label' => 'Em Produção',           'tasks' => $myProductionTasks,         'status' => 'em_producao',      'extra' => null],
            ['icon' => 'clipboard-list', 'label' => 'Pronto para Produção',  'tasks' => $myReadyForProductionTasks, 'status' => 'backlog',           'extra' => ['situation' => 'Pronto para produção']],
        ];
    @endphp
    <div id="dashboard-board" class="grid gap-4 mb-6" style="grid-template-columns: repeat(3, 1fr)"
         data-kanban-board data-status-field="status">
        @foreach($quadros as $q)
            <div class="card px-5 py-4"
                 data-kanban-column data-status="{{ $q['status'] }}"
                 @if($q['extra']) data-extra='{{ json_encode($q['extra']) }}' @endif>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                        <x-icon :name="$q['icon']" size="14" />
                        {{ $q['label'] }} (<span data-kanban-count>{{ $q['tasks']->count() }}</span>)
                    </h3>
                    <a href="{{ route('tasks.index', ['executor_id' => auth()->id(), 'status' => $q['status']]) }}"
                       class="text-xs font-mono" style="color:var(--purple)">Ver todas</a>
                </div>
                <div class="flex flex-col gap-2" style="min-height:40px" data-kanban-list>
                    @forelse($q['tasks'] as $task)
                        @php
                            $statusUrl = $task->is_ticket ? route('tickets.update-status', $task) : route('tasks.update-status-direct', $task);
                            // Data de aprovação, não vencimento — é o prazo relevante nesta etapa.
                            $isApprovalOverdue = $task->approval_date && $task->approval_date->isPast() && $task->status !== 'concluido';
                        @endphp
                        <div data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $statusUrl }}"
                             class="flex items-center gap-2.5 px-3 py-2 transition-colors" style="cursor:pointer;
                                    background:var(--s2); {{ $isApprovalOverdue ? 'border-left:2px solid var(--red)' : '' }}"
                             onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'"
                             onclick="window.location='{{ route('tasks.show', $task) }}'">
                            <x-icon-chip :icon="$task->typeIcon()" :color="$task->statusColor()" size="32" />
                            <div class="min-w-0">
                                <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->displayName() ?? '—' }}</span>
                                    @if($task->approval_date)
                                        <span class="text-xs font-mono" style="color:{{ $isApprovalOverdue ? 'var(--red)' : 'var(--muted)' }}">
                                            {{ $task->approval_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                            <x-icon name="party-popper" size="13" />
                            Nada por aqui.
                        </p>
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
    {{-- $dashboardRoles/$headsRoles/$showHeadsSection/$showAtendimentoSection/$showTrafegoSection
         calculados lá em cima (perto da saudação) porque Atendimento, Heads e Mídia Paga já
         foram destacados antes daqui, fora deste loop. --}}
    @if(!empty($dashboardRoles))
        <div class="flex flex-col gap-6">
            @foreach($dashboardRoles as $role)
                @continue(in_array($role, $headsRoles) || $role === 'atendimento' || $role === 'trafego')
                @if($functionRoleLabels->has($role))
                    <div>
                        <h2 class="text-base font-bold mb-3" style="color:var(--text)">
                            {{ $functionRoleLabels[$role] }}
                        </h2>
                        @if($role === 'estrategia')
                            @include('dashboard.sections.estrategia')
                        @else
                            <div class="card px-5 py-4">
                                <p class="text-xs" style="color:var(--muted)">
                                    Painel de <strong>{{ $functionRoleLabels[$role] }}</strong> em construção — em breve.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</x-app-layout>
