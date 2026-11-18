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
                class="field flex-1 min-w-0 px-4 py-2 text-sm font-medium"
                style="outline:none; max-width:360px">
        </form>

        <a href="{{ route('help.create') }}" class="btn btn-primary flex items-center gap-2">
            <x-icon name="plus" size="16" />
            Novo Artigo
        </a>
    </div>

    @if($documentMap && $documentMap->total > 0)
        <a href="{{ route('help.documents.index') }}"
           class="card px-5 py-4 mb-5 flex items-center gap-4 transition-colors"
           style="border-left:3px solid var(--purple)"
           onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
            <span class="flex-none flex items-center justify-center" style="width:36px; height:36px; border-radius:var(--radius); background:var(--s3); color:var(--purple)">
                <x-icon name="map" size="18" />
            </span>
            <span class="flex-1 min-w-0">
                <span class="block text-sm font-bold" style="color:var(--text)">Mapa dos Documentos</span>
                <span class="block text-xs" style="color:var(--muted2)">Os documentos padrão da agência por etapa, com modelo e manual de cada um.</span>
            </span>
            <span class="flex-none text-xs font-mono" style="color:var(--muted)">{{ $documentMap->fechados }} de {{ $documentMap->total }} fechados</span>
        </a>
    @endif

    <div id="help-results">
        @include('help._results')
    </div>

</x-app-layout>
