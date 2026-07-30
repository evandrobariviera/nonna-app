<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Comercial</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Contratos</h1>
            </div>
        </div>
    </x-slot>

    {{-- ── STATS ── --}}
    <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(4, 1fr)">
        <a href="{{ route('contracts.index') }}"
           class="card px-4 py-4 text-left transition-all"
           style="{{ !request()->hasAny(['status', 'vencidos']) ? 'border-color:var(--purple); box-shadow:0 0 0 1px var(--purple)' : '' }}">
            <div class="text-2xl font-black mb-1" style="color:var(--text)">{{ $stats['total_ativos'] }}</div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Contratos ativos</div>
        </a>

        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--purple)">
                R$ {{ number_format($stats['fee_mensal_total'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Fee mensal total</div>
        </div>

        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--green)">
                R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Valor total (ativos)</div>
        </div>

        <a href="{{ route('contracts.index', ['vencidos' => 1]) }}"
           class="card px-4 py-4 text-left transition-all"
           style="{{ request()->boolean('vencidos') ? 'border-color:var(--red); box-shadow:0 0 0 1px var(--red)' : '' }}">
            <div class="text-2xl font-black mb-1" style="color:var(--red)">{{ $stats['vencidos'] }}</div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Vencidos</div>
        </a>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('contracts.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
          data-live-filter data-results-url="{{ route('contracts.results') }}" data-target="#contracts-results">
        <input type="hidden" name="vencidos" value="{{ request('vencidos') }}">
        <select name="status"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Contract::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        @if(request()->hasAny(['status', 'vencidos']))
            <a href="{{ route('contracts.index') }}" class="btn btn-ghost btn-sm">
                Limpar
            </a>
        @endif
    </form>

    <div id="contracts-results">
        @include('contracts._results')
    </div>
</x-app-layout>
