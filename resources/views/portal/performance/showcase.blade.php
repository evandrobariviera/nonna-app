{{-- Vitrine de um módulo da Central de Performance que o cliente ainda não
     contratou. Conteúdo em ClientModule::$showcase. --}}
@php
    $label = \App\Models\ClientModule::$modules[$moduleKey];
    $info = \App\Models\ClientModule::$showcase[$moduleKey];
@endphp

<x-portal-layout>
    <x-slot name="title">{{ $label }}</x-slot>

    @include('portal.performance._tabs')

    @if(session('success'))
        <div class="mb-6 px-4 py-3 text-sm border rounded-lg" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-2xl mx-auto py-8 text-center">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5" style="background: var(--purple-soft, rgba(100,59,142,.12))">
            <x-icon :name="$info['icon']" size="26" style="color: var(--purple)" />
        </div>

        <h1 class="text-2xl font-black mb-3" style="color: var(--text)">{{ $label }}</h1>
        <p class="text-sm leading-relaxed mb-8" style="color: var(--muted)">{{ $info['pitch'] }}</p>

        <div class="grid gap-4 mb-8 text-left" style="grid-template-columns:repeat(auto-fit, minmax(170px, 1fr))">
            @foreach($info['benefits'] as [$title, $text])
                <div class="card p-4">
                    <p class="text-sm font-semibold mb-1" style="color: var(--text)">{{ $title }}</p>
                    <p class="text-xs" style="color: var(--muted)">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        @if($module?->requested_at)
            <div class="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-lg"
                 style="background: var(--s2); border: 1px solid var(--border2); color: var(--muted2)">
                Pedido enviado em {{ $module->requested_at->format('d/m/Y') }} — nosso time vai entrar em contato.
            </div>
        @else
            <form method="POST" action="{{ route('portal.performance.request-module', $moduleKey) }}">
                @csrf
                <button type="submit" class="px-6 py-3 text-sm font-semibold text-white rounded-lg"
                        style="background: var(--purple)"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    Quero contratar
                </button>
            </form>
        @endif
    </div>
</x-portal-layout>
