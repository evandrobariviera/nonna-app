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
          style="border-bottom:1px solid var(--border2)">

        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por título…"
            class="filter-select" style="cursor:text; min-width:220px">
        <button type="submit" class="btn btn-ghost btn-sm">🔍 Buscar</button>

        <select name="status" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="client_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <select name="executor_id" onchange="this.form.submit()" class="filter-select">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('executor_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>

        <div class="flex items-center gap-2 pb-0.5">
            <input type="checkbox" name="mostrar_fechados" value="1" id="chk_mostrar_fechados"
                onchange="this.form.submit()"
                {{ request()->boolean('mostrar_fechados') ? 'checked' : '' }}
                class="w-4 h-4" style="accent-color:var(--purple)">
            <label for="chk_mostrar_fechados" class="text-sm font-medium cursor-pointer" style="color:var(--muted)">
                Mostrar concluídos/cancelados
            </label>
        </div>

        @if(request()->hasAny(['search','status','client_id','executor_id','mostrar_fechados']))
            <a href="{{ route('tickets.index') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
        @endif

        <span class="ml-auto text-sm" style="color:var(--muted)">
            {{ $tickets->total() }} ticket{{ $tickets->total() !== 1 ? 's' : '' }}
        </span>
    </form>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 text-sm font-semibold rounded"
             style="background:rgba(5,150,105,.08); border:1px solid rgba(5,150,105,.2); color:#059669">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="taskBulk()" x-cloak>
        @include('partials._task-bulk-bar')

        {{-- TABELA --}}
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    @include('partials._task-thead')
                    <tbody>
                        @forelse($tickets as $ticket)
                            @include('partials._task-tr', ['task' => $ticket, 'context' => 'ticket'])
                        @empty
                            <tr>
                                <td colspan="12">
                                    <div class="tab-placeholder">
                                        <div class="tab-placeholder-icon">🎫</div>
                                        <div class="tab-placeholder-title">Nenhum ticket encontrado</div>
                                        <div class="tab-placeholder-desc">Ajuste os filtros ou crie um novo ticket.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>{{-- /x-data taskBulk --}}

    <div class="mt-4">{{ $tickets->links() }}</div>

</x-app-layout>
