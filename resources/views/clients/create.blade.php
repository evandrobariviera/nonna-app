<x-app-layout>
    <x-slot name="header">Novo Cliente</x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('clients.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                ← Clientes
            </a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">Novo Cliente</span>
        </div>

        <form method="POST" action="{{ route('clients.store') }}" enctype="multipart/form-data">
            @csrf
            @include('clients.partials.form', ['client' => new App\Models\Client()])

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('clients.index') }}"
                   class="px-5 py-2.5 text-sm font-bold"
                   style="color:var(--muted); border:1px solid var(--border2)">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2.5 text-sm font-bold text-white"
                    style="background:var(--grad)">
                    Criar Cliente
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
