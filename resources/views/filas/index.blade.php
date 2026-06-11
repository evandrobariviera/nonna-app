<x-app-layout>
    <x-slot name="header">Filas</x-slot>

    {{-- ══ STATS ══ --}}
    <div class="grid grid-cols-5 gap-3 mb-5">
        @php
            $statsConfig = [
                ['label' => 'Na Fila',    'value' => $stats['total'],     'color' => 'var(--text)',   'filter' => null],
                ['label' => 'Projetos',   'value' => $stats['projetos'],  'color' => 'var(--purple)', 'filter' => 'origin=projeto'],
                ['label' => 'Tickets',    'value' => $stats['tickets'],   'color' => 'var(--orange)', 'filter' => 'origin=ticket'],
                ['label' => 'Atrasadas',  'value' => $stats['atrasadas'], 'color' => 'var(--red)',    'filter' => 'atrasadas=1'],
                ['label' => 'Clientes',   'value' => $stats['clientes'],  'color' => 'var(--muted2)', 'filter' => null],
            ];
        @endphp
        @foreach($statsConfig as $s)
            <div class="stat-card">
                <p class="stat-label">{{ $s['label'] }}</p>
                <p class="stat-value" style="color:{{ $s['color'] }}">{{ $s['value'] }}</p>
                @if($s['filter'])
                    <a href="{{ route('fila.index') }}?{{ $s['filter'] }}"
                       class="text-xs mt-2 block"
                       style="color:var(--muted)"
                       onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                        filtrar →
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ══ SPRINT ATIVA (contexto) ══ --}}
    @if($activeSprint)
        <div class="mb-4 flex items-center gap-3 px-5 py-3 card"
             style="background:rgba(5,150,105,.04); border-color:rgba(5,150,105,.2)">
            <span class="h-2 w-2 rounded-full flex-shrink-0 animate-pulse" style="background:var(--green)"></span>
            <div class="flex-1">
                <span class="text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.08em">Sprint Ativa</span>
                <span class="text-sm font-semibold ml-2" style="color:var(--text)">{{ $activeSprint->title }}</span>
                @if($activeSprint->ends_at)
                    <span class="text-xs ml-2" style="color:var(--muted)">· encerra {{ $activeSprint->ends_at->format('d/m/Y') }} ({{ $activeSprint->ends_at->diffForHumans() }})</span>
                @endif
            </div>
            <a href="{{ route('sprints.show', $activeSprint) }}"
               class="text-xs font-semibold" style="color:var(--green)"
               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                Ver Sprint →
            </a>
        </div>
    @else
        <div class="mb-4 flex items-center gap-3 px-5 py-3 card"
             style="background:rgba(217,119,6,.04); border-color:rgba(217,119,6,.2)">
            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--yellow)"></span>
            <p class="text-sm" style="color:var(--muted)">Nenhuma sprint ativa no momento.
                <a href="{{ route('sprints.create') }}" style="color:var(--orange)"
                   onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                    Criar sprint →
                </a>
            </p>
        </div>
    @endif

    {{-- ══ FILTROS ══ --}}
    <form method="GET" action="{{ route('fila.index') }}"
          class="card card-body mb-5 flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
            <select name="client_id" class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-36">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Origem</label>
            <select name="origin" class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todas</option>
                @foreach(\App\Models\Task::$origins as $key => $label)
                    <option value="{{ $key }}" {{ request('origin') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-44">
            <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Tipo</label>
            <select name="task_type" class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os tipos</option>
                @foreach(\App\Models\Task::$types as $key => $label)
                    <option value="{{ $key }}" {{ request('task_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2 mb-0.5">
            <input type="checkbox" name="atrasadas" value="1" id="chk_atrasadas"
                {{ request('atrasadas') ? 'checked' : '' }}
                class="w-4 h-4" style="accent-color:var(--red)">
            <label for="chk_atrasadas" class="text-sm font-medium cursor-pointer" style="color:var(--red)">
                Só atrasadas
            </label>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white" style="background:var(--purple)">
                Filtrar
            </button>
            @if(request()->hasAny(['client_id','origin','task_type','atrasadas']))
                <a href="{{ route('fila.index') }}"
                   class="px-4 py-2 text-sm font-medium"
                   style="border:1px solid var(--border2); color:var(--muted)">
                    Limpar
                </a>
            @endif
        </div>
    </form>

    {{-- ══ LISTA AGRUPADA POR CLIENTE ══ --}}
    @if($tasks->isEmpty())
        <div class="tab-placeholder">
            <div class="tab-placeholder-icon">🎉</div>
            <p class="tab-placeholder-title">Fila vazia!</p>
            <p class="tab-placeholder-desc">Todas as tarefas foram alocadas em sprints ou não há tarefas no backlog.</p>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach($byClient as $clientId => $clientTasks)
                @php
                    $client = $clientId !== '__sem_cliente__' ? $clientTasks->first()->client : null;
                    $overdueCount = $clientTasks->filter(fn($t) => $t->isOverdue())->count();
                @endphp

                <div x-data="{ open: true }" class="card" style="overflow:hidden">

                    {{-- Cabeçalho do grupo --}}
                    <button @click="open = !open"
                            class="w-full flex items-center gap-3 px-5 py-3.5 text-left transition-colors"
                            style="border-bottom:1px solid var(--border2)"
                            :style="open ? 'border-bottom-color:var(--border2)' : 'border-bottom:none'"
                            onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='transparent'">

                        <svg class="h-3.5 w-3.5 flex-shrink-0 transition-transform duration-200"
                             :class="open ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"
                             style="color:var(--muted)">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>

                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            @if($client)
                                <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0"
                                     style="background:var(--grad)">
                                    {{ strtoupper(substr($client->company_name, 0, 2)) }}
                                </div>
                                <span class="text-sm font-semibold truncate" style="color:var(--text)">{{ $client->company_name }}</span>
                            @else
                                <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold flex-shrink-0"
                                     style="background:var(--s3); color:var(--muted)">—</div>
                                <span class="text-sm font-semibold" style="color:var(--muted)">Sem cliente</span>
                            @endif

                            <span class="badge" style="background:rgba(106,90,205,.08); border-color:rgba(106,90,205,.2); color:var(--purple)">
                                {{ $clientTasks->count() }} {{ $clientTasks->count() === 1 ? 'tarefa' : 'tarefas' }}
                            </span>
                            @if($overdueCount > 0)
                                <span class="badge" style="background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.2); color:var(--red)">
                                    {{ $overdueCount }} atrasada{{ $overdueCount > 1 ? 's' : '' }}
                                </span>
                            @endif
                        </div>

                        @if($client)
                            <a href="{{ route('clients.show', $client) }}" @click.stop
                               class="text-xs flex-shrink-0 transition-colors" style="color:var(--muted)"
                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                Ver cliente →
                            </a>
                        @endif
                    </button>

                    {{-- Linhas de tarefas --}}
                    <div x-show="open" x-cloak>
                        @foreach($clientTasks as $task)
                            <div class="flex items-center gap-3 px-5 py-3 transition-colors"
                                 style="{{ !$loop->last ? 'border-bottom:1px solid var(--border2)' : '' }};
                                        {{ $task->isOverdue() ? 'background:rgba(220,38,38,.02)' : '' }}"
                                 onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='{{ $task->isOverdue() ? 'rgba(220,38,38,.02)' : 'transparent' }}'">

                                {{-- Indicador de atraso --}}
                                <div class="flex-shrink-0 h-full">
                                    @if($task->isOverdue())
                                        <div class="h-4 w-1 rounded" style="background:var(--red)"></div>
                                    @else
                                        <div class="h-4 w-1 rounded" style="background:var(--border2)"></div>
                                    @endif
                                </div>

                                {{-- Título --}}
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="text-sm font-medium block truncate transition-colors"
                                       style="color:var(--text)"
                                       onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--text)'">
                                        {{ $task->title }}
                                    </a>
                                    @if($task->project)
                                        <p class="text-xs mt-0.5 truncate" style="color:var(--muted)">
                                            {{ $task->project->macroPlan?->title }} · {{ $task->project->title }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Badges --}}
                                <div class="hidden md:flex items-center gap-1.5 flex-shrink-0">
                                    <span class="badge" style="font-size:11px">{{ $task->typeLabel() }}</span>
                                    <span class="badge {{ $task->is_ticket ? 'badge-orange' : '' }}" style="font-size:11px">
                                        {{ $task->originLabel() }}
                                    </span>
                                </div>

                                {{-- Vencimento --}}
                                <div class="flex-shrink-0 w-24 text-right">
                                    @if($task->due_date)
                                        <p class="text-xs font-semibold" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted2)' }}">
                                            {{ $task->due_date->format('d/m/Y') }}
                                        </p>
                                        @if($task->isOverdue())
                                            <p class="text-xs" style="color:var(--red); opacity:.7">{{ $task->due_date->diffForHumans() }}</p>
                                        @endif
                                    @else
                                        <p class="text-xs" style="color:var(--muted)">sem data</p>
                                    @endif
                                </div>

                                {{-- Executor --}}
                                <div class="flex-shrink-0 w-24 text-right">
                                    @if($task->executors->count() > 0)
                                        <div class="flex items-center justify-end gap-1">
                                            @foreach($task->executors->take(2) as $exec)
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white"
                                                     title="{{ $exec->name }}"
                                                     style="background:var(--grad); font-size:9px">
                                                    {{ strtoupper(substr($exec->name, 0, 2)) }}
                                                </div>
                                            @endforeach
                                            @if($task->executors->count() > 2)
                                                <span class="text-xs" style="color:var(--muted)">+{{ $task->executors->count() - 2 }}</span>
                                            @endif
                                        </div>
                                    @elseif($task->executor)
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white ml-auto"
                                             title="{{ $task->executor->name }}"
                                             style="background:var(--grad); font-size:9px">
                                            {{ strtoupper(substr($task->executor->name, 0, 2)) }}
                                        </div>
                                    @else
                                        <p class="text-xs" style="color:var(--muted)">—</p>
                                    @endif
                                </div>

                                {{-- Enviar para Sprint --}}
                                @if($sprints->count() > 0)
                                    <div class="flex-shrink-0" x-data="{ open: false }">
                                        <div class="flex items-stretch">
                                            {{-- Botão rápido → sprint ativa --}}
                                            @if($activeSprint)
                                                <form method="POST" action="{{ route('sprints.add-task', [$activeSprint, $task]) }}">
                                                    @csrf
                                                    <button type="submit"
                                                        class="px-3 py-1.5 text-xs font-semibold text-white transition-opacity"
                                                        style="background:var(--green)"
                                                        onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'"
                                                        title="Enviar para: {{ $activeSprint->title }}">
                                                        → Sprint
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Dropdown para escolher outra sprint --}}
                                            @if($sprints->count() > 1 || !$activeSprint)
                                                <div class="relative">
                                                    <button @click="open = !open" @click.stop
                                                        class="px-2 py-1.5 text-xs transition-colors flex-shrink-0"
                                                        style="{{ $activeSprint ? 'border-left:1px solid rgba(255,255,255,.3); background:var(--green); color:rgba(255,255,255,.8)' : 'border:1px solid var(--border2); color:var(--muted)' }}"
                                                        title="Escolher sprint">
                                                        ▾
                                                    </button>
                                                    <div x-show="open" @click.outside="open = false" x-cloak
                                                         class="absolute right-0 mt-1 z-30 py-1 min-w-48"
                                                         style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1); top:100%">
                                                        <p class="px-3 py-1.5 text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.08em">Enviar para</p>
                                                        @foreach($sprints as $sprint)
                                                            <form method="POST" action="{{ route('sprints.add-task', [$sprint, $task]) }}">
                                                                @csrf
                                                                <button type="submit" @click="open = false"
                                                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors"
                                                                    style="color:var(--text)"
                                                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                                                    <span class="h-1.5 w-1.5 rounded-full flex-shrink-0"
                                                                          style="background:{{ $sprint->status === 'active' ? 'var(--green)' : 'var(--orange)' }}"></span>
                                                                    <span class="font-medium truncate">{{ $sprint->title }}</span>
                                                                    <span class="text-xs ml-auto flex-shrink-0" style="color:var(--muted)">
                                                                        {{ $sprint->status === 'active' ? 'Ativa' : 'Planning' }}
                                                                    </span>
                                                                </button>
                                                            </form>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ route('sprints.create') }}"
                                       class="text-xs flex-shrink-0 px-3 py-1.5"
                                       style="border:1px solid var(--border2); color:var(--muted)"
                                       title="Nenhuma sprint disponível">
                                        + Sprint
                                    </a>
                                @endif

                            </div>
                        @endforeach
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</x-app-layout>
