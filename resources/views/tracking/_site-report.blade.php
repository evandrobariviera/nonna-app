{{-- Números do site (GA4 + Search Console), carregado por fetch na tela de Rastreamento. --}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $channel = ['Organic Search' => 'Busca no Google (orgânico)', 'Paid Search' => 'Busca paga', 'Direct' => 'Direto', 'Organic Social' => 'Redes sociais (orgânico)', 'Paid Social' => 'Redes sociais (anúncio)', 'Referral' => 'Outros sites', 'Cross-network' => 'Google (várias redes)', 'Paid Other' => 'Outros anúncios', 'Display' => 'Display', 'Email' => 'E-mail', 'Unassigned' => 'Sem origem', 'Organic Video' => 'Vídeo (orgânico)', 'Paid Video' => 'Vídeo (anúncio)'];
    $path = fn ($u) => \Illuminate\Support\Str::limit(parse_url($u, PHP_URL_PATH) ?: $u, 48);
@endphp
<div class="flex flex-col gap-4 text-sm">
    @if($ga4)
        <div class="min-w-0">
            <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">Google Analytics</p>
            @if(!$ga4['ok'])
                <p class="text-xs" style="color:var(--orange)">{{ $ga4['error'] }}</p>
            @else
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach([['Visitas', $n($ga4['totals']['sessions'])], ['Pessoas', $n($ga4['totals']['users'])], ['Conversões', $n($ga4['totals']['key_events'])], ['Engajamento', $ga4['totals']['engagement'] . '%']] as [$label, $val])
                        <div class="rounded px-3 py-2" style="background:var(--s2)"><p class="text-xs" style="color:var(--muted)">{{ $label }}</p><p class="text-base font-semibold">{{ $val }}</p></div>
                    @endforeach
                </div>
                @if($ga4['channels'])
                    <p class="text-xs mt-3 mb-1" style="color:var(--muted)">De onde vêm as visitas</p>
                    @php $max = max(1, collect($ga4['channels'])->max('sessions')); @endphp
                    @foreach($ga4['channels'] as $c)
                        <div class="flex items-center gap-2 text-xs py-0.5">
                            <span class="w-40 shrink-0 truncate">{{ $channel[$c['name']] ?? $c['name'] }}</span>
                            <span class="flex-1 h-1.5 rounded min-w-0" style="background:var(--s2)"><span class="block h-1.5 rounded" style="width:{{ round($c['sessions'] / $max * 100) }}%; background:var(--purple)"></span></span>
                            <span class="w-24 text-right shrink-0" style="color:var(--muted2)">{{ $n($c['sessions']) }}{{ $c['key_events'] ? ' · ' . $n($c['key_events']) . ' conv.' : '' }}</span>
                        </div>
                    @endforeach
                @endif
                @if($ga4['pages'])
                    <p class="text-xs mt-3 mb-1" style="color:var(--muted)">Páginas por onde as pessoas entram</p>
                    @foreach($ga4['pages'] as $p)
                        <div class="flex justify-between gap-2 text-xs py-0.5"><span class="truncate min-w-0">{{ $path($p['page']) }}</span><span class="shrink-0" style="color:var(--muted2)">{{ $n($p['sessions']) }}{{ $p['key_events'] ? ' · ' . $n($p['key_events']) . ' conv.' : '' }}</span></div>
                    @endforeach
                @endif
            @endif
        </div>
    @endif

    @if($gsc)
        <div class="min-w-0">
            <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">Busca no Google (Search Console)</p>
            @if(!$gsc['ok'])
                <p class="text-xs" style="color:var(--orange)">{{ $gsc['error'] }}</p>
            @else
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach([['Cliques', $n($gsc['totals']['clicks'])], ['Vezes que apareceu', $n($gsc['totals']['impressions'])], ['Taxa de clique', number_format($gsc['totals']['ctr'], 1, ',', '.') . '%'], ['Posição média', number_format($gsc['totals']['position'], 1, ',', '.')]] as [$label, $val])
                        <div class="rounded px-3 py-2" style="background:var(--s2)"><p class="text-xs" style="color:var(--muted)">{{ $label }}</p><p class="text-base font-semibold">{{ $val }}</p></div>
                    @endforeach
                </div>
                @if($gsc['queries'])
                    <p class="text-xs mt-3 mb-1" style="color:var(--muted)">O que as pessoas buscaram (cliques · posição)</p>
                    @foreach($gsc['queries'] as $q)
                        <div class="flex justify-between gap-2 text-xs py-0.5"><span class="truncate min-w-0">{{ $q['key'] }}</span><span class="shrink-0" style="color:var(--muted2)">{{ $n($q['clicks']) }} · {{ number_format($q['position'], 1, ',', '.') }}º</span></div>
                    @endforeach
                @endif
                @if($gsc['pages'])
                    <p class="text-xs mt-3 mb-1" style="color:var(--muted)">Páginas mais clicadas no Google</p>
                    @foreach($gsc['pages'] as $p)
                        <div class="flex justify-between gap-2 text-xs py-0.5"><span class="truncate min-w-0">{{ $path($p['key']) }}</span><span class="shrink-0" style="color:var(--muted2)">{{ $n($p['clicks']) }} cliques</span></div>
                    @endforeach
                @endif
                <p class="text-xs mt-2" style="color:var(--muted)">O Google leva cerca de 2 dias para fechar os números da busca.</p>
            @endif
        </div>
    @endif
</div>
