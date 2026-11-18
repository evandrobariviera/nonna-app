<x-app-layout>
    <x-slot name="header">Central de Ajuda</x-slot>

    <div class="mb-5">
        <a href="{{ route('help.documents.index') }}"
           class="text-xs transition-colors" style="color:var(--muted)"
           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
            ← Mapa dos Documentos
        </a>
    </div>

    <div class="card px-6 py-6 md:px-10 md:py-8" style="max-width:860px">
        <div class="flex items-center gap-3 mb-2 flex-wrap">
            <span class="text-xs font-mono px-2 py-0.5" style="background:var(--s3); color:var(--purple); border-radius:4px">
                {{ $document->stageLabel() }}
            </span>
            <span class="flex items-center gap-1.5 text-xs font-semibold" style="color:{{ $document->statusColor() }}">
                <span style="width:8px; height:8px; border-radius:999px; background:{{ $document->statusColor() }}"></span>
                {{ $document->statusLabel() }}
            </span>
        </div>

        <h1 class="text-2xl font-extrabold mb-2" style="color:var(--text); letter-spacing:-.01em">{{ $document->name }}</h1>
        @if($document->summary)
            <p class="text-sm mb-6" style="color:var(--muted2)">{{ $document->summary }}</p>
        @endif

        {{-- Ficha do documento --}}
        @php
            $ficha = [
                'Quando é feito'        => $document->when_made,
                'Quem faz'              => $document->made_by,
                'Quem revisa'           => $document->reviewed_by,
                'Com quais informações' => $document->inputs,
                'Próximo passo'         => $document->next_step,
            ];
        @endphp
        <div class="flex flex-col mb-6" style="border-top:1px solid var(--border)">
            @foreach($ficha as $label => $value)
                <div class="grid gap-1 sm:grid-cols-3 py-3" style="border-bottom:1px solid var(--border)">
                    <span class="text-xs font-semibold uppercase" style="color:var(--muted); letter-spacing:.06em; padding-top:2px">{{ $label }}</span>
                    <span class="text-sm sm:col-span-2" style="color:{{ $value ? 'var(--text)' : 'var(--muted)' }}">{{ $value ?: 'A definir' }}</span>
                </div>
            @endforeach
        </div>

        {{-- Modelo e manual --}}
        <div class="flex items-center gap-3 flex-wrap">
            @if($document->template_html)
                <a href="{{ route('help.documents.template', $document) }}" target="_blank" rel="noopener"
                   class="btn btn-primary flex items-center gap-2">
                    <x-icon name="file-text" size="16" />
                    Abrir o modelo
                </a>
            @else
                <span class="text-sm" style="color:var(--muted)">Modelo ainda não criado.</span>
            @endif

            @if($manual)
                <a href="{{ route('help.show', $manual) }}" class="btn btn-ghost flex items-center gap-2">
                    <x-icon name="book-open" size="16" />
                    Ler o manual
                </a>
            @else
                <span class="text-sm" style="color:var(--muted)">Manual ainda não escrito.</span>
            @endif
        </div>
        @if($document->template_html)
            <p class="text-xs mt-2" style="color:var(--muted)">O modelo abre numa aba nova. Para gerar o PDF, use Imprimir → Salvar como PDF.</p>
        @endif
    </div>

    {{-- Anterior / próximo na ordem do mapa --}}
    <div class="flex items-stretch justify-between gap-3 mt-4" style="max-width:860px">
        @if($previous)
            <a href="{{ route('help.documents.show', $previous) }}" class="card px-4 py-3 flex-1 min-w-0"
               onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
                <span class="block text-xs" style="color:var(--muted)">← Anterior</span>
                <span class="block text-sm font-semibold truncate" style="color:var(--text)">{{ $previous->name }}</span>
            </a>
        @else
            <span class="flex-1"></span>
        @endif
        @if($next)
            <a href="{{ route('help.documents.show', $next) }}" class="card px-4 py-3 flex-1 min-w-0 text-right"
               onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
                <span class="block text-xs" style="color:var(--muted)">Próximo →</span>
                <span class="block text-sm font-semibold truncate" style="color:var(--text)">{{ $next->name }}</span>
            </a>
        @else
            <span class="flex-1"></span>
        @endif
    </div>

</x-app-layout>
