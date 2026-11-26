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

    <div @if($running) x-init="setTimeout(() => window.location.reload(), 6000)" @endif>

        {{-- Topo no desenho do protótipo (.claude/docs/prototipos/central-comercio.html):
             descrição à esquerda, Segmentos e Cidades como botões à direita. --}}
        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <p class="text-sm max-w-2xl" style="color:var(--muted2); line-height:1.6">
                Mapeie empresas por cidade e segmento, veja a Nota Nonna de presença digital e prepare a abordagem.
                A varredura busca no Google Maps e não tem custo. Os dados de cada empresa são consultados no Google uma vez e ficam guardados no App.
            </p>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ $tabUrl('configurar') }}#segmentos" class="btn btn-sm"><x-icon name="layers" size="14" /> Segmentos</a>
                <a href="{{ $tabUrl('configurar') }}#cidades" class="btn btn-sm"><x-icon name="map" size="14" /> Cidades</a>
            </div>
        </div>

        {{-- Abas — padrão do App (.tab-bar/.tab-btn). Envios e metas entram na etapa 6. --}}
        <nav class="tab-bar" aria-label="Prospecção">
            @foreach([
                'empresas'   => ['Empresas', 'building-2'],
                'ia'         => ['Rodadas de IA', 'sparkles'],
                'varreduras' => ['Histórico de varreduras', 'history'],
            ] as $key => [$label, $icon])
                <a href="{{ $tabUrl($key) }}" class="tab-btn {{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>
                    <x-icon :name="$icon" size="15" /> {{ $label }}
                    @if($key === 'varreduras' && $running && $tab !== 'ia')<span class="badge badge-orange">varrendo</span>@endif
                </a>
            @endforeach
            @if($tab === 'configurar')
                <span class="tab-btn active" aria-current="page"><x-icon name="settings-2" size="15" /> Segmentos e cidades</span>
            @endif
        </nav>

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">{{ session('error') }}</div>
        @endif
        @include('commerce._google-paid')

        @if(!$configured)
            <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); border-radius:var(--radius)">
                <b>Falta a chave do Google Maps.</b> Peça para o administrador cadastrar em
                <a href="{{ route('settings.index') }}?tab=integracoes" class="underline">Configurações → Integrações → Nova integração → Google Maps (Places)</a>.
                Enquanto isso, dá para configurar segmentos e cidades.
            </div>
        @endif

        {{-- Medidor do Google no mês (trava de custo — GoogleUsage). Na aba Empresas ele vira o card "Custo no mês". --}}
        @if($configured && $tab !== 'empresas')
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

        {{-- Nova varredura: sempre à vista em cima da lista (protótipo), não mais escondida atrás de botão. --}}
        @if(!in_array($tab, ['configurar', 'ia'], true))
            <div class="card p-5 mb-5">
                <h2 class="card-title mb-3 flex items-center gap-1.5"><x-icon name="search" size="15" /> Nova varredura</h2>
                <form method="POST" action="{{ route('commerce.scans.store') }}" class="grid gap-3 items-start" style="grid-template-columns:repeat(auto-fit, minmax(190px, 1fr))">
                    @csrf
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Cidade *
                        <select name="city_id" class="field text-sm px-2.5 py-1.5" required @disabled(!$configured)>
                            @foreach($cities as $c)
                                <option value="{{ $c->id }}" @selected(old('city_id') === $c->id)>{{ $c->label() }}</option>
                            @endforeach
                        </select>
                        @error('city_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Segmento
                        <select name="segment_id" class="field text-sm px-2.5 py-1.5" @disabled(!$configured)>
                            <option value="">— só o nicho —</option>
                            @foreach($segments->where('is_active', true) as $s)
                                <option value="{{ $s->id }}" @selected(old('segment_id') === $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('segment_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Nicho (opcional)
                        <input type="text" name="niche" value="{{ old('niche') }}" maxlength="120" class="field text-sm px-2.5 py-1.5" placeholder="ex.: harmonização facial" @disabled(!$configured)>
                        <span style="color:var(--muted)">Texto livre, para recortes que o Google não separa.</span>
                    </label>
                    <div class="text-xs flex flex-col gap-1">
                        <span aria-hidden="true">&nbsp;</span>
                        <div><button type="submit" class="btn btn-primary btn-sm" @disabled(!$configured)><x-icon name="search" size="14" /> Varrer</button></div>
                    </div>
                </form>
            </div>
        @endif

        @if($tab === 'empresas')
            @php
                $sc = !empty($filters['varredura']) ? $scans->firstWhere('id', $filters['varredura']) : null;
                $recorte = $sc?->label() ?? collect([
                    $segments->firstWhere('id', $filters['segmento'] ?? null)?->name,
                    $cities->firstWhere('id', $filters['cidade'] ?? null)?->name,
                ])->filter()->implode(' · ');
                $pctNota = $kpi->mapeadas ? round($kpi->com_nota / $kpi->mapeadas * 100) : 0;
                $chipUrl = fn ($situacao) => route('commerce.prospecting', array_filter(array_merge(['aba' => 'empresas'], \Illuminate\Support\Arr::except($filters, ['situacao']), ['situacao' => $situacao])));
                $situacao = $filters['situacao'] ?? '';
                $usageTitle = collect($usage)->map(fn ($u) => \Illuminate\Support\Str::before($u['label'], ' (') . ': ' . $u['used'] . ($u['free'] !== null ? ' de ' . $u['free'] . ' grátis' : ' (sem custo)'))->implode("\n");
            @endphp

            {{-- Números do topo (protótipo). Raio-x enviados e Responderam entram com o envio (etapa 6). --}}
            <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(170px, 1fr))">
                <div class="card px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Mapeadas</p>
                    <p class="text-2xl font-bold" style="color:var(--text)">{{ number_format($kpi->mapeadas, 0, ',', '.') }}</p>
                    <p class="text-xs truncate" style="color:var(--muted2)">{{ $recorte ?: 'todas as cidades e segmentos' }}</p>
                </div>
                <div class="card px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Com nota</p>
                    <p class="text-2xl font-bold" style="color:var(--purple)">{{ number_format($kpi->com_nota, 0, ',', '.') }}</p>
                    <p class="text-xs" style="color:var(--muted2)">{{ $pctNota }}% · Google + site</p>
                </div>
                <div class="card px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Com WhatsApp</p>
                    <p class="text-2xl font-bold" style="color:var(--green)">{{ number_format($kpi->com_whatsapp, 0, ',', '.') }}</p>
                    <p class="text-xs" style="color:var(--muted2)">achado no site da empresa</p>
                </div>
                @if($configured)
                    @php $col = $usage['coleta'] ?? null; $lst = $usage['lista'] ?? null; @endphp
                    <div class="card px-4 py-3" title="{{ $usageTitle }}">
                        <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Custo no mês</p>
                        <p class="text-2xl font-bold" style="color:{{ $usageUsd > 0 ? 'var(--orange)' : 'var(--text)' }}">US$ {{ number_format($usageUsd, 2, ',', '.') }}</p>
                        <p class="text-xs" style="color:var(--muted2)">
                            @if($col)Coleta {{ number_format($col['used'], 0, ',', '.') }} de {{ number_format($col['free'], 0, ',', '.') }}@endif
                            @if($lst) · Lista {{ number_format($lst['used'], 0, ',', '.') }} de {{ number_format($lst['free'], 0, ',', '.') }}@endif
                            grátis
                        </p>
                    </div>
                @endif
            </div>

            @if($sc && $sc->saturated)
                <div class="mb-4 px-4 py-3 text-sm" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); border-radius:var(--radius); color:var(--muted2)">
                    Pelo menos uma busca dessa varredura <b style="color:var(--text)">voltou cheia</b> (o Google entrega no máximo 60 por busca). Pode ter mais empresas: inclua outros termos no segmento e use <b style="color:var(--text)">Varrer de novo</b> no Histórico de varreduras. Quem já está não duplica.
                </div>
            @endif

            {{-- Filtros rápidos por situação (protótipo): Todas · Sem nota · Com nota · ... --}}
            @php
                $chips = ['' => ['Todas', $counts->except('discarded')->sum()]];
                foreach (\App\Models\CommercePlace::$statuses as $key => $st) {
                    $n = (int) ($counts[$key] ?? 0);
                    if ($n > 0 || in_array($key, ['mapped', 'scored'], true)) {
                        $chips[$key] = [$key === 'mapped' ? 'Sem nota' : $st['label'], $n];
                    }
                }
            @endphp
            <div class="flex items-center gap-1.5 flex-wrap mb-3">
                @foreach($chips as $key => [$label, $n])
                    <a href="{{ $chipUrl($key) }}" class="btn btn-xs {{ $situacao === $key ? 'btn-primary' : '' }}" @if($situacao === $key) aria-current="true" @endif>
                        {{ $label }} <span style="opacity:.7">{{ number_format($n, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" class="flex items-center gap-2 flex-wrap mb-4">
                <input type="hidden" name="aba" value="empresas">
                @if($situacao !== '')<input type="hidden" name="situacao" value="{{ $situacao }}">@endif
                <input type="search" name="busca" value="{{ $filters['busca'] ?? '' }}" placeholder="Buscar por nome" class="field text-sm px-2.5 py-1.5" style="width:180px">
                <select name="cidade" class="field text-sm pl-2.5 pr-8 py-1.5" onchange="this.form.submit()">
                    <option value="">Todas as cidades</option>
                    @foreach($cities as $c)<option value="{{ $c->id }}" @selected(($filters['cidade'] ?? '') === $c->id)>{{ $c->label() }}</option>@endforeach
                </select>
                <select name="segmento" class="field text-sm pl-2.5 pr-8 py-1.5" onchange="this.form.submit()">
                    <option value="">Todos os segmentos</option>
                    @foreach($segments as $s)<option value="{{ $s->id }}" @selected(($filters['segmento'] ?? '') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <select name="sinal" class="field text-sm pl-2.5 pr-8 py-1.5" onchange="this.form.submit()">
                    <option value="">Sinal: todos</option>
                    @foreach(\App\Services\Commerce\OpportunitySignal::$labels as $key => $sg)<option value="{{ $key }}" @selected(($filters['sinal'] ?? '') === $key)>{{ $sg['label'] }}</option>@endforeach
                </select>
                <select name="ordem" class="field text-sm pl-2.5 pr-8 py-1.5" onchange="this.form.submit()" aria-label="Ordenar">
                    @foreach(['' => 'Ordenar: menor nota primeiro', 'maior' => 'Maior nota primeiro', 'avaliacoes' => 'Mais avaliações no Google', 'recentes' => 'Mapeadas mais recentes'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['ordem'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($sc)
                    <input type="hidden" name="varredura" value="{{ $filters['varredura'] }}">
                    @if(!empty($filters['novas']))<input type="hidden" name="novas" value="1">@endif
                    <a href="{{ route('commerce.prospecting', array_merge(['aba' => 'empresas'], \Illuminate\Support\Arr::except($filters, ['varredura', 'novas']))) }}" class="badge badge-purple" style="padding:4px 10px">Varredura: {{ $sc->label() }}{{ !empty($filters['novas']) ? ' · só as novas da última rodada' : '' }} ✕</a>
                @endif
            </form>

            {{-- Lista com seleção (protótipo): marcar empresas e "Coletar selecionadas". --}}
            <div x-data="{ sel: [], page: @js($places->getCollection()->pluck('id')->values()) }">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <form method="POST" action="{{ route('commerce.places.collect-selected') }}">
                        @csrf
                        <input type="hidden" name="ids" :value="sel.join(',')">
                        <button type="submit" class="btn btn-xs btn-primary" :disabled="!sel.length || {{ $configured ? 'false' : 'true' }}"
                                title="Busca os dados no Google, checa o site e calcula a Nota Nonna das empresas marcadas">
                            <x-icon name="download" size="12" /> Coletar selecionadas (<span x-text="sel.length">0</span>)
                        </button>
                    </form>
                    <button type="button" class="btn btn-xs" x-show="sel.length" x-cloak @click="sel = []">Limpar seleção</button>
                    <span class="text-xs ml-auto" style="color:var(--muted)">Mostrando {{ $places->count() }} de {{ number_format($places->total(), 0, ',', '.') }}</span>
                </div>

                <div class="card" style="padding:0; overflow-x:auto">
                    <table class="nonna-table w-full">
                        <thead>
                            <tr>
                                <th style="width:32px"><input type="checkbox" aria-label="Marcar todas desta página"
                                       :checked="page.length && page.every(id => sel.includes(id))"
                                       @change="sel = $event.target.checked ? [...new Set([...sel, ...page])] : sel.filter(id => !page.includes(id))"></th>
                                <th>Empresa</th><th>Nota Nonna</th><th>Google</th><th>Site</th><th title="Em quantas respostas do ChatGPT a empresa foi citada (Rodadas de IA)">Nas IAs</th><th>Contato</th><th>Sinal</th><th>Situação</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($places as $p)
                                @php
                                    $g = $live[$p->place_id] ?? null;
                                    $st = \App\Models\CommercePlace::$statuses[$p->status] ?? null;
                                    $gd = $p->google_data ?? [];
                                    $site = $p->site_signals ?? [];
                                @endphp
                                <tr :style="sel.includes(@js($p->id)) ? 'background:rgba(100,59,142,.06)' : ''">
                                    <td><input type="checkbox" value="{{ $p->id }}" x-model="sel" aria-label="Marcar empresa"></td>
                                    <td class="min-w-0">
                                        @if($g)
                                            <a href="{{ route('commerce.places.show', $p) }}" class="font-semibold hover:underline" style="color:var(--text)">{{ $g['name'] }}</a>
                                            @if($g['closed'])<span class="badge badge-red ml-1">fechada</span>@endif
                                            <span class="block text-xs" style="color:var(--muted2)">{{ $g['type'] }}{{ $g['type'] && $g['address'] ? ' · ' : '' }}{{ $g['address'] }}</span>
                                        @else
                                            <a href="{{ route('commerce.places.show', $p) }}" class="text-xs hover:underline" style="color:var(--muted)">{{ $configured ? (($usage['lista']['left'] ?? 1) === 0 ? 'Franquia do mês acabou' : 'Não foi possível ler agora') : 'Sem chave do Google' }}</a>
                                        @endif
                                        <span class="block text-xs" style="color:var(--muted)">{{ $p->segment?->name ?? '—' }} · {{ $p->city?->name ?? '—' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        @if($p->score !== null)
                                            <b style="color:{{ $p->score >= $p->scoreMax() * 0.7 ? 'var(--green)' : ($p->score >= $p->scoreMax() * 0.45 ? 'var(--orange)' : 'var(--red)') }}">{{ $p->score }}</b><span class="text-xs" style="color:var(--muted)">/{{ $p->scoreMax() }}</span>
                                        @elseif($p->collect_error)
                                            <span class="text-xs" style="color:var(--red)" title="{{ $p->collect_error }}">erro</span>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    {{-- Google: nota e nº de avaliações (só depois da coleta). --}}
                                    <td class="whitespace-nowrap text-xs">
                                        @if(isset($gd['rating']))
                                            <span style="color:var(--text)">★ {{ number_format($gd['rating'], 1, ',', '') }}</span>
                                            <span style="color:var(--muted2)">· {{ number_format((int) ($gd['reviews'] ?? 0), 0, ',', '.') }}</span>
                                        @elseif($p->collected_at)
                                            <span style="color:var(--muted)">sem avaliações</span>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    {{-- Site: no ar? velocidade no celular (PageSpeed). --}}
                                    <td class="whitespace-nowrap text-xs">
                                        @if(!$p->collected_at)
                                            <span style="color:var(--muted)">—</span>
                                        @elseif(empty($gd['website']))
                                            <span style="color:var(--red)">sem site</span>
                                        @elseif(empty($site['reachable']))
                                            <span style="color:var(--red)" title="{{ $site['error'] ?? '' }}">não abriu</span>
                                        @elseif(isset($site['pagespeed']))
                                            @php $ps = (int) $site['pagespeed']; @endphp
                                            <span title="Velocidade no celular (PageSpeed)" style="color:{{ $ps >= 70 ? 'var(--green)' : ($ps >= 40 ? 'var(--orange)' : 'var(--red)') }}">celular {{ $ps }}</span>
                                        @else
                                            <span style="color:var(--green)">no ar</span>
                                        @endif
                                    </td>
                                    {{-- Nas IAs: citações na última rodada de IA do segmento e cidade. --}}
                                    @php $ai = $p->score_parts['ai_detail'] ?? null; @endphp
                                    <td class="whitespace-nowrap text-xs">
                                        @if($ai)
                                            <span style="color:{{ $ai['mentions'] ? 'var(--green)' : 'var(--red)' }}">{{ $ai['mentions'] }}/{{ $ai['answers'] }}</span>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    {{-- Contatos achados no site (guardados, sem custo de Google) --}}
                                    @php $ct = $site['contacts'] ?? []; @endphp
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
                                    <td class="whitespace-nowrap">@php $sg = \App\Services\Commerce\OpportunitySignal::$labels[$signals[$p->id] ?? ''] ?? null; @endphp
                                        @if($sg && $p->collected_at)<span class="badge badge-{{ $sg['color'] }}" title="{{ $sg['hint'] }}">{{ $sg['label'] }}</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap">@if($st)<span class="badge badge-{{ $st['color'] }}">{{ $p->status === 'mapped' ? 'Sem nota' : $st['label'] }}</span>@endif</td>
                                    <td class="text-right whitespace-nowrap">
                                        @if($g && $g['maps_url'])
                                            <a href="{{ $g['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-xs">Maps</a>
                                        @endif
                                        <form method="POST" action="{{ route('commerce.places.discard', $p) }}" class="inline">@csrf
                                            <button type="submit" class="btn btn-xs">{{ $p->status === 'discarded' ? 'Voltar' : 'Descartar' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-sm" style="color:var(--muted)">Nenhuma empresa aqui. Faça uma varredura ou mude os filtros.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
                <span class="text-xs" style="color:var(--muted)">Dados de empresas: <b>Google Maps</b>. Consultados uma vez e guardados no App; "Atualizar dados" na empresa busca de novo.</span>
                {{ $places->links() }}
            </div>
            <p class="text-xs mt-2" style="color:var(--muted)">Nota Nonna: perfil no Google (30) + reputação (30) + site (20) + nas IAs (20). Enquanto o segmento e a cidade não tiverem rodada de IA, a nota vai até 80. Menor nota primeiro: é quem mais precisa de ajuda.</p>

        @elseif($tab === 'ia')
            {{-- Rodadas de IA (etapa 4): perguntas de cliente ao ChatGPT com busca na web. --}}
            <div class="card p-5 mb-5">
                <h2 class="card-title mb-1 flex items-center gap-1.5"><x-icon name="sparkles" size="15" /> Nova rodada de IA</h2>
                <p class="text-xs mb-3" style="color:var(--muted2)">Perguntas que um cliente faria à IA. Cada uma roda algumas vezes no <b>ChatGPT com busca na internet</b>, porque a resposta muda a cada vez. Uma rodada vale para todas as empresas daquele segmento e cidade: quantas vezes cada uma foi citada vira a parte <b>"Nas IAs"</b> da nota (20 pontos).</p>
                <form method="POST" action="{{ route('commerce.ai-rounds.store') }}" class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(190px, 1fr))">
                    @csrf
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Cidade *
                        <select name="city_id" class="field text-sm px-2.5 py-1.5" required>
                            @foreach($cities as $c)<option value="{{ $c->id }}" @selected(old('city_id') === $c->id)>{{ $c->label() }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Segmento *
                        <select name="segment_id" class="field text-sm px-2.5 py-1.5" required>
                            @foreach($segments->where('is_active', true) as $s)<option value="{{ $s->id }}" @selected(old('segment_id') === $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                        @error('segment_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Repetições
                        <select name="repetitions" class="field text-sm px-2.5 py-1.5">
                            <option value="3">3 vezes cada pergunta (recomendado)</option>
                            <option value="2">2 vezes</option>
                            <option value="1">1 vez (teste rápido)</option>
                        </select>
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2); grid-column:1/-1">Perguntas (opcional, uma por linha, até 8)
                        <textarea name="questions" rows="3" class="field text-sm px-2.5 py-1.5" placeholder="Deixe em branco para usar as 5 perguntas padrão, montadas com o 1º termo do segmento e a cidade. Exemplo:&#10;{{ implode('&#10;', $exampleQuestions) }}">{{ old('questions') }}</textarea>
                    </label>
                    <div><button type="submit" class="btn btn-primary btn-sm"><x-icon name="sparkles" size="14" /> Perguntar à IA</button></div>
                </form>
            </div>

            @forelse($rounds as $round)
                @php
                    $st = \App\Models\CommerceAiRound::$statuses[$round->status] ?? null;
                    $ans = $roundAnswered[$round->id] ?? null;
                    $ok = (int) ($ans->ok ?? 0);
                    $cited = $roundMentions[$round->id] ?? collect();
                    $cityName = $round->city?->name;
                    $keys = $cited->mapWithKeys(fn ($m) => [$m->id => \App\Services\Commerce\AiRoundRunner::nameKeys((string) (json_decode($m->google_data, true)['name'] ?? ''), $cityName)]);
                @endphp
                <details class="card mb-3" style="padding:0" @if($loop->first) open @endif>
                    <summary class="px-5 py-4 cursor-pointer flex items-center gap-3 flex-wrap" style="list-style:none">
                        <b style="color:var(--text)">{{ $round->label() }}</b>
                        @if($st)<span class="badge badge-{{ $st['color'] }}">{{ $st['label'] }}</span>@endif
                        <span class="text-xs" style="color:var(--muted2)">
                            {{ count($round->questions) }} perguntas × {{ $round->repetitions }} = {{ $round->expected() }} respostas
                            @if(in_array($round->status, ['queued', 'running'], true)) · {{ (int) ($ans->n ?? 0) }} de {{ $round->expected() }} prontas @endif
                            · ChatGPT com busca · custo US$ {{ number_format($roundCost[$round->id] ?? 0, 2, ',', '.') }}
                            · {{ $round->created_at->format('d/m H:i') }}{{ $round->creator ? ' · ' . $round->creator->name : '' }}
                        </span>
                        @if($round->status === 'error')<span class="text-xs w-full" style="color:var(--red)">{{ $round->error }}</span>@endif
                    </summary>
                    <div class="px-5 pb-5 grid gap-5" style="grid-template-columns:repeat(auto-fit, minmax(300px, 1fr))">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted)">Perguntas</p>
                            <ol class="text-sm flex flex-col gap-1" style="list-style:decimal; padding-left:18px">
                                @foreach($round->questions as $q)<li>{{ $q }}</li>@endforeach
                            </ol>
                            <p class="text-xs mt-3" style="color:var(--muted)">A resposta da IA muda a cada vez que se pergunta. Por isso cada pergunta roda {{ $round->repetitions }} {{ $round->repetitions === 1 ? 'vez' : 'vezes' }}, e a nota usa a frequência, não uma resposta só.</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted)">Quem aparece nas respostas</p>
                            @if($round->status !== 'done')
                                <p class="text-sm" style="color:var(--muted)">Aparece quando todas as respostas chegarem.</p>
                            @elseif($cited->isEmpty())
                                <p class="text-sm" style="color:var(--muted)">Nenhuma empresa mapeada foi citada nas {{ $ok }} respostas.</p>
                            @else
                                <table class="w-full text-sm">
                                    @foreach($cited as $m)
                                        @php $gd = json_decode($m->google_data, true) ?? []; @endphp
                                        <tr style="border-bottom:1px solid var(--border)">
                                            <td class="py-1.5"><a href="{{ route('commerce.places.show', $m->id) }}" class="hover:underline" style="color:var(--text)">{{ $gd['name'] ?? '—' }}</a></td>
                                            <td class="py-1.5 text-right whitespace-nowrap"><b>{{ $m->mentions }}</b><span style="color:var(--muted)">/{{ $ok }}</span></td>
                                        </tr>
                                    @endforeach
                                </table>
                                <p class="text-xs mt-2" style="color:var(--muted)">As outras empresas do segmento na cidade: 0/{{ $ok }}.</p>
                            @endif
                        </div>
                        @if($round->status === 'done')
                            @php
                                $answers = $round->answers()->orderBy('question_index')->orderBy('repetition')->get();
                                $loose = $answers->pluck('names')->flatten()->filter()->map(fn ($n) => trim($n))->unique(fn ($n) => \App\Services\Commerce\AiRoundRunner::norm($n))
                                    ->reject(fn ($n) => $keys->flatten()->contains(fn ($k) => str_contains(' ' . \App\Services\Commerce\AiRoundRunner::norm($n) . ' ', " {$k} ")))->values();
                            @endphp
                            <div style="grid-column:1/-1">
                                @if($loose->isNotEmpty())
                                    <p class="text-xs mb-3" style="color:var(--muted2)"><b style="color:var(--text)">Citados pela IA que não batem com nenhuma empresa mapeada ({{ $loose->count() }}):</b> {{ $loose->take(20)->implode(' · ') }}. Pode ser empresa que a varredura não achou, ou nome escrito diferente do Google.</p>
                                @endif
                                <details>
                                    <summary class="text-xs cursor-pointer" style="color:var(--purple)">Ler as {{ $answers->count() }} respostas da IA</summary>
                                    <div class="flex flex-col gap-3 mt-3">
                                        @foreach($answers as $a)
                                            <div class="rounded px-3 py-2 text-sm" style="background:var(--s2)">
                                                <p class="text-xs font-semibold mb-1" style="color:var(--muted2)">{{ $round->questions[$a->question_index] ?? '' }} · {{ $a->repetition }}ª vez</p>
                                                @if($a->error)<p style="color:var(--red)">{{ $a->error }}</p>@else<div style="white-space:pre-line; color:var(--text)">{{ $a->answer }}</div>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            </div>
                        @endif
                    </div>
                </details>
            @empty
                <div class="card card-body text-sm" style="color:var(--muted)">Nenhuma rodada ainda. Comece por um segmento e cidade que já tenham varredura e empresas com nota.</div>
            @endforelse

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
                                <td class="text-xs whitespace-nowrap" style="color:var(--muted2)">
                                    @if($s->runs > 1)
                                        Última: {{ $s->lastRunStart()->format('d/m H:i') }}
                                        <span class="block">rodou {{ $s->runs }} vezes</span>
                                        <span class="block" style="color:var(--muted)">1ª em {{ $s->created_at->format('d/m') }} · {{ $s->creator?->name }}</span>
                                    @else
                                        {{ $s->created_at->format('d/m H:i') }}<span class="block">{{ $s->creator?->name }}</span>
                                    @endif
                                </td>
                                @php $pg = $progress[$s->id] ?? null; @endphp
                                <td class="text-sm">{{ $s->status === 'done' ? $s->places_found : '—' }}
                                    @if($s->status === 'done')
                                        @if($s->places_new)
                                            <a href="{{ $tabUrl('empresas', ['varredura' => $s->id, 'novas' => 1]) }}" class="block text-xs underline" style="color:var(--green)" title="Empresas que entraram {{ $s->runs > 1 ? 'na última rodada' : 'nesta varredura' }}">{{ $s->runs > 1 ? 'Ver as ' . $s->places_new . ' novas' : $s->places_new . ' nova(s)' }}</a>
                                        @elseif($s->runs > 1)
                                            <span class="block text-xs" style="color:var(--muted)">nenhuma nova na última rodada</span>
                                        @endif
                                    @endif
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
                                @endif
                                @if(in_array($s->status, ['done', 'error'], true))
                                    <form method="POST" action="{{ route('commerce.scans.rerun', $s) }}" class="inline">@csrf
                                        <button type="submit" class="btn btn-xs" title="Roda a mesma busca de novo e atualiza esta linha. Quem já estava não duplica; as novas aparecem aqui.">
                                            <x-icon name="refresh-cw" size="12" /> {{ $s->status === 'error' ? 'Tentar de novo' : 'Varrer de novo' }}
                                        </button>
                                    </form>
                                @endif
                                @if($canEdit && !in_array($s->status, ['queued', 'running'], true))
                                    <form method="POST" action="{{ route('commerce.scans.destroy', $s) }}" class="inline" x-data
                                          @submit.prevent="if (await $store.confirmDialog.ask('Excluir a varredura {{ addslashes($s->label()) }} e as empresas que vieram só por ela? Empresas que também estão em outra varredura, ou que já tiveram conversa, ficam. Não dá para desfazer.')) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs" style="color:var(--red)" title="Apaga a varredura e as empresas que só vieram por ela">
                                            <x-icon name="trash-2" size="12" /> Excluir
                                        </button>
                                    </form>
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
                <section id="segmentos" class="card p-5 min-w-0">
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

                <section id="cidades" class="card p-5 min-w-0">
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

                {{-- Botão do raio-x público: "Quero melhorar meu raio-x" abre este WhatsApp com mensagem pronta. --}}
                <section id="raio-x" class="card p-5 min-w-0">
                    <h2 class="card-title">WhatsApp do raio-x</h2>
                    <p class="text-xs mt-1 mb-3" style="color:var(--muted2)">É o número do botão <b>"Quero melhorar meu raio-x"</b>, na página que a empresa recebe. Ele abre o WhatsApp com a mensagem "Vi o raio-x da [empresa] e quero entender como melhorar", e o clique aparece na página da empresa. Sem número, a página pede para responder a mensagem da abordagem.</p>
                    @php $rxWhats = data_get(app('currentOrganization')->settings, 'prospecting.whatsapp'); @endphp
                    @if($canEdit)
                        <form method="POST" action="{{ route('commerce.report-contact') }}" class="flex gap-2 flex-wrap">@csrf
                            <input type="text" name="whatsapp" value="{{ old('whatsapp', $rxWhats) }}" class="field text-sm px-2.5 py-1.5 flex-1 min-w-0" placeholder="(49) 99999-9999" maxlength="30">
                            <button type="submit" class="btn btn-sm">Salvar</button>
                        </form>
                        @error('whatsapp')<p class="text-xs mt-2" style="color:var(--red)">{{ $message }}</p>@enderror
                    @else
                        <p class="text-sm">{{ $rxWhats ?: 'Ainda sem número.' }}</p>
                    @endif
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
