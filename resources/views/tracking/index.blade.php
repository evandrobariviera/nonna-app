<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Campanhas</h1>
        </div>
    </x-slot>

    @php
        $state = fn ($t) => !$t ? ['Sem rastreamento', 'muted'] : ($t->send_enabled ? ['Enviando', 'green'] : ($t->isTestMode() ? ['Em teste', 'purple'] : ['Desligado', 'orange']));
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <form method="GET" action="{{ route('tracking.index') }}">
                <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                    <option value="todos">Todos os clientes</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($client?->id === $c->id)>{{ $c->displayName() }}</option>
                    @endforeach
                </select>
            </form>
            <span class="text-sm" style="color:var(--muted2)">Para onde o App manda as conversões de cada cliente. Substitui o Tag Manager.</span>
        </div>

        @if(!$client)
            <section class="card p-5">
                <h2 class="card-title">Clientes</h2>
                <div class="flex flex-col mt-3">
                    @foreach($clients as $c)
                        @php $t = $trackings->get($c->id); [$label, $color] = $state($t); $l = $last->get($c->id); @endphp
                        <a href="{{ route('tracking.index', ['client_id' => $c->id]) }}" class="flex flex-wrap items-center gap-2 py-2.5 text-sm" style="{{ !$loop->first ? 'border-top:1px solid var(--border)' : '' }}">
                            <b class="min-w-0 flex-1" style="color:var(--text)">{{ $c->displayName() }}</b>
                            <span class="text-xs" style="color:var(--muted2)">
                                Pixel {{ $t?->meta_pixel_id ? '✓' : '—' }} · Google {{ $t?->google_lead_action ? '✓' : '—' }} · GA4 {{ $t?->ga4_measurement_id ? '✓' : '—' }}
                                @if($l) · último envio {{ \Illuminate\Support\Carbon::parse($l->last_at)->format('d/m H:i') }}@endif
                            </span>
                            @if($l && $l->errors)<span class="badge badge-red">{{ $l->errors }} erro(s)</span>@endif
                            <span class="badge badge-{{ $color }}">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            @php $t = $detail['tracking']; [$label, $color] = $state($t); @endphp
            <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <form method="POST" action="{{ route('tracking.update', $client->id) }}" class="card p-5 flex flex-col gap-5 min-w-0">
                    @csrf
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <h2 class="card-title">{{ $client->displayName() }}</h2>
                        <span class="badge badge-{{ $color }}">{{ $label }}</span>
                    </div>

                    {{-- Meta --}}
                    <div>
                        <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">Meta — Pixel (conjunto de dados)</p>
                        @if($detail['pixels'])
                            <select name="meta_pixel_id" class="text-sm w-full min-w-0">
                                <option value="">Não enviar para a Meta</option>
                                @foreach($detail['pixels'] as $p)
                                    <option value="{{ $p['id'] }}" @selected($t->meta_pixel_id === $p['id'] || (!$t->meta_pixel_id && count($detail['pixels']) === 1))>{{ $p['name'] }} ({{ $p['id'] }}){{ $p['last'] ? ' · último uso ' . \Illuminate\Support\Carbon::parse($p['last'])->format('d/m/Y') : ' · nunca usado' }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="meta_pixel_id" value="{{ old('meta_pixel_id', $t->meta_pixel_id) }}" placeholder="ID do Pixel (só números)" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                            <p class="text-xs mt-1" style="color:var(--muted)">O App não achou Pixel nas contas Meta deste cliente; digite o ID.</p>
                        @endif
                        <label class="text-xs flex flex-col gap-1 mt-2" style="color:var(--muted2)">Código de teste <span style="color:var(--muted)">(da aba "Eventos de teste" do Gerenciador de Eventos; com ele preenchido, nada conta para os anúncios)</span>
                            <input type="text" name="meta_test_event_code" value="{{ old('meta_test_event_code', $t->meta_test_event_code) }}" placeholder="Ex.: TEST12345" class="field text-sm px-2.5 py-1.5 w-full min-w-0" style="max-width:220px">
                        </label>
                    </div>

                    {{-- Google Ads --}}
                    <div>
                        <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">Google Ads — conversão importada</p>
                        @if($detail['googleAccounts']->isEmpty())
                            <p class="text-sm" style="color:var(--muted)">Este cliente não tem conta do Google Ads cadastrada.</p>
                        @else
                            <select name="google_ads_account_id" class="text-sm w-full min-w-0 mb-2">
                                <option value="">Não enviar para o Google</option>
                                @foreach($detail['googleAccounts'] as $g)
                                    <option value="{{ $g->id }}" @selected($t->google_ads_account_id === $g->id)>Google · {{ $g->account_id }}</option>
                                @endforeach
                            </select>
                            @foreach(['lead' => ['google_lead_action', 'Lead'], 'venda' => ['google_purchase_action', 'Venda']] as $kind => [$field, $word])
                                <div class="flex items-center gap-2 flex-wrap mt-1.5">
                                    <span class="text-xs w-12" style="color:var(--muted2)">{{ $word }}</span>
                                    @if($detail['googleActions'])
                                        <select name="{{ $field }}" class="text-sm min-w-0 flex-1">
                                            <option value="">—</option>
                                            @foreach($detail['googleActions'] as $a)
                                                <option value="{{ $a['resource'] }}" @selected($t->{$field} === $a['resource'])>{{ $a['name'] }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="hidden" name="{{ $field }}" value="{{ $t->{$field} }}">
                                        <span class="text-xs flex-1" style="color:var(--muted)">{{ $t->{$field} ? 'configurada' : 'nenhuma conversão importada nesta conta' }}</span>
                                    @endif
                                    @if(!$t->{$field} && $t->google_ads_account_id)
                                        <button type="submit" form="google-{{ $kind }}" class="btn btn-xs">Criar "{{ $word }} — Nonna App"</button>
                                    @endif
                                </div>
                            @endforeach
                            <p class="text-xs mt-1.5" style="color:var(--muted)">Só lead que veio de clique no Google (com gclid) é enviado. Escolha a conta e salve antes de criar a conversão.</p>
                        @endif
                    </div>

                    {{-- GA4 --}}
                    <div>
                        <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">Google Analytics 4</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">ID de medição
                                <input type="text" name="ga4_measurement_id" value="{{ old('ga4_measurement_id', $t->ga4_measurement_id) }}" placeholder="G-AB12CD34EF" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                            </label>
                            <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Chave secreta da API {{ $t->ga4_api_secret ? '(já salva)' : '' }}
                                <input type="password" name="ga4_api_secret" value="" placeholder="{{ $t->ga4_api_secret ? '••••••••  (deixe vazio para manter)' : 'Administrador → Fluxos de dados → Chaves secretas da API' }}" autocomplete="off" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                            </label>
                        </div>
                    </div>

                    {{-- Clarity --}}
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Microsoft Clarity — ID do projeto <span style="color:var(--muted)">(usado pela Nonna Tag no site)</span>
                        <input type="text" name="clarity_id" value="{{ old('clarity_id', $t->clarity_id) }}" class="field text-sm px-2.5 py-1.5 w-full min-w-0" style="max-width:220px">
                    </label>

                    <label class="flex items-start gap-2 text-sm rounded px-3 py-2.5" style="background:var(--s2)">
                        <input type="checkbox" name="send_enabled" value="1" @checked($t->send_enabled) class="mt-1" style="accent-color:var(--purple)">
                        <span><b>Enviar as conversões de verdade</b>
                            <span class="block text-xs" style="color:var(--muted2)">Cada lead novo deste cliente vai para as plataformas acima e passa a contar para os anúncios. Desligue o envio de Lead do Tag Manager antes, para não contar duas vezes.</span></span>
                    </label>

                    <div class="flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                        @if($t->meta_pixel_id && $t->isTestMode())
                            <button type="submit" form="tracking-test" class="btn btn-sm"><x-icon name="send" size="13" />Enviar teste para a Meta</button>
                        @endif
                    </div>
                </form>

                <form id="tracking-test" method="POST" action="{{ route('tracking.test', $client->id) }}" class="hidden">@csrf</form>
                <form id="google-lead" method="POST" action="{{ route('tracking.google-action', $client->id) }}" class="hidden">@csrf<input type="hidden" name="kind" value="lead"></form>
                <form id="google-venda" method="POST" action="{{ route('tracking.google-action', $client->id) }}" class="hidden">@csrf<input type="hidden" name="kind" value="venda"></form>

                <div class="flex flex-col gap-5 min-w-0">
                <section class="card p-5 min-w-0" x-data="{ copied: false }">
                    <h2 class="card-title">Nonna Tag no site</h2>
                    <p class="text-xs mt-1 mb-2" style="color:var(--muted2)">Substitui o Tag Manager: carrega o Pixel, o GA4, a tag do Google Ads e o Clarity configurados aqui e capta os formulários. No WordPress, o plugin Nonna Leads cola sozinho (campo "Chave da Nonna Tag"). Em outro site, cole no &lt;head&gt; de todas as páginas:</p>
                    @php $snippet = '<script async src="' . url("/api/t/{$t->site_key}.js") . '"></script>'; @endphp
                    <code class="block text-[11px] px-2 py-1.5 rounded break-all" style="background:var(--s2); color:var(--purple)">{{ $snippet }}</code>
                    <div class="flex items-center gap-2 mt-2 flex-wrap">
                        <button type="button" class="btn btn-xs" @click="navigator.clipboard.writeText(@js($snippet)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copiado!' : 'Copiar código'"></button>
                        <span class="text-xs" style="color:var(--muted)">Chave do site: <b class="font-mono">{{ $t->site_key }}</b></span>
                    </div>
                    @php $seen = \App\Http\Controllers\Api\NonnaTagController::lastSeen($t); @endphp
                    <p class="text-xs mt-2 flex items-center gap-1.5" style="color:{{ $seen ? 'var(--green)' : 'var(--muted)' }}">
                        <span class="h-2 w-2 rounded-full inline-block" style="background:{{ $seen ? 'var(--green)' : 'var(--border)' }}"></span>
                        {{ $seen ? 'Tag vista no site' . ($seen['host'] ? ' ' . $seen['host'] : '') . ' ' . \Illuminate\Support\Carbon::parse($seen['at'])->diffForHumans() : 'A tag ainda não foi vista em nenhum site.' }}
                    </p>
                    <p class="text-xs mt-2" style="color:var(--orange)">Tire o Tag Manager do site no mesmo dia: os dois juntos disparam o Pixel e o GA4 em dobro.</p>
                </section>

                <section class="card p-5 min-w-0">
                    <h2 class="card-title">Últimos envios <small class="font-normal text-xs" style="color:var(--muted)">{{ $detail['leads7d'] }} lead(s) nos últimos 7 dias</small></h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($detail['events'] as $e)
                            <div class="rounded px-3 py-2 text-xs" style="background:var(--s2)">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span><b style="color:var(--text)">{{ ['meta' => 'Meta', 'google' => 'Google Ads', 'ga4' => 'GA4'][$e->platform] ?? $e->platform }}</b> · {{ $e->event_name }}{{ $e->opportunity?->lead?->name ? ' · ' . $e->opportunity->lead->name : '' }}</span>
                                    <span class="badge badge-{{ \App\Models\ConversionEvent::$statuses[$e->status]['color'] ?? 'muted' }}">{{ \App\Models\ConversionEvent::$statuses[$e->status]['label'] ?? $e->status }}</span>
                                </div>
                                <p class="mt-1" style="color:var(--muted2)">{{ $e->created_at->format('d/m H:i') }} · {{ \Illuminate\Support\Str::limit($e->detail, 160) }}</p>
                            </div>
                        @empty
                            <p class="text-sm" style="color:var(--muted)">Nenhum envio ainda.</p>
                        @endforelse
                    </div>
                </section>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
