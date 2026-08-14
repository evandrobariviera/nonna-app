<x-portal-layout>
    <x-slot name="title">Início</x-slot>

    {{-- Saudação --}}
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black flex items-center gap-2" style="color: var(--text)">
                Olá, {{ $portalContact->name }} <x-icon name="hand" size="22" />
            </h1>
            <p class="text-sm mt-1" style="color: var(--muted)">
                {{ $client->company_name }} · acompanhe o andamento de tudo com a Nonna.
            </p>
        </div>
        <span class="text-xs font-semibold px-3 py-1.5 rounded-full"
              style="background: rgba(5,150,105,.1); color: var(--green)">
            {{ $client->statusLabel() }}
        </span>
    </div>

    {{-- Próxima reunião + chamados abertos + aprovações pendentes --}}
    @if($nextMeeting || $openTicketsCount > 0 || $pendingApprovalsCount > 0)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            @if($nextMeeting)
                <a href="{{ route('portal.meetings.show', $nextMeeting) }}" class="card p-5 flex items-center justify-between transition-colors" style="text-decoration:none">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Próxima Reunião</p>
                        <p class="text-sm font-bold" style="color: var(--text)">{{ $nextMeeting->title }}</p>
                        <p class="text-xs mt-0.5" style="color: var(--purple)">{{ $nextMeeting->scheduled_at->format('d/m/Y \à\s H:i') }}</p>
                    </div>
                    <span style="color: var(--muted)">→</span>
                </a>
            @else
                <div class="card p-5">
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Próxima Reunião</p>
                    <p class="text-sm" style="color: var(--muted)">Nenhuma reunião agendada.</p>
                </div>
            @endif

            <a href="{{ route('portal.tickets.index') }}" class="card p-5 flex items-center justify-between transition-colors" style="text-decoration:none">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Chamados Abertos</p>
                    <p class="text-2xl font-black" style="color: var(--text)">{{ $openTicketsCount }}</p>
                </div>
                <span style="color: var(--muted)">→</span>
            </a>

            <a href="{{ route('portal.approvals.index') }}" class="card p-5 flex items-center justify-between transition-colors" style="text-decoration:none">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color: var(--muted)">Aprovações Pendentes</p>
                    <p class="text-2xl font-black" style="color: {{ $pendingApprovalsCount > 0 ? 'var(--orange)' : 'var(--text)' }}">{{ $pendingApprovalsCount }}</p>
                </div>
                <span style="color: var(--muted)">→</span>
            </a>
        </div>
    @endif

    {{-- Bloco de métricas principais --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">

        {{-- Planejamentos --}}
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Planejamentos</p>
            <p class="text-4xl font-black leading-none mb-1" style="color: var(--text)">{{ $stats['plans_total'] }}</p>
            <div class="flex items-center gap-1.5 mt-2">
                <span class="h-2 w-2 rounded-full inline-block" style="background: var(--green)"></span>
                <span class="text-xs font-semibold" style="color: var(--green)">{{ $stats['plans_active'] }} ativo{{ $stats['plans_active'] !== 1 ? 's' : '' }}</span>
            </div>
        </div>

        {{-- Projetos --}}
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Projetos</p>
            <p class="text-4xl font-black leading-none mb-1" style="color: var(--text)">{{ $stats['projects_total'] }}</p>
            <div class="flex items-center gap-1.5 mt-2">
                <span class="h-2 w-2 rounded-full inline-block" style="background: var(--purple)"></span>
                <span class="text-xs font-semibold" style="color: var(--purple)">{{ $stats['projects_active'] }} ativo{{ $stats['projects_active'] !== 1 ? 's' : '' }}</span>
            </div>
        </div>

        {{-- Tarefas concluídas --}}
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Concluídas</p>
            <p class="text-4xl font-black leading-none mb-1" style="color: var(--text)">{{ $stats['tasks_done'] }}</p>
            <div class="flex items-center gap-1.5 mt-2">
                <span class="text-xs" style="color: var(--muted)">de {{ $stats['tasks_total'] }} tarefas</span>
            </div>
            @if($stats['tasks_total'] > 0)
                <div class="h-1 rounded-full mt-3" style="background: var(--s3)">
                    <div class="h-1 rounded-full" style="background: var(--green); width: {{ round($stats['tasks_done'] / $stats['tasks_total'] * 100) }}%"></div>
                </div>
            @endif
        </div>

        {{-- Em andamento --}}
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Em Andamento</p>
            <p class="text-4xl font-black leading-none mb-1" style="color: var(--text)">{{ $stats['tasks_progress'] }}</p>
            <div class="flex items-center gap-1.5 mt-2">
                <span class="h-2 w-2 rounded-full inline-block" style="background: var(--orange)"></span>
                <span class="text-xs font-semibold" style="color: var(--orange)">em produção</span>
            </div>
        </div>

    </div>

    {{-- Planejamento ativo --}}
    @if($activePlan)
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">Planejamento Ativo</h2>
                <a href="{{ route('portal.projects.show', $activePlan) }}"
                   class="text-xs font-semibold" style="color: var(--purple)">
                    Ver detalhes →
                </a>
            </div>

            <div class="card p-6">
                {{-- Header do plano --}}
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <p class="text-lg font-black" style="color: var(--text)">{{ $activePlan->title }}</p>
                        <p class="text-xs mt-0.5" style="color: var(--muted)">
                            {{ $activePlan->period_start?->format('d/m/Y') }} —
                            {{ $activePlan->period_end?->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-black" style="color: var(--purple)">{{ $activePlanStats['progress'] }}%</p>
                        <p class="text-xs" style="color: var(--muted)">{{ $activePlanStats['done'] }}/{{ $activePlanStats['total'] }} tarefas</p>
                    </div>
                </div>

                {{-- Barra de progresso geral --}}
                <div class="h-2 rounded-full mb-6" style="background: var(--s3)">
                    <div class="h-2 rounded-full transition-all"
                         style="background: var(--grad); width: {{ $activePlanStats['progress'] }}%"></div>
                </div>

                {{-- Grid de projetos --}}
                @if($activePlan->projects->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($activePlan->projects as $project)
                            @php
                                $done  = $project->tasks->where('status', 'concluido')->count();
                                $total = $project->tasks->count();
                                $pct   = $total > 0 ? round($done / $total * 100) : 0;
                                $isActive = $project->status === 'em_execucao';
                            @endphp
                            <div class="rounded-xl p-4" style="background: var(--s2); border: 1px solid var(--border2)">
                                <div class="flex items-start justify-between mb-2">
                                    <p class="text-sm font-bold leading-tight pr-2" style="color: var(--text)">{{ $project->title }}</p>
                                    <span class="text-xs font-black flex-shrink-0" style="color: var(--purple)">{{ $pct }}%</span>
                                </div>
                                <div class="flex items-center justify-between text-xs mb-2.5" style="color: var(--muted)">
                                    <span>{{ $done }}/{{ $total }} tarefas</span>
                                    @if($isActive)
                                        <span class="font-semibold" style="color: var(--green)">● ativo</span>
                                    @endif
                                </div>
                                <div class="h-1.5 rounded-full" style="background: var(--s3)">
                                    <div class="h-1.5 rounded-full" style="background: var(--grad); width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-center py-4" style="color: var(--muted)">Nenhum projeto neste planejamento ainda.</p>
                @endif
            </div>
        </div>
    @else
        <div class="card p-10 text-center">
            <p class="mb-2 flex justify-center" style="color:var(--muted)"><x-icon name="clipboard-list" size="28" /></p>
            <p class="text-sm font-semibold" style="color: var(--text)">Nenhum planejamento ativo</p>
            <p class="text-xs mt-1" style="color: var(--muted)">Em breve seu próximo ciclo estratégico aparecerá aqui.</p>
        </div>
    @endif

</x-portal-layout>
