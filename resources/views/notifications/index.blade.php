{{-- Notificações — mesmo padrão do sino (Direção A, 12/11/2026): cada aviso no formato do seu
     bloco (partials._notification-item, molde único do sino e desta página), cor do ícone =
     do que se trata. Aqui fica o histórico inteiro: aberto em destaque, o resto apagado. --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3">
            <h1 class="page-title">Notificações</h1>
            <form method="POST" action="{{ route('notifications.clear-seen') }}"
                  @submit.prevent="if (await $store.confirmDialog.ask('Apagar todos os avisos que você já leu, resolveu ou que expiraram?')) $el.submit()">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" title="Apaga os avisos lidos, resolvidos e expirados — os abertos ficam">Limpar os já vistos</button>
            </form>
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
    <div class="flex items-center gap-2 flex-wrap mb-4">
        @foreach($filters as $key => $label)
            @php $active = ($bloco ?? '') === $key; $count = $key ? $openCounts[$key] : array_sum($openCounts); @endphp
            <a href="{{ route('notifications.index', $key ? ['bloco' => $key] : []) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-full"
               style="{{ $active ? 'background:var(--purple); color:#fff' : 'background:var(--s1); border:1px solid var(--border); color:var(--text)' }}">
                {{ $label }}
                @if($count)
                    <span class="text-xs font-bold px-1.5 rounded-full"
                          style="{{ $active ? 'background:rgba(255,255,255,.25); color:#fff' : 'background:' . ($key ? $filterColor[$key] : 'var(--muted2)') . '; color:#fff' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach

        @if(in_array($bloco, ['conversa', 'saber'], true) && $openCounts[$bloco] > 0)
            <form method="POST" action="{{ route('notifications.mark-seen') }}" class="ml-auto">
                @csrf
                <input type="hidden" name="group" value="{{ $bloco }}">
                <button type="submit" class="btn btn-ghost btn-sm">Marcar todos como vistos</button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="card px-6 py-10 text-center" style="color:var(--muted)">
            <p class="text-sm">{{ $bloco ? 'Nada neste bloco.' : 'Nenhuma notificação ainda.' }}</p>
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
