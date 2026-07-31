<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Rotina</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Campanhas</h1>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('campaigns.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
          data-live-filter data-results-url="{{ route('campaigns.results') }}" data-target="#campaigns-results">
        <select name="client_id" onchange="this.form.ad_campaign_id.value=''"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ $clientId === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <select name="ad_campaign_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todas as campanhas</option>
            @foreach($campaignOptions as $opt)
                <option value="{{ $opt->id }}" {{ $campaignId === $opt->id ? 'selected' : '' }}>
                    {{ $clientId ? $opt->name : ($opt->adAccount?->client?->company_name . ' — ' . $opt->name) }}
                </option>
            @endforeach
        </select>

        <select name="platform" onchange="this.form.ad_campaign_id.value=''"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todas as plataformas</option>
            <option value="meta" {{ $platform === 'meta' ? 'selected' : '' }}>Meta Ads</option>
            <option value="google" {{ $platform === 'google' ? 'selected' : '' }}>Google Ads</option>
        </select>

        <select name="status" onchange="this.form.ad_campaign_id.value=''"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="" {{ $statusFilter === '' ? 'selected' : '' }}>Todos os status</option>
            @foreach(\App\Http\Controllers\CampaignController::$campaignStatuses as $key => $label)
                <option value="{{ $key }}" {{ $statusFilter === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="period"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            @foreach(\App\Http\Controllers\CampaignController::$periods as $key => $label)
                <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="group_by"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="" {{ $groupBy === '' ? 'selected' : '' }}>Sem agrupamento</option>
            <option value="cliente" {{ $groupBy === 'cliente' ? 'selected' : '' }}>Agrupar por Cliente</option>
            <option value="situacao" {{ $groupBy === 'situacao' ? 'selected' : '' }}>Agrupar por Situação</option>
        </select>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="mostrar_inativos" value="1" id="chk_mostrar_inativos"
                {{ request()->boolean('mostrar_inativos') ? 'checked' : '' }}
                class="w-4 h-4" style="accent-color:var(--muted)">
            <label for="chk_mostrar_inativos" class="text-xs font-medium cursor-pointer" style="color:var(--muted2)">
                Mostrar clientes inativos
            </label>
        </div>

        @if(request()->hasAny(['client_id', 'ad_campaign_id', 'platform', 'status', 'period', 'group_by', 'mostrar_inativos']))
            <a href="{{ route('campaigns.index') }}" class="btn btn-ghost btn-sm">
                Limpar
            </a>
        @endif
    </form>

    <div id="campaigns-results">
        @include('campaigns._results')
    </div>
</x-app-layout>
