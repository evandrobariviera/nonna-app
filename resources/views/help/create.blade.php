<x-app-layout>
    <x-slot name="header">Novo Artigo — Central de Ajuda</x-slot>

    <div class="mb-5">
        <a href="{{ route('help.index') }}"
           class="text-xs transition-colors" style="color:var(--muted)"
           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
            ← Central de Ajuda
        </a>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 text-sm"
             style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form action="{{ route('help.store') }}" method="POST" style="max-width:860px">
        @csrf

        <div class="card px-6 py-5">
            <div class="grid gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TÍTULO *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required autofocus
                           placeholder="Ex: Fluxo de Reuniões e Agendamento"
                           class="w-full px-3 py-2.5 text-sm focus:outline-none"
                           style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CATEGORIA</label>
                    <input type="text" name="category" value="{{ old('category') }}" list="category-options"
                           placeholder="Ex: Reuniões, Automações, Financeiro..."
                           class="w-full px-3 py-2.5 text-sm focus:outline-none"
                           style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    <datalist id="category-options">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CONTEÚDO</label>
                    <x-rich-editor name="body" :value="old('body')" min-height="320px" />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 mt-5">
            <button type="submit"
                class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                style="background:var(--purple)">
                Criar Artigo
            </button>
            <a href="{{ route('help.index') }}" class="btn btn-ghost btn-sm">Cancelar</a>
        </div>
    </form>

</x-app-layout>
