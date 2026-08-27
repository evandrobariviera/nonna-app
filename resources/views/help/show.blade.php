<x-app-layout>
    <x-slot name="header">Central de Ajuda</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-5">
        <a href="{{ route('help.index') }}"
           class="text-xs transition-colors" style="color:var(--muted)"
           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
            ← Central de Ajuda
        </a>
    </div>

    <div class="card px-6 py-5" style="max-width:860px">
        <div class="flex items-start justify-between gap-4 mb-1">
            <h1 class="text-xl font-bold" style="color:var(--text)">{{ $article->title }}</h1>
            <div class="flex items-center gap-2 flex-none">
                <a href="{{ route('help.edit', $article) }}" class="btn btn-ghost btn-xs">Editar</a>
                <form method="POST" action="{{ route('help.destroy', $article) }}"
                      @submit.prevent="if (await $store.confirmDialog.ask('Remover este artigo?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--red)">Remover</button>
                </form>
            </div>
        </div>

        <div class="flex items-center gap-3 mb-6">
            @if($article->category)
                <span class="text-xs font-mono px-2 py-0.5" style="background:var(--s3); color:var(--purple); border-radius:4px">
                    {{ $article->category }}
                </span>
            @endif
            <span class="text-xs font-mono" style="color:var(--muted)">
                atualizado {{ $article->updated_at->diffForHumans() }}
                @if($article->updatedBy) por {{ $article->updatedBy->name }} @endif
            </span>
        </div>

        @if($article->body)
            <div class="ProseMirror" style="color:var(--text); line-height:1.65">{!! $article->body !!}</div>
        @else
            <p class="text-sm" style="color:var(--muted)">Artigo ainda sem conteúdo — <a href="{{ route('help.edit', $article) }}" style="color:var(--purple)">editar</a>.</p>
        @endif
    </div>

</x-app-layout>
