<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('ad-creations.index') }}" class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">← Criativos</a>
            <h1 class="text-2xl font-black text-[var(--text)]">Levar para o Google · {{ $task->title }}</h1>
        </div>
    </x-slot>

    @php
        $usable = fn ($a) => !in_array($connections->get($a->id)?->status, ['sem_acesso'], true) && $connections->get($a->id)?->can_edit !== false;
        $firstUsable = $accounts->first($usable);
        $okImages = $images->where('info.ok', true);
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm mb-4" style="color:var(--muted2)">A arte aprovada entra como imagem num <b>grupo de recursos</b> de uma campanha Performance Max que já existe. O Google combina as imagens com os textos do grupo. Ela nasce <b>pausada</b> e só entra na combinação depois do <b>Ativar</b>.
            <a href="{{ route('ad-creations.create', $task) }}" class="underline" style="color:var(--purple)">Prefere a Meta?</a></p>

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
             x-data="pmaxForm({ accountId: @js(old('client_ad_account_id', $firstUsable?->id)), groupId: @js(old('asset_group_id')), youtube: @js(old('youtube_url', '')), groupsUrl: @js(url('/central-campanhas/contas')) })"
             x-init="loadGroups()">

            <form method="POST" action="{{ route('google-pmax.store', $task) }}" class="card p-5 flex flex-col gap-4 min-w-0"
                  @submit.prevent="if (await $store.confirmDialog.ask('Enviar PAUSADO ao grupo de recursos? Só entra no ar quando alguém clicar em Ativar.')) { saving = true; $el.submit() }">
                @csrf
                <input type="hidden" name="client_ad_account_id" :value="accountId">
                <input type="hidden" name="asset_group_id" :value="groupId">

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">1. Conta do Google</p>
                    <div class="flex flex-col gap-1.5">
                        @forelse($accounts as $a)
                            @php $conn = $connections->get($a->id); $ok = $usable($a); @endphp
                            <label class="flex items-start gap-2 text-sm rounded px-3 py-2 {{ $ok ? 'cursor-pointer' : 'opacity-60' }}" style="background:var(--s2)">
                                <input type="radio" value="{{ $a->id }}" x-model="accountId" @change="groupId = ''; loadGroups()" @disabled(!$ok) class="mt-1" style="accent-color:var(--purple)">
                                <span><b>Google · {{ $a->account_id }}</b>
                                    @unless($ok)<span class="block text-xs" style="color:var(--orange)">Sem acesso de edição. <a href="{{ route('ad-connections.index', ['client_id' => $task->client_id]) }}" class="underline">Ver em Contas e conexões</a></span>@endunless
                                </span>
                            </label>
                        @empty
                            <p class="text-sm" style="color:var(--muted)">Este cliente não tem conta do Google cadastrada.</p>
                        @endforelse
                    </div>
                </div>

                <div x-show="accountId" x-cloak>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">2. Grupo de recursos (Performance Max ativa)</p>
                    <p class="text-sm" x-show="loading" style="color:var(--muted)">Buscando no Google…</p>
                    <p class="text-sm" x-show="error" style="color:var(--red)" x-text="error"></p>
                    <div class="flex flex-col gap-1.5" x-show="!loading && groups.length">
                        <template x-for="g in groups" :key="g.id">
                            <label class="flex items-start gap-2 text-sm rounded px-3 py-2 cursor-pointer min-w-0" style="background:var(--s2)">
                                <input type="radio" :value="g.id" x-model="groupId" class="mt-1" style="accent-color:var(--purple)">
                                <span class="min-w-0">
                                    <b x-text="g.name"></b> <span class="text-xs" x-show="g.status !== 'ENABLED'" style="color:var(--orange)">(grupo pausado)</span>
                                    <span class="block text-xs break-all" style="color:var(--muted2)" x-text="g.campaign_name"></span>
                                    <span class="block text-xs" :style="'color:' + (g.total >= 20 ? 'var(--red)' : 'var(--muted)')"
                                          x-text="g.total + '/20 imagens · ' + g.images.MARKETING_IMAGE + ' paisagem, ' + g.images.SQUARE_MARKETING_IMAGE + ' quadradas, ' + g.images.PORTRAIT_MARKETING_IMAGE + ' verticais · ' + g.videos + '/15 vídeos'"></span>
                                </span>
                            </label>
                        </template>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">3. Imagens da arte aprovada</p>
                    <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill, minmax(140px, 1fr))">
                        @foreach($images as ['image' => $img, 'info' => $info])
                            <label class="rounded overflow-hidden flex flex-col {{ $info['ok'] ? 'cursor-pointer' : 'opacity-60' }}" style="background:var(--s2); border:1px solid var(--border)">
                                <img src="{{ $img->url() }}" alt="" loading="lazy" class="w-full object-contain" style="aspect-ratio:1; background:var(--s1)">
                                <span class="p-2 text-xs flex items-start gap-1.5">
                                    <input type="checkbox" name="images[]" value="{{ $img->id }}" @checked($info['ok'] && in_array($img->id, old('images', $okImages->pluck('image.id')->all()), true)) @disabled(!$info['ok']) style="accent-color:var(--purple)" class="mt-0.5">
                                    <span>
                                        @if($info['ok'])
                                            <b style="color:var(--text)">{{ \App\Models\GoogleAssetCreation::$fieldLabels[$info['field_type']] }}</b>
                                            <span class="block" style="color:var(--muted)">{{ $info['width'] }}×{{ $info['height'] }}</span>
                                        @else
                                            <span style="color:var(--orange)">{{ $info['why_not'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if($okImages->isEmpty())
                        <p class="text-xs mt-2" style="color:var(--orange)">Nenhuma imagem desta arte tem o formato da Performance Max (1:1, 4:5 ou 1,91:1). Se for vídeo, use o campo do YouTube abaixo.</p>
                    @endif
                </div>

                <div>
                    <p class="text-xs font-semibold mb-1.5" style="color:var(--muted2)">4. Vídeo <span class="font-normal" style="color:var(--muted)">(opcional — o Google só aceita vídeo que está no YouTube; suba como "não listado" e cole o link)</span></p>
                    <input type="url" name="youtube_url" x-model="youtube" value="{{ old('youtube_url') }}" placeholder="https://youtu.be/…" class="field text-sm px-2.5 py-1.5 w-full min-w-0">
                </div>

                <button type="submit" class="btn btn-primary self-start" :disabled="saving || !groupId">
                    <x-icon name="upload" size="14" /><span x-text="saving ? 'Enviando… (cerca de 20 s por imagem)' : 'Enviar pausadas'"></span>
                </button>
            </form>

            <section class="card p-5 min-w-0">
                <h2 class="card-title">Já enviadas desta arte</h2>
                <div class="flex flex-col gap-2 mt-3">
                    @forelse($creations as $c)
                        <div class="rounded px-3 py-2 text-sm flex gap-3" style="background:var(--s2)">
                            @if($c->youtube_id)<img src="https://i.ytimg.com/vi/{{ $c->youtube_id }}/mqdefault.jpg" alt="" class="w-12 h-12 object-cover rounded flex-shrink-0">
                            @elseif($c->attachment)<img src="{{ $c->attachment->url() }}" alt="" class="w-12 h-12 object-cover rounded flex-shrink-0">@endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="badge badge-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span>
                                    <span class="text-xs" style="color:var(--muted)">{{ $c->created_at->format('d/m H:i') }}{{ $c->createdBy ? ' · ' . \Illuminate\Support\Str::before($c->createdBy->name, ' ') : '' }}</span>
                                </div>
                                <p class="text-xs mt-1 break-all" style="color:var(--muted2)">{{ $c->fieldLabel() }} · {{ $c->asset_group_name }} · {{ $c->campaign_name }}</p>
                                @if($c->error)<p class="text-xs" style="color:var(--red)">{{ $c->error }}</p>@endif
                                @if($c->status === 'pausado')
                                    <form method="POST" action="{{ route('google-pmax.activate', $c) }}" class="mt-2"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Ativar a imagem? Ela entra nas combinações da Performance Max depois da revisão do Google.')) $el.submit()">
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
    </div>

    @push('scripts')
    <script>
        function pmaxForm(cfg) {
            return {
                ...cfg, groups: [], loading: false, error: '', saving: false,
                async loadGroups() {
                    if (!this.accountId) return;
                    this.loading = true; this.error = ''; this.groups = [];
                    const res = await fetch(this.groupsUrl + '/' + this.accountId + '/grupos-de-recursos', { headers: { Accept: 'application/json' } }).catch(() => null);
                    const data = res ? await res.json().catch(() => ({})) : {};
                    this.loading = false;
                    if (!res || !res.ok) { this.error = data.message || 'Não deu para buscar os grupos no Google.'; return; }
                    this.groups = data.groups || [];
                    if (!this.groups.length) this.error = 'Nenhuma Performance Max ativa nesta conta.';
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
