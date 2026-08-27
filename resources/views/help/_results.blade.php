{{-- Fragmento reaproveitado no load inicial (help.index) e na busca dinâmica via AJAX
     (HelpArticleController::results(), fetch disparado por live-filter.js). --}}
@if($articlesByCategory->isEmpty())
    <div class="card px-5 py-8 text-center">
        <p class="text-sm" style="color:var(--muted)">
            @if(request('q'))
                Nenhum artigo encontrado pra "{{ request('q') }}".
            @else
                Nenhum artigo ainda — crie o primeiro.
            @endif
        </p>
    </div>
@else
    <div class="flex flex-col gap-5">
        @foreach($articlesByCategory as $category => $articles)
            <div class="card px-5 py-4">
                <h4 class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--purple)">
                    {{ $category }} ({{ $articles->count() }})
                </h4>
                <div class="flex flex-col gap-1.5">
                    @foreach($articles as $article)
                        <a href="{{ route('help.show', $article) }}"
                           class="flex items-center justify-between gap-3 px-3 py-2.5 transition-colors"
                           style="background:var(--s2)"
                           onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                            <span class="text-sm font-semibold" style="color:var(--text)">{{ $article->title }}</span>
                            <span class="text-xs font-mono flex-none" style="color:var(--muted)">
                                atualizado {{ $article->updated_at->diffForHumans() }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
