<x-app-layout>
    <x-slot name="header">Editar Cliente</x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('clients.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Clientes</a>
            <span style="color:var(--border2)">/</span>
            <a href="{{ route('clients.show', $client) }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">{{ $client->displayName() }}</a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">Editar</span>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('clients.update', $client) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('clients.partials.form')

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('clients.show', $client) }}"
                   class="px-5 py-2.5 text-sm font-bold"
                   style="color:var(--muted); border:1px solid var(--border2)">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2.5 text-sm font-bold text-white"
                    style="background:var(--grad)">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
