<x-app-layout>
    <x-slot name="header">
        <span class="text-base font-bold" style="color:var(--text)">Tarefas</span>
    </x-slot>

    {{-- BARRA DE FILTROS --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          class="flex items-center gap-2 flex-wrap mb-5 pb-4"
          style="border-bottom:1px solid var(--border2)"
          data-live-filter data-results-url="{{ route('tasks.results') }}" data-target="#tasks-results">

        <input type="hidden" name="mostrar_concluidos" value="{{ request('mostrar_concluidos') }}">
        <input type="hidden" name="sem_projeto" value="{{ request('sem_projeto') }}">

        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por título…"
            class="filter-select" style="cursor:text; min-width:220px">

        <select name="status" class="filter-select">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                    {{ $s['label'] }}
                </option>
            @endforeach
        </select>

        <select name="client_id" class="filter-select">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>
                    {{ $c->company_name }}
                </option>
            @endforeach
        </select>

        <select name="executor_id" class="filter-select">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
            @endforeach
        </select>

        <select name="sprint_id" class="filter-select">
            <option value="">Todas as sprints</option>
            <option value="sem_sprint" {{ request()->boolean('sem_sprint') ? 'selected' : '' }}>— Backlog (sem sprint) —</option>
            @foreach($sprints as $s)
                <option value="{{ $s->id }}" {{ request('sprint_id') === $s->id ? 'selected' : '' }}>
                    {{ $s->title }}
                </option>
            @endforeach
        </select>

        @if(request()->hasAny(['search','status','client_id','executor_id','sprint_id','sem_sprint','mostrar_concluidos']))
            <a href="{{ route('tasks.index') }}"
               class="btn btn-ghost btn-sm">
                ✕ Limpar
            </a>
        @endif

        @php
            $toggleParams = request()->except('mostrar_concluidos', 'page');
            if (!request()->boolean('mostrar_concluidos')) $toggleParams['mostrar_concluidos'] = '1';
        @endphp
        <a href="{{ route('tasks.index', $toggleParams) }}"
           class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
           style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_concluidos') ? 'var(--purple)' : 'var(--muted)' }}">
            {{ request()->boolean('mostrar_concluidos') ? '⊙ Ocultar finalizados' : '○ Mostrar finalizados' }}
        </a>

        @php
            $semProjetoParams = request()->except('sem_projeto', 'page');
            if (!request()->boolean('sem_projeto')) $semProjetoParams['sem_projeto'] = '1';
        @endphp
        <a href="{{ route('tasks.index', $semProjetoParams) }}"
           class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
           style="border:1px solid var(--border2); color:{{ request()->boolean('sem_projeto') ? 'var(--purple)' : 'var(--muted)' }}">
            {{ request()->boolean('sem_projeto') ? '⊙ Só sem projeto' : '○ Só sem projeto' }}
        </a>

    </form>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 text-sm font-semibold rounded"
             style="background:rgba(5,150,105,.08); border:1px solid rgba(5,150,105,.2); color:#059669">
            {{ session('success') }}
        </div>
    @endif

    <div id="tasks-results">
        @include('tasks._results')
    </div>

</x-app-layout>
