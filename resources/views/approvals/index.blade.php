<x-app-layout>
    <x-slot name="header">Central de Aprovações</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(238, 121, 25,.08); border:1px solid rgba(238, 121, 25,.25); color:var(--orange)">
            ⚠ {{ session('warning') }}
        </div>
    @endif

    {{-- ══ CARDS DE ESTATÍSTICAS ══ --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-5">
        @php
            $statCards = [
                [
                    'label' => 'Aguardando Envio',
                    'value' => $stats['awaiting_send'],
                    'color' => 'var(--muted2)',
                    'bg'    => 'var(--s2)',
                    'border'=> 'var(--border2)',
                    'filter'=> 'awaiting_send',
                ],
                [
                    'label' => 'Aguardando Resposta',
                    'value' => $stats['pending'],
                    'color' => 'var(--purple)',
                    'bg'    => 'rgba(100, 59, 142,.08)',
                    'border'=> 'rgba(100, 59, 142,.2)',
                    'filter'=> 'waiting_reply',
                ],
                [
                    'label' => 'Ajustes Solicitados',
                    'value' => $stats['changes'],
                    'color' => 'var(--orange)',
                    'bg'    => 'rgba(238, 121, 25,.08)',
                    'border'=> 'rgba(238, 121, 25,.2)',
                    'filter'=> 'changes_open',
                ],
                [
                    'label' => 'Aprovados Hoje',
                    'value' => $stats['approved_today'],
                    'color' => 'var(--green)',
                    'bg'    => 'rgba(34,197,94,.08)',
                    'border'=> 'rgba(34,197,94,.2)',
                    'filter'=> 'approved_today',
                ],
                [
                    'label' => 'Total Aprovados',
                    'value' => $stats['approved_total'],
                    'color' => 'var(--muted2)',
                    'bg'    => 'var(--s2)',
                    'border'=> 'var(--border2)',
                    'filter'=> 'approved',
                ],
            ];
        @endphp

        {{-- Cada card é um filtro da Lista: mostra exatamente o que ele conta.
             Clicar de novo no card ativo tira o filtro. --}}
        @foreach($statCards as $card)
            @php
                $active = request('status') === $card['filter'];
                $cardParams = request()->except('status', 'page', 'view') + ['view' => 'list'];
                if (!$active) $cardParams['status'] = $card['filter'];
            @endphp
            <a href="{{ route('approvals.index', $cardParams) }}"
               class="card card-body transition-all"
               title="{{ $active ? 'Tirar o filtro' : 'Filtrar a lista: ' . $card['label'] }}"
               style="border-color:{{ $active ? $card['border'] : 'var(--border2)' }}; {{ $active ? 'background:' . $card['bg'] . ';' : '' }} text-decoration:none"
               onmouseover="this.style.borderColor='{{ $card['border'] }}'" onmouseout="this.style.borderColor='{{ $active ? $card['border'] : 'var(--border2)' }}'">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2 flex items-center justify-between gap-2" style="color:var(--muted); letter-spacing:.08em">
                    <span>{{ $card['label'] }}</span>
                    @if($active)<span style="color:{{ $card['color'] }}" aria-hidden="true">✕</span>@endif
                </p>
                <p class="text-3xl font-bold" style="color:{{ $card['color'] }}; letter-spacing:-.03em">{{ $card['value'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- Lista é a aba padrão. Se a página abrir em "Por cliente", Quadros/Lista só
         carregam (fetch do fragmento, via live-filter) na primeira vez que abrirem. --}}
    <div x-data="{ tab: '{{ $activeTab }}', loaded: {{ $rounds ? 'true' : 'false' }} }"
         x-init="$watch('tab', v => { if (v !== 'clientes' && !loaded) { loaded = true; $nextTick(() => window.refreshLiveFilter()) } })"
         x-cloak>

        {{-- ══ TOGGLE QUADROS / LISTA ══ --}}
        <div class="flex items-center gap-1 mb-5" style="border-bottom:1px solid var(--border2)">
            <button @click="tab = 'list'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'list' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Lista
            </button>
            <button @click="tab = 'board'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'board' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Quadros
            </button>
            <button @click="tab = 'clientes'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'clientes' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Por cliente
                @if($byClient->isNotEmpty())
                    <span class="ml-1">({{ $byClient->count() }})</span>
                @endif
            </button>
        </div>

        @include('approvals._by-client')

        {{-- ══ FILTROS (afetam Quadros e Lista — client_id filtra os dois; status só filtra a Lista) ══ --}}
        <form x-show="tab !== 'clientes'" method="GET" action="{{ route('approvals.index') }}" class="flex gap-3 mb-5 flex-wrap items-end"
              data-live-filter data-results-url="{{ route('approvals.results') }}" data-target="#approvals-results">
            <input type="hidden" name="mostrar_aprovados" value="{{ request('mostrar_aprovados') }}">
            <input type="hidden" name="project_id" value="{{ request('project_id') }}">

            @if($projectFilterLabel)
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Projeto</label>
                    <a href="{{ route('approvals.index', request()->except('project_id', 'page') + ['view' => 'list']) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 text-sm"
                       style="background:rgba(100,59,142,.08); border:1px solid rgba(100,59,142,.25); color:var(--purple)"
                       title="Tirar o filtro de projeto">
                        {{ $projectFilterLabel }} <span aria-hidden="true">✕</span>
                    </a>
                </div>
            @endif

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Status</label>
                <select name="status" class="field px-3 py-2 text-sm focus:outline-none"
                    style="min-width:180px">
                    <option value="">Todos os status</option>
                    @foreach([
                        'awaiting_send'     => 'Aguardando envio',
                        'waiting_reply'     => 'Aguardando resposta',
                        'pending'           => 'Pendentes (envio + resposta)',
                        'changes_open'      => 'Ajustes solicitados (não tratados)',
                        'changes_requested' => 'Ajustes solicitados (todos)',
                        'approved_today'    => 'Aprovados hoje',
                        'approved'          => 'Aprovados (todos)',
                    ] as $v => $l)
                        <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Tipo</label>
                <select name="type" class="field px-3 py-2 text-sm focus:outline-none"
                    style="min-width:160px">
                    <option value="">Todos os tipos</option>
                    <option value="aprovacao" {{ request('type') === 'aprovacao' ? 'selected' : '' }}>Aprovação</option>
                    <option value="aviso"     {{ request('type') === 'aviso'     ? 'selected' : '' }}>Aviso</option>
                </select>
            </div>

            @if($clients->count() > 0)
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
                    <select name="client_id" class="field px-3 py-2 text-sm focus:outline-none"
                        style="min-width:200px">
                        <option value="">Todos os clientes</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>
                                {{ $c->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(request()->hasAny(['status','client_id','project_id','type','mostrar_aprovados']))
                <a href="{{ route('approvals.index', ['view' => 'list']) }}" class="px-4 py-2 text-sm"
                   style="color:var(--muted); border:1px solid var(--border2)">Limpar</a>
            @endif

            @php
                $toggleAprovadosParams = request()->except('mostrar_aprovados', 'page') + ['view' => 'list'];
                if (!request()->boolean('mostrar_aprovados')) $toggleAprovadosParams['mostrar_aprovados'] = '1';
            @endphp
            <a href="{{ route('approvals.index', $toggleAprovadosParams) }}"
               class="flex items-center gap-1.5 text-xs font-mono px-3 py-2 transition-all"
               style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_aprovados') ? 'var(--purple)' : 'var(--muted)' }}">
                {{ request()->boolean('mostrar_aprovados') ? '⊙ Ocultar aprovados' : '○ Mostrar aprovados' }}
            </a>
        </form>

        <div id="approvals-results">
            @if($rounds)
                @include('approvals._results')
            @else
                <div x-show="tab !== 'clientes'" class="card card-body text-sm text-center" style="color:var(--muted)">Carregando…</div>
            @endif
        </div>
    </div>{{-- /x-data --}}

</x-app-layout>
