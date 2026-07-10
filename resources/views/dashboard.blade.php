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

    {{-- ── LINHA 2: camada operacional da sprint (minhas tarefas por etapa) ── --}}
    @php
        $quadros = [
            ['emoji' => '🔧', 'label' => 'Ajuste / Alteração',   'tasks' => $myAdjustmentTasks,         'status' => 'ajuste_alteracao'],
            ['emoji' => '⚙️', 'label' => 'Em Produção',           'tasks' => $myProductionTasks,         'status' => 'em_producao'],
            ['emoji' => '📋', 'label' => 'Pronto para Produção',  'tasks' => $myReadyForProductionTasks, 'status' => 'backlog'],
        ];
    @endphp
    <div class="grid gap-4 mb-6" style="grid-template-columns: repeat(3, 1fr)">
        @foreach($quadros as $q)
            <div class="card px-5 py-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold" style="color:var(--text)">
                        {{ $q['emoji'] }} {{ $q['label'] }} ({{ $q['tasks']->count() }})
                    </h3>
                    <a href="{{ route('tasks.index', ['executor_id' => auth()->id(), 'status' => $q['status']]) }}"
                       class="text-xs font-mono" style="color:var(--purple)">Ver todas</a>
                </div>
                @if($q['tasks']->isEmpty())
                    <p class="text-xs" style="color:var(--muted)">Nada por aqui. 🎉</p>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach($q['tasks'] as $task)
                            <a href="{{ route('tasks.show', $task) }}" class="block px-3 py-2 transition-colors"
                               style="background:var(--s2); border-left:2px solid {{ $task->isOverdue() ? 'var(--red)' : 'var(--purple)' }}"
                               onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                                <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ $task->client?->company_name ?? '—' }}</span>
                                    @if($task->due_date)
                                        <span class="text-xs font-mono" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            {{ $task->due_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ── SEÇÕES POR FUNÇÃO ── --}}
    {{-- Administrador vê todas as funções, não só as que tem atribuídas — mesma regra já usada em /visoes/{role}. --}}
    @php
        $dashboardRoles = ($isOrgAdmin ?? false)
            ? array_keys(\App\Models\OrganizationUser::$functionRoles)
            : ($userFunctionRoles ?? []);
    @endphp
    @if(!empty($dashboardRoles))
        <div class="flex flex-col gap-2">
            @foreach($dashboardRoles as $role)
                @if(isset(\App\Models\OrganizationUser::$functionRoles[$role]))
                    <div class="card" x-data="{ open: false }">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center gap-2 px-5 py-3 text-left transition-colors"
                            style="color:var(--text)">
                            <span :class="open ? 'rotate-90' : ''" class="transition-transform text-xs" style="color:var(--muted)">▶</span>
                            <span class="text-sm font-bold">
                                {{ \App\Models\OrganizationUser::$functionRoles[$role] }}
                            </span>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5" style="border-top:1px solid var(--border2)">
                            <p class="text-xs mt-4" style="color:var(--muted)">
                                Painel de <strong>{{ \App\Models\OrganizationUser::$functionRoles[$role] }}</strong> em construção — em breve.
                            </p>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</x-app-layout>
