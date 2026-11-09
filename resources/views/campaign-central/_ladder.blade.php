{{-- Modal "Otimizar campanha" — a escada das 5 perguntas. Incluir uma vez por
     página; qualquer botão abre com:
       @click="$dispatch('open-ladder', { id: '<uuid da campanha>' })"
     Os números de cada degrau vêm de GET /campanhas/{id}/escada. O gestor
     responde Sim/Não na ordem, a escada para no primeiro "não", ele escreve o
     que fez e isso vira uma "Otimização Realizada" no diário (zera o relógio). --}}
<div x-data="ladderModal()" x-on:open-ladder.window="open($event.detail.id)" x-cloak>
    <div x-show="visible" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(22,22,22,.45)" @keydown.escape.window="close()" @click.self="close()">
        <div class="card w-full max-w-2xl max-h-full overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="ladder-title">
            <div class="flex items-start justify-between gap-3 px-5 py-4" style="border-bottom:1px solid var(--border)">
                <div class="min-w-0">
                    <h3 id="ladder-title" class="text-base font-bold" style="color:var(--text)">Otimizar campanha</h3>
                    <p class="text-sm mt-0.5 truncate" style="color:var(--muted2)" x-text="data ? (data.campaign.client + ' · ' + data.campaign.name) : 'Carregando…'"></p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" @click="close()" aria-label="Fechar"><x-icon name="x" size="16" /></button>
            </div>

            <div class="px-5 py-4 flex flex-col gap-3">
                <template x-if="loading">
                    <p class="text-sm" style="color:var(--muted)">Buscando os números da campanha…</p>
                </template>

                <template x-if="data && data.campaign.locked">
                    <div class="text-sm px-3 py-2 rounded" style="background:color-mix(in srgb, var(--orange) 8%, var(--s1)); border:1px solid color-mix(in srgb, var(--orange) 30%, transparent)">
                        <b>Campanha com trava:</b> <span x-text="data.campaign.locked"></span>. Responder a escada pode, mas mexer na campanha agora pode atrapalhar.
                    </div>
                </template>

                <p x-show="data" class="text-sm" style="color:var(--muted2)">
                    Responda na ordem. A escada para no primeiro <b>"não"</b>: é ali que está o problema.
                </p>

                <template x-for="(step, i) in (data ? data.steps : [])" :key="step.key">
                    <div class="rounded px-3 py-3" :style="stepStyle(i)">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-xs font-bold flex-shrink-0"
                                  :style="badgeStyle(i)" x-text="i + 1"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm" style="color:var(--text)">
                                    <span x-text="step.question"></span>
                                    <span class="ml-1 text-[11px] font-semibold uppercase tracking-wide" style="color:var(--muted)"
                                          x-text="step.owner === 'gestor' ? 'gestor decide' : 'estratégia decide'"></span>
                                </p>
                                <p class="text-xs mt-0.5" style="color:var(--muted2)" x-text="step.hint"></p>

                                <template x-if="i <= current">
                                    <div class="mt-2">
                                        <dl class="grid gap-x-4 gap-y-1 text-sm" style="grid-template-columns:minmax(0,auto) minmax(0,1fr)">
                                            <template x-for="(value, label) in data.facts[step.key]" :key="label">
                                                <div class="contents">
                                                    <dt style="color:var(--muted2)" x-text="label"></dt>
                                                    <dd class="font-semibold" style="color:var(--text)" x-text="value"></dd>
                                                </div>
                                            </template>
                                        </dl>
                                        <div class="flex gap-2 mt-3" x-show="i === current && !finished">
                                            <button type="button" class="btn btn-sm btn-success" @click="answer(step.key, true)"><x-icon name="check" size="14" />Sim</button>
                                            <button type="button" class="btn btn-sm btn-danger" @click="answer(step.key, false)"><x-icon name="x" size="14" />Não</button>
                                        </div>
                                        <p class="text-xs mt-2 font-semibold" x-show="answers[step.key] !== undefined"
                                           :style="answers[step.key] ? 'color:var(--green)' : 'color:var(--red)'"
                                           x-text="answers[step.key] ? 'Sim' : 'Não'"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="finished">
                    <div class="flex flex-col gap-2">
                        <div class="text-sm px-3 py-2 rounded" :style="stoppedAt === null
                                ? 'background:color-mix(in srgb, var(--green) 8%, var(--s1)); border:1px solid color-mix(in srgb, var(--green) 30%, transparent)'
                                : 'background:color-mix(in srgb, var(--orange) 8%, var(--s1)); border:1px solid color-mix(in srgb, var(--orange) 30%, transparent)'">
                            <template x-if="stoppedAt === null"><span><b>Passou pelos 5 degraus.</b> Campanha boa precisa de tempo: registre que conferiu e não mexa.</span></template>
                            <template x-if="stoppedAt !== null && stoppedAt < 2"><span><b x-text="'Parou no degrau ' + (stoppedAt + 1) + '.'"></b> Isso é com o gestor: resolva e registre o que fez.</span></template>
                            <template x-if="stoppedAt !== null && stoppedAt >= 2"><span><b x-text="'Parou no degrau ' + (stoppedAt + 1) + '.'"></b> Daqui pra frente a decisão é da estratégia: registre e avise quem cuida da estratégia do cliente.</span></template>
                        </div>
                        <label class="text-xs font-semibold" style="color:var(--muted2)" for="ladder-comment">O que foi feito ou decidido</label>
                        <textarea id="ladder-comment" x-model="comment" rows="4" class="w-full text-sm"
                                  placeholder="Ex.: troquei o criativo cansado pela arte nova aprovada; custo por clique tinha subido 40%."></textarea>
                        <p class="text-xs" style="color:var(--red)" x-show="error" x-text="error"></p>
                    </div>
                </template>
            </div>

            <div class="flex justify-between gap-2 px-5 py-3" style="border-top:1px solid var(--border)">
                <button type="button" class="btn btn-ghost btn-sm" @click="restart()" x-show="current > 0 || finished">Recomeçar</button>
                <span x-show="!(current > 0 || finished)"></span>
                <button type="button" class="btn btn-primary btn-sm" x-show="finished" :disabled="saving || !comment.trim()" @click="save()">
                    <span x-text="saving ? 'Registrando…' : 'Registrar no diário'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function ladderModal() {
    return {
        visible: false, loading: false, data: null, campaignId: null,
        current: 0, answers: {}, finished: false, stoppedAt: null,
        comment: '', saving: false, error: '',
        async open(id) {
            Object.assign(this, { visible: true, loading: true, data: null, campaignId: id, error: '' });
            this.restart();
            try {
                const res = await fetch('{{ url('/campanhas') }}/' + id + '/escada', { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error();
                this.data = await res.json();
            } catch (e) {
                this.error = 'Não foi possível carregar os números da campanha.';
                this.finished = false;
            } finally {
                this.loading = false;
            }
        },
        close() { this.visible = false; },
        restart() { Object.assign(this, { current: 0, answers: {}, finished: false, stoppedAt: null, comment: '' }); },
        answer(key, yes) {
            this.answers[key] = yes;
            const total = this.data.steps.length;
            if (!yes) { this.stoppedAt = this.current; this.finished = true; return; }
            if (this.current + 1 >= total) { this.finished = true; return; }
            this.current++;
        },
        stepStyle(i) {
            const key = this.data.steps[i].key;
            if (this.answers[key] === false) return 'border:1px solid color-mix(in srgb, var(--red) 35%, transparent); background:color-mix(in srgb, var(--red) 4%, var(--s1))';
            if (i > this.current) return 'border:1px solid var(--border); opacity:.45';
            return 'border:1px solid var(--border)';
        },
        badgeStyle(i) {
            const a = this.answers[this.data.steps[i].key];
            if (a === true) return 'background:color-mix(in srgb, var(--green) 15%, transparent); color:var(--green)';
            if (a === false) return 'background:var(--red); color:#fff';
            return 'background:var(--s3); color:var(--muted2)';
        },
        async save() {
            this.saving = true; this.error = '';
            try {
                const res = await fetch('{{ url('/campanhas') }}/' + this.campaignId + '/escada', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ answers: this.answers, comment: this.comment }),
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = body.errors ? Object.values(body.errors).flat()[0] : 'Não foi possível registrar.';
                    return;
                }
                window.location.reload();
            } catch (e) {
                this.error = 'Não foi possível registrar.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endonce
