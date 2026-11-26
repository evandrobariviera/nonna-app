{{-- Central de Comércio → Prospecção (CommerceProspectingController).
     Etapa 2: varredura no Google Places por cidade + segmento/nicho. Termos do Google:
     dados do Google guardados em google_data (decisão 25/11/2026); quem ainda não tem é consultado 1x,
     sempre com a atribuição "Google Maps". Config (segmentos/cidades): só admin/dono. --}}
@php
    $canEdit = $isOrgAdmin ?? false;
    $tabUrl = fn ($aba, $params = []) => route('commerce.prospecting', array_merge(['aba' => $aba], $params));
@endphp
<x-app-layout>
    <x-slot name="header">Prospecção</x-slot>

    <div x-data="{ newScan: {{ $errors->hasAny(['city_id', 'segment_id', 'niche']) ? 'true' : 'false' }} }"
         @if($running) x-init="setTimeout(() => window.location.reload(), 6000)" @endif>

        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <p class="text-sm max-w-2xl" style="color:var(--muted2); line-height:1.6">
                Encontre empresas de um segmento numa cidade, veja quem são e prepare a abordagem.
                A varredura busca no Google Maps e não tem custo. Os dados de cada empresa são consultados no Google uma vez e ficam guardados no App.
            </p>
            <button type="button" class="btn btn-primary btn-sm" @click="newScan = !newScan" @disabled(!$configured)>
                <x-icon name="search" size="14" /> Nova varredura
            </button>
        </div>

        {{-- Abas — padrão do App (.tab-bar/.tab-btn, igual Cliente e Configurações). --}}
        <nav class="tab-bar" aria-label="Prospecção">
            @foreach([
                'empresas'   => ['Empresas', 'building-2'],
                'varreduras' => ['Varreduras', 'history'],
                'configurar' => ['Segmentos e cidades', 'settings-2'],
            ] as $key => [$label, $icon])
                <a href="{{ $tabUrl($key) }}" class="tab-btn {{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>
                    <x-icon :name="$icon" size="15" /> {{ $label }}
                    @if($key === 'varreduras' && $running)<span class="badge badge-orange">varrendo</span>@endif
                </a>
            @endforeach
        </nav>

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">{{ session('error') }}</div>
        @endif
        @include('commerce._google-paid')

        {{-- Medidor do Google no mês (trava de custo — GoogleUsage). --}}
        @if($configured)
            <div class="mb-5 px-4 py-3 text-xs flex flex-wrap gap-x-6 gap-y-2 items-center" style="border:1px solid var(--border2); border-radius:var(--radius); color:var(--muted2)">
                <span class="font-semibold flex items-center gap-1.5" style="color:var(--text)"><x-icon name="circle-dollar-sign" size="14" /> Google neste mês</span>
                @foreach($usage as $u)
                    @php $pct = $u['free'] ? min(100, round($u['used'] / $u['free'] * 100)) : 0; @endphp
                    <span title="{{ $u['label'] }}">
                        {{ \Illuminate\Support\Str::before($u['label'], ' (') }}:
                        @if($u['free'] === null)
                            <b style="color:var(--text)">{{ number_format($u['used'], 0, ',', '.') }}</b> · sem custo
                        @else
                            <b style="color:{{ $pct >= 100 ? 'var(--red)' : ($pct >= 80 ? 'var(--orange)' : 'var(--text)') }}">{{ number_format($u['used'], 0, ',', '.') }} de {{ number_format($u['free'], 0, ',', '.') }}</b> grátis
                            @if($u['left'] === 0)<span style="color:var(--red)"> · acabou, travado</span>@endif
                        @endif
                    </span>
                @endforeach
            </div>
        @endif

        @if(!$configured)
            <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); border-radius:var(--radius)">
                <b>Falta a chave do Google Maps.</b> Peça para o administrador cadastrar em
                <a href="{{ route('settings.index') }}?tab=integracoes" class="underline">Configurações → Integrações → Nova integração → Google Maps (Places)</a>.
                Enquanto isso, dá para configurar segmentos e cidades.
            </div>
        @endif

        {{-- Nova varredura --}}
        <div x-show="newScan" x-transition style="display:none" class="card p-5 mb-5">
            <h2 class="card-title mb-3">Nova varredura</h2>
            <form method="POST" action="{{ route('commerce.scans.store') }}" class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr))">
                @csrf
                <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Cidade *
                    <select name="city_id" class="field text-sm px-2.5 py-1.5" required>
                        @foreach($cities as $c)
                            <option value="{{ $c->id }}" @selected(old('city_id') === $c->id)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                    @error('city_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Segmento
                    <select name="segment_id" class="field text-sm px-2.5 py-1.5">
                        <option value="">— só o nicho —</option>
                        @foreach($segments->where('is_active', true) as $s)
                            <option value="{{ $s->id }}" @selected(old('segment_id') === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('segment_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Nicho (opcional)
                    <input type="text" name="niche" value="{{ old('niche') }}" maxlength="120" class="field text-sm px-2.5 py-1.5" placeholder="ex.: harmonização facial">
                    <span style="color:var(--muted)">Com nicho, a busca usa só ele (recorte que o Google não separa).</span>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm">Varrer</button>
                </div>
            </form>
        </div>

        @if($tab === 'empresas')
            {{-- Filtros --}}
            <form method="GET" class="flex items-center gap-2 flex-wrap mb-4">
                <input type="hidden" name="aba" value="empresas">
                <input type="search" name="busca" value="{{ $filters['busca'] ?? '' }}" placeholder="Buscar por nome" class="field text-sm px-2.5 py-1.5" style="width:180px">
                <select name="cidade" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Todas as cidades</option>
                    @foreach($cities as $c)<option value="{{ $c->id }}" @selected(($filters['cidade'] ?? '') === $c->id)>{{ $c->label() }}</option>@endforeach
                </select>
                <select name="segmento" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Todos os segmentos</option>
                    @foreach($segments as $s)<option value="{{ $s->id }}" @selected(($filters['segmento'] ?? '') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <select name="situacao" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Todas as situações</option>
                    @foreach(\App\Models\CommercePlace::$statuses as $key => $st)<option value="{{ $key }}" @selected(($filters['situacao'] ?? '') === $key)>{{ $st['label'] }}</option>@endforeach
                </select>
                <select name="sinal" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Sinal: todos</option>
                    @foreach(\App\Services\Commerce\OpportunitySignal::$labels as $key => $sg)<option value="{{ $key }}" @selected(($filters['sinal'] ?? '') === $key)>{{ $sg['label'] }}</option>@endforeach
                </select>
                @if(!empty($filters['varredura']))
                    @php $sc = $scans->firstWhere('id', $filters['varredura']); @endphp
                    <input type="hidden" name="varredura" value="{{ $filters['varredura'] }}">
                    <a href="{{ route('commerce.prospecting', array_merge(['aba' => 'empresas'], \Illuminate\Support\Arr::except($filters, 'varredura'))) }}" class="badge badge-purple" style="padding:4px 10px">Varredura: {{ $sc?->label() ?? '—' }} ✕</a>
                @endif
                <span class="text-xs ml-auto" style="color:var(--muted)">{{ $places->total() }} empresa(s)</span>
            </form>

            <div class="card" style="padding:0; overflow-x:auto">
                <table class="nonna-table w-full">
                    <thead>
                        <tr><th>Empresa</th><th>Segmento · Cidade</th><th>Nota Nonna</th><th>Contato</th><th>Sinal</th><th>Situação</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($places as $p)
                            @php $g = $live[$p->place_id] ?? null; $st = \App\Models\CommercePlace::$statuses[$p->status] ?? null; @endphp
                            <tr>
                                <td class="min-w-0">
                                    @if($g)
                                        <a href="{{ route('commerce.places.show', $p) }}" class="font-semibold hover:underline" style="color:var(--text)">{{ $g['name'] }}</a>
                                        @if($g['closed'])<span class="badge badge-red ml-1">fechada</span>@endif
                                        <span class="block text-xs" style="color:var(--muted2)">{{ $g['type'] }}{{ $g['type'] && $g['address'] ? ' · ' : '' }}{{ $g['address'] }}</span>
                                    @else
                                        <a href="{{ route('commerce.places.show', $p) }}" class="text-xs hover:underline" style="color:var(--muted)">{{ $configured ? (($usage['lista']['left'] ?? 1) === 0 ? 'Franquia do mês acabou' : 'Não foi possível ler agora') : 'Sem chave do Google' }}</a>
                                    @endif
                                </td>
                                <td class="text-xs" style="color:var(--muted2)">{{ $p->segment?->name ?? '—' }} · {{ $p->city?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">
                                    @if($p->score !== null)
                                        <b style="color:{{ $p->score >= $p->scoreMax() * 0.7 ? 'var(--green)' : ($p->score >= $p->scoreMax() * 0.45 ? 'var(--orange)' : 'var(--red)') }}">{{ $p->score }}</b><span class="text-xs" style="color:var(--muted)">/{{ $p->scoreMax() }}</span>
                                    @elseif($p->collect_error)
                                        <span class="text-xs" style="color:var(--red)" title="{{ $p->collect_error }}">erro</span>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                {{-- Contatos achados no site (guardados, sem custo de Google) --}}
                                @php $ct = $p->site_signals['contacts'] ?? []; @endphp
                                <td class="whitespace-nowrap text-xs">
                                    @if($wa = $ct['whatsapp'][0] ?? null)
                                        <a href="https://wa.me/55{{ preg_replace('/\D/', '', $wa) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline" style="color:var(--green)"><x-icon name="message-circle" size="12" />{{ $wa }}</a>
                                    @elseif($tel = $ct['phones'][0] ?? null)
                                        <span class="inline-flex items-center gap-1" style="color:var(--muted2)"><x-icon name="phone" size="12" />{{ $tel }}</span>
                                    @endif
                                    <span class="inline-flex gap-1 align-middle">
                                        @if($ig = $ct['instagram'][0] ?? null)<a href="{{ $ig }}" target="_blank" rel="noopener" title="Instagram" class="badge badge-muted" style="padding:1px 5px">IG</a>@endif
                                        @if($em = $ct['emails'][0] ?? null)<a href="mailto:{{ $em }}" title="{{ $em }}" class="badge badge-muted" style="padding:1px 5px"><x-icon name="mail" size="11" /></a>@endif
                                    </span>
                                    @if(!$ct && !$p->collected_at)<span style="color:var(--muted)">—</span>@endif
                                </td>
                                <td>@php $sg = \App\Services\Commerce\OpportunitySignal::$labels[$signals[$p->id] ?? ''] ?? null; @endphp
                                    @if($sg && $p->collected_at)<span class="badge badge-{{ $sg['color'] }}" title="{{ $sg['hint'] }}">{{ $sg['label'] }}</span>@endif
                                </td>
                                <td>@if($st)<span class="badge badge-{{ $st['color'] }}">{{ $st['label'] }}</span>@endif</td>
                                <td class="text-right whitespace-nowrap">
                                    @if($g && $g['maps_url'])
                                        <a href="{{ $g['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-xs">Ver no Maps</a>
                                    @endif
                                    <form method="POST" action="{{ route('commerce.places.discard', $p) }}" class="inline">@csrf
                                        <button type="submit" class="btn btn-xs">{{ $p->status === 'discarded' ? 'Voltar' : 'Descartar' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-sm" style="color:var(--muted)">Nenhuma empresa ainda. Faça uma varredura.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
                <span class="text-xs" style="color:var(--muted)">Dados de empresas: <b>Google Maps</b>. Consultados uma vez e guardados no App; "Atualizar dados" na empresa busca de novo.</span>
                {{ $places->links() }}
            </div>
            <p class="text-xs mt-2" style="color:var(--muted)">Nota Nonna: perfil no Google (30) + reputação (30) + site (20). A parte "nas IAs" (20) entra com as rodadas de IA; até lá a nota vai até 80. Menor nota primeiro: é quem mais precisa de ajuda.</p>

        @elseif($tab === 'varreduras')
            <div class="card" style="padding:0; overflow-x:auto">
                <table class="nonna-table w-full">
                    <thead><tr><th>Varredura</th><th>Quando</th><th>Empresas</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                        @forelse($scans as $s)
                            @php $st = \App\Models\CommerceScan::$statuses[$s->status] ?? null; @endphp
                            <tr>
                                <td>
                                    <b style="color:var(--text)">{{ $s->label() }}</b>
                                    @if($s->terms)
                                        <span class="block text-xs" style="color:var(--muted2)">{{ collect($s->terms)->map(fn ($t) => \Illuminate\Support\Str::before($t['term'], ' em ') . ' (' . $t['found'] . ($t['saturated'] ? ', cheia' : '') . ')')->implode(' · ') }}</span>
                                    @endif
                                    @if($s->status === 'error')<span class="block text-xs" style="color:var(--red)">{{ $s->error }}</span>@endif
                                    @if($s->saturated)
                                        <span class="block text-xs mt-1" style="color:var(--orange)">Alguma busca voltou cheia (60, o máximo do Google): pode haver mais empresas. Rode nichos mais específicos para completar.</span>
                                    @endif
                                </td>
                                <td class="text-xs whitespace-nowrap" style="color:var(--muted2)">{{ $s->created_at->format('d/m H:i') }}<span class="block">{{ $s->creator?->name }}</span></td>
                                @php $pg = $progress[$s->id] ?? null; @endphp
                                <td class="text-sm">{{ $s->status === 'done' ? $s->places_found : '—' }}@if($s->status === 'done' && $s->places_new)<span class="block text-xs" style="color:var(--green)">{{ $s->places_new }} nova(s)</span>@endif
                                    @if($pg && $pg->total)<span class="block text-xs" style="color:var(--muted2)">{{ $pg->collected }} de {{ $pg->total }} coletada(s)</span>@endif
                                </td>
                                <td>@if($st)<span class="badge badge-{{ $st['color'] }}">{{ $st['label'] }}</span>@endif</td>
                                <td class="text-right whitespace-nowrap">@if($s->status === 'done')
                                    <a href="{{ $tabUrl('empresas', ['varredura' => $s->id]) }}" class="btn btn-xs">Ver empresas</a>
                                    @if($pg && $pg->collected < $pg->total)
                                        <form method="POST" action="{{ route('commerce.scans.collect', $s) }}" class="inline">@csrf
                                            <button type="submit" class="btn btn-xs btn-primary" title="Busca os dados no Google, checa o site e calcula a Nota Nonna de cada empresa">Coletar e dar nota</button>
                                        </form>
                                    @endif
                                @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-sm" style="color:var(--muted)">Nenhuma varredura ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            <div class="grid gap-5" style="grid-template-columns:repeat(auto-fit, minmax(320px, 1fr))">
                <section class="card p-5 min-w-0">
                    <h2 class="card-title">Segmentos</h2>
                    <p class="text-xs mt-1 mb-3" style="color:var(--muted2)">Cada termo vira uma busca no Google ("dentista em Videira SC"). O tipo do Google filtra a busca (opcional) — no campo, digite em português que ele sugere; a tabela de consulta fica no fim da página.</p>
                    <div class="flex flex-col gap-2">
                        @foreach($segments as $s)
                            <details class="rounded px-3 py-2" style="background:var(--s2)">
                                <summary class="text-sm cursor-pointer"><b style="color:var(--text)">{{ $s->name }}</b>
                                    @unless($s->is_active)<span class="badge badge-muted ml-1">desativado</span>@endunless
                                    <span class="block text-xs" style="color:var(--muted2)">{{ implode(', ', $s->search_terms ?? []) }}@if($s->google_type) · tipo {{ \App\Services\Commerce\GooglePlaceTypes::label($s->google_type) ? \App\Services\Commerce\GooglePlaceTypes::label($s->google_type) . ' (' . $s->google_type . ')' : $s->google_type }}@endif</span>
                                </summary>
                                @if($canEdit)
                                    <form method="POST" action="{{ route('commerce.segments.update', $s) }}" class="grid gap-2 mt-3">@csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $s->name }}" class="field text-sm px-2.5 py-1.5" required maxlength="120">
                                        <textarea name="search_terms" rows="2" class="field text-sm px-2.5 py-1.5" required>{{ implode("\n", $s->search_terms ?? []) }}</textarea>
                                        <input type="text" name="google_type" list="google-types" autocomplete="off" value="{{ $s->google_type }}" class="field text-sm px-2.5 py-1.5" placeholder="tipo do Google (opcional) — digite em português">
                                        <label class="text-xs flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> Ativo</label>
                                        <div><button type="submit" class="btn btn-xs btn-primary">Salvar</button></div>
                                    </form>
                                @endif
                            </details>
                        @endforeach
                    </div>
                    @if($canEdit)
                        <form method="POST" action="{{ route('commerce.segments.store') }}" class="grid gap-2 mt-4 pt-4" style="border-top:1px solid var(--border)">@csrf
                            <p class="text-xs font-semibold" style="color:var(--text)">Novo segmento</p>
                            <input type="text" name="name" value="{{ old('name') }}" class="field text-sm px-2.5 py-1.5" placeholder="ex.: Saúde · Fisioterapia" required maxlength="120">
                            <textarea name="search_terms" rows="2" class="field text-sm px-2.5 py-1.5" placeholder="um termo por linha: fisioterapia, clínica de fisioterapia" required>{{ old('search_terms') }}</textarea>
                            <input type="text" name="google_type" id="new-segment-type" list="google-types" autocomplete="off" value="{{ old('google_type') }}" class="field text-sm px-2.5 py-1.5" placeholder="tipo do Google (opcional) — digite em português, ex.: fisio">
                            <span class="text-xs" style="color:var(--muted)">Não sabe o tipo? Veja a tabela de consulta logo abaixo.</span>
                            @foreach(['name', 'search_terms', 'google_type'] as $f)@error($f)<span class="text-xs" style="color:var(--red)">{{ $message }}</span>@enderror @endforeach
                            <div><button type="submit" class="btn btn-sm">Incluir segmento</button></div>
                        </form>
                    @endif
                </section>

                <section class="card p-5 min-w-0">
                    <h2 class="card-title">Cidades</h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @foreach($cities as $c)
                            <div class="flex items-center justify-between rounded px-3 py-2 text-sm" style="background:var(--s2)">
                                <span class="min-w-0">{{ $c->label() }}
                                    <span class="block text-xs" style="color:var(--muted2)">
                                        @if($c->located())<x-icon name="map" size="11" /> Google: {{ $c->google_label }} · a varredura fica dentro da cidade
                                        @else Ainda não localizada no Google · localiza na próxima varredura @endif
                                    </span>
                                </span>
                                @if($canEdit)
                                    <form method="POST" action="{{ route('commerce.cities.destroy', $c) }}" @submit.prevent="if (await $store.confirmDialog.ask('Tirar {{ addslashes($c->name) }} da lista?')) $el.submit()">@csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs">Remover</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($canEdit)
                        <form method="POST" action="{{ route('commerce.cities.store') }}" class="flex gap-2 mt-4 pt-4 flex-wrap" style="border-top:1px solid var(--border)">@csrf
                            <input type="text" name="name" class="field text-sm px-2.5 py-1.5 flex-1 min-w-0" placeholder="Cidade" required maxlength="120">
                            <input type="text" name="state" class="field text-sm px-2.5 py-1.5" style="width:64px" placeholder="UF" required maxlength="2">
                            <button type="submit" class="btn btn-sm">Incluir</button>
                        </form>
                        @error('city')<p class="text-xs mt-2" style="color:var(--red)">{{ $message }}</p>@enderror
                        <p class="text-xs mt-2" style="color:var(--muted)">Escreva com acento (Tangará, não Tangara). O App confere no Google se a cidade existe naquela UF.</p>
                    @endif
                    @unless($canEdit)<p class="text-xs mt-3" style="color:var(--muted)">Só administradores mudam segmentos e cidades.</p>@endunless
                </section>
            </div>

            {{-- Tipos do Google traduzidos (GooglePlaceTypes): sugestão nos campos + tabela de consulta. --}}
            <datalist id="google-types">
                @foreach($googleTypes as $type => $t)<option value="{{ $type }}" label="{{ $t['label'] }} · {{ $t['group'] }}"></option>@endforeach
            </datalist>

            <section class="card p-5 mt-5" x-data="{ q: '', norm: s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() }">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                    <div>
                        <h2 class="card-title">Tipos do Google — consulta</h2>
                        <p class="text-xs mt-1 max-w-2xl" style="color:var(--muted2); line-height:1.6">
                            O tipo é opcional e filtra a varredura: só entram empresas que o Google classificou daquele jeito.
                            Cada segmento aceita <b>um tipo só</b> — se o segmento mistura coisas diferentes (ex.: carros e motos), deixe em branco e use só os termos.
                            <a href="{{ \App\Services\Commerce\GooglePlaceTypes::SOURCE_URL }}" target="_blank" rel="noopener" class="underline">Lista completa do Google</a>.
                        </p>
                    </div>
                    <input type="search" x-model="q" placeholder="Buscar: dentista, pizza, loja…" class="field text-sm px-2.5 py-1.5" style="width:220px">
                </div>
                <div class="overflow-y-auto" style="max-height:420px; border:1px solid var(--border2); border-radius:var(--radius)">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0" style="background:var(--s2)">
                            <tr class="text-left text-xs" style="color:var(--muted2)">
                                <th class="px-3 py-2 font-semibold">Em português</th>
                                <th class="px-3 py-2 font-semibold">Tipo do Google</th>
                                <th class="px-3 py-2 font-semibold">Área</th>
                                @if($canEdit)<th class="px-3 py-2"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($googleTypes as $type => $t)
                                <tr x-show="!q || norm(@js($t['label'] . ' ' . $type . ' ' . $t['group'])).includes(norm(q))" style="border-top:1px solid var(--border)">
                                    <td class="px-3 py-1.5" style="color:var(--text)">{{ $t['label'] }}</td>
                                    <td class="px-3 py-1.5"><code class="text-xs">{{ $type }}</code></td>
                                    <td class="px-3 py-1.5 text-xs" style="color:var(--muted2)">{{ $t['group'] }}</td>
                                    @if($canEdit)
                                        <td class="px-3 py-1.5 text-right">
                                            <button type="button" class="btn btn-xs" title="Preencher o tipo no Novo segmento"
                                                    @click="const f = document.getElementById('new-segment-type'); f.value = @js($type); f.scrollIntoView({ behavior: 'smooth', block: 'center' }); f.focus()">Usar</button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
