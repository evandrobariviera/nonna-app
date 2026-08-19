{{-- Filtros + toggle Kanban/Lista compartilhados entre a Central de Leads
     interna (leads/index.blade.php) e a do Portal (portal/leads/index.blade.php).
     Espera: $view, $indexRoute, $resultsRoute, $resultsTarget, $showClientFilter,
     $clients (só quando $showClientFilter), $channels, $sources. --}}
@php
    $filterKeys = ['client_id', 'stage', 'channel_id', 'source_id', 'utm_source', 'date_from', 'date_to', 'q'];
    $viewParams = fn ($v) => array_merge(request()->except(['view']), ['view' => $v]);
    $liveOrSubmit = $view === 'lista' ? "this.form._liveFilterRefresh && this.form._liveFilterRefresh()" : 'this.form.submit()';
@endphp

<div class="flex items-center gap-2 mb-4">
    <a href="{{ route($indexRoute, $viewParams('kanban')) }}"
       class="btn btn-sm {{ $view === 'kanban' ? '' : 'btn-ghost' }}"
       @if($view === 'kanban') style="background:var(--purple); color:#fff" @endif>
        Kanban
    </a>
    <a href="{{ route($indexRoute, $viewParams('lista')) }}"
       class="btn btn-sm {{ $view === 'lista' ? '' : 'btn-ghost' }}"
       @if($view === 'lista') style="background:var(--purple); color:#fff" @endif>
        Lista
    </a>
</div>

<form method="GET" action="{{ route($indexRoute) }}" class="flex flex-wrap items-end gap-3 mb-5"
      @if($view === 'lista')
          data-live-filter data-results-url="{{ route($resultsRoute) }}" data-target="{{ $resultsTarget }}"
      @endif>
    <input type="hidden" name="view" value="{{ $view }}">

    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nome, telefone ou e-mail…"
           class="filter-select" style="cursor:text; min-width:200px">

    @if($showClientFilter)
        <select name="client_id" @if($view !== 'lista') onchange="this.form.submit()" @endif class="filter-select">
            <option value="">Todos os clientes</option>
            <option value="sem_cliente" {{ request('client_id') === 'sem_cliente' ? 'selected' : '' }}>Sem cliente (triagem)</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->displayName() }}</option>
            @endforeach
        </select>
    @endif

    <select name="stage" @if($view !== 'lista') onchange="this.form.submit()" @endif class="filter-select">
        <option value="">Todos os estágios</option>
        @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
            <option value="{{ $key }}" {{ request('stage') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
        @endforeach
    </select>

    <select name="channel_id" @if($view !== 'lista') onchange="this.form.submit()" @endif class="filter-select">
        <option value="">Todas as plataformas</option>
        @foreach($channels as $ch)
            <option value="{{ $ch->id }}" {{ request('channel_id') === $ch->id ? 'selected' : '' }}>{{ $ch->name }}</option>
        @endforeach
    </select>

    <select name="source_id" @if($view !== 'lista') onchange="this.form.submit()" @endif class="filter-select">
        <option value="">Todas as origens</option>
        @foreach($sources as $src)
            <option value="{{ $src->id }}" {{ request('source_id') === $src->id ? 'selected' : '' }}>
                @if($showClientFilter && $src->client){{ $src->client->displayName() }} — @endif{{ $src->displayName() }}
            </option>
        @endforeach
    </select>

    <input type="text" name="utm_source" value="{{ request('utm_source') }}" placeholder="UTM Source…"
           class="filter-select" style="cursor:text; min-width:130px">

    <div class="flex items-center gap-1">
        <label class="text-xs" style="color:var(--muted)">de</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}"
               onchange="{{ $liveOrSubmit }}" class="filter-select" style="padding:6px 8px">
        <label class="text-xs" style="color:var(--muted)">até</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}"
               onchange="{{ $liveOrSubmit }}" class="filter-select" style="padding:6px 8px">
    </div>

    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>

    @if(request()->hasAny($filterKeys))
        <a href="{{ route($indexRoute, ['view' => $view]) }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
    @endif
</form>
