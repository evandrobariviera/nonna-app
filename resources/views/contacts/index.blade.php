<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">CRM</p>
                <h1 class="text-2xl font-black text-[var(--text)]">Contatos</h1>
            </div>
            <a href="{{ route('contacts.create') }}" class="btn btn-primary">
                + Novo Contato
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-6">

        {{-- Filtros --}}
        <form method="GET" action="{{ route('contacts.index') }}" class="flex items-center gap-3 mb-6"
              data-live-filter data-results-url="{{ route('contacts.results') }}" data-target="#contacts-results">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nome, e-mail, empresa..."
                   class="flex-1 bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2 focus:outline-none focus:border-[var(--purple)] placeholder:text-[var(--muted)]">

            <select name="status"
                    class="bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Contact::$statuses as $key => $meta)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                        {{ $meta['label'] }}
                    </option>
                @endforeach
            </select>

            <select name="source"
                    class="bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                <option value="">Todas as origens</option>
                @foreach(\App\Models\Contact::$sources as $key => $label)
                    <option value="{{ $key }}" {{ request('source') === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            @if(request()->hasAny(['q', 'status', 'source']))
                <a href="{{ route('contacts.index') }}"
                   class="px-3 py-2 text-xs font-mono text-[var(--muted)] hover:text-[var(--text)] transition-colors">
                    Limpar
                </a>
            @endif
        </form>

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-4 px-4 py-3 text-sm border" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 text-sm border" style="background: rgba(220,38,38,.06); border-color: rgba(220,38,38,.25); color: var(--red);">
                {{ session('error') }}
            </div>
        @endif

        {{-- Tabela --}}
        <div id="contacts-results">
            @include('contacts._results')
        </div>

    </div>
</x-app-layout>
