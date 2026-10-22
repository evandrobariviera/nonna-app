{{-- Pauta citada (MeetingAgendaService): planejamentos, campanhas/projetos e tarefas que a
     reunião discute. A reunião aparece sozinha na página de cada item citado (e, citando
     uma tarefa, também no projeto e no planejamento dela). Busca filtra pelo cliente
     escolhido no form, se houver. --}}
<div x-data="{
        items: @js($items ?? []),
        q: '',
        results: [],
        open: false,
        loading: false,
        timer: null,
        search() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                this.loading = true;
                const client = document.querySelector('[name=client_id]')?.value || '';
                const params = new URLSearchParams({ q: this.q, client_id: client });
                try {
                    const res = await fetch(@js(route('meetings.agenda-search')) + '?' + params, { headers: { 'Accept': 'application/json' } });
                    this.results = res.ok ? (await res.json()).items : [];
                } catch (e) { this.results = []; }
                this.loading = false;
                this.open = true;
            }, 250);
        },
        has(r) { return this.items.some(i => i.type === r.type && i.id === r.id) },
        add(r) { if (!this.has(r)) this.items.push(r); this.q = ''; this.open = false },
     }"
     @click.outside="open = false">
    <input type="hidden" name="agenda_items_sent" value="1">

    <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Itens da pauta</label>
    <p class="text-xs mb-2" style="color:var(--muted2)">Cite planejamentos, campanhas/projetos ou tarefas. A reunião aparece sozinha na página de cada um — citando uma tarefa, também no projeto e no planejamento dela.</p>

    <div class="flex flex-col gap-1.5 mb-2" x-show="items.length">
        <template x-for="(item, idx) in items" :key="item.type + item.id">
            <div class="flex items-center gap-2 px-3 py-2" style="background:var(--s3); border:1px solid var(--border2); border-radius:8px">
                <span class="badge flex-shrink-0" x-text="item.kind"></span>
                <span class="text-sm font-semibold truncate" style="color:var(--text)" x-text="item.label"></span>
                <span class="text-xs truncate" style="color:var(--muted)" x-text="item.sub"></span>
                <button type="button" @click="items.splice(idx, 1)" class="ml-auto flex-shrink-0" style="background:none; border:none; cursor:pointer; color:var(--muted)" title="Tirar da pauta">✕</button>
                <input type="hidden" :name="`agenda_items[${idx}][type]`" :value="item.type">
                <input type="hidden" :name="`agenda_items[${idx}][id]`" :value="item.id">
            </div>
        </template>
    </div>

    <div class="relative">
        <input type="text" x-model="q" @input="search()" @focus="search()" @keydown.escape="open = false"
               placeholder="Buscar planejamento, campanha, projeto ou tarefa..."
               class="field w-full px-4 py-2.5 text-sm focus:outline-none">
        <div x-show="open" x-cloak class="absolute left-0 right-0 mt-1 z-20 overflow-y-auto"
             style="max-height:280px; background:var(--s1); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12)">
            <p x-show="loading" class="px-3 py-2 text-xs" style="color:var(--muted)">Buscando…</p>
            <p x-show="!loading && !results.length" class="px-3 py-2 text-xs" style="color:var(--muted)">Nada encontrado.</p>
            <template x-for="r in results" :key="r.type + r.id">
                <button type="button" @click="add(r)" :disabled="has(r)"
                        class="w-full flex items-center gap-2 px-3 py-2 text-left"
                        style="background:none; border:none; border-bottom:1px solid var(--border2); cursor:pointer"
                        :style="has(r) ? 'opacity:.45; cursor:default' : ''"
                        onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background='none'">
                    <span class="badge flex-shrink-0" x-text="r.kind"></span>
                    <span class="text-sm truncate" style="color:var(--text)" x-text="r.label"></span>
                    <span class="text-xs truncate ml-auto" style="color:var(--muted)" x-text="r.sub"></span>
                </button>
            </template>
        </div>
    </div>
</div>
