<x-app-layout>
    <x-slot name="header">Editar novidade</x-slot>

    <div class="mb-5">
        <a href="{{ route('app-updates.index') }}" class="text-xs" style="color:var(--muted)">← Novidades do App</a>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 text-sm"
             style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form action="{{ route('app-updates.update', $update) }}" method="POST" style="max-width:860px">
        @csrf @method('PATCH')
        @include('novidades._form', ['update' => $update, 'areas' => $areas])

        <div class="flex items-center gap-4 mt-5">
            <button type="submit"
                    class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                    style="background:var(--purple)">
                Salvar alterações
            </button>
            <a href="{{ route('app-updates.index') }}" class="btn btn-ghost btn-sm">Cancelar</a>
        </div>
    </form>
</x-app-layout>
