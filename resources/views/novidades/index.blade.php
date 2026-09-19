{{-- Novidades do App — linha do tempo do que muda no sistema, escrita pra equipe ler.
     Deliberadamente fora do sino: notificação é evento dirigido a alguém e some; isto
     fica, e é o lugar de consultar "o que mudou no app ultimamente". --}}
<x-app-layout>
    <x-slot name="header">Novidades do App</x-slot>

    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <p class="text-sm" style="color:var(--muted); max-width:560px">
            O que mudou no sistema, do mais recente pro mais antigo. Sempre que sai uma melhoria,
            ela aparece aqui — assim todo mundo sabe o que está diferente sem precisar descobrir usando.
        </p>
        @if($podeEscrever)
            <a href="{{ route('app-updates.create') }}"
               class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
               style="background:var(--purple)">
                + Nova novidade
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @if($total === 0)
        <div class="tab-placeholder" style="min-height:320px">
            <p class="tab-placeholder-title">Nada publicado ainda</p>
            <p class="tab-placeholder-desc" style="margin-top:8px; max-width:380px">
                Quando a primeira melhoria for registrada, ela aparece aqui pra toda a equipe.
            </p>
        </div>
    @endif

    @foreach($porMes as $mes => $entradas)
        <p class="text-xs font-semibold uppercase tracking-widest mb-3 mt-6 first:mt-0"
           style="color:var(--muted); letter-spacing:.1em">{{ $mes }}</p>

        <div class="flex flex-col gap-3">
            @foreach($entradas as $u)
                @php
                    // "Novo pra você" = publicado depois da última vez que essa pessoa abriu a tela.
                    $novo = $u->published_at && (! $vistoAte || $u->published_at->gt($vistoAte));
                @endphp
                <div class="card px-5 py-4"
                     style="{{ $novo ? 'border-left:3px solid var(--purple)' : '' }}">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span class="text-xs font-semibold px-2 py-0.5"
                                      style="color:{{ $u->kindColor() }}; border:1px solid {{ $u->kindColor() }}; border-radius:4px">
                                    {{ $u->kindLabel() }}
                                </span>
                                @if($u->area)
                                    <span class="badge" style="font-size:10px">{{ $u->area }}</span>
                                @endif
                                @if($novo)
                                    <span class="text-xs font-bold" style="color:var(--purple)">novo pra você</span>
                                @endif
                                @if(! $u->published_at)
                                    <span class="text-xs font-bold" style="color:var(--orange)">rascunho — só você vê</span>
                                @endif
                            </div>

                            <p class="text-sm font-bold" style="color:var(--text)">{{ $u->title }}</p>

                            @if($u->summary)
                                <p class="text-sm mt-1" style="color:var(--muted2)">{{ $u->summary }}</p>
                            @endif

                            @if($u->body)
                                <div x-data="{ aberto: false }" class="mt-2">
                                    <button type="button" @click="aberto = !aberto"
                                            class="text-xs font-semibold" style="color:var(--purple)">
                                        <span x-show="!aberto">Ver detalhes</span>
                                        <span x-show="aberto" x-cloak>Esconder detalhes</span>
                                    </button>
                                    <div x-show="aberto" x-cloak class="mt-2">
                                        <x-rich-content :value="$u->body" />
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-mono" style="color:var(--muted)">
                                {{ ($u->published_at ?? $u->created_at)->format('d/m/Y') }}
                            </p>
                            @if($podeEscrever)
                                <div class="flex items-center gap-2 justify-end mt-2">
                                    <a href="{{ route('app-updates.edit', $u) }}" class="text-xs" style="color:var(--muted)">Editar</a>
                                    <form action="{{ route('app-updates.destroy', $u) }}" method="POST"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover esta novidade da linha do tempo?')) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs" style="color:var(--red)">Remover</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</x-app-layout>
