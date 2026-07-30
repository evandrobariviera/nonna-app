<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Fluxo de Trabalho</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Planejamentos</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('macroplans.import.create') }}" class="btn btn-ghost">
                    Importar HTML
                </a>
                <a href="{{ route('macroplans.create') }}" class="btn btn-primary">
                    + Novo Planejamento
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('macroplans.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
          data-live-filter data-results-url="{{ route('macroplans.results') }}" data-target="#macroplans-results">
        <input type="hidden" name="mostrar_concluidos" value="{{ request('mostrar_concluidos') }}">
        <select name="status"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os status</option>
            @foreach(\App\Models\MacroPlan::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="client_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        @if(request()->hasAny(['status','client_id','mostrar_concluidos']))
            <a href="{{ route('macroplans.index') }}" class="btn btn-ghost btn-sm">
                Limpar
            </a>
        @endif

        @php
            $toggleParams = request()->except('mostrar_concluidos', 'page');
            if (!request()->boolean('mostrar_concluidos')) $toggleParams['mostrar_concluidos'] = '1';
        @endphp
        <a href="{{ route('macroplans.index', $toggleParams) }}"
           class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
           style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_concluidos') ? 'var(--purple)' : 'var(--muted)' }}">
            {{ request()->boolean('mostrar_concluidos') ? '⊙ Ocultar encerrados' : '○ Mostrar encerrados' }}
        </a>
    </form>

    <div id="macroplans-results">
        @include('macroplans._results')
    </div>

</x-app-layout>
