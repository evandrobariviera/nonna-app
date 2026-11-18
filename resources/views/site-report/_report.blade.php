{{-- Corpo do Relatório Analítico (GA4 + Search Console), usado no Portal e na aba "Site"
     da Central de Performance. Recebe $ga4, $daily, $gsc e $errorText($erro) — no Portal o
     cliente vê uma mensagem neutra; dentro do App, o motivo técnico pra equipe resolver. --}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $d1 = fn ($v) => number_format((float) $v, 1, ',', '.');
    $path = fn ($u) => ($p = parse_url($u, PHP_URL_PATH) ?: $u) === '/' ? 'Página inicial' : \Illuminate\Support\Str::limit($p, 56);
    $days_ = collect($daily['days'] ?? []);
@endphp

    {{-- ══ O SITE ══ --}}
    @if($ga4)
        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Visitas ao site</p>
        @if(!$ga4['ok'])
            <div class="card px-5 py-4 mb-6"><p class="text-sm" style="color:var(--muted2)">{{ $errorText($ga4['error'] ?? null) }}</p></div>
        @else
            <div class="grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(min(140px, 100%), 1fr))">
                @foreach([
                    ['Visitas', $n($ga4['totals']['sessions']), 'vezes que alguém entrou no site'],
                    ['Pessoas', $n($ga4['totals']['users']), 'pessoas diferentes'],
                    ['Conversões', $n($ga4['totals']['key_events']), 'pedidos de orçamento, contatos e cliques no WhatsApp'],
                    ['Engajamento', $ga4['totals']['engagement'] . '%', 'das visitas leram, rolaram ou clicaram'],
                ] as [$label, $value, $hint])
                    <div class="card px-4 py-3">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
                        <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $value }}</p>
                        <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>

            @if($daily && $daily['ok'] && $days_->isNotEmpty())
                <div class="grid gap-5 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr))">
                    <div class="card px-5 py-4">
                        <p class="text-sm font-semibold mb-1" style="color:var(--text)">Visitas por dia</p>
                        <x-chart type="area" :height="220"
                            :series="[['name' => 'Visitas', 'data' => $days_->pluck('sessions')->all()]]"
                            :categories="$days_->map(fn ($d) => \Carbon\Carbon::parse($d['date'])->format('d/m'))->all()"
                            :colors="['var(--purple)']" />
                    </div>
                    <div class="card px-5 py-4">
                        <p class="text-sm font-semibold mb-1" style="color:var(--text)">Conversões por dia</p>
                        <x-chart type="bar" :height="220"
                            :series="[['name' => 'Conversões', 'data' => $days_->pluck('key_events')->all()]]"
                            :categories="$days_->map(fn ($d) => \Carbon\Carbon::parse($d['date'])->format('d/m'))->all()"
                            :colors="['var(--orange)']" />
                    </div>
                </div>
            @endif

            <div class="grid gap-5 mb-8" style="grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr))">
                @if($ga4['channels'])
                    <div class="card px-5 py-4">
                        <p class="text-sm font-semibold mb-3" style="color:var(--text)">De onde vêm as visitas</p>
                        @php $max = max(1, collect($ga4['channels'])->max('sessions')); @endphp
                        @foreach($ga4['channels'] as $c)
                            <div class="py-1.5">
                                <div class="flex justify-between gap-2 text-sm">
                                    <span class="truncate min-w-0" style="color:var(--text)">{{ \App\Services\Tracking\SiteReports::CHANNEL_LABELS[$c['name']] ?? $c['name'] }}</span>
                                    <span class="shrink-0" style="color:var(--muted2)">{{ $n($c['sessions']) }}@if($c['key_events']) · {{ $n($c['key_events']) }} conv.@endif</span>
                                </div>
                                <div class="h-1.5 rounded mt-1" style="background:var(--s2)"><div class="h-1.5 rounded" style="width:{{ round($c['sessions'] / $max * 100) }}%; background:var(--purple)"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($ga4['pages'])
                    <div class="card px-5 py-4">
                        <p class="text-sm font-semibold mb-3" style="color:var(--text)">Páginas por onde as pessoas entram</p>
                        @foreach($ga4['pages'] as $p)
                            <div class="flex justify-between gap-3 text-sm py-1.5" style="border-top:1px solid var(--border)">
                                <span class="truncate min-w-0" style="color:var(--text)">{{ $path($p['page']) }}</span>
                                <span class="shrink-0" style="color:var(--muted2)">{{ $n($p['sessions']) }}@if($p['key_events']) · {{ $n($p['key_events']) }} conv.@endif</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- ══ BUSCA NO GOOGLE ══ --}}
    @if($gsc)
        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Busca no Google</p>
        @if(!$gsc['ok'])
            <div class="card px-5 py-4"><p class="text-sm" style="color:var(--muted2)">{{ $errorText($gsc['error'] ?? null) }}</p></div>
        @else
            <div class="grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(min(140px, 100%), 1fr))">
                @foreach([
                    ['Cliques', $n($gsc['totals']['clicks']), 'pessoas que clicaram no site na busca'],
                    ['Aparições', $n($gsc['totals']['impressions']), 'vezes que o site apareceu numa busca'],
                    ['Taxa de clique', $d1($gsc['totals']['ctr']) . '%', 'de quem viu, quantos clicaram'],
                    ['Posição média', $d1($gsc['totals']['position']) . 'º', 'lugar médio nos resultados'],
                ] as [$label, $value, $hint])
                    <div class="card px-4 py-3">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $label }}</p>
                        <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $value }}</p>
                        <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-5 mb-3" style="grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr))">
                @if($gsc['queries'])
                    <div class="card px-5 py-4">
                        <p class="text-sm font-semibold mb-1" style="color:var(--text)">O que as pessoas buscaram</p>
                        <p class="text-xs mb-2" style="color:var(--muted)">cliques · posição no Google</p>
                        @foreach($gsc['queries'] as $q)
                            <div class="flex justify-between gap-3 text-sm py-1.5" style="border-top:1px solid var(--border)">
                                <span class="truncate min-w-0" style="color:var(--text)">{{ $q['key'] }}</span>
                                <span class="shrink-0" style="color:var(--muted2)">{{ $n($q['clicks']) }} · {{ $d1($q['position']) }}º</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($gsc['pages'])
                    <div class="card px-5 py-4">
                        <p class="text-sm font-semibold mb-1" style="color:var(--text)">Páginas mais clicadas no Google</p>
                        <p class="text-xs mb-2" style="color:var(--muted)">cliques</p>
                        @foreach($gsc['pages'] as $p)
                            <div class="flex justify-between gap-3 text-sm py-1.5" style="border-top:1px solid var(--border)">
                                <span class="truncate min-w-0" style="color:var(--text)">{{ $path($p['key']) }}</span>
                                <span class="shrink-0" style="color:var(--muted2)">{{ $n($p['clicks']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <p class="text-xs" style="color:var(--muted)">O Google leva cerca de 2 dias para fechar os números da busca.</p>
        @endif
    @endif
