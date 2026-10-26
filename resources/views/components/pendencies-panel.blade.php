{{-- Bloco "Pendências" de uma Tarefa/Ticket, Projeto ou Planejamento. Abertas primeiro
     (prazo mais próximo no topo); resolvidas/canceladas recolhidas no fim.
     Pendência ≠ Trava: aqui é "esta origem ESPERA algo" — ver App\Models\Pendency. --}}
@props(['pendable', 'alias', 'users', 'returnTo' => null])

@php
    $panel = app(\App\Services\PendencyService::class)->panelData($pendable);
    // Depois de resolver/cobrar, volta pro bloco (não pro topo da página).
    $returnTo = $returnTo ?? url()->current() . '#pendencias';
@endphp

<div class="card card-body-lg" id="pendencias"
     x-data="{ adding: {{ $errors->pendency->any() ? 'true' : 'false' }}, showClosed: false }"
     @pendency-form-close="adding = false">
    <div class="flex items-center justify-between mb-4">
        <p class="card-title flex items-center gap-2">
            <span class="icon-badge">
                <x-icon name="hourglass" size="16" />
            </span>
            Pendências
            @if($panel['open']->isNotEmpty())
                <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:rgba(238,121,25,.12); border:1px solid rgba(238,121,25,.3); border-radius:8px; color:var(--orange)">
                    {{ $panel['open']->count() }}
                </span>
            @endif
        </p>
        <button type="button" class="text-xs font-semibold" style="color:var(--purple)" x-show="!adding" @click="adding = true">+ Nova</button>
    </div>

    @if($errors->pendency->any())
        <div class="mb-3 px-3 py-2 text-xs" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red); border-radius:6px">
            {{ $errors->pendency->first() }}
        </div>
    @endif

    <div x-show="adding" x-cloak class="mb-4 p-3" style="background:var(--s2); border:1px solid var(--border2); border-radius:8px">
        @include('pendencies._form', [
            'users'         => $users,
            'contacts'      => $panel['contacts'],
            'pendableAlias' => $alias,
            'pendableId'    => $pendable->getKey(),
            'returnTo'      => $returnTo,
        ])
    </div>

    @if($panel['open']->isEmpty() && !$errors->pendency->any())
        <p class="text-xs" style="color:var(--muted)" x-show="!adding">
            Nada pendente. Use <strong>+ Nova</strong> quando o trabalho depender de algo do cliente, de um terceiro ou de alguém do time.
        </p>
    @endif

    <div class="flex flex-col">
        @foreach($panel['open'] as $p)
            @include('pendencies._item', ['p' => $p, 'contacts' => $panel['contacts'], 'users' => $users, 'showOrigin' => false, 'last' => $loop->last, 'returnTo' => $returnTo])
        @endforeach
    </div>

    @if($panel['closed']->isNotEmpty())
        <button type="button" class="text-xs mt-3" style="color:var(--muted2)" @click="showClosed = !showClosed"
                x-text="showClosed ? 'Ocultar resolvidas' : 'Ver {{ $panel['closed']->count() }} resolvida(s)'"></button>
        <div x-show="showClosed" x-cloak class="flex flex-col mt-2">
            @foreach($panel['closed'] as $p)
                @include('pendencies._item', ['p' => $p, 'contacts' => $panel['contacts'], 'users' => $users, 'showOrigin' => false, 'last' => $loop->last, 'returnTo' => $returnTo])
            @endforeach
        </div>
    @endif
</div>
