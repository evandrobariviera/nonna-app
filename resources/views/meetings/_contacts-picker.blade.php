{{--
    Contatos da reunião (create/edit). Precisa de `clientId` no x-data do form pai.
    - Cliente escolhido: os contatos dele aparecem marcáveis, como antes.
    - Busca: acha QUALQUER contato da lista (nome, empresa, e-mail ou telefone) —
      pra chamar gente de fora do cliente (parceiro, fornecedor, indicação...).
    Contatos de fora do cliente ficam como etiquetas, com X pra tirar.
    $contacts: Contact com clients:id; $selected: ids já marcados.
--}}
@php
    $contactsData = $contacts->map(fn ($c) => [
        'id'      => $c->id,
        'name'    => $c->name,
        'company' => $c->company_name,
        'email'   => $c->email,
        'phone'   => $c->whatsapp ?: $c->phone,
        'clients' => $c->clients->pluck('id')->values(),
    ])->values();
@endphp
<div x-data="{
        all: @js($contactsData),
        selected: @js(array_values($selected ?? [])),
        q: '',
        norm(s) { return (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
        ofClient(c) { return !!clientId && c.clients.includes(clientId); },
        get clientContacts() { return this.all.filter(c => this.ofClient(c)); },
        get extras() { return this.all.filter(c => this.selected.includes(c.id) && !this.ofClient(c)); },
        get results() {
            const q = this.norm(this.q).trim();
            if (q.length < 2) return [];
            const digits = q.replace(/\D/g, '');
            return this.all.filter(c => !this.selected.includes(c.id) && (
                this.norm(c.name).includes(q) || this.norm(c.company).includes(q) || this.norm(c.email).includes(q)
                || (digits.length >= 3 && (c.phone || '').replace(/\D/g, '').includes(digits))
            )).slice(0, 8);
        },
        detail(c) { return [c.company, c.email].filter(Boolean).join(' · '); },
        toggle(id) { this.selected.includes(id) ? this.remove(id) : this.selected.push(id); },
        add(c) { if (!this.selected.includes(c.id)) this.selected.push(c.id); this.q = ''; this.$refs.search.focus(); },
        remove(id) { this.selected = this.selected.filter(x => x !== id); },
     }">
    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
        Contatos (para notificações)
    </label>

    <template x-for="id in selected" :key="id">
        <input type="hidden" name="contacts[]" :value="id">
    </template>

    {{-- Contatos do cliente escolhido --}}
    <div x-show="clientContacts.length" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
        <template x-for="c in clientContacts" :key="c.id">
            <label class="flex items-center gap-2 text-sm cursor-pointer py-1" style="color:var(--text)">
                <input type="checkbox" :checked="selected.includes(c.id)" @change="toggle(c.id)"
                    style="accent-color:var(--purple)">
                <span x-text="c.name"></span>
            </label>
        </template>
    </div>
    <p x-show="clientId && !clientContacts.length" x-cloak class="text-xs mb-3" style="color:var(--muted)">
        Esse cliente ainda não tem contatos vinculados — busque abaixo.
    </p>

    {{-- Contatos de fora do cliente já escolhidos --}}
    <div x-show="extras.length" x-cloak class="flex flex-wrap gap-2 mb-3">
        <template x-for="c in extras" :key="c.id">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold pl-2.5 pr-1.5 py-1"
                  style="background:rgba(100,59,142,.08); border:1px solid rgba(100,59,142,.25); color:var(--purple); border-radius:999px">
                <span x-text="c.name"></span>
                <button type="button" @click="remove(c.id)" title="Tirar da reunião"
                        class="inline-flex items-center justify-center hover:opacity-70">
                    <x-icon name="x" size="12" />
                </button>
            </span>
        </template>
    </div>

    {{-- Busca na lista inteira de contatos --}}
    <div class="relative" @click.outside="q = ''">
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" style="color:var(--muted)">
                <x-icon name="search" size="14" />
            </span>
            <input type="text" x-ref="search" x-model="q" autocomplete="off"
                placeholder="Buscar outro contato (nome, empresa, e-mail ou telefone)..."
                @keydown.enter.prevent="results.length && add(results[0])"
                @keydown.escape="q = ''"
                class="field w-full pl-9 pr-4 py-2.5 text-sm focus:outline-none">
        </div>

        <div x-show="norm(q).trim().length >= 2" x-cloak
             class="absolute z-20 left-0 right-0 mt-1 overflow-hidden"
             style="background:var(--s1); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12)">
            <template x-for="c in results" :key="c.id">
                <button type="button" @click="add(c)"
                        class="w-full text-left px-4 py-2 block hover:bg-black/5">
                    <span class="block text-sm font-semibold" style="color:var(--text)" x-text="c.name"></span>
                    <span x-show="detail(c)" class="block text-xs truncate" style="color:var(--muted)" x-text="detail(c)"></span>
                </button>
            </template>
            <div x-show="!results.length" class="px-4 py-3 text-xs" style="color:var(--muted)">
                Ninguém com esse nome na lista.
                <a href="{{ route('contacts.create') }}" target="_blank" class="font-semibold underline" style="color:var(--purple)">Cadastrar contato</a>
                (abre em outra aba) e incluir depois, editando a reunião.
            </div>
        </div>
    </div>
</div>
