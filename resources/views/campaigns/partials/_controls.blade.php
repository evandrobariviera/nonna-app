{{-- Central de Campanhas, etapa 4: pausar / reativar / verba diária (CampaignControlController). --}}
<div class="card card-body-lg" id="controle" x-data="campaignControls()" x-init="load()">
    <p class="card-title mb-1">Controle no ar</p>
    <p class="text-xs mb-3" style="color:var(--muted)">Pausar, reativar e mudar a verba diária direto na plataforma. Tudo pede o porquê e vai para o histórico.</p>

    <p x-show="loading" class="text-sm" style="color:var(--muted)">Consultando a plataforma…</p>
    <p x-show="!loading && state && state.error" x-cloak class="text-sm" style="color:var(--red)" x-text="'Não deu para consultar agora: ' + (state && state.error)"></p>

    <template x-if="!loading && state && !state.error">
        <div>
            <div x-show="!state.can_edit" class="text-sm mb-3 px-3 py-2 rounded" style="background:rgba(220,38,38,.06); color:var(--red)">
                O App só tem permissão de leitura nesta conta. Dê ao usuário "Nonna - n8n" a função de Anunciante para poder pausar e mudar verba por aqui.
            </div>

            <div class="flex items-center gap-2 flex-wrap mb-3">
                <span class="text-sm" style="color:var(--muted2)">Na plataforma agora:</span>
                <span class="badge" :class="isActive() ? 'badge-green' : 'badge-red'" x-text="isActive() ? 'No ar' : 'Pausada'"></span>
                <button type="button" class="btn btn-sm" x-show="state.can_edit" @click="ask('status')"
                    x-text="isActive() ? 'Pausar campanha' : 'Reativar campanha'"></button>
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="b in state.budgets" :key="b.id">
                    <div class="rounded px-3 py-2.5" style="background:var(--s2)">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate" style="color:var(--text)" x-text="b.name"></p>
                                <p class="text-xs" style="color:var(--muted2)">
                                    <span x-show="b.daily" x-text="'Verba diária: ' + money(b.daily)"></span>
                                    <span x-show="!b.daily && b.lifetime" x-text="'Verba total: ' + money(b.lifetime)"></span>
                                    <span x-show="b.level === 'adset' && b.status && b.status !== 'ACTIVE'"> · pausado</span>
                                </p>
                            </div>
                            <button type="button" class="btn btn-xs" x-show="state.can_edit && b.editable" @click="ask('budget', b)">Mudar verba</button>
                            <span class="text-xs" x-show="!b.editable" style="color:var(--muted)" x-text="b.why_not"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- Formulário da ação --}}
    <div x-show="action" x-cloak class="mt-3 rounded px-3 py-3" style="border:1px solid var(--border)">
        <p class="text-sm font-semibold mb-2" style="color:var(--text)" x-text="title()"></p>
        <label x-show="action === 'budget'" class="text-xs flex flex-col gap-1 mb-2" style="color:var(--muted2)">Nova verba diária (R$)
            <input type="number" step="0.01" min="1" x-model="daily" class="field text-sm px-3 py-2" style="max-width:180px">
        </label>
        <textarea x-model="reason" rows="3" placeholder="Por quê? (obrigatório — vai para o histórico)"
            class="field w-full px-3 py-2 text-sm mb-2 focus:outline-none" style="resize:vertical"></textarea>
        <p x-show="message" class="text-xs mb-2" :style="'color:' + (ok ? 'var(--green)' : 'var(--red)')" x-text="message"></p>
        <div class="flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" :disabled="busy || reason.trim().length < 5" @click="submit()" x-text="busy ? 'Enviando…' : 'Confirmar'"></button>
            <button type="button" class="btn btn-sm" @click="action = null">Cancelar</button>
        </div>
        <p x-show="action === 'budget'" class="text-xs mt-2" style="color:var(--muted)">Aumento só passa se couber na verba do mês do cliente. Diminuir é sempre liberado.</p>
    </div>
    <p x-show="!action && message" x-cloak class="text-xs mt-2" :style="'color:' + (ok ? 'var(--green)' : 'var(--red)')" x-text="message"></p>

    @if(\App\Services\AdSync\GoogleSearchAdCreator::isSearch($campaign))
        <div class="mt-4 pt-3 flex items-center justify-between gap-2 flex-wrap" style="border-top:1px solid var(--border)">
            <span class="text-xs" style="color:var(--muted2)">Novo anúncio nesta campanha, com textos sugeridos pela IA.</span>
            <a href="{{ route('google-search-ads.create', $campaign) }}" class="btn btn-sm"><x-icon name="search" size="13" />Novo anúncio de Pesquisa</a>
        </div>
    @endif
</div>

@once
<script>
    function campaignControls() {
        return {
            loading: true, state: null, action: null, target: null, daily: '', reason: '', busy: false, message: '', ok: true,
            urls: { state: @js(route('campaign-controls.state', $campaign)), status: @js(route('campaign-controls.status', $campaign)), budget: @js(route('campaign-controls.budget', $campaign)) },
            async load() {
                this.loading = true;
                const res = await fetch(this.urls.state, { headers: { Accept: 'application/json' } }).catch(() => null);
                this.state = res && res.ok ? await res.json() : { error: 'sem resposta', budgets: [] };
                this.loading = false;
            },
            isActive() { return ['ACTIVE', 'ENABLED'].includes(this.state?.status); },
            money(v) { return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); },
            ask(kind, b = null) { this.action = kind; this.target = b; this.daily = b?.daily ?? ''; this.reason = ''; this.message = ''; },
            title() {
                if (this.action === 'status') return this.isActive() ? 'Pausar a campanha' : 'Reativar a campanha';
                return 'Mudar verba diária — ' + (this.target?.name ?? '');
            },
            async submit() {
                this.busy = true; this.message = '';
                const body = this.action === 'status'
                    ? { active: !this.isActive(), reason: this.reason }
                    : { level: this.target.level, target: this.target.id, daily: this.daily, reason: this.reason };
                const res = await fetch(this.action === 'status' ? this.urls.status : this.urls.budget, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(body),
                }).catch(() => null);
                const data = res ? await res.json().catch(() => ({})) : {};
                this.busy = false;
                this.ok = !!(res && res.ok);
                this.message = data.message || (this.ok ? 'Feito.' : 'Não foi possível concluir.');
                if (this.ok) { this.action = null; if (data.state) this.state = data.state; }
            },
        };
    }
</script>
@endonce
