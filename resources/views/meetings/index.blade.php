<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Atendimento</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Agenda</h1>
            </div>
            <a href="{{ route('meetings.create') }}"
               class="flex items-center gap-2 px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
               style="background:var(--purple)">
                + Nova Reunião
            </a>
        </div>
    </x-slot>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('meetings.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
          data-live-filter data-results-url="{{ route('meetings.results') }}" data-target="#meetings-results">
        <select name="status"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Meeting::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="type"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os tipos</option>
            @foreach(\App\Models\Meeting::$types as $key => $label)
                <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="client_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        @if(request()->hasAny(['status','type','client_id']))
            <a href="{{ route('meetings.index') }}" class="btn btn-ghost btn-sm">
                Limpar
            </a>
        @endif
    </form>

    {{-- Tabela --}}
    <div id="meetings-results">
        @include('meetings._results')
    </div>

</x-app-layout>
