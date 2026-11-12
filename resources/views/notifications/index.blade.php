{{-- Notificações — mesmo padrão do sino (Direção A, 12/11/2026): cada aviso no formato do seu
     bloco (partials._notification-item, molde único do sino e desta página), cor do ícone =
     do que se trata. Aqui fica o histórico inteiro: aberto em destaque, o resto apagado. --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3">
            <h1 class="page-title">Notificações</h1>
            @if($historico)
                <form method="POST" action="{{ route('notifications.clear-seen') }}"
                      @submit.prevent="if (await $store.confirmDialog.ask('Apagar todo o histórico (avisos lidos, resolvidos e expirados)? Os abertos ficam.')) $el.submit()">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm" title="Apaga os avisos lidos, resolvidos e expirados — os abertos ficam">Limpar histórico</button>
                </form>
            @endif
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros por bloco, com quantos estão abertos em cada --}}
    @php
        $filters = ['' => 'Todos'] + \App\Support\NotificationPresenter::GROUPS;
        $filterColor = ['acao' => 'var(--orange)', 'conversa' => 'var(--purple)', 'saber' => 'var(--slate)'];
    @endphp
    {{-- Abertos (padrão) × Histórico — misturar os dois confundia (12/11). --}}
    <div class="inline-flex mb-3 p-1 rounded-full" style="background:var(--s2); border:1px solid var(--border)">
        @foreach([false => 'Abertos', true => 'Histórico'] as $isHist => $label)
            @php $on = (bool) $historico === (bool) $isHist; @endphp
            <a href="{{ route('notifications.index', array_filter(['bloco' => $bloco, 'historico' => $isHist ? 1 : null])) }}"
               class="px-4 py-1.5 text-sm font-semibold rounded-full"
               style="{{ $on ? 'background:var(--s1); color:var(--text); box-shadow:0 1px 3px rgba(0,0,0,.08)' : 'color:var(--muted2)' }}">
                {{ $label }}@if(! $isHist && array_sum($openCounts)) ({{ array_sum($openCounts) }})@endif
            </a>
        @endforeach
    </div>

    <div class="flex items-center gap-2 flex-wrap mb-4">
        @foreach($filters as $key => $label)
            @php $active = ($bloco ?? '') === $key; $count = $historico ? 0 : ($key ? $openCounts[$key] : array_sum($openCounts)); @endphp
            <a href="{{ route('notifications.index', array_filter(['bloco' => $key ?: null, 'historico' => $historico ? 1 : null])) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-full"
               style="{{ $active ? 'background:var(--purple); color:#fff' : 'background:var(--s1); border:1px solid var(--border); color:var(--text)' }}">
                {{ $label }}
                @if($count)
                    <span class="text-xs font-bold px-1.5 rounded-full"
                          style="{{ $active ? 'background:rgba(255,255,255,.25); color:#fff' : 'background:' . ($key ? $filterColor[$key] : 'var(--muted2)') . '; color:#fff' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach

        @if(! $historico && in_array($bloco, ['conversa', 'saber'], true) && $openCounts[$bloco] > 0)
            <form method="POST" action="{{ route('notifications.mark-seen') }}" class="ml-auto">
                @csrf
                <input type="hidden" name="group" value="{{ $bloco }}">
                <button type="submit" class="btn btn-ghost btn-sm">Marcar todos como vistos</button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="card px-6 py-10 text-center" style="color:var(--muted)">
            @if($historico)
                <p class="text-sm">{{ $bloco ? 'Nada no histórico deste bloco.' : 'Histórico vazio.' }}</p>
            @else
                <p class="text-sm font-semibold flex items-center justify-center gap-1.5" style="color:var(--green)">
                    <x-icon name="circle-check" size="16" /> Tudo em dia{{ $bloco ? ' neste bloco' : '' }}.
                </p>
                <p class="text-xs mt-1">Nada aberto esperando você.
                    <a href="{{ route('notifications.index', array_filter(['bloco' => $bloco, 'historico' => 1])) }}" style="color:var(--purple)">Ver histórico</a></p>
            @endif
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach($days as $day => $items)
                @php $d = \Carbon\Carbon::parse($day); @endphp
                <section class="card px-3 py-3">
                    <h2 class="px-2 pb-2 text-xs font-bold uppercase tracking-widest" style="color:var(--muted2)">
                        {{ $d->isToday() ? 'Hoje' : ($d->isYesterday() ? 'Ontem' : $d->translatedFormat('l, d/m')) }}
                    </h2>
                    <div class="bell-list">
                        @foreach($items as $it)
                            @include('partials._notification-item', ['it' => $it, 'page' => true])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-app-layout>
