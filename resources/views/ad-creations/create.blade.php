<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('ad-creations.index') }}" class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">← Criativos</a>
            <h1 class="text-2xl font-black text-[var(--text)]">Criar anúncio · {{ $task->title }}</h1>
        </div>
    </x-slot>

    @php
        $usable = fn ($a) => $connections->get($a->id)?->page_id && $connections->get($a->id)?->can_edit !== false;
        $firstUsable = $accounts->first($usable);
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
             x-data="adWizard({
                 accountId: @js(old('client_ad_account_id', $firstUsable?->id)),
                 imageId: @js(old('task_attachment_id', $images->first()?->id)),
                 message: @js(old('message', $caption)),
                 caption: @js($caption),
                 headline: @js(old('headline', \Illuminate\Support\Str::limit($task->title, 40, ''))),
                 adsetsUrl: @js(url('/central-campanhas/contas')),
                 previewUrl: @js(route('ad-creations.preview', $task)),
             })" x-init="loadAdsets(); $watch('adsetId', () => { previewHtml = ''; if (adset && adset.needs_link && adset.link && !link) link = adset.link })">

            {{-- Formulário --}}
            <form method="POST" action="{{ route('ad-creations.store', $task) }}" class="card p-5 flex flex-col gap-4 min-w-0"
                  @submit.prevent="if (await $store.confirmDialog.ask('Criar o anúncio PAUSADO na Meta? Ele só começa a gastar quando alguém clicar em Ativar.')) { saving = true; $el.submit() }">
                @csrf
                <input type="hidden" name="client_ad_account_id" :value="accountId">
                <input type="hidden" name="adset_id" :value="adsetId">
                <input type="hidden" name="task_attachment_id" :value="imageId">

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">1. Conta de anúncio</p>
                    <div class="flex flex-col gap-1.5">
                        @forelse($accounts as $a)
                            @php $conn = $connections->get($a->id); $ok = $usable($a); @endphp
                            <label class="flex items-start gap-2 text-sm rounded px-3 py-2 {{ $ok ? 'cursor-pointer' : 'opacity-60' }}" style="background:var(--s2)">
                                <input type="radio" value="{{ $a->id }}" x-model="accountId" @change="loadAdsets()" @disabled(!$ok) class="mt-1" style="accent-color:var(--purple)">
                                <span>
                                    <b>Meta · {{ $a->account_id }}</b>
                                    @if($conn?->can_edit === false)
                                        <span class="block text-xs" style="color:var(--orange)">O App só tem permissão de leitura nesta conta. <a href="{{ route('ad-connections.index', ['client_id' => $task->client_id]) }}" class="underline">Ver em Contas e conexões</a></span>
                                    @elseif($conn?->page_id)
                                        <span class="block text-xs" style="color:var(--muted2)">Página {{ $conn->page_name }}{{ $conn->instagram_username ? ' · @' . $conn->instagram_username : ' · sem Instagram (sairá só no Facebook)' }}</span>
                                    @else
                                        <span class="block text-xs" style="color:var(--orange)">Sem Página liberada para o App. <a href="{{ route('ad-connections.index', ['client_id' => $task->client_id]) }}" class="underline">Ver em Contas e conexões</a></span>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="text-sm" style="color:var(--red)">Este cliente não tem conta Meta cadastrada.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold mb-1.5 flex items-center justify-between gap-2" style="color:var(--muted2)">2. Conjunto onde o anúncio vai entrar
                        <button type="button" class="font-normal underline" style="color:var(--purple)" x-show="accountId && !loadingAdsets" @click="loadAdsets(true)">atualizar lista</button></p>
                    <p class="text-sm" style="color:var(--muted)" x-show="loadingAdsets">Buscando conjuntos na Meta… (conta grande pode levar até 20 s)</p>
                    <select class="text-sm w-full" x-model="adsetId" x-show="!loadingAdsets && adsets.length" aria-label="Conjunto">
                        <option value="">Escolha o conjunto</option>
                        <template x-for="s in adsets" :key="s.id">
                            <option :value="s.id" :disabled="!s.supported" x-text="(s.supported ? '' : '⛔ ') + s.campaign_name + ' › ' + s.name + (s.status === 'PAUSED' ? ' (pausado)' : '')"></option>
                        </template>
                    </select>
                    <p class="text-sm" style="color:var(--muted)" x-show="!loadingAdsets && !adsets.length && accountId">Nenhum conjunto ativo ou pausado nesta conta.</p>
                    <template x-if="adset">
                        <div class="text-xs mt-1.5 rounded px-3 py-2" style="background:var(--s2); color:var(--muted2)">
                            Destino: <b style="color:var(--text)" x-text="adset.destination"></b>
                            <span x-show="adset.template_ad"> · copiado de um anúncio que já está no conjunto</span>
                            <p x-show="!adset.supported" style="color:var(--orange)" x-text="adset.why_not"></p>
                        </div>
                    </template>
                </div>

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">3. Arte aprovada <span class="font-normal" style="color:var(--muted)">(imagem ou vídeo; vídeo leva alguns minutos para a Meta processar)</span></p>
                    <div class="flex flex-wrap gap-2">
                        @forelse($images as $img)
                            <label class="cursor-pointer rounded overflow-hidden border-2 w-24 h-24" :style="imageId === @js($img->id) ? 'border-color:var(--purple)' : 'border-color:transparent'">
                                <input type="radio" value="{{ $img->id }}" x-model="imageId" class="sr-only">
                                @if(\App\Services\AdSync\MetaAdCreator::isVideo($img))
                                    <video src="{{ $img->url() }}#t=0.5" preload="metadata" muted playsinline class="w-full h-full object-cover" title="{{ $img->filename }}"></video>
                                @else
                                    <img src="{{ $img->url() }}" alt="{{ $img->filename }}" class="w-full h-full object-cover">
                                @endif
                            </label>
                        @empty
                            <p class="text-sm" style="color:var(--orange)">Nenhuma imagem ou vídeo entregue nesta tarefa.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold flex flex-wrap items-center gap-2 mb-1.5" style="color:var(--muted2)" for="ad-message">4. Texto principal
                        <span class="badge badge-green" x-show="caption && message.trim() === caption.trim()">Legenda aprovada pelo cliente</span>
                        <span class="badge badge-orange" x-show="caption && message.trim() !== caption.trim()" x-cloak>Diferente da legenda aprovada</span>
                    </label>
                    <textarea id="ad-message" name="message" x-model="message" rows="5" class="w-full text-sm" required></textarea>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-semibold flex flex-col gap-1" style="color:var(--muted2)">Título
                        <input type="text" name="headline" x-model="headline" maxlength="255" class="text-sm w-full">
                    </label>
                    <template x-if="adset && adset.needs_link">
                        <label class="text-xs font-semibold flex flex-col gap-1" style="color:var(--muted2)">Link de destino
                            <input type="url" name="link" x-model="link" required placeholder="https://" class="text-sm w-full">
                        </label>
                    </template>
                    <template x-if="adset && !adset.template_ad">
                        <label class="text-xs font-semibold flex flex-col gap-1" style="color:var(--muted2)">Botão
                            <select name="cta" x-model="cta" class="text-sm w-full">
                                @foreach($ctaOptions as $key => $label)
                                    @continue(in_array($key, ['WHATSAPP_MESSAGE', 'MESSAGE_PAGE']))
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </template>
                </div>

                <p class="text-xs rounded px-3 py-2" style="background:var(--s2); color:var(--muted2)">A UTM padrão da Nonna entra sozinha. O anúncio nasce <b>pausado</b> e fica registrado no diário da campanha.</p>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" @click="preview()" :disabled="!ready() || previewing"><x-icon name="eye" size="14" /><span x-text="previewing ? 'Gerando prévia…' : 'Ver prévia'"></span></button>
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="!ready() || saving"><x-icon name="upload" size="14" /><span x-text="saving ? 'Criando…' : 'Criar anúncio pausado'"></span></button>
                </div>
            </form>

            {{-- Prévia + anúncios já criados --}}
            <div class="flex flex-col gap-5 min-w-0">
                <section class="card p-5">
                    <h2 class="card-title flex items-center justify-between gap-2">Prévia
                        <select x-model="format" @change="previewHtml && preview()" class="text-xs" aria-label="Formato da prévia">
                            <option value="MOBILE_FEED_STANDARD">Feed do Facebook</option>
                            <option value="INSTAGRAM_STANDARD">Feed do Instagram</option>
                            <option value="INSTAGRAM_STORY">Stories do Instagram</option>
                        </select>
                    </h2>
                    <p class="text-xs mt-1" style="color:var(--red)" x-show="previewError" x-text="previewError"></p>
                    <div class="mt-3 flex justify-center" x-show="previewHtml" x-html="previewHtml"></div>
                    <p class="text-sm mt-3" style="color:var(--muted)" x-show="!previewHtml && !previewError">Escolha o conjunto e clique em "Ver prévia": é a prévia da própria Meta, sem criar nada.</p>
                </section>

                <section class="card p-5">
                    <h2 class="card-title">Anúncios criados desta arte</h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($creations as $c)
                            <div class="rounded px-3 py-2 text-sm" style="background:var(--s2)">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="badge badge-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span>
                                    <span class="text-xs" style="color:var(--muted)">{{ $c->created_at->format('d/m H:i') }}{{ $c->createdBy ? ' · ' . \Illuminate\Support\Str::before($c->createdBy->name, ' ') : '' }}</span>
                                </div>
                                <p class="text-xs mt-1" style="color:var(--muted2)">{{ $c->campaign_name }} › {{ $c->adset_name }}</p>
                                @if($c->caption_changed)<p class="text-xs" style="color:var(--orange)">Texto diferente da legenda aprovada.</p>@endif
                                @if($c->error)<p class="text-xs" style="color:var(--red)">{{ $c->error }}</p>@endif
                                <div class="flex gap-2 mt-2">
                                    @if($c->managerUrl())<a href="{{ $c->managerUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-xs">Abrir no Gerenciador ↗</a>@endif
                                    @if($c->status === 'pausado')
                                        <form method="POST" action="{{ route('ad-creations.activate', $c) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Ativar o anúncio? Ele passa a gastar verba (se o conjunto e a campanha estiverem ativos).')) $el.submit()">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-xs"><x-icon name="play" size="12" />Ativar</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm" style="color:var(--muted)">Nenhum ainda.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function adWizard(cfg) {
        return {
            ...cfg, adsets: [], adsetId: @js(old('adset_id', '')), link: @js(old('link', '')), cta: 'LEARN_MORE', format: 'MOBILE_FEED_STANDARD',
            loadingAdsets: false, previewing: false, saving: false, previewHtml: '', previewError: '',
            get adset() { return this.adsets.find(s => s.id === this.adsetId) || null; },
            ready() { return this.accountId && this.adset && this.adset.supported && this.imageId && this.message.trim() && (!this.adset.needs_link || this.link); },
            async loadAdsets(fresh = false) {
                if (!this.accountId) return;
                this.loadingAdsets = true; this.adsets = []; this.previewHtml = '';
                const res = await fetch(this.adsetsUrl + '/' + this.accountId + '/conjuntos' + (fresh ? '?atualizar=1' : ''), { headers: { Accept: 'application/json' } }).catch(() => null);
                this.adsets = res && res.ok ? (await res.json()).adsets : [];
                if (!this.adsets.find(s => s.id === this.adsetId)) this.adsetId = '';
                this.loadingAdsets = false;
            },
            async preview() {
                if (!this.ready()) return;
                this.previewing = true; this.previewError = '';
                if (this.adset.link && !this.link) this.link = this.adset.link;
                const res = await fetch(this.previewUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ client_ad_account_id: this.accountId, adset_id: this.adsetId, task_attachment_id: this.imageId, message: this.message, headline: this.headline, link: this.link || null, cta: this.cta, format: this.format }),
                }).catch(() => null);
                const body = res ? await res.json().catch(() => ({})) : {};
                if (res && res.ok && body.ok) { this.previewHtml = body.html; }
                else { this.previewHtml = ''; this.previewError = body.error || (body.errors ? Object.values(body.errors).flat()[0] : 'Não foi possível gerar a prévia.'); }
                this.previewing = false;
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
