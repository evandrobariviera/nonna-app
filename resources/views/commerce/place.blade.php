{{-- Central de Comércio → Prospecção → empresa (CommerceProspectingController@showPlace).
     Dados do Google ($g, $topLive) ficam guardados em google_data (decisão 25/11/2026), consultados 1x;
     sempre com a atribuição "Google Maps". Pontos, sinais do site e conferência de anúncio são da Nonna. --}}
@php
    $parts = $place->score_parts['parts'] ?? null;
    $site = $place->site_signals ?? [];
    $max = $place->scoreMax();
    $sig = \App\Services\Commerce\OpportunitySignal::$labels[$signal] ?? null;
    $name = $g['name'] ?? 'Empresa';
    $icon = ['ok' => ['✓', 'var(--green)'], 'bad' => ['✗', 'var(--red)'], 'warn' => ['!', 'var(--orange)']];
    $metaLib = 'https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=BR&media_type=all&search_type=keyword_unordered&q=' . rawurlencode($name);
    $host = !empty($g['website']) ? preg_replace('/^www\./', '', (string) parse_url($g['website'], PHP_URL_HOST)) : null;
    $googleLib = 'https://adstransparency.google.com/' . ($host ? '?region=BR&domain=' . rawurlencode($host) : '?region=BR');
