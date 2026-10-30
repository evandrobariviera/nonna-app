{{-- Coluna "Discussões" da entrada do Portal (PortalDiscussionFeed). --}}
<aside class="w-full lg:w-80 flex-shrink-0">
    <div class="card p-5">
        <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">Discussões</h2>
        <p class="text-xs mt-1 mb-3" style="color: var(--muted)">O que estamos conversando sobre o seu trabalho.</p>

        @forelse($discussions as $d)
            <div class="py-3" x-data="{ all: false }" style="border-top:1px solid var(--border2)">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-semibold truncate" style="color: var(--text)">{{ $d['author'] }}</span>
                    <span class="text-xs flex-shrink-0" style="color: var(--muted)" title="{{ $d['at']->format('d/m/Y H:i') }}">{{ $d['at']->diffForHumans(short: true) }}</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--text); line-height:1.5; overflow-wrap:anywhere"
                   :style="all ? '' : 'display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden'">{{ $d['text'] }}</p>
                @if(mb_strlen($d['text']) > 160)
                    <button type="button" class="text-xs font-semibold mt-1" style="color: var(--purple)" @click="all = !all" x-text="all ? 'Ver menos' : 'Ver tudo'"></button>
                @endif
                @if($d['url'])
                    <a href="{{ $d['url'] }}" class="block text-xs mt-1.5 truncate" style="color: var(--purple)">{{ $d['place'] }} →</a>
                @else
                    <p class="text-xs mt-1.5 truncate" style="color: var(--muted)">{{ $d['place'] }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm py-3" style="color: var(--muted); border-top:1px solid var(--border2)">As conversas sobre o seu trabalho vão aparecer aqui.</p>
        @endforelse
    </div>
</aside>
