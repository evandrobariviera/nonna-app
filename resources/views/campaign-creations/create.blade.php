<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Central de Campanhas</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Nova campanha na Meta</h1>
        </div>
    </x-slot>

    @php
        $usable = fn ($a) => $connections->get($a->id)?->page_id && $connections->get($a->id)?->can_edit !== false;
        $firstUsable = $accounts->first($usable);
        $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $artsJs = $arts->map(fn ($a) => [
            'id' => $a['task']->id, 'title' => $a['task']->title, 'caption' => $a['task']->latestApprovalRound?->caption ?? '',
            'media' => $a['media']->map(fn ($m) => ['id' => $m->id, 'url' => $m->url(), 'video' => \App\Services\AdSync\MetaAdCreator::isVideo($m), 'name' => $m->filename])->values(),
        ])->values();
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <form method="GET" action="{{ route('campaign-creations.create') }}" class="mb-4 flex flex-wrap items-center gap-3">
            <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                <option value="">Escolha o cliente…</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
            <span class="text-sm" style="color:var(--muted2)">Campanha, conjunto e anúncio nascem <b>pausados</b>. A verba passa pelo teto da verba do mês do cliente.</span>
        </form>

        @if($clientId)
        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
             x-data="campaignWizard({
                 accountId: @js(old('client_ad_account_id', $firstUsable?->id)),
                 kind: @js(old('kind', 'mensagens')),
                 kinds: @js(collect($kinds)->map(fn ($k) => ['tag' => $k['tag'], 'result' => $k['result'], 'label' => $k['label']])),
                 cities: @js(old('cities', [])),
                 radius: @js((int) old('radius', 20)),
                 ageMin: @js((int) old('age_min', 20)), ageMax: @js((int) old('age_max', 55)), gender: @js(old('gender', 'todos')),
                 housing: @js((bool) old('housing')),
                 budget: @js(old('daily_budget', '')),
                 room: @js($room),
                 description: @js(old('description', '')),
                 campaignName: @js(old('campaign_name', '')), adsetName: @js(old('adset_name', '')),
                 arts: @js($artsJs), taskId: @js(old('task_id', '')), attachmentId: @js(old('attachment_id', '')),
                 message: @js(old('message', '')), headline: @js(old('headline', '')), link: @js(old('link', $client?->website ?? '')),
                 citiesUrl: @js(route('campaign-creations.cities')),
             })">

            <form method="POST" action="{{ route('campaign-creations.store') }}" class="card p-5 flex flex-col gap-5 min-w-0"
                  @submit.prevent="if (problems().length) return; if (await $store.confirmDialog.ask('Criar a campanha PAUSADA na Meta? Nada é gasto até alguém clicar em Ativar.')) { saving = true; $el.submit() }">
                @csrf
                <div class="hidden">
                    <input type="hidden" name="client_ad_account_id" :value="accountId">
                    <input type="hidden" name="housing" :value="housing ? 1 : 0">
                    <template x-for="(c, i) in cities" :key="c.key">
                        <span><input type="hidden" :name="'cities[' + i + '][key]'" :value="c.key"><input type="hidden" :name="'cities[' + i + '][name]'" :value="c.name"></span>
                    </template>
                </div>

                {{-- 1. Conta --}}
                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">1. Conta de anúncio</p>
                    <div class="flex flex-col gap-1.5">
                        @forelse($accounts as $a)
                            @php $conn = $connections->get($a->id); $ok = $usable($a); @endphp
                            <label class="flex items-start gap-2 text-sm rounded px-3 py-2 {{ $ok ? 'cursor-pointer' : 'opacity-60' }}" style="background:var(--s2)">
                                <input type="radio" value="{{ $a->id }}" x-model="accountId" @disabled(!$ok) class="mt-1" style="accent-color:var(--purple)">
                                <span><b>Meta · {{ $a->account_id }}</b>
                                    @if($conn?->can_edit === false)<span class="block text-xs" style="color:var(--orange)">O App só tem permissão de leitura nesta conta.</span>
                                    @elseif(!$conn?->page_id)<span class="block text-xs" style="color:var(--orange)">Sem Página liberada para o App.</span>
                                    @else<span class="block text-xs" style="color:var(--muted2)">Página {{ $conn->page_name }}{{ $conn->instagram_username ? ' · @' . $conn->instagram_username : '' }}</span>@endif
                                </span>
                            </label>
                        @empty
                            <p class="text-sm" style="color:var(--red)">Este cliente não tem conta Meta cadastrada.</p>
                        @endforelse
                    </div>
                </div>

                {{-- 2. Tipo --}}
                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">2. O que a campanha quer</p>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach(['mensagens' => 'Conversas no WhatsApp do cliente.', 'trafego' => 'Visitas a uma página do site.', 'reconhecimento' => 'Ser visto pelo maior número de pessoas da região.'] as $key => $hint)
                            <label class="rounded px-3 py-2.5 cursor-pointer text-sm" :style="kind === '{{ $key }}' ? 'background:color-mix(in srgb, var(--purple) 8%, transparent); border:1px solid var(--purple)' : 'background:var(--s2); border:1px solid transparent'">
                                <input type="radio" name="kind" value="{{ $key }}" x-model="kind" class="sr-only">
                                <b style="color:var(--text)">{{ $kinds[$key]['label'] }}</b>
                                <span class="block text-xs mt-0.5" style="color:var(--muted2)">{{ $hint }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- 3. Público --}}
                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">3. Público</p>
                    <div class="relative">
                        <input type="text" x-model="cityQuery" @input.debounce.400ms="searchCities()" placeholder="Digite a cidade (ex.: Videira)" class="field text-sm px-2.5 py-1.5 w-full min-w-0" aria-label="Buscar cidade">
                        <div x-show="cityResults.length" x-cloak @click.outside="cityResults = []" class="absolute z-10 left-0 right-0 mt-1 rounded shadow-lg overflow-hidden" style="background:var(--s1); border:1px solid var(--border)">
                            <template x-for="c in cityResults" :key="c.key">
                                <button type="button" class="block w-full text-left px-3 py-2 text-sm hover:opacity-80" style="color:var(--text)" @click="addCity(c)" x-text="c.name + ' — ' + c.region"></button>
                            </template>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <template x-for="c in cities" :key="c.key">
                            <span class="badge badge-purple flex items-center gap-1"><span x-text="c.name"></span><button type="button" @click="cities = cities.filter(x => x.key !== c.key)" aria-label="Remover">×</button></span>
                        </template>
                    </div>
                    <div class="grid gap-2 mt-3 grid-cols-2 sm:grid-cols-4">
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Raio (km)
                            <input type="number" name="radius" x-model.number="radius" min="0" max="80" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                        </label>
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)" x-show="!housing">Idade de
                            <input type="number" name="age_min" x-model.number="ageMin" min="18" max="65" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                        </label>
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)" x-show="!housing">até
                            <input type="number" name="age_max" x-model.number="ageMax" min="18" max="65" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                        </label>
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)" x-show="!housing">Gênero
                            <select name="gender" x-model="gender" class="text-sm w-full min-w-0">
                                <option value="todos">Homens e mulheres</option>
                                <option value="homens">Só homens</option>
                                <option value="mulheres">Só mulheres</option>
                            </select>
                        </label>
                    </div>
                    <p class="text-xs mt-1" style="color:var(--muted)">Raio 0 = só a cidade. Com raio: de 17 a 80 km.</p>
                    <label class="flex items-start gap-2 text-sm mt-2">
                        <input type="checkbox" x-model="housing" class="mt-1" style="accent-color:var(--purple)">
                        <span>Anúncio de <b>imóveis</b> (venda ou aluguel)
                            <span class="block text-xs" style="color:var(--muted2)">Regra da Meta: sem filtro de idade e gênero, e raio de pelo menos 25 km.</span></span>
                    </label>
                </div>

                {{-- 4. Verba --}}
                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">4. Verba e início</p>
                    <div class="grid gap-2 grid-cols-2">
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Verba diária (R$)
                            <input type="number" name="daily_budget" x-model="budget" step="0.01" min="6" required class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                        </label>
                        <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Começa em
                            <input type="date" name="start" value="{{ old('start', now()->toDateString()) }}" min="{{ now()->toDateString() }}" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                        </label>
                    </div>
                    @if($room)
                        <p class="text-xs mt-1.5" :style="'color:' + (budget && Number(budget) > room.room ? 'var(--red)' : 'var(--muted2)')">
                            Verba do mês do cliente: {{ $money($room['cap']) }} · gasto até agora {{ $money($room['spent']) }} · cabe mais <b>{{ $money($room['room']) }} por dia</b> no ritmo atual.
                        </p>
                    @else
                        <p class="text-xs mt-1.5" style="color:var(--orange)">Este cliente não tem verba do mês cadastrada (ficha do cliente → Orçamento): o App não tem teto para conferir.</p>
                    @endif
                </div>

                {{-- 5. Nomes --}}
                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">5. Nomes <span class="font-normal" style="color:var(--muted)">(no padrão do time; dá para ajustar)</span></p>
                    <label class="text-xs flex flex-col gap-1 mb-2" style="color:var(--muted2)">Do que é a campanha
                        <input type="text" name="description" x-model="description" placeholder="Ex.: Black November" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                    </label>
                    <label class="text-xs flex flex-col gap-1 mb-2" style="color:var(--muted2)">Campanha
                        <input type="text" name="campaign_name" :value="campaignName || autoCampaign()" @input="campaignName = $event.target.value" required class="field text-sm px-2.5 py-1.5 w-full min-w-0 font-mono">
                    </label>
                    <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Conjunto
                        <input type="text" name="adset_name" :value="adsetName || autoAdset()" @input="adsetName = $event.target.value" required class="field text-sm px-2.5 py-1.5 w-full min-w-0 font-mono">
                    </label>
                </div>

                {{-- 6. Anúncio --}}
                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">6. Anúncio <span class="font-normal" style="color:var(--muted)">(opcional — dá para criar depois em Criativos)</span></p>
                    <select name="task_id" x-model="taskId" @change="pickArt()" class="text-sm w-full min-w-0" aria-label="Arte aprovada">
                        <option value="">Sem anúncio agora</option>
                        <template x-for="a in arts" :key="a.id"><option :value="a.id" x-text="a.title"></option></template>
                    </select>
                    <p class="text-xs mt-1" x-show="!arts.length" style="color:var(--muted)">Nenhuma arte aprovada deste cliente nos últimos 60 dias.</p>
                    <template x-if="art">
                        <div class="mt-2 flex flex-col gap-2">
                            <input type="hidden" name="attachment_id" :value="attachmentId">
                            <div class="flex flex-wrap gap-2">
                                <template x-for="m in art.media" :key="m.id">
                                    <button type="button" class="rounded overflow-hidden border-2 w-20 h-20" :style="attachmentId === m.id ? 'border-color:var(--purple)' : 'border-color:transparent'" @click="attachmentId = m.id">
                                        <template x-if="m.video"><video :src="m.url + '#t=0.5'" preload="metadata" muted class="w-full h-full object-cover"></video></template>
                                        <template x-if="!m.video"><img :src="m.url" alt="" class="w-full h-full object-cover"></template>
                                    </button>
                                </template>
                            </div>
                            <textarea name="message" x-model="message" rows="4" class="field w-full px-3 py-2 text-sm" placeholder="Texto do anúncio (vem a legenda aprovada)"></textarea>
                            <input type="text" name="headline" x-model="headline" maxlength="255" placeholder="Título (opcional)" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                        </div>
                    </template>
                    <label class="text-xs flex flex-col gap-1 mt-2" style="color:var(--muted2)" x-show="kind !== 'mensagens'">Link do site <span x-show="kind === 'trafego'">(obrigatório)</span>
                        <input type="url" name="link" x-model="link" placeholder="https://" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                    </label>
                </div>

                <ul x-show="problems().length" x-cloak class="text-xs flex flex-col gap-0.5" style="color:var(--orange)">
                    <template x-for="p in problems()" :key="p"><li x-text="'• ' + p"></li></template>
                </ul>

                <button type="submit" class="btn btn-primary self-start" :disabled="saving || problems().length > 0">
                    <x-icon name="upload" size="14" /><span x-text="saving ? 'Criando… (pode levar até 1 minuto)' : 'Criar campanha pausada'"></span>
                </button>
            </form>

            <section class="card p-5 min-w-0">
                <h2 class="card-title">Campanhas criadas pelo App</h2>
                <div class="flex flex-col gap-2 mt-3">
                    @forelse($creations as $c)
                        <div class="rounded px-3 py-2 text-sm" style="background:var(--s2)">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="badge badge-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span>
                                <span class="text-xs" style="color:var(--muted)">{{ $c->created_at->format('d/m H:i') }}{{ $c->createdBy ? ' · ' . \Illuminate\Support\Str::before($c->createdBy->name, ' ') : '' }}</span>
                            </div>
                            <p class="text-xs mt-1 font-mono break-all" style="color:var(--text)">{{ $c->campaign_name }}</p>
                            <p class="text-xs" style="color:var(--muted2)">{{ $c->kindLabel() }} · {{ $money($c->daily_budget) }}/dia · {{ app(\App\Services\AdSync\MetaCampaignCreator::class)->audienceText($c->audience, $c->housing) }}</p>
                            <p class="text-xs" style="color:var(--muted2)">{{ $c->adCreation ? 'Anúncio: ' . $c->adCreation->statusLabel() : 'Sem anúncio (crie em Criativos)' }}</p>
                            @if($c->error)<p class="text-xs" style="color:var(--red)">{{ $c->error }}</p>@endif
                            @if($c->ad_error)<p class="text-xs" style="color:var(--orange)">{{ $c->ad_error }}</p>@endif
                            <div class="flex gap-2 mt-2 flex-wrap">
                                @if($c->managerUrl())<a href="{{ $c->managerUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-xs">Abrir no Gerenciador ↗</a>@endif
                                @if($c->status === 'pausado')
                                    <form method="POST" action="{{ route('campaign-creations.activate', $c) }}"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Ativar a campanha? Ela começa a gastar {{ $money($c->daily_budget) }} por dia depois da revisão da Meta.')) $el.submit()">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-xs"><x-icon name="play" size="12" />Ativar</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm" style="color:var(--muted)">Nenhuma ainda.</p>
                    @endforelse
                </div>
            </section>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function campaignWizard(cfg) {
            return {
                ...cfg, cityQuery: '', cityResults: [], saving: false,
                get art() { return this.arts.find(a => a.id === this.taskId) || null; },
                pickArt() {
                    const a = this.art;
                    this.attachmentId = a && a.media.length ? a.media[0].id : '';
                    if (a && !this.message) this.message = a.caption || '';
                    if (a && !this.headline) this.headline = a.title.slice(0, 40);
                },
                async searchCities() {
                    const q = this.cityQuery.trim(); if (q.length < 3) { this.cityResults = []; return; }
                    const res = await fetch(this.citiesUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).catch(() => null);
                    this.cityResults = res && res.ok ? (await res.json()).cities : [];
                },
                addCity(c) { if (!this.cities.find(x => x.key === c.key)) this.cities.push({ key: c.key, name: c.name }); this.cityQuery = ''; this.cityResults = []; },
                autoCampaign() {
                    const k = this.kinds[this.kind]; const d = (this.description || 'CAMPANHA').trim().replace(/\s+/g, ' ').toUpperCase();
                    return `[00][CBO][INT+EXT][${k.tag}][${d}][${k.result}]`;
                },
                autoAdset() {
                    const who = this.housing ? 'HO + MU 18 A 65+' : (this.gender === 'homens' ? 'HO' : this.gender === 'mulheres' ? 'MU' : 'HO + MU') + ` ${this.ageMin} A ${this.ageMax}`;
                    const where = this.cities.map(c => c.name.toUpperCase()).join(' + ') + (this.radius ? ` +${this.radius}KM` : '');
                    return `[00][FB+IG][AUTO][ABERTO][${who}][${where}]`;
                },
                problems() {
                    const p = [];
                    if (!this.accountId) p.push('Escolha a conta de anúncio.');
                    if (!this.cities.length) p.push('Escolha pelo menos uma cidade.');
                    const min = this.housing ? 25 : 17;
                    if (this.radius && (this.radius < min || this.radius > 80)) p.push(`Raio entre ${min} e 80 km (ou 0 para só a cidade).`);
                    if (this.housing && !this.radius) p.push('Imóveis: raio de pelo menos 25 km.');
                    if (!this.housing && (this.ageMin < 18 || this.ageMax > 65 || this.ageMin > this.ageMax)) p.push('Idade entre 18 e 65 anos.');
                    if (!this.budget || Number(this.budget) < 6) p.push('Verba diária mínima: R$ 6,00.');
                    else if (this.room && Number(this.budget) > this.room.room) p.push('A verba passa do que cabe na verba do mês do cliente.');
                    if (this.kind === 'trafego' && !this.link) p.push('Tráfego precisa do link do site.');
                    return p;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
