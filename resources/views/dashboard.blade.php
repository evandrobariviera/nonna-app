<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
        // "Ver como": a tela inteira é a da outra pessoa, inclusive a saudação.
        $firstName = explode(' ', ($viewingAs ?? Auth::user())->name)[0];

        // Papéis sem painel próprio — listados só na Visão geral, no fim da página.
        // Administrador vê todas as funções (mesma regra de /visoes/{role}). Papéis/admin
        // são os de quem a Dashboard está mostrando ($subjectRoles/$subjectIsAdmin, que no
        // "Ver como" são da outra pessoa, não de quem está logado).
        $orgFunctionalRoles = \App\Models\FunctionalRole::where('organization_id', $currentOrg?->id)
            ->orderBy('name')
            ->get();
        $dashboardRoles = $subjectIsAdmin
            ? $orgFunctionalRoles->pluck('key')->all()
            : $subjectRoles;
        // Heads/Atendimento/Tráfego/Estratégia têm seção própria (ligada a um modo) —
        // ficam fora do loop genérico de papéis lá embaixo.
        $headsRoles = ['head_criativa', 'head_tech'];
        $functionRoleLabels = $orgFunctionalRoles->pluck('name', 'key');
        // Qual seção aparece agora depende do MODO (DashboardController::MODE_BLOCKS,
        // $show('bloco')), não mais direto do papel — o papel só decide quais modos a
        // pessoa tem (App\Support\DashboardModes).
        $modeMeta = \App\Support\DashboardModes::ALL[$mode];
        // Links internos da Dashboard (semana, modo) carregam o "ver como" junto.
        $dashUrl = fn (array $params = []) => route('dashboard', array_filter(array_merge(
            ['ver_como' => $viewingAs?->id, 'modo' => $viewingAs ? $mode : null], $params
        ), fn ($v) => $v !== null && $v !== ''));
    @endphp

    {{-- ── VER COMO — faixa de aviso enquanto o admin olha a Dashboard de outra pessoa.
         Só leitura: pendências e quadro ficam sem ação (ver $viewingAs mais abaixo). ── --}}
    @if($viewingAs)
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 mb-4"
             style="background:rgba(46,144,250,.08); border:1px solid rgba(46,144,250,.35); border-left:4px solid var(--blue)">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <x-icon name="eye" size="16" style="color:var(--blue)" />
                <p class="text-sm" style="color:var(--text)">
                    Vendo a Dashboard como <strong>{{ $viewingAs->name }}</strong>
                    <span class="text-xs" style="color:var(--muted)">· só leitura</span>
                </p>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach($availableModes as $modeKey)
                    @php $m = \App\Support\DashboardModes::ALL[$modeKey]; @endphp
                    <a href="{{ $dashUrl(['modo' => $modeKey]) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold"
                       style="border-radius:100px; border:1px solid {{ $modeKey === $mode ? 'rgba(100,59,142,.4)' : 'var(--border2)' }}; background:{{ $modeKey === $mode ? 'rgba(100,59,142,.12)' : 'var(--s1)' }}; color:{{ $modeKey === $mode ? 'var(--purple)' : 'var(--muted2)' }}">
                        <x-icon :name="$m['icon']" size="12" /> {{ $m['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-xs ml-1">Sair</a>
            </div>
        </div>
    @endif

    {{-- ── LINHA 1: boas-vindas (full width) + sprint (principal) | pendências de cadastro (cardo) ── --}}
    <div class="mb-4 flex flex-col sm:flex-row sm:items-start gap-3">
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-black flex items-center gap-2" style="color:var(--text)">{{ $greeting }}, {{ $firstName }} <x-icon name="hand" size="20" /></h1>
            <p class="text-sm mt-1 flex items-center gap-1.5 flex-wrap" style="color:var(--muted)">
                @if(count($availableModes) > 1)
                    <span class="inline-flex items-center gap-1 font-semibold" style="color:var(--purple)">
                        <x-icon :name="$modeMeta['icon']" size="14" /> Modo {{ $modeMeta['label'] }}
                    </span>
                    <span>· {{ $modeMeta['hint'] }}.@unless($viewingAs) Troque de modo no seletor lá em cima.@endunless</span>
                @else
                    Aqui está o resumo do que precisa da sua atenção hoje.
                @endif
            </p>
        </div>

        {{-- Ver como (só admin/dono) — escolher alguém abre a Dashboard dessa pessoa. --}}
        @if($teamMembers->isNotEmpty())
            <label class="flex items-center gap-2 flex-shrink-0">
                <span class="text-xs font-semibold flex items-center gap-1" style="color:var(--muted)"><x-icon name="eye" size="13" /> Ver como</span>
                <select onchange="window.location = this.value"
                        class="px-3 py-1.5 text-xs focus:outline-none"
                        style="background:var(--s2); border:1px solid var(--border2); color:var(--text); max-width:220px">
                    <option value="{{ route('dashboard') }}">Eu mesmo</option>
                    @foreach($teamMembers as $member)
                        <option value="{{ route('dashboard', ['ver_como' => $member->id]) }}" @selected($viewingAs?->id === $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
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

    @if($show('sprint') || $show('cadastro'))
    <div class="grid gap-4 mb-6 md:grid-cols-[7fr_3fr] items-stretch">

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
                    @foreach(array_reverse(\App\Models\Task::$statuses, true) as $statusKey => $meta)
                        @continue($statusKey === 'cancelado')
                        @php $cnt = $sprintByStatus[$statusKey] ?? 0; @endphp
                        @if($cnt > 0)
                            <span class="flex items-center gap-1 text-xs font-mono" style="color:var(--muted)">
                                <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--{{ $meta['color'] === 'muted' ? 'muted' : $meta['color'] }})"></span>
                                {{ $meta['label'] }} {{ $sprintTotal > 0 ? round($cnt / $sprintTotal * 100) : 0 }}%
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
    @endif

    {{-- ── PENDÊNCIAS PESSOAIS — só as SUAS notificações (não a fila do papel, como
         Mídia Paga abaixo). Some completamente sem nada pendente (ver
         DashboardController::index(), filtra status=novo) — de propósito chamativa,
         largura cheia, pra não passar batido igual um card discreto a mais. ── --}}
    @if($myNotifications->isNotEmpty())
        <div class="mb-6 flex flex-col gap-3">
            @foreach($myNotifications as $notification)
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 px-5 py-4"
                     style="background:rgba(238, 121, 25,.06); border:1px solid rgba(238, 121, 25,.3); border-left:4px solid var(--orange)">
                    <x-icon-chip :icon="$notification->kindIcon()" color="orange" size="40" />
                    @if($notification->link)
                        <a href="{{ $notification->link }}" class="flex-1 min-w-0">
                            <p class="text-sm font-bold" style="color:var(--text)">{{ $notification->title }}</p>
                            @if($notification->body)
                                <p class="text-xs mt-0.5" style="color:var(--muted2)">{{ $notification->body }}</p>
                            @endif
                        </a>
                    @else
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold" style="color:var(--text)">{{ $notification->title }}</p>
                            @if($notification->body)
                                <p class="text-xs mt-0.5" style="color:var(--muted2)">{{ $notification->body }}</p>
                            @endif
                        </div>
                    @endif
                    {{-- No "Ver como" a pendência é da outra pessoa — resolver aqui sumiria com ela da tela dela. --}}
                    <div class="flex items-center gap-2 flex-shrink-0" @if($viewingAs) style="display:none" @endif>
                        <form method="POST" action="{{ route('notifications.update-status', $notification) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="descartado">
                            <button type="submit" class="btn btn-ghost btn-xs">Descartar</button>
                        </form>
                        <form method="POST" action="{{ route('notifications.update-status', $notification) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="resolvido">
                            <button type="submit" class="btn btn-primary btn-xs">✓ Resolver</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── FAIXA "HOJE" — fixa em qualquer modo: minhas reuniões de hoje + o que está
         atrasado comigo na sprint. Some quando não há nada (o modo cuida do resto). ── --}}
    @if($myMeetingsToday->isNotEmpty() || $myOverdueTasks->isNotEmpty())
        <div class="card px-5 py-4 mb-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-bold flex items-center gap-1.5 mb-2" style="color:var(--text)">
                        <x-icon name="calendar" size="14" /> Reuniões de hoje ({{ $myMeetingsToday->count() }})
                    </h3>
                    <div class="flex flex-col gap-1.5">
                        @forelse($myMeetingsToday as $meeting)
                            <a href="{{ route('meetings.show', $meeting) }}" class="flex items-center gap-2 px-3 py-1.5 text-xs transition-colors"
                               style="background:var(--s2)" onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                                <span class="font-mono flex-shrink-0" style="color:var(--purple)">{{ $meeting->scheduled_at->format('H:i') }}</span>
                                <span class="font-semibold truncate" style="color:var(--text)">{{ $meeting->title }}</span>
                                <span class="truncate" style="color:var(--muted)">{{ $meeting->client?->displayName() }}</span>
                            </a>
                        @empty
                            <p class="text-xs" style="color:var(--muted)">Nenhuma reunião hoje.</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold flex items-center gap-1.5 mb-2" style="color:{{ $myOverdueTasks->isNotEmpty() ? 'var(--red)' : 'var(--text)' }}">
                        <x-icon name="alarm-clock" size="14" /> Atrasadas comigo ({{ $myOverdueTasks->count() }})
                    </h3>
                    <div class="flex flex-col gap-1.5" style="max-height:180px; overflow-y:auto">
                        @forelse($myOverdueTasks as $task)
                            <a href="{{ route('tasks.show', $task) }}" class="flex items-center gap-2 px-3 py-1.5 text-xs transition-colors"
                               style="background:var(--s2); border-left:2px solid var(--red)" onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                                <span class="font-mono flex-shrink-0" style="color:var(--red)">{{ $task->approval_date->format('d/m') }}</span>
                                <span class="font-semibold truncate" style="color:var(--text)">{{ $task->title }}</span>
                                <span class="truncate" style="color:var(--muted)">{{ $task->client?->displayName() }}</span>
                            </a>
                        @empty
                            <p class="text-xs" style="color:var(--muted)">Nada atrasado com você na sprint.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── LINHA 1.5: Agenda — quadro por status (para_agendar/agendada/pos_reuniao/revisao_ata) ──
         Estático (sem drag-and-drop) — mudar status é só na própria página da reunião. --}}
    @php
        $agendaStatuses = ['para_agendar', 'agendada', 'pos_reuniao', 'revisao_ata'];
    @endphp
    @if($show('agenda'))
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
        <div class="auto-grid">
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
    @endif

    {{-- ── Atendimento (seção destacada logo abaixo da Agenda) ── --}}
    @if($show('atendimento'))
        <div class="mb-6">
            <h2 class="text-base font-bold mb-3" style="color:var(--text)">Atendimento</h2>
            @include('dashboard.sections.atendimento')
        </div>
    @endif

    {{-- ── Heads (entre Atendimento e Operação) ── --}}
    {{-- ── Distribuição (modo Distribuição) — cockpit do Head ── --}}
    @if($show('distribuicao'))
        <div class="mb-2">
            <h2 class="text-base font-bold mb-3" style="color:var(--text)">Distribuição</h2>
            @include('dashboard.sections.distribuicao')
        </div>
    @endif

    @if($show('heads'))
        <div class="mb-6">
            <h2 class="text-base font-bold mb-3" style="color:var(--text)">Heads</h2>
            @include('dashboard.sections.heads')
        </div>
    @endif

    {{-- ── Mídia Paga (papel Tráfego) ── --}}
    @if($show('midia_paga'))
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
    @if($show('meus_numeros') || $show('kanban'))
    <h2 class="text-base font-bold mb-3" style="color:var(--text)">Operação</h2>
    @endif

    {{-- MEUS NÚMEROS NA SPRINT — recorte pessoal (executor) dentro da sprint ativa,
         antes dos 3 quadros abaixo. Cores reaproveitam a paleta de status já usada
         em badges/dropdowns em todo o App (Task::colorHex), sem inventar cor nova. --}}
    @if($activeSprint && $show('meus_numeros'))
        <div class="card px-5 py-4 mb-4">
            @php
                $sprintDaysLeft = $activeSprint->ends_at ? max(0, (int) today()->diffInDays($activeSprint->ends_at, false)) : null;
                $myRemaining    = $myExecutorSprintTotal - $myExecutorSprintDone;
                $myDonePct      = $myExecutorSprintTotal > 0 ? (int) round($myExecutorSprintDone / $myExecutorSprintTotal * 100) : 0;
            @endphp
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <span class="text-sm font-bold flex items-center gap-2" style="color:var(--text)">
                    <x-icon name="bar-chart-3" size="15" />
                    Meus Números na Sprint
                    <span class="text-xs font-mono font-normal" style="color:var(--muted)">— como Executor</span>
                </span>
                <span class="text-xs font-mono" style="color:var(--muted)">
                    {{ $activeSprint->title }}
                    @if($sprintDaysLeft !== null)
                        · <span style="color:{{ $sprintDaysLeft <= 2 && $myRemaining > 0 ? 'var(--orange)' : 'var(--muted)' }}">{{ $sprintDaysLeft === 0 ? 'termina hoje' : "faltam {$sprintDaysLeft} dia(s)" }}</span>
                    @endif
                </span>
            </div>

            @if($myExecutorSprintTotal > 0)
                {{-- Linha principal: o tamanho da régua e quanto ainda falta dela. --}}
                <div class="grid gap-3 mb-3" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr))">
                    <div class="px-3 py-3 text-center" style="background:var(--s2); border-top:3px solid var(--purple)">
                        <p class="text-3xl font-black" style="color:var(--text)">{{ $myExecutorSprintTotal }}</p>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">Tarefas na Sprint</p>
                        <p class="text-xs font-mono font-bold" style="color:var(--purple)">{{ $myPointsTotal }} pts</p>
                    </div>
                    <div class="px-3 py-3 text-center" style="background:var(--s2); border-top:3px solid var(--green)">
                        <p class="text-3xl font-black" style="color:var(--green)">{{ $myExecutorSprintDone }}</p>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">Concluídas · {{ $myDonePct }}%</p>
                        <p class="text-xs font-mono font-bold" style="color:var(--green)">{{ $myPointsDone }} pts</p>
                    </div>
                    <div class="px-3 py-3 text-center" style="background:var(--s2); border-top:3px solid var(--orange)">
                        <p class="text-3xl font-black" style="color:{{ $myRemaining > 0 ? 'var(--orange)' : 'var(--muted)' }}">{{ $myRemaining }}</p>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">Faltam</p>
                        <p class="text-xs font-mono font-bold" style="color:var(--orange)">{{ $myPointsTotal - $myPointsDone }} pts</p>
                    </div>
                    <div class="px-3 py-3 text-center" style="background:var(--s2); border-top:3px solid var(--red)">
                        <p class="text-3xl font-black" style="color:{{ $myOverdueTasks->isNotEmpty() ? 'var(--red)' : 'var(--muted)' }}">{{ $myOverdueTasks->count() }}</p>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">Atrasadas</p>
                    </div>
                </div>

                {{-- Barra segmentada — distribuição das mesmas tarefas por status (cores de
                     Task::colorHex), com a legenda logo abaixo. --}}
                <div class="flex h-2.5 rounded-full overflow-hidden gap-0.5" style="background:var(--border2)">
                    @foreach($myExecutorSprintByStatus as $row)
                        <div style="width:{{ round($row['count'] / $myExecutorSprintTotal * 100, 2) }}%; background:{{ $row['hex'] }}"
                             title="{{ $row['label'] }}: {{ $row['count'] }} ({{ round($row['count'] / $myExecutorSprintTotal * 100) }}%)"></div>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                    @foreach($myExecutorSprintByStatus as $row)
                        <span class="flex items-center gap-1.5 text-xs font-mono" style="color:var(--muted2)">
                            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:{{ $row['hex'] }}"></span>
                            {{ $row['label'] }} <strong style="color:var(--text)">{{ $row['count'] }}</strong>
                        </span>
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

    @if($show('kanban'))
    @php
        $quadros = [
            ['icon' => 'wrench',         'label' => 'Ajuste / Alteração',   'tasks' => $myAdjustmentTasks,         'status' => 'ajuste_alteracao', 'extra' => null],
            ['icon' => 'settings',       'label' => 'Em Produção',           'tasks' => $myProductionTasks,         'status' => 'em_producao',      'extra' => null],
            ['icon' => 'clipboard-list', 'label' => 'Pronto para Produção',  'tasks' => $myReadyForProductionTasks, 'status' => 'backlog',           'extra' => ['situation' => 'Pronto para produção']],
        ];
    @endphp
    <div id="dashboard-board" class="auto-grid mb-6"
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
            // Arraste-e-solte só no desktop — no toque conflita com o scroll da
            // página; no mobile os cards viram lista e abrem a tarefa no toque.
            // No "Ver como" o quadro é da outra pessoa — fica só pra olhar, sem arrastar.
            if (@json(! $viewingAs) && document.getElementById('dashboard-board') && window.matchMedia('(min-width: 768px)').matches) {
                initKanbanDnd('#dashboard-board');
            }
        });
    </script>
    @endpush
    @endif

    {{-- ── MINHA SEMANA (modo Execução) — segunda a sexta, minhas tarefas na coluna da
         data de aprovação (DashboardController, bloco 'minha_semana'). Concluídas da semana
         aparecem apagadas no fim da coluna, pra pessoa ver o que já entregou. Sem arrastar:
         a data é decisão de quem distribui. ── --}}
    @if($show('minha_semana'))
        @php
            $corRgb = ['green' => '16,185,129', 'purple' => '100,59,142', 'orange' => '238,121,25', 'red' => '220,38,38', 'muted' => '152,161,178', 'blue' => '46,144,250'];
            $weekTotal = collect($weekDays)->sum(fn ($d) => $d['tasks']->count());
            $weekPoints = collect($weekDays)->sum(fn ($d) => $d['tasks']->sum(fn ($t) => $t->sprint_points ?? 1));
            $weekDone  = collect($weekDays)->sum(fn ($d) => $d['tasks']->where('status', 'concluido')->count());
        @endphp
        <div class="card px-5 py-4 mb-6">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <span class="text-sm font-bold flex items-center gap-2" style="color:var(--text)">
                    <x-icon name="calendar" size="15" />
                    Minha Semana
                    <span class="text-xs font-mono font-normal" style="color:var(--muted)">
                        — {{ $weekTotal }} tarefa(s) · {{ $weekPoints }} pts · {{ $weekDone }} concluída(s)
                    </span>
                </span>
                <div class="flex items-center gap-1.5">
                    <a href="{{ $dashUrl(['semana' => $weekOffset - 1]) }}" class="btn btn-ghost btn-xs">‹ Anterior</a>
                    <span class="text-xs font-mono px-1" style="color:var(--text)">
                        {{ $weekDays[0]['data']->format('d/m') }} a {{ $weekDays[4]['data']->format('d/m') }}
                    </span>
                    <a href="{{ $dashUrl(['semana' => $weekOffset + 1]) }}" class="btn btn-ghost btn-xs">Próxima ›</a>
                    @if($weekOffset !== 0)
                        <a href="{{ $dashUrl() }}" class="btn btn-ghost btn-xs">Hoje</a>
                    @endif
                </div>
            </div>

            @if($weekBeforeCount || $weekAfterCount || $weekNoDateCount)
                <p class="text-xs font-mono mb-3 flex flex-wrap gap-x-3" style="color:var(--muted)">
                    @if($weekBeforeCount)<span style="color:var(--red)">{{ $weekBeforeCount }} aberta(s) com data antes desta semana</span>@endif
                    @if($weekAfterCount)<span>{{ $weekAfterCount }} depois desta semana</span>@endif
                    @if($weekNoDateCount)<span style="color:var(--orange)">{{ $weekNoDateCount }} sem data de aprovação</span>@endif
                </p>
            @endif

            <div class="grid gap-3 md:grid-cols-5">
                @foreach($weekDays as $dia)
                    @php $hoje = $dia['data']->isToday(); @endphp
                    <div class="flex flex-col gap-2 min-w-0" style="{{ $hoje ? 'padding:6px; border-radius:10px; border:2px solid var(--green); background:rgba(52,211,153,.05)' : '' }}">
                        <div class="flex items-center justify-between px-3 py-2"
                             style="{{ $hoje ? 'background:rgba(52,211,153,.12); border:1px solid var(--green)' : 'background:var(--s2); border:1px solid var(--border2)' }}">
                            <span class="text-xs font-bold font-mono uppercase tracking-widest" style="color:{{ $hoje ? 'var(--green)' : 'var(--purple)' }}">
                                {{ ucfirst($dia['data']->translatedFormat('D')) }} · {{ $dia['data']->format('d/m') }}{{ $hoje ? ' · Hoje' : '' }}
                            </span>
                            <span class="text-xs font-mono font-bold" style="color:var(--muted)">{{ $dia['tasks']->count() }} · <span style="color:var(--purple)">{{ $dia['tasks']->sum(fn ($t) => $t->sprint_points ?? 1) }} pts</span></span>
                        </div>
                        <div class="flex flex-col gap-2" style="max-height:420px; overflow-y:auto">
                            @forelse($dia['tasks'] as $task)
                                @php
                                    $done = $task->status === 'concluido';
                                    $late = ! $done && $task->approval_date->lt(today());
                                    $rgb  = $corRgb[$task->statusColor()] ?? $corRgb['muted'];
                                @endphp
                                <div class="px-3 py-2.5" style="cursor:pointer; background:rgba({{ $rgb }}, {{ $done ? '.08' : '.18' }}); border:1px solid var(--border2); {{ $late ? 'border-left:3px solid var(--red);' : '' }} {{ $done ? 'opacity:.6' : '' }}"
                                     @click="$store.taskPopup.open('{{ route('tasks.show', $task) }}')">
                                    @if(! $task->sprint_id)
                                        <p class="text-xs font-mono truncate" style="color:var(--orange)">Fila</p>
                                    @endif
                                    <p class="text-xs font-mono truncate" style="color:var(--purple)">{{ $task->client?->displayName() ?? '—' }}</p>
                                    <p class="text-sm font-semibold leading-snug mt-1" style="color:var(--text); {{ $done ? 'text-decoration:line-through' : '' }}">{{ $task->title }}</p>
                                    <div class="flex items-center gap-1 mt-1.5 flex-wrap">
                                        <span class="badge badge-{{ $task->statusColor() }}" style="font-size:8px; padding:1px 5px">{{ $task->statusLabel() }}</span>
                                        <span class="text-xs font-mono font-bold" style="color:var(--purple)">{{ $task->sprint_points ?? 1 }} pts</span>
                                        @if($task->situation && ! $done)
                                            <span class="badge" style="font-size:8px; padding:1px 5px; background:{{ $task->situationColor() }}; color:#fff; border-color:transparent">{{ $task->situationLabel() }}</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs px-1 py-2" style="color:var(--muted)">Nada lançado.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Estratégia (modo Planejamento) ── --}}
    @if($show('estrategia'))
        <div class="mb-6">
            <h2 class="text-base font-bold mb-3" style="color:var(--text)">Estratégia</h2>
            @include('dashboard.sections.estrategia')
        </div>
    @endif

    {{-- ── Demais papéis sem painel próprio ainda (só na Visão geral) ── --}}
    @if($show('outros_papeis') && !empty($dashboardRoles))
        <div class="flex flex-col gap-6">
            @foreach($dashboardRoles as $role)
                @continue(in_array($role, $headsRoles) || in_array($role, ['atendimento', 'trafego', 'estrategia']))
                @if($functionRoleLabels->has($role))
                    <div>
                        <h2 class="text-base font-bold mb-3" style="color:var(--text)">
                            {{ $functionRoleLabels[$role] }}
                        </h2>
                        <div class="card px-5 py-4">
                            <p class="text-xs" style="color:var(--muted)">
                                Painel de <strong>{{ $functionRoleLabels[$role] }}</strong> em construção — em breve.
                            </p>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</x-app-layout>
