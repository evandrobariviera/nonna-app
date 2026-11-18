{{-- Aba Site da Central de Performance: o Relatório Analítico do Portal visto pela equipe. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Site</h1>
        </div>
    </x-slot>

    <div class="py-8 px-6">
        @include('performance._tabs')

        <p class="text-sm mb-4" style="color:var(--muted)">
            Visitas, origem das visitas e buscas no Google do site do cliente — os mesmos números que ele vê no <strong>Relatório Analítico</strong> do Portal.
        </p>

        <form method="GET" action="{{ route('performance.site') }}" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="client_id" onchange="this.form.submit()" class="filter-select">
                <option value="">Escolha um cliente</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected($client?->id === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
            @if($client)
                <select name="dias" onchange="this.form.submit()" class="filter-select">
                    @foreach(\App\Http\Controllers\Portal\SiteReportController::PERIODS as $value => $label)
                        <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="text-xs" style="color:var(--muted)">Até ontem</span>
            @endif
        </form>

        @if(!$client)
            <div class="card px-5 py-4">
                <p class="text-sm font-semibold mb-2" style="color:var(--text)">Clientes com os números do site ligados</p>
                @forelse($linkedClients as $c)
                    <a href="{{ route('performance.site', ['client_id' => $c->id]) }}" class="flex items-center justify-between gap-3 py-2 text-sm hover:underline" style="border-top:1px solid var(--border); color:var(--text)">
                        <span class="truncate">{{ $c->displayName() }}</span>
                        <span class="shrink-0" style="color:var(--purple)">Ver →</span>
                    </a>
                @empty
                    <p class="text-sm" style="color:var(--muted2)">Nenhum cliente com Google Analytics ou Search Console vinculado ainda. Vincule na aba <a href="{{ route('tracking.index') }}" class="font-semibold hover:underline" style="color:var(--purple)">Rastreamento</a> — o cliente passa a ver o Relatório Analítico no Portal na hora.</p>
                @endforelse
            </div>
        @elseif(!$tracking)
            <div class="card px-5 py-4">
                <p class="text-sm" style="color:var(--muted2)">
                    {{ $client->displayName() }} ainda não tem Google Analytics nem Search Console vinculado.
                    <a href="{{ route('tracking.index', ['client_id' => $client->id]) }}" class="font-semibold hover:underline" style="color:var(--purple)">Vincular na aba Rastreamento →</a>
                </p>
            </div>
        @else
            @include('site-report._report', ['errorText' => fn ($e) => $e ?: 'O Google não respondeu.'])
        @endif
    </div>
</x-app-layout>