@endphp
<x-app-layout>
    <x-slot name="header">Prospecção</x-slot>

    <div class="flex items-center gap-2 text-xs mb-4" style="color:var(--muted)">
        <a href="{{ route('commerce.prospecting') }}" class="hover:underline">Prospecção</a> ›
        <span>{{ $place->segment?->name ?? '—' }} · {{ $place->city?->name ?? '—' }}</span>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">{{ session('error') }}</div>
    @endif
    @include('commerce._google-paid')

    <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
        <div class="min-w-0">
            <h1 class="text-xl font-bold" style="color:var(--text)">{{ $name }}</h1>
            <p class="text-sm" style="color:var(--muted2)">
                @if($g){{ $g['type'] }}{{ $g['type'] && $g['address'] ? ' · ' : '' }}{{ $g['address'] }}{{ $g['phone'] ? ' · ' . $g['phone'] : '' }}
                @elseif($liveError)<span style="color:var(--red)">{{ $liveError }}</span>
                @else Sem chave do Google: dados da empresa indisponíveis.@endif
            </p>
            @php $st = \App\Models\CommercePlace::$statuses[$place->status] ?? null; @endphp
            <div class="flex items-center gap-2 mt-2 flex-wrap">
                @if($st)<span class="badge badge-{{ $st['color'] }}">{{ $st['label'] }}</span>@endif
                @if($g && $g['closed'])<span class="badge badge-red">Fechada no Google</span>@endif
                @if($sig && $place->collected_at)<span class="badge badge-{{ $sig['color'] }}">{{ $sig['label'] }}</span>@endif
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($g && $g['maps_url'])<a href="{{ $g['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-sm">Ver no Maps</a>@endif
            @if($g && $g['website'])<a href="{{ $g['website'] }}" target="_blank" rel="noopener" class="btn btn-sm">Abrir site</a>@endif
            <form method="POST" action="{{ route('commerce.places.collect', $place) }}">@csrf
                <button type="submit" class="btn btn-sm {{ $place->score === null ? 'btn-primary' : '' }}">{{ $place->collected_at ? 'Atualizar dados' : 'Coletar e dar nota' }}</button>
            </form>
            {{-- Raio-x público (etapa 5): página que a empresa recebe pelo link. --}}
            @if($place->report_token)
                <a href="{{ $place->reportUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary"><x-icon name="file-text" size="14" /> Ver raio-x</a>
                <button type="button" class="btn btn-sm" x-data="{ ok: false }"
                        @click="navigator.clipboard.writeText(@js($place->reportUrl())).then(() => { ok = true; setTimeout(() => ok = false, 2000) })">
                    <x-icon name="copy" size="14" /> <span x-text="ok ? 'Copiado!' : 'Copiar link'">Copiar link</span>
                </button>
            @elseif($place->score !== null && !in_array($place->status, ['opted_out', 'discarded'], true))
                <form method="POST" action="{{ route('commerce.places.report', $place) }}">@csrf
                    <button type="submit" class="btn btn-sm btn-primary" title="Cria a página que a empresa recebe pelo link: a nota explicada, comparada com a cidade"><x-icon name="file-text" size="14" /> Gerar raio-x</button>
                </form>
            @endif
        </div>
    </div>
    @if($place->report_token)
        <p class="text-xs -mt-3 mb-5" style="color:var(--muted2)">
            Raio-x gerado {{ $place->report_at?->format('d/m H:i') }} ·
            @if($place->report_views)
                a empresa abriu <b style="color:var(--text)">{{ $place->report_views }} {{ $place->report_views === 1 ? 'vez' : 'vezes' }}</b>, a última em {{ $place->report_last_view_at?->format('d/m H:i') }}
            @else
                a empresa ainda não abriu
            @endif
            @if($place->opted_out_at) · <span style="color:var(--red)">pediu para não receber mensagens em {{ $place->opted_out_at->format('d/m H:i') }}</span>@endif
            · o link mostra sempre os dados mais recentes da empresa.
        </p>
    @endif

    <div class="grid gap-5" style="grid-template-columns:repeat(auto-fit, minmax(320px, 1fr))">
        {{-- Nota --}}
        <section class="card p-5 min-w-0">
            <h2 class="card-title">Nota Nonna</h2>
            @if($place->score !== null)
                <div class="flex items-end gap-3 mt-2">
                    <span class="text-4xl font-bold" style="color:{{ $place->score >= $max * 0.7 ? 'var(--green)' : ($place->score >= $max * 0.45 ? 'var(--orange)' : 'var(--red)') }}">{{ $place->score }}</span>
                    <span class="text-sm pb-1" style="color:var(--muted)">de {{ $max }}{{ $max < 100 ? ' (falta a parte "nas IAs")' : '' }}</span>
                </div>
                @if($rank !== false)
                    <p class="text-sm mt-2" style="color:var(--muted2)">Está em <b style="color:var(--text)">{{ $rank + 1 }}º de {{ $peers->count() }}</b> entre {{ \Illuminate\Support\Str::lower($place->segment?->name ?? 'empresas') }} de {{ $place->city?->name }} com nota.
                        @if($peers->first() && $peers->first()->id !== $place->id) O 1º colocado tem {{ $peers->first()->score }}.@endif</p>
                @endif
                <div class="grid grid-cols-2 gap-2 mt-4">
                    @foreach(['profile' => ['Perfil no Google', 30], 'reputation' => ['Reputação', 30], 'site' => ['Site', 20], 'ai' => ['Nas IAs', 20]] as $k => [$label, $of])
                        <div class="rounded px-3 py-2" style="background:var(--s2)">
                            <p class="text-xs" style="color:var(--muted2)">{{ $label }}</p>
                            <p class="text-lg font-bold" style="color:var(--text)">{{ $parts[$k] ?? '—' }}<span class="text-xs font-normal" style="color:var(--muted)">/{{ $of }}</span></p>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs mt-3" style="color:var(--muted)">Coletada {{ $place->collected_at?->diffForHumans() }}.</p>
            @elseif($place->collect_error)
                <p class="text-sm mt-2" style="color:var(--red)">A última coleta deu erro: {{ $place->collect_error }}</p>
            @else
                <p class="text-sm mt-2" style="color:var(--muted)">Ainda sem nota. Clique em "Coletar e dar nota".</p>
            @endif
        </section>

        {{-- O que pesou --}}
        <section class="card p-5 min-w-0">
            <h2 class="card-title">O que pesou na nota</h2>
            <ul class="mt-3 flex flex-col gap-1.5">
                @forelse($reasons as $r)
                    <li class="text-sm flex gap-2"><b style="color:{{ $icon[$r['icon']][1] }}; width:14px; flex-shrink:0">{{ $icon[$r['icon']][0] }}</b><span>{{ $r['text'] }}</span></li>
                @empty
                    <li class="text-sm" style="color:var(--muted)">Aparece depois da coleta.</li>
                @endforelse
                @if($max < 100)<li class="text-sm flex gap-2"><b style="color:var(--muted); width:14px">…</b><span style="color:var(--muted)">Presença nas IAs: entra com as rodadas de IA.</span></li>@endif
            </ul>
            @if($g)<p class="text-xs mt-3" style="color:var(--muted)">Notas e avaliações: <b>Google Maps</b> · {{ $place->google_data_at ? "consultadas em " . $place->google_data_at->format("d/m/Y") : "consultadas agora" }}.</p>@endif
        </section>

        {{-- Contatos: os do SITE ficam guardados (SiteInspector::contacts, não são do Google);
             os do Google (telefone) também ficam guardados em google_data. --}}
        @php
            $ct = $site['contacts'] ?? [];
            $digits = fn ($s) => preg_replace('/\D/', '', $s);
            $temContato = collect($ct)->flatten()->isNotEmpty() || !empty($g['phone']) || !empty($g['website']);
        @endphp
        <section class="card p-5 min-w-0">
            <h2 class="card-title">Contatos</h2>
            @if($temContato)
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    @foreach($ct['whatsapp'] ?? [] as $n)
                        <li class="flex items-center gap-2"><x-icon name="message-circle" size="14" style="color:var(--green)" />
                            <a href="https://wa.me/55{{ $digits($n) }}" target="_blank" rel="noopener" class="underline">{{ $n }}</a> <span class="text-xs" style="color:var(--muted)">WhatsApp do site</span></li>
                    @endforeach
                    @foreach($ct['phones'] ?? [] as $n)
                        <li class="flex items-center gap-2"><x-icon name="phone" size="14" style="color:var(--muted)" />
                            <a href="tel:{{ $digits($n) }}" class="underline">{{ $n }}</a> <span class="text-xs" style="color:var(--muted)">telefone do site</span></li>
                    @endforeach
                    @if(!empty($g['phone']) && !in_array($g['phone'], array_merge($ct['phones'] ?? [], $ct['whatsapp'] ?? []), true))
                        <li class="flex items-center gap-2"><x-icon name="phone" size="14" style="color:var(--muted)" />
                            <a href="tel:{{ $digits($g['phone']) }}" class="underline">{{ $g['phone'] }}</a> <span class="text-xs" style="color:var(--muted)">telefone no Google</span></li>
                    @endif
                    @foreach($ct['emails'] ?? [] as $e)
                        <li class="flex items-center gap-2"><x-icon name="mail" size="14" style="color:var(--muted)" />
                            <a href="mailto:{{ $e }}" class="underline">{{ $e }}</a></li>
                    @endforeach
                    @php $siteLink = $site['site_url'] ?? ($g['website'] ?? null); @endphp
                    @if($siteLink)
                        <li class="flex items-center gap-2"><x-icon name="globe" size="14" style="color:var(--muted)" />
                            <a href="{{ $siteLink }}" target="_blank" rel="noopener" class="underline truncate">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($siteLink, '/')) }}</a></li>
                    @endif
                    @php $redes = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'youtube' => 'YouTube']; @endphp
                    @if(collect($redes)->keys()->contains(fn ($k) => !empty($ct[$k])))
                        <li class="flex items-center gap-2 flex-wrap"><x-icon name="link" size="14" style="color:var(--muted)" />
                            @foreach($redes as $k => $label)
                                @foreach($ct[$k] ?? [] as $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="badge badge-muted" title="{{ $url }}">{{ $label }}{{ count($ct[$k]) > 1 ? ' ' . $loop->iteration : '' }} ↗</a>
                                @endforeach
                            @endforeach
                        </li>
                    @endif
                </ul>
                <p class="text-xs mt-3" style="color:var(--muted)">Contatos guardados no App: do site da empresa e do perfil no Google.</p>
            @else
                <p class="text-sm mt-2" style="color:var(--muted)">{{ $place->collected_at ? 'Nenhum contato publicado no site nem no Google.' : 'Aparece depois da coleta.' }}</p>
            @endif
        </section>

        {{-- Comparação --}}
        @if($top->isNotEmpty())
            <section class="card p-5 min-w-0">
                <h2 class="card-title">Comparação com os melhores da cidade</h2>
                <table class="nonna-table w-full mt-3">
                    <thead><tr><th>Empresa</th><th>Nota</th></tr></thead>
                    <tbody>
                        @foreach($top as $t)
                            <tr @if($t->id === $place->id) style="font-weight:700" @endif>
                                <td>{{ $topLive[$t->place_id]['name'] ?? '—' }}</td><td>{{ $t->score }}</td>
                            </tr>
                        @endforeach
                        @unless($top->contains('id', $place->id))
                            <tr style="font-weight:700"><td>{{ $name }}</td><td>{{ $place->score ?? '—' }}</td></tr>
                        @endunless
                    </tbody>
                </table>
                <p class="text-xs mt-2" style="color:var(--muted)">Nomes: <b>Google Maps</b>.</p>
            </section>
        @endif

        {{-- Site e anúncios --}}
        <section class="card p-5 min-w-0">
            <h2 class="card-title">Anúncios <small class="font-normal text-xs" style="color:var(--muted)">não entra na nota, é sinal para a abordagem</small></h2>
            <p class="text-xs mt-3 font-semibold" style="color:var(--text)">Estrutura no site <span class="font-normal" style="color:var(--muted)">(lida no código do site e no GTM)</span></p>
            @if(!empty($site['checked']) && !empty($site['reachable']))
                <div class="flex gap-2 flex-wrap mt-2">
                    @foreach(['gtm' => 'GTM', 'ga4' => 'GA4', 'pixel' => 'Pixel Meta', 'ads_tag' => 'Tag Google Ads'] as $k => $label)
                        <span class="badge {{ !empty($site[$k]) ? 'badge-green' : 'badge-muted' }}" title="{{ ($site['sources'][$k] ?? '') === 'gtm' ? 'achado dentro do GTM' : '' }}">{{ $label }} {{ !empty($site[$k]) ? '✓' : '✗' }}</span>
                    @endforeach
                </div>
            @else
                <p class="text-xs mt-1" style="color:var(--muted)">{{ empty($site['checked']) ? ($place->collected_at ? 'Sem site no perfil do Google.' : 'Aparece depois da coleta.') : 'O site não abriu.' }}</p>
            @endif

            @foreach(['meta' => ['Anuncia na Meta?', $metaLib, 'Abrir Biblioteca de Anúncios', $place->ads_meta], 'google' => ['Anuncia no Google?', $googleLib, 'Abrir Central de Transparência', $place->ads_google]] as $platform => [$q, $link, $linkLabel, $current])
                <div class="mt-4">
                    <p class="text-xs font-semibold" style="color:var(--text)">{{ $q }}</p>
                    <a href="{{ $link }}" target="_blank" rel="noopener" class="text-xs underline" style="color:var(--purple)">{{ $linkLabel }} ↗</a>
                    <span class="text-xs" style="color:var(--muted)">{{ $platform === 'meta' ? 'já pesquisando o nome' : ($host ? 'já pesquisando o site' : 'pesquise pelo nome') }}</span>
                    <div class="flex gap-1 mt-1.5">
                        @foreach(['yes' => 'Sim', 'no' => 'Não', 'unknown' => 'Não sei'] as $ans => $ansLabel)
                            <form method="POST" action="{{ route('commerce.places.ads', $place) }}">@csrf
                                <input type="hidden" name="platform" value="{{ $platform }}"><input type="hidden" name="answer" value="{{ $ans }}">
                                <button type="submit" class="btn btn-xs {{ $current === $ans ? 'btn-primary' : '' }}">{{ $ansLabel }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if($place->ads_checked_at)
                <p class="text-xs mt-3" style="color:var(--muted)">Conferido por {{ $place->adsChecker?->name ?? '—' }} em {{ $place->ads_checked_at->format('d/m, H:i') }}</p>
            @endif
            @if($sig && $place->collected_at)
                <div class="rounded px-3 py-2.5 mt-4" style="background:var(--s2)">
                    <p class="text-sm font-semibold" style="color:var(--text)">{{ $sig['label'] }}</p>
                    <p class="text-xs mt-1" style="color:var(--muted2)">{{ $sig['hint'] }}</p>
                </div>
            @endif
        </section>

        {{-- Histórico --}}
        <section class="card p-5 min-w-0">
            <h2 class="card-title">Histórico</h2>
            <ul class="mt-3 flex flex-col gap-2 text-sm">
                @if($place->opted_out_at)<li style="color:var(--red)">Pediu para não receber mensagens (pelo raio-x) <span class="text-xs block" style="color:var(--muted)">{{ $place->opted_out_at->format('d/m/Y H:i') }}</span></li>@endif
                @if($place->report_last_view_at)<li>Empresa abriu o raio-x ({{ $place->report_views }}x) <span class="text-xs block" style="color:var(--muted)">última em {{ $place->report_last_view_at->format('d/m/Y H:i') }}</span></li>@endif
                @if($place->report_at)<li>Raio-x gerado <span class="text-xs block" style="color:var(--muted)">{{ $place->report_at->format('d/m/Y H:i') }}</span></li>@endif
                @if($place->collected_at)<li>Dados e site coletados <span class="text-xs block" style="color:var(--muted)">{{ $place->collected_at->format('d/m/Y H:i') }}</span></li>@endif
                @foreach($place->scans->sortByDesc('created_at') as $s)
                    <li>Mapeada na varredura "{{ $s->label() }}" <span class="text-xs block" style="color:var(--muted)">{{ $s->created_at->format('d/m/Y H:i') }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-app-layout>
