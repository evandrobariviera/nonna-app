@php $visibleComments = $task->comments->where('visible_to_client', true)->sortBy('created_at'); @endphp

<div class="card p-6" id="comentarios">
    <h2 class="text-sm font-bold uppercase tracking-wide mb-4" style="color: var(--muted)">
        Comentários
        @if($visibleComments->count() > 0)
            <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full" style="background:var(--s3); color:var(--muted2)">{{ $visibleComments->count() }}</span>
        @endif
    </h2>

    @if($visibleComments->isNotEmpty())
        <div class="flex flex-col mb-5">
            @foreach($visibleComments as $comment)
                <div class="py-3" style="{{ !$loop->last ? 'border-bottom:1px solid var(--border2)' : '' }}">
                    <div class="flex items-baseline gap-3 mb-1 flex-wrap">
                        <span class="text-sm font-semibold" style="color: var(--text)">{{ $comment->user->name }}</span>
                        <span class="text-xs" style="color: var(--muted)">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.65">{{ $comment->body }}</p>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm mb-5" style="color: var(--muted)">Nenhum comentário ainda.</p>
    @endif

    <form method="POST" action="{{ route('portal.tasks.comments.store', $task) }}"
          x-data="{ body: '' }">
        @csrf
        <textarea name="body" x-model="body" rows="3"
                  placeholder="Escreva um comentário..."
                  class="w-full px-4 py-3 text-sm focus:outline-none resize-none rounded-lg"
                  style="background: var(--s2); border: 1px solid var(--border2); color: var(--text); line-height: 1.65"
                  onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'"></textarea>
        <div class="flex justify-end mt-2" x-show="body.trim().length > 0" x-cloak>
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                    style="background: var(--purple)"
                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                Comentar
            </button>
        </div>
    </form>
</div>
