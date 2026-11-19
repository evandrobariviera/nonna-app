<x-app-layout>
    <x-slot name="header">Central de Ajuda</x-slot>

    <div class="mb-5">
        <a href="{{ route('help.index') }}"
           class="text-xs transition-colors" style="color:var(--muted)"
           onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
            ← Central de Ajuda
        </a>
    </div>

    @php
        // "A decidir" são candidatos: aparecem no mapa, mas não contam no andamento.
        $total = $documents->where('status', '!=', 'a_decidir')->count();
        $percent = $total ? round($closed / $total * 100) : 0;
        $building = $documents->where('status', 'em_construcao')->count();
        $buildingPercent = $total ? round($building / $total * 100) : 0;
    @endphp

    {{-- Cabeçalho: o que é o mapa + andamento da padronização --}}
    <div class="card px-6 py-6 md:px-8 mb-6" style="max-width:1080px">
        <div class="flex items-start gap-3 mb-2">
            <span class="flex-none flex items-center justify-center" style="width:36px; height:36px; border-radius:var(--radius); background:var(--s3); color:var(--purple)">
                <x-icon name="map" size="18" />
            </span>
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold" style="color:var(--text); letter-spacing:-.01em">Mapa dos Documentos</h1>
                <p class="text-sm mt-1" style="color:var(--muted2); max-width:640px">
                    Todos os documentos padrão da agência, na ordem em que o trabalho acontece. Cada um tem um modelo
                    pronto para preencher e um manual que explica quando fazer, quem faz e como preencher.
                </p>
            </div>
        </div>

        @if($total)
            <div class="mt-5">
                <div class="flex items-baseline justify-between gap-3 mb-1.5 flex-wrap">
                    <span class="text-sm font-semibold" style="color:var(--text)">{{ $closed }} de {{ $total }} documentos fechados</span>
                    <span class="flex items-center gap-4 text-xs flex-wrap" style="color:var(--muted2)">
                        @foreach(\App\Models\DocumentStandard::$statuses as $key => $st)
                            <span class="flex items-center gap-1.5">
                                <span style="width:10px; height:10px; border-radius:3px; background:{{ $st['bg'] ?: 'var(--s1)' }}; border:1.5px solid {{ $st['color'] }}"></span>
                                {{ $st['label'] }} ({{ $documents->where('status', $key)->count() }})
                            </span>
                        @endforeach
                    </span>
                </div>
                <div class="flex" style="height:8px; border-radius:999px; background:var(--s3); overflow:hidden">
                    <div style="height:100%; width:{{ $percent }}%; background:var(--green)"></div>
                    <div style="height:100%; width:{{ $buildingPercent }}%; background:var(--orange); opacity:.7"></div>
                </div>
            </div>
        @endif
    </div>

    @if($documents->isEmpty())
        <div class="card px-5 py-8 text-center" style="max-width:1080px">
            <p class="text-sm" style="color:var(--muted)">Nenhum documento padrão cadastrado ainda.</p>
        </div>
    @else
        {{-- O mapa: uma etapa por bloco, ligadas por uma linha vertical --}}
        <div class="flex flex-col" style="max-width:1080px">
            @php $stageNumber = 0; @endphp
            @foreach(\App\Models\DocumentStandard::$stages as $stageKey => $stage)
                @php $stageDocs = $byStage->get($stageKey, collect()); @endphp
                @continue($stageDocs->isEmpty())
                @php $stageNumber++; $isLast = $stageKey === $byStage->keys()->last(); @endphp

                <div class="relative" style="padding-left:52px; padding-bottom:{{ $isLast ? '0' : '28px' }}">
                    @unless($isLast)
                        <span aria-hidden="true" style="position:absolute; left:17px; top:36px; bottom:0; width:2px; background:var(--border)"></span>
                    @endunless
                    <span class="flex items-center justify-center text-sm font-extrabold"
                          style="position:absolute; left:0; top:0; width:36px; height:36px; border-radius:999px; background:var(--purple); color:#fff">
                        {{ $stageNumber }}
                    </span>

                    <div class="mb-3" style="padding-top:5px">
                        <h2 class="text-lg font-extrabold" style="color:var(--text)">{{ $stage['label'] }}</h2>
                        <p class="text-sm" style="color:var(--muted2)">{{ $stage['description'] }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($stageDocs as $doc)
                            @php $bg = $doc->statusBackground(); @endphp
                            <a href="{{ route('help.documents.show', $doc) }}"
                               class="card px-4 py-3.5 flex flex-col gap-1.5 transition-colors"
                               style="border-left:3px {{ $doc->status === 'a_decidir' ? 'dashed' : 'solid' }} {{ $doc->statusColor() }}; {{ $bg ? "background:{$bg}" : '' }}"
                               onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='{{ $bg }}'">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ $stageNumber }}.{{ $loop->iteration }}</span>
                                    <span class="text-xs font-semibold" style="color:{{ $doc->statusColor() }}">{{ $doc->statusLabel() }}</span>
                                </span>
                                <span class="text-sm font-bold" style="color:var(--text)">{{ $doc->name }}</span>
                                @if($doc->summary)
                                    <span class="text-xs" style="color:var(--muted2); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden">{{ $doc->summary }}</span>
                                @endif
                                @if($doc->template_html || $doc->help_article_slug)
                                    <span class="flex items-center gap-3 text-xs mt-0.5" style="color:var(--purple)">
                                        @if($doc->template_html)
                                            <span class="flex items-center gap-1"><x-icon name="file-text" size="13" /> Modelo</span>
                                        @endif
                                        @if($doc->help_article_slug)
                                            <span class="flex items-center gap-1"><x-icon name="book-open" size="13" /> Manual</span>
                                        @endif
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-app-layout>
