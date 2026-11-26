{{-- Janela de Novidades no primeiro acesso do dia (AppUpdate::popupPara).
     Abre 1x por dia, só se tiver novidade que a pessoa ainda não viu. Destaques
     (is_featured) vêm grandes e com detalhes; o resto, lista curta.
     "Entendi" conta como lido (some o contador da barra lateral). --}}
@php $_popup = \App\Models\AppUpdate::popupPara(auth()->user()); @endphp
@if($_popup)
<div x-data="{
        open: true,
        close() {
            this.open = false;
            fetch(@js(route('app-updates.popup-seen')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } });
        }
     }"
     x-show="open" x-transition.opacity @keydown.escape.window="close()"
     class="fixed inset-0 flex items-center justify-center p-4" style="z-index:90; background:rgba(15,10,25,.45)"
     role="dialog" aria-modal="true" aria-labelledby="novidades-popup-titulo">
    <div class="card w-full flex flex-col" style="max-width:560px; max-height:calc(100vh - 32px); padding:0" @click.outside="close()">
        <div class="px-5 pt-5 pb-3 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--purple)">Novidades no App</p>
                <h2 id="novidades-popup-titulo" class="text-lg font-semibold mt-1" style="color:var(--text)">O que mudou desde sua última visita</h2>
            </div>
            <button type="button" @click="close()" class="p-1" style="color:var(--muted)" aria-label="Fechar"><x-icon name="x" size="18" /></button>
        </div>

        <div class="px-5 pb-2 overflow-y-auto flex flex-col gap-3">
            @foreach($_popup['destaques'] as $u)
                <div class="rounded p-4" style="background:rgba(100,59,142,.06); border:1px solid rgba(100,59,142,.2); border-radius:var(--radius)">
                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                        <span class="badge badge-orange" style="font-size:10px">destaque</span>
                        <span class="text-xs font-semibold" style="color:{{ $u->kindColor() }}">{{ $u->kindLabel() }}</span>
                        @if($u->area)<span class="text-xs" style="color:var(--muted)">· {{ $u->area }}</span>@endif
                    </div>
                    <p class="font-semibold" style="color:var(--text)">{{ $u->title }}</p>
                    @if($u->summary)<p class="text-sm mt-1" style="color:var(--muted2)">{{ $u->summary }}</p>@endif
                    @if($u->body)<div class="mt-2 text-sm"><x-rich-content :value="$u->body" /></div>@endif
                </div>
            @endforeach

            @if($_popup['outras']->isNotEmpty())
                <ul class="flex flex-col">
                    @foreach($_popup['outras'] as $u)
                        <li class="py-2.5 flex gap-3" style="{{ $loop->first ? '' : 'border-top:1px solid var(--border)' }}">
                            <span class="mt-1.5 flex-shrink-0 rounded-full" style="width:8px; height:8px; background:{{ $u->kindColor() }}" title="{{ $u->kindLabel() }}"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $u->title }}</p>
                                @if($u->summary)<p class="text-xs mt-0.5" style="color:var(--muted2); line-height:1.5">{{ $u->summary }}</p>@endif
                                <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $u->kindLabel() }}@if($u->area) · {{ $u->area }}@endif · {{ $u->chegouEm()->locale('pt_BR')->translatedFormat('d \d\e M') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap" style="border-top:1px solid var(--border)">
            <a href="{{ route('app-updates.index') }}" class="text-sm font-semibold" style="color:var(--purple)">
                @if($_popup['total'] > $_popup['destaques']->count() + $_popup['outras']->count())
                    Ver todas ({{ $_popup['total'] }})
                @else
                    Ver na página de Novidades
                @endif
            </a>
            <button type="button" class="btn btn-primary btn-sm" @click="close()">Entendi</button>
        </div>
    </div>
</div>
@endif
