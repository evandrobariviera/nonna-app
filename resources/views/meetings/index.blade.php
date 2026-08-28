<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Atendimento</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Agenda</h1>
            </div>
        </div>
    </x-slot>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Toggle Lista / Calendário / Quadros --}}
    <div class="flex items-center gap-2 mb-5">
        @php($viewParams = fn ($v) => array_merge(request()->except(['view', 'month']), ['view' => $v]))
        <a href="{{ route('meetings.index', $viewParams('lista')) }}"
           class="btn btn-sm {{ $view === 'lista' ? '' : 'btn-ghost' }}"
           @if($view === 'lista') style="background:var(--purple); color:#fff" @endif>
            Lista
        </a>
        <a href="{{ route('meetings.index', $viewParams('calendario')) }}"
           class="btn btn-sm {{ $view === 'calendario' ? '' : 'btn-ghost' }}"
           @if($view === 'calendario') style="background:var(--purple); color:#fff" @endif>
            Calendário
        </a>
        <a href="{{ route('meetings.index', $viewParams('quadros')) }}"
           class="btn btn-sm {{ $view === 'quadros' ? '' : 'btn-ghost' }}"
           @if($view === 'quadros') style="background:var(--purple); color:#fff" @endif>
            Quadros
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('meetings.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
          @if($view === 'lista')
              data-live-filter data-results-url="{{ route('meetings.results') }}" data-target="#meetings-results"
          @endif>
        <input type="hidden" name="view" value="{{ $view }}">
        @if($view === 'calendario' && request('month'))
            <input type="hidden" name="month" value="{{ request('month') }}">
        @endif

        @unless($view === 'quadros')
            <select name="status" @if($view !== 'lista') onchange="this.form.submit()" @endif
                class="w-full sm:w-auto"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Meeting::$statuses as $key => $s)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
        @endunless

        <select name="type" @if($view !== 'lista') onchange="this.form.submit()" @endif
            class="w-full sm:w-auto"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os tipos</option>
            @foreach(\App\Models\Meeting::$types as $key => $label)
                <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="client_id" @if($view !== 'lista') onchange="this.form.submit()" @endif
            class="w-full sm:w-auto"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->displayName() }}</option>
            @endforeach
        </select>

        @if(request()->hasAny(['status','type','client_id']))
            <a href="{{ route('meetings.index', ['view' => $view]) }}" class="btn btn-ghost btn-sm">
                Limpar
            </a>
        @endif
    </form>

    @if($view === 'lista')
        {{-- Tabela --}}
        <div id="meetings-results">
            @include('meetings._results')
        </div>
    @elseif($view === 'calendario')
        @include('meetings._calendar')
    @else
        @include('meetings._board')
    @endif

</x-app-layout>
