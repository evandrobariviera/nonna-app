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
                    'filter'=> null,
                ],
                [
                    'label' => 'Aguardando Resposta',
                    'value' => $stats['pending'],
                    'color' => 'var(--purple)',
                    'bg'    => 'rgba(100, 59, 142,.08)',
                    'border'=> 'rgba(100, 59, 142,.2)',
                    'filter'=> 'pending',
                ],
                [
                    'label' => 'Ajustes Solicitados',
                    'value' => $stats['changes'],
                    'color' => 'var(--orange)',
                    'bg'    => 'rgba(238, 121, 25,.08)',
                    'border'=> 'rgba(238, 121, 25,.2)',
                    'filter'=> 'changes_requested',
                ],
                [
                    'label' => 'Aprovados Hoje',
                    'value' => $stats['approved_today'],
                    'color' => 'var(--green)',
                    'bg'    => 'rgba(34,197,94,.08)',
                    'border'=> 'rgba(34,197,94,.2)',
                    'filter'=> null,
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

        @foreach($statCards as $card)
            @if($card['filter'])
                <a href="{{ route('approvals.index', ['status' => $card['filter']]) }}"
                   class="card card-body transition-all"
                   style="border-color:{{ request('status') === $card['filter'] ? $card['border'] : 'var(--border2)' }}; text-decoration:none"
                   onmouseover="this.style.borderColor='{{ $card['border'] }}'" onmouseout="this.style.borderColor='{{ request('status') === $card['filter'] ? $card['border'] : 'var(--border2)' }}'">
            @else
                <div class="card card-body">
            @endif
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">{{ $card['label'] }}</p>
                <p class="text-3xl font-bold" style="color:{{ $card['color'] }}; letter-spacing:-.03em">{{ $card['value'] }}</p>
            @if($card['filter'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    <div x-data="{ tab: '{{ request('view', 'list') }}' }" x-cloak>

        {{-- ══ TOGGLE QUADROS / LISTA ══ --}}
        <div class="flex items-center gap-1 mb-5" style="border-bottom:1px solid var(--border2)">
            <button @click="tab = 'board'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'board' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Quadros
            </button>
            <button @click="tab = 'list'"
                class="px-4 py-2 text-xs font-mono uppercase tracking-widest transition-colors"
                :style="tab === 'list' ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)'">
                Lista
            </button>
        </div>

        {{-- ══ FILTROS (afetam Quadros e Lista — client_id filtra os dois; status só filtra a Lista) ══ --}}
        <form method="GET" action="{{ route('approvals.index') }}" class="flex gap-3 mb-5 flex-wrap items-end"
              data-live-filter data-results-url="{{ route('approvals.results') }}" data-target="#approvals-results">
            <input type="hidden" name="mostrar_aprovados" value="{{ request('mostrar_aprovados') }}">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Status</label>
                <select name="status" class="px-3 py-2 text-sm focus:outline-none"
                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text); min-width:180px">
                    <option value="">Todos os status</option>
                    <option value="pending"            {{ request('status') === 'pending'            ? 'selected' : '' }}>Aguardando Resposta</option>
                    <option value="changes_requested"  {{ request('status') === 'changes_requested'  ? 'selected' : '' }}>Ajustes Solicitados</option>
                    <option value="approved"           {{ request('status') === 'approved'           ? 'selected' : '' }}>Aprovado</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Tipo</label>
                <select name="type" class="px-3 py-2 text-sm focus:outline-none"
                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text); min-width:160px">
                    <option value="">Todos os tipos</option>
                    <option value="aprovacao" {{ request('type') === 'aprovacao' ? 'selected' : '' }}>Aprovação</option>
                    <option value="aviso"     {{ request('type') === 'aviso'     ? 'selected' : '' }}>Aviso</option>
                </select>
            </div>

            @if($clients->count() > 0)
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
                    <select name="client_id" class="px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--s2); border:1px solid var(--border2); color:var(--text); min-width:200px">
                        <option value="">Todos os clientes</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>
                                {{ $c->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(request()->hasAny(['status','client_id','type','mostrar_aprovados']))
                <a href="{{ route('approvals.index') }}" class="px-4 py-2 text-sm"
                   style="color:var(--muted); border:1px solid var(--border2)">Limpar</a>
            @endif

            @php
                $toggleAprovadosParams = request()->except('mostrar_aprovados', 'page');
                if (!request()->boolean('mostrar_aprovados')) $toggleAprovadosParams['mostrar_aprovados'] = '1';
            @endphp
            <a href="{{ route('approvals.index', $toggleAprovadosParams) }}"
               class="flex items-center gap-1.5 text-xs font-mono px-3 py-2 transition-all"
               style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_aprovados') ? 'var(--purple)' : 'var(--muted)' }}">
                {{ request()->boolean('mostrar_aprovados') ? '⊙ Ocultar aprovados' : '○ Mostrar aprovados' }}
            </a>
        </form>

        <div id="approvals-results">
            @include('approvals._results')
        </div>
    </div>{{-- /x-data --}}

</x-app-layout>
