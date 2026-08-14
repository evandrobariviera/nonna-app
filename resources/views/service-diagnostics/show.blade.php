<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">
            <a href="{{ route('service-diagnostics.index') }}" style="color:var(--muted)">Atendimento Assistido</a>
            <span style="color:var(--muted)">/</span>
            <a href="{{ route('service-diagnostics.integration', $integration) }}" style="color:var(--muted)">{{ $integration->client->displayName() }} · {{ $integration->label }}</a>
            <span style="color:var(--muted)">/</span>
            Versão {{ $diagnostic->version }}
        </span>
    </x-slot>

    <div style="max-width:960px">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded text-sm font-medium"
                 style="background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.25)">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                {{ $diagnostic->period_start->format('d/m/Y') }} – {{ $diagnostic->period_end->format('d/m/Y') }}
                · gerado {{ $diagnostic->generated_by === 'scheduled' ? 'automaticamente' : 'manualmente' }}
                @if($diagnostic->aiAgent) · agente {{ $diagnostic->aiAgent->name }} @endif
            </p>
            <div class="flex items-center gap-3">
                <a href="{{ route('service-diagnostics.print', [$integration, $diagnostic]) }}" target="_blank"
                   class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest flex items-center gap-1.5"
                   style="border:1px solid var(--border2); color:var(--muted2)">
                    <x-icon name="printer" size="13" />
                    Exportar PDF
                </a>
                <span class="text-xs font-mono px-3 py-1 rounded"
                      style="background:var(--s3); color: {{ $diagnostic->isPublished() ? 'var(--green)' : 'var(--muted)' }}">
                    {{ $diagnostic->statusLabel() }}
                </span>
                @unless($diagnostic->isPublished())
                    <form method="POST" action="{{ route('service-diagnostics.publish', [$integration, $diagnostic]) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Publicar este diagnóstico? Ele passa a ficar visível pro cliente no Portal.')) $el.submit()">
                        @csrf
                        <button type="submit"
                                class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--green)">
                            Publicar
                        </button>
                    </form>
                @endunless
            </div>
        </div>

        @include('service-diagnostics._report')

    </div>
</x-app-layout>
