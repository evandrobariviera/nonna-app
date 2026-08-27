<x-app-layout>
    <x-slot name="header">Central de Ajuda</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
        <form method="GET" action="{{ route('help.index') }}" class="flex items-center gap-3 flex-1 min-w-0"
              data-live-filter data-results-url="{{ route('help.results') }}" data-target="#help-results">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar artigo..."
                class="flex-1 min-w-0 px-4 py-2 text-sm font-medium"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text); outline:none; max-width:360px">
        </form>

        <a href="{{ route('help.create') }}" class="btn btn-primary flex items-center gap-2">
            <x-icon name="plus" size="16" />
            Novo Artigo
        </a>
    </div>

    <div id="help-results">
        @include('help._results')
    </div>

</x-app-layout>
