<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Insights</h1>
        </div>
    </x-slot>

    <div class="py-8 px-6">
        @include('performance._tabs')

        <p class="text-sm mb-4" style="color:var(--muted)">
            Os alertas automáticos das campanhas (custo subindo, verba parada, criativo cansado…) e as análises dos agentes de IA ("Do anúncio à venda"), num lugar só.
        </p>

        <form method="GET" action="{{ route('performance.insights') }}" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="client_id" onchange="this.form.submit()" class="filter-select">
                <option value="">Todos os clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $client?->id === $c->id ? 'selected' : '' }}>{{ $c->displayName() }}</option>
                @endforeach
            </select>
            <select name="tipo" onchange="this.form.submit()" class="filter-select">
                <option value="">Alertas e análises</option>
                <option value="analises" {{ $tipo === 'analises' ? 'selected' : '' }}>Só análises da IA</option>
                <option value="alertas" {{ $tipo === 'alertas' ? 'selected' : '' }}>Só alertas</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="filter-select">
                <option value="abertos" {{ $status === 'abertos' ? 'selected' : '' }}>Em aberto</option>
                <option value="fechados" {{ $status === 'fechados' ? 'selected' : '' }}>Resolvidos e descartados</option>
                <option value="todos" {{ $status === 'todos' ? 'selected' : '' }}>Todos</option>
            </select>
            @if(request()->hasAny(['client_id', 'tipo', 'status']))
                <a href="{{ route('performance.insights') }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
            @endif
        </form>

        @include('campaigns.partials._insights-panel', ['insights' => $insights, 'openByDefault' => true])

        {{ $insights->links() }}
    </div>
</x-app-layout>
