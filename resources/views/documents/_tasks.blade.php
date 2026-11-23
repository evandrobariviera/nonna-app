{{-- Peças da campanha / entregas do projeto viram tarefas (DocumentTasks, etapa D, 23/11).
     Recebe: $project, $document. Sem IA: a lista sai do documento; a pessoa confere e confirma. --}}
@php
    $docTaskRows = app(\App\Services\Documents\DocumentTasks::class)->rows($document, $project);
    $docTaskTeam = \App\Models\User::whereNull('client_id')->orderBy('name')->get(['id', 'name'])
        ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
    $isCampaign  = $document->kind === 'campanha';
    $pending     = collect($docTaskRows)->whereNull('task')->count();
@endphp

<div class="card mt-3 overflow-hidden" x-data="docTasks(@js([
        'rows'  => $docTaskRows,
        'url'   => route('projects.field-document.tasks', $project),
        'types' => \App\Models\Task::$types,
        'team'  => $docTaskTeam,
    ]))" style="border:1px solid rgba(100,59,142,.35)">
    <button type="button" class="w-full px-5 py-3 flex items-center justify-between gap-3 text-left" @click="open = !open"
            style="background:rgba(100,59,142,.05)">
        <span class="flex items-center gap-2 flex-wrap">
            <x-icon name="list-checks" size="14" style="color:var(--purple)" />
            <span class="text-sm font-semibold" style="color:var(--text)">{{ $isCampaign ? 'Peças viram tarefas' : 'Entregas viram tarefas' }}</span>
            <span class="text-xs" style="color:var(--muted)">
                @if(!$docTaskRows)
                    · nenhuma {{ $isCampaign ? 'peça' : 'entrega' }} preenchida ainda
                @else
                    · {{ count($docTaskRows) - $pending }} de {{ count($docTaskRows) }} já são tarefa
                @endif
            </span>
        </span>
        <span class="text-xs" style="color:var(--muted)" x-text="open ? 'Fechar' : 'Abrir'"></span>
    </button>

    <div x-show="open" x-cloak class="px-5 py-4" style="border-top:1px solid var(--border2)">
        <p class="text-xs mb-3" style="color:var(--muted); max-width:90ch">
            {{ $isCampaign ? 'Cada peça da seção 07' : 'Cada entrega da seção 03 (e, se quiser, cada pedido da seção 04)' }} vira uma tarefa
            {{ $isCampaign ? 'desta campanha' : 'deste projeto' }}, com o contexto do documento na descrição. Confira o tipo, a data e quem faz, e confirme.
            Não gasta crédito. Mudou o documento? <button type="button" class="underline" @click="location.reload()">Atualize a lista</button>.
        </p>

        <template x-if="!rows.length">
            <p class="text-sm" style="color:var(--muted)">Preencha {{ $isCampaign ? 'as peças (seção 07)' : 'as entregas (seção 03)' }} no documento acima e atualize esta lista.</p>
        </template>

        <template x-if="rows.length">
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-3 text-xs" x-show="pendingRows().length" style="color:var(--muted)">
                    <span>Para todas as marcadas:</span>
                    <select class="field text-xs px-2 py-1" @change="fillAll('responsavel_user_id', $event.target.value); $event.target.value = ''">
                        <option value="">Responsável…</option>
                        <template x-for="u in team" :key="u.id"><option :value="u.id" x-text="u.name"></option></template>
                    </select>
                    <select class="field text-xs px-2 py-1" @change="fillAll('executor_user_id', $event.target.value); $event.target.value = ''">
                        <option value="">Quem executa…</option>
                        <template x-for="u in team" :key="u.id"><option :value="u.id" x-text="u.name"></option></template>
                    </select>
                </div>

                <div class="space-y-2">
                    <template x-for="(r, i) in rows" :key="r.item_id">
                        <div>
                            <p x-show="i === 0 || rows[i - 1].grupo !== r.grupo" class="text-[11px] font-semibold uppercase mt-3 mb-1.5"
                               style="color:var(--muted); letter-spacing:.06em" x-text="r.grupo"></p>
                            {{-- Já virou tarefa --}}
                            <div x-show="r.task" class="flex items-center gap-2 px-3 py-2 text-sm" style="background:rgba(34,197,94,.06); border:1px solid rgba(34,197,94,.25)">
                                <x-icon name="check" size="14" style="color:var(--green)" />
                                <a :href="r.task?.url" class="font-medium hover:underline" style="color:var(--text)" x-text="r.task?.title"></a>
                                <span class="text-xs" style="color:var(--muted)" x-text="'· ' + (r.task?.status || '')"></span>
                            </div>
                            {{-- Ainda não --}}
                            <div x-show="!r.task" class="px-3 py-2 grid gap-2 items-center" :style="{ opacity: r.checked ? 1 : .55 }"
                                 style="border:1px solid var(--border2); grid-template-columns: auto minmax(0, 1fr)">
                                <input type="checkbox" x-model="r.checked" class="mt-0.5" title="Criar esta tarefa">
                                <input type="text" x-model="r.title" maxlength="300" class="field w-full px-2 py-1 text-sm">
                                <span></span>
                                <div class="flex flex-wrap gap-2">
                                    <select x-model="r.task_type" class="field text-xs px-2 py-1" title="Tipo da tarefa">
                                        <option value="">Tipo…</option>
                                        <template x-for="(label, key) in types" :key="key"><option :value="key" x-text="label" :selected="key === r.task_type"></option></template>
                                    </select>
                                    <input type="date" x-model="r.due_date" class="field text-xs px-2 py-1" title="Prazo">
                                    <select x-model="r.responsavel_user_id" class="field text-xs px-2 py-1" title="Responsável">
                                        <option value="">Responsável…</option>
                                        <template x-for="u in team" :key="u.id"><option :value="String(u.id)" x-text="u.name" :selected="String(u.id) === r.responsavel_user_id"></option></template>
                                    </select>
                                    <select x-model="r.executor_user_id" class="field text-xs px-2 py-1" title="Quem executa">
                                        <option value="">Quem executa…</option>
                                        <template x-for="u in team" :key="u.id"><option :value="String(u.id)" x-text="u.name" :selected="String(u.id) === r.executor_user_id"></option></template>
                                    </select>
                                    <button type="button" class="text-xs underline" style="color:var(--muted)" @click="r.showDesc = !r.showDesc"
                                            x-text="r.showDesc ? 'Esconder descrição' : 'Ver descrição'"></button>
                                </div>
                                <span x-show="r.showDesc"></span>
                                <pre x-show="r.showDesc" class="text-xs whitespace-pre-wrap px-2 py-1.5" style="background:var(--s2); color:var(--muted); font-family:inherit" x-text="r.description"></pre>
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="error" x-cloak class="text-xs mt-3 px-3 py-2" style="background:rgba(239,68,68,.06); border:1px solid rgba(239,68,68,.25); color:var(--red)" x-text="error"></p>
                <div x-show="notes.length" x-cloak class="text-xs mt-3 px-3 py-2" style="background:rgba(238,121,25,.06); border:1px solid rgba(238,121,25,.3); color:var(--text)">
                    <template x-for="n in notes"><p x-text="n"></p></template>
                    <button type="button" class="underline mt-1" @click="location.reload()">Atualizar a lista</button>
                </div>
                <div x-show="pendingRows().length && !notes.length" class="mt-4 flex items-center gap-3">
                    <button type="button" class="btn btn-primary btn-xs flex items-center gap-1" :disabled="busy || !picked().length" @click="submit()">
                        <x-icon name="list-checks" size="12" />
                        <span x-text="busy ? 'Criando…' : (picked().length === 1 ? 'Criar 1 tarefa' : 'Criar ' + picked().length + ' tarefas')"></span>
                    </button>
                    <span class="text-xs" style="color:var(--muted)">Entram no Backlog {{ $isCampaign ? 'desta campanha' : 'deste projeto' }}.</span>
                </div>
            </div>
        </template>
    </div>
