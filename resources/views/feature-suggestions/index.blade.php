<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Equipe</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Sugestões de Melhoria</h1>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">

        {{-- Adição rápida --}}
        <form method="POST" action="{{ route('feature-suggestions.store') }}" class="card p-4 mb-6" x-data="{ details: false }">
            @csrf
            <div class="flex items-center gap-2">
                <input type="text" name="title" required autofocus placeholder="Nova sugestão..."
                    class="flex-1 px-3 py-2 text-sm focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Adicionar</button>
            </div>

            <button type="button" @click="details = !details" x-text="details ? '- detalhes' : '+ detalhes'"
                class="text-xs font-mono mt-2" style="color:var(--muted)"></button>

            <div x-show="details" x-cloak class="mt-2">
                <textarea name="description" rows="3" placeholder="Detalhes (opcional)..."
                    class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
            </div>
        </form>

        {{-- Lista --}}
        <div class="card">
            @if($suggestions->isEmpty())
                <div class="px-6 py-16 text-center" style="color:var(--muted)">
                    <p class="text-sm">Nenhuma sugestão ainda. Seja o primeiro a anotar uma ideia.</p>
                </div>
            @else
                @foreach($suggestions as $s)
                    <div class="group px-4 py-3 flex items-start gap-3" style="border-bottom:1px solid var(--border)"
                         x-data="{ done: {{ $s->done ? 'true' : 'false' }} }">
                        <input type="checkbox" x-model="done" @change="
                                inlinePatch('{{ route('feature-suggestions.update-status', $s) }}', { done: done })
                                    .then(ok => { if (!ok) { done = !done; alert('Falha ao salvar. Atualize a página e tente novamente.'); } })
                            "
                            class="mt-1 flex-shrink-0" style="accent-color:var(--purple); width:16px; height:16px; cursor:pointer">

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium" :class="done ? 'opacity-50' : ''"
                               :style="done ? 'text-decoration:line-through; color:var(--muted2)' : 'color:var(--text)'">
                                {{ $s->title }}
                            </p>
                            @if($s->description)
                                <p class="text-xs mt-1" style="color:var(--muted2); white-space:pre-line">{{ $s->description }}</p>
                            @endif
                            <p class="text-xs font-mono mt-1.5" style="color:var(--muted)">
                                {{ $s->createdBy?->name ?? '—' }} · {{ $s->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('feature-suggestions.destroy', $s) }}"
                              class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0"
                              @submit.prevent="if (await $store.confirmDialog.ask('Excluir esta sugestão?')) $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-xs">Excluir</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>

    </div>

</x-app-layout>
