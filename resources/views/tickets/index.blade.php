<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <span class="text-base font-bold" style="color:var(--text)">Tickets / Suporte</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">+ Novo Ticket</a>
        </div>
    </x-slot>

    {{-- BARRA DE FILTROS --}}
    <form method="GET" action="{{ route('tickets.index') }}"
          class="flex items-center gap-2 flex-wrap mb-5 pb-4"
          style="border-bottom:1px solid var(--border2)"
          data-live-filter data-results-url="{{ route('tickets.results') }}" data-target="#tickets-results">

        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por título…"
            class="filter-select" style="cursor:text; min-width:220px">

        <select name="status" class="filter-select">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="client_id" class="filter-select">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <select name="executor_id" class="filter-select">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>

        <div class="flex items-center gap-2 pb-0.5">
            <input type="checkbox" name="mostrar_fechados" value="1" id="chk_mostrar_fechados"
                {{ request()->boolean('mostrar_fechados') ? 'checked' : '' }}
                class="w-4 h-4" style="accent-color:var(--purple)">
            <label for="chk_mostrar_fechados" class="text-sm font-medium cursor-pointer" style="color:var(--muted)">
                Mostrar concluídos/cancelados
            </label>
        </div>

        @if(request()->hasAny(['search','status','client_id','executor_id','mostrar_fechados']))
            <a href="{{ route('tickets.index') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
        @endif
    </form>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 text-sm font-semibold rounded"
             style="background:rgba(5,150,105,.08); border:1px solid rgba(5,150,105,.2); color:#059669">
            {{ session('success') }}
        </div>
    @endif

    <div id="tickets-results">
        @include('tickets._results')
    </div>

</x-app-layout>