</div>

@once
<script>
function docTasks(cfg) {
    return {
        open: false, busy: false, error: '',
        rows: cfg.rows.map(r => ({ ...r, task_type: r.task_type || '', due_date: r.due_date || '', responsavel_user_id: '', executor_user_id: '', showDesc: false })),
        types: cfg.types,
        team: cfg.team, notes: [],
        pendingRows() { return this.rows.filter(r => !r.task); },
        picked() { return this.rows.filter(r => !r.task && r.checked); },
        fillAll(key, value) { if (value) this.picked().forEach(r => r[key] = String(value)); },
        async submit() {
            this.error = '';
            const rows = this.picked();
            if (rows.some(r => !r.task_type)) { this.error = 'Escolha o tipo de cada tarefa marcada.'; return; }
            if (!await this.$store.confirmDialog.ask(rows.length === 1 ? 'Criar 1 tarefa no Backlog?' : 'Criar ' + rows.length + ' tarefas no Backlog?')) return;
            this.busy = true;
            try {
                const res = await fetch(cfg.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ rows: rows.map(r => ({ item_id: r.item_id, title: r.title, task_type: r.task_type, due_date: r.due_date || null,
                        responsavel_user_id: r.responsavel_user_id || null, executor_user_id: r.executor_user_id || null })) }),
                });
                const d = await res.json();
                if (!res.ok) { this.error = d.message || 'Não deu certo. Tente de novo.'; this.busy = false; return; }
                const notes = [...(d.skipped || []), ...(d.warnings || [])];
                if (!notes.length) { location.reload(); return; }
                this.notes = notes; this.busy = false;
            } catch (e) { this.error = 'Não deu certo. Tente de novo.'; this.busy = false; }
        },
    };
}
</script>
@endonce
