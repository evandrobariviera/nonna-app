<x-app-layout>
    <x-slot name="header">Clientes</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(220,38,38,.08); border:1px solid rgba(220,38,38,.25); color:var(--red)">
            {{ session('error') }}
        </div>
    @endif

    {{-- TOOLBAR --}}
    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
        <form method="GET" action="{{ route('clients.index') }}" class="flex items-center gap-3 flex-1 min-w-0"
              data-live-filter data-results-url="{{ route('clients.results') }}" data-target="#clients-results">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar empresa, CNPJ ou e-mail..."
                class="flex-1 min-w-0 px-4 py-2 text-sm font-medium"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text); outline:none; max-width:360px">

            @php $selectedStatus = request()->filled('status') ? request('status') : 'active'; @endphp
            <select name="status"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; font-family:'Syne',sans-serif; outline:none; cursor:pointer">
                <option value="todos" {{ $selectedStatus === 'todos' ? 'selected' : '' }}>Todos os status</option>
                @foreach(App\Models\Client::$statuses as $key => $s)
                    <option value="{{ $key }}" {{ $selectedStatus === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('clients.create') }}" class="btn btn-primary flex items-center gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Novo Cliente
        </a>
    </div>

    {{-- TABELA --}}
    <div id="clients-results">
        @include('clients._results')
    </div>

</x-app-layout>
