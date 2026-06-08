<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">CRM</p>
                <h1 class="text-2xl font-black text-[var(--text)]">Contatos</h1>
            </div>
            <a href="{{ route('contacts.create') }}"
               class="flex items-center gap-2 px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
               style="background: var(--purple);">
                + Novo Contato
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-6">

        {{-- Filtros --}}
        <form method="GET" action="{{ route('contacts.index') }}" class="flex items-center gap-3 mb-6">
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

            <button type="submit"
                    class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] hover:border-[var(--purple)] transition-colors">
                Filtrar
            </button>

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

        {{-- Tabela --}}
        <div class="card">
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Empresa</th>
                        <th>Contato</th>
                        <th>Origem</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contacts as $contact)
                        <tr>
                            <td>
                                <a href="{{ route('contacts.show', $contact) }}"
                                   class="font-semibold text-[var(--text)] hover:text-[var(--purple)] transition-colors">
                                    {{ $contact->name }}
                                </a>
                                @if($contact->job_title)
                                    <div class="text-xs text-[var(--muted)] mt-0.5">{{ $contact->job_title }}</div>
                                @endif
                            </td>
                            <td class="text-sm text-[var(--muted2)]">
                                {{ $contact->company_name ?: '—' }}
                            </td>
                            <td class="text-sm text-[var(--muted2)]">
                                @if($contact->email)
                                    <div>{{ $contact->email }}</div>
                                @endif
                                @if($contact->whatsapp ?: $contact->phone)
                                    <div class="text-xs text-[var(--muted)]">{{ $contact->whatsapp ?: $contact->phone }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge">
                                    {{ \App\Models\Contact::$sources[$contact->source] ?? $contact->source }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $contact->statusColor() }}">
                                    {{ $contact->statusLabel() }}
                                </span>
                            </td>
                            <td class="text-xs text-[var(--muted)] font-mono">
                                {{ $contact->created_at->format('d/m/Y') }}
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('opportunities.create', ['contact_id' => $contact->id]) }}"
                                       class="text-xs font-mono text-[var(--orange)] hover:text-[var(--text)] transition-colors whitespace-nowrap">
                                        + Oportunidade
                                    </a>
                                    <a href="{{ route('contacts.show', $contact) }}"
                                       class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">
                                        Ver →
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-16 text-[var(--muted)]">
                                <div class="text-2xl mb-3 opacity-30">👤</div>
                                <div class="text-sm font-semibold text-[var(--muted2)] mb-1">Nenhum contato cadastrado</div>
                                <div class="text-xs text-[var(--muted)]">Cadastre o primeiro lead para iniciar o funil comercial.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        @if($contacts->hasPages())
            <div class="mt-4">
                {{ $contacts->links() }}
            </div>
        @endif

    </div>
</x-app-layout>
