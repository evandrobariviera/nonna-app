<x-portal-layout>
    <x-slot name="title">Novo Chamado</x-slot>

    <div class="mb-6">
        <a href="{{ route('portal.tickets.index') }}" class="text-xs font-semibold" style="color: var(--muted)">← Chamados</a>
    </div>

    <div class="max-w-xl">
        <h1 class="text-2xl font-black mb-1" style="color: var(--text)">Novo Chamado</h1>
        <p class="text-sm mb-6" style="color: var(--muted)">Abra uma solicitação para a equipe da Nonna.</p>

        <form method="POST" action="{{ route('portal.tickets.store') }}" enctype="multipart/form-data" class="card p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Título</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="300"
                       placeholder="Resumo do que você precisa"
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                @error('title')
                    <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Categoria</label>
                <select name="task_type" required
                        class="w-full rounded-lg border px-3 py-2 text-sm"
                        style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    <option value="">Selecione...</option>
                    @foreach(\App\Models\Task::$types as $value => $label)
                        <option value="{{ $value }}" @selected(old('task_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('task_type')
                    <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Urgência</label>
                <select name="priority"
                        class="w-full rounded-lg border px-3 py-2 text-sm"
                        style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    @foreach(\App\Models\Task::$priorities as $value => $p)
                        <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $p['label'] }}</option>
                    @endforeach
                </select>
                @error('priority')
                    <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Descrição</label>
                <textarea name="description" rows="5"
                          placeholder="Detalhe sua solicitação..."
                          class="w-full rounded-lg border px-3 py-2 text-sm resize-none"
                          style="background: var(--s2); border-color: var(--border2); color: var(--text)">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Anexos</label>
                <input type="file" name="files[]" multiple
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                <p class="mt-1 text-xs" style="color: var(--muted)">Opcional — imagens, documentos, o que ajudar a explicar o pedido. Até 100 MB por arquivo.</p>
                @error('files.*')
                    <p class="mt-1 text-xs" style="color: var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold">
                Abrir Chamado
            </button>
        </form>
    </div>

</x-portal-layout>
