{{-- Central de Comércio → Produtos e serviços (CatalogItemController).
     Catálogo de preços usado pelas Propostas. Três tipos: serviço mensal, produto/projeto
     único e pacote (fechado = PlaNo C com regras de nível; aberto = conjunto de itens com
     preço próprio). Todo mundo vê; só admin/dono edita ($isOrgAdmin). --}}
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $canEdit = $isOrgAdmin ?? false;
    $editorData = $items->merge($closedPackages)->merge($componentOptions)->unique('id')->mapWithKeys(fn ($i) => [$i->id => [
        'id' => $i->id, 'name' => $i->name, 'line' => $i->line, 'kind' => $i->kind, 'package_type' => $i->package_type,
        'description' => $i->description, 'monthly_price' => $i->monthly_price, 'one_time_price' => $i->one_time_price,
        'price_is_starting' => $i->price_is_starting, 'value_status' => $i->value_status, 'is_active' => $i->is_active,
        'rules' => $i->rules ?? (object) [],
        'components' => $i->components->map(fn ($c) => ['item_id' => $c->id, 'quantity' => $c->pivot->quantity])->values(),
    ]]);
    $filterUrl = fn ($params) => route('catalog.index', array_filter(array_merge(request()->only('tipo', 'linha', 'inativos'), $params), fn ($v) => $v !== null));
@endphp
<x-app-layout>
    <x-slot name="header">Produtos e serviços</x-slot>

    <div x-data="catalogEditor(@js($editorData), @js(route('catalog.store')), @js(url('/produtos')))">

        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <p class="text-sm max-w-2xl" style="color:var(--muted2); line-height:1.6">
                A tabela de preços que as propostas usam. Mudar um preço aqui vale para as propostas novas;
                as que já foram enviadas guardam o preço da época.
            </p>
            @if($canEdit)
                <div class="flex gap-2">
                    <button type="button" class="btn btn-sm" @click="openNew('monthly')"><x-icon name="plus" size="14" /> Novo item</button>
                    <button type="button" class="btn btn-primary btn-sm" @click="openNew('package')"><x-icon name="package" size="14" /> Montar pacote</button>
                </div>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">{{ session('error') }}</div>
        @endif

        {{-- Filtros: tipo + linha --}}
        <div class="flex items-center gap-2 flex-wrap mb-4">
            @foreach(['' => 'Todos'] + collect(\App\Models\CatalogItem::$kinds)->map(fn ($k, $key) => ['package' => 'Pacotes', 'monthly' => 'Serviços mensais', 'one_time' => 'Produtos e projetos'][$key])->all() as $key => $label)
                <a href="{{ $filterUrl(['tipo' => $key ?: null]) }}" class="badge {{ ($kind ?? '') === $key || (!$kind && $key === '') ? 'badge-purple' : 'badge-muted' }}" style="padding:4px 12px">{{ $label }}</a>
            @endforeach
            <span style="width:1px; height:18px; background:var(--border)"></span>
            <a href="{{ $filterUrl(['linha' => null]) }}" class="badge {{ !$line ? 'badge-purple' : 'badge-muted' }}" style="padding:4px 12px">Todas as linhas</a>
            @foreach($lines as $l)
                <a href="{{ $filterUrl(['linha' => $l]) }}" class="badge {{ $line === $l ? 'badge-purple' : 'badge-muted' }}" style="padding:4px 12px">{{ $l }}</a>
            @endforeach
            <a href="{{ $filterUrl(['inativos' => request()->boolean('inativos') ? null : 1]) }}" class="text-xs ml-auto" style="color:var(--muted)">
                {{ request()->boolean('inativos') ? 'Esconder desativados' : 'Mostrar desativados' }}
            </a>
        </div>

        <div class="card" style="padding:0; overflow-x:auto">
            <table class="nonna-table w-full">
                <thead>
                    <tr><th>Item</th><th>Tipo</th><th>Linha</th><th>Preço</th><th>Valor</th></tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr @if($canEdit) class="cursor-pointer" @click="openEdit(@js($item->id))" @endif style="{{ $item->is_active ? '' : 'opacity:.5' }}">
                            <td>
                                <p class="font-semibold" style="color:var(--text)">{{ $item->name }} @unless($item->is_active)<span class="badge badge-muted">desativado</span>@endunless</p>
                                @if($item->isOpenPackage() && $item->components->isNotEmpty())
                                    <p class="text-xs" style="color:var(--muted)">{{ $item->components->map(fn ($c) => ($c->pivot->quantity > 1 ? $c->pivot->quantity . '× ' : '') . $c->name)->implode(' · ') }}</p>
                                @elseif($item->description)
                                    <p class="text-xs" style="color:var(--muted)">{{ $item->description }}</p>
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $item->kindColor() }}">{{ $item->kindLabel() }}</span></td>
                            <td class="text-sm" style="color:var(--muted2)">{{ $item->line }}</td>
                            <td class="num text-sm" style="white-space:nowrap">{{ $item->priceLabel() }}</td>
                            <td><span class="badge badge-{{ $item->valueStatusColor() }}">{{ $item->valueStatusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-sm py-8" style="color:var(--muted)">Nenhum item com esses filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($canEdit)
        {{-- ── Editor (novo / editar) ── --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-8" style="background:rgba(0,0,0,.45)" @keydown.escape.window="open = false">
            <div class="card w-full" style="max-width:640px" @click.outside="open = false">
                <form method="POST" :action="action" class="card-body flex flex-col gap-4">
                    @csrf
                    <template x-if="f.id"><input type="hidden" name="_method" value="PATCH"></template>

                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-bold" style="color:var(--text)" x-text="f.id ? 'Editar item' : (f.kind === 'package' ? 'Montar pacote' : 'Novo item')"></h3>
                        <button type="button" class="btn-icon" @click="open = false" aria-label="Fechar"><x-icon name="x" size="16" /></button>
                    </div>

                    <div class="grid gap-3" style="grid-template-columns:2fr 1fr">
                        <div>
                            <label class="text-xs font-semibold" for="ci-name" style="color:var(--muted2)">Nome</label>
                            <input id="ci-name" name="name" x-model="f.name" class="field w-full" required maxlength="160">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" for="ci-line" style="color:var(--muted2)">Linha</label>
                            <input id="ci-line" name="line" x-model="f.line" class="field w-full" list="ci-lines" required maxlength="60">
                            <datalist id="ci-lines">@foreach($lines as $l)<option value="{{ $l }}">@endforeach</datalist>
                        </div>
                    </div>

                    <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
                        <div>
                            <label class="text-xs font-semibold" for="ci-kind" style="color:var(--muted2)">Tipo</label>
                            <select id="ci-kind" name="kind" x-model="f.kind" class="field w-full">
                                <option value="monthly">Serviço mensal</option>
                                <option value="one_time">Produto ou projeto (único)</option>
                                <option value="package">Pacote</option>
                            </select>
                        </div>
                        <div x-show="f.kind === 'package'">
                            <label class="text-xs font-semibold" for="ci-ptype" style="color:var(--muted2)">Tipo de pacote</label>
                            <select id="ci-ptype" name="package_type" x-model="f.package_type" class="field w-full" :disabled="f.kind !== 'package'">
                                <option value="open">Aberto: conjunto de itens com preço próprio</option>
                                <option value="closed">Fechado: nível com volumes e regras (PlaNo C)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
                        <div x-show="f.kind !== 'one_time'">
                            <label class="text-xs font-semibold" for="ci-mp" style="color:var(--muted2)" x-text="f.kind === 'package' ? 'Mensalidade (R$)' : 'Preço por mês (R$)'"></label>
                            <input id="ci-mp" name="monthly_price" x-model="f.monthly_price" type="number" step="0.01" min="0" class="field w-full num" :disabled="f.kind === 'one_time'">
                        </div>
                        <div x-show="f.kind !== 'monthly'">
                            <label class="text-xs font-semibold" for="ci-op" style="color:var(--muted2)" x-text="f.kind === 'package' ? 'Implantação ou valor único (R$)' : 'Preço (R$)'"></label>
                            <input id="ci-op" name="one_time_price" x-model="f.one_time_price" type="number" step="0.01" min="0" class="field w-full num" :disabled="f.kind === 'monthly'">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold" for="ci-desc" style="color:var(--muted2)" x-text="f.package_type === 'closed' && f.kind === 'package' ? 'Pergunta do nível (o cliente vê)' : 'Regra ou observação (aparece na proposta)'"></label>
                        <input id="ci-desc" name="description" x-model="f.description" class="field w-full" maxlength="1000">
                    </div>

                    {{-- Pacote aberto: composição --}}
                    <div x-show="f.kind === 'package' && f.package_type === 'open'" class="flex flex-col gap-2">
                        <p class="text-xs font-semibold" style="color:var(--muted2)">O que vem no pacote</p>
                        <template x-for="(c, i) in f.components" :key="i">
                            <div class="flex gap-2 items-center">
                                <select :name="`components[${i}][item_id]`" x-model="c.item_id" class="field flex-1" aria-label="Item do pacote">
                                    <option value="">Escolha um item…</option>
                                    @foreach($componentOptions as $o)
                                        <option value="{{ $o->id }}">{{ $o->name }} · {{ $o->priceLabel() }}</option>
                                    @endforeach
                                </select>
                                <input :name="`components[${i}][quantity]`" x-model="c.quantity" type="number" min="1" class="field num" style="width:70px" aria-label="Quantidade">
                                <button type="button" class="btn-icon" @click="f.components.splice(i, 1)" aria-label="Tirar item"><x-icon name="trash-2" size="14" /></button>
                            </div>
                        </template>
                        <button type="button" class="btn btn-sm self-start" @click="f.components.push({item_id:'', quantity:1})"><x-icon name="plus" size="13" /> Adicionar item</button>
                        <p class="text-xs" style="color:var(--muted)" x-show="componentsSum().one || componentsSum().month">
                            Soma dos itens: <span x-text="brl(componentsSum().one)"></span><span x-show="componentsSum().month"> + <span x-text="brl(componentsSum().month)"></span>/mês</span>
                        </p>
                    </div>

                    {{-- Pacote fechado: regras de nível --}}
                    <div x-show="f.kind === 'package' && f.package_type === 'closed'" class="flex flex-col gap-3">
                        <div class="grid gap-3" style="grid-template-columns:repeat(3,1fr)">
                            <div><label class="text-xs font-semibold" for="ci-ads" style="color:var(--muted2)">Anúncios por mês</label><input id="ci-ads" name="ads_per_month" x-model="f.rules.ads_per_month" type="number" min="0" class="field w-full num"></div>
                            <div><label class="text-xs font-semibold" for="ci-vid" style="color:var(--muted2)">Vídeos (dentro dos anúncios)</label><input id="ci-vid" name="videos_per_month" x-model="f.rules.videos_per_month" type="number" min="0" class="field w-full num"></div>
                            <div><label class="text-xs font-semibold" for="ci-camp" style="color:var(--muted2)">Campanhas ao mesmo tempo</label><input id="ci-camp" name="concurrent_campaigns" x-model="f.rules.concurrent_campaigns" type="number" min="0" class="field w-full num"></div>
                            <div><label class="text-xs font-semibold" for="ci-cap" style="color:var(--muted2)">Teto de verba (R$)</label><input id="ci-cap" name="ad_budget_cap" x-model="f.rules.ad_budget_cap" type="number" min="0" step="100" class="field w-full num"></div>
                            <div><label class="text-xs font-semibold" for="ci-exc" style="color:var(--muted2)">% sobre a verba acima do teto</label><input id="ci-exc" name="excess_rate" x-model="f.excess_pct" type="number" min="0" max="100" step="0.5" class="field w-full num"></div>
                            <div><label class="text-xs font-semibold" for="ci-att" style="color:var(--muted2)">Atendentes avisados</label><input id="ci-att" name="notified_attendants" x-model="f.rules.notified_attendants" type="number" min="0" class="field w-full num" placeholder="sem limite"></div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" for="ci-up" style="color:var(--muted2)">Nível de cima (pra sugerir upgrade)</label>
                            <select id="ci-up" name="upgrade_to" x-model="f.rules.upgrade_to" class="field w-full">
                                <option value="">Nenhum</option>
                                @foreach($closedPackages as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" for="ci-inc" style="color:var(--muted2)">O que está incluído (uma linha por item; o cliente vê)</label>
                            <textarea id="ci-inc" name="includes" x-model="f.includes" rows="5" class="field w-full"></textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="recommended" value="0"><input type="checkbox" name="recommended" value="1" x-model="f.rules.recommended"> Mostrar como recomendado</label>
                    </div>

                    {{-- Item: regras --}}
                    <div x-show="f.kind !== 'package'" class="flex flex-col gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="requires_closed_package" value="0"><input type="checkbox" name="requires_closed_package" value="1" x-model="f.rules.requires_closed_package"> Só pode ser vendido junto de um pacote fechado (PlaNo C)</label>
                        @if($closedPackages->isNotEmpty())
                        <div>
                            <p class="text-xs font-semibold mb-1" style="color:var(--muted2)">Não pode ser vendido com</p>
                            <div class="flex gap-4 flex-wrap text-sm">
                                @foreach($closedPackages as $p)
                                    <label class="flex items-center gap-1.5"><input type="checkbox" name="not_in_packages[]" value="{{ $p->id }}" x-model="f.rules.not_in_packages"> {{ $p->name }}</label>
                                @endforeach
                            </div>
                            <input name="not_in_reason" x-model="f.rules.not_in_reason" x-show="(f.rules.not_in_packages || []).length" class="field w-full mt-2" placeholder="Motivo (aparece pro vendedor)" maxlength="200" aria-label="Motivo">
                        </div>
                        @endif
                        <div x-show="f.rules.requires_closed_package">
                            <p class="text-xs font-semibold mb-1" style="color:var(--muted2)">Soma no pacote do cliente</p>
                            <div class="grid gap-3" style="grid-template-columns:repeat(4,1fr)">
                                <div><label class="text-xs" for="ci-ea" style="color:var(--muted)">+ anúncios</label><input id="ci-ea" name="effect_ads" x-model="f.rules.effects.ads" type="number" min="0" class="field w-full num"></div>
                                <div><label class="text-xs" for="ci-ev" style="color:var(--muted)">+ vídeos</label><input id="ci-ev" name="effect_videos" x-model="f.rules.effects.videos" type="number" min="0" class="field w-full num"></div>
                                <div><label class="text-xs" for="ci-ec" style="color:var(--muted)">+ campanhas</label><input id="ci-ec" name="effect_campaigns" x-model="f.rules.effects.campaigns" type="number" min="0" class="field w-full num"></div>
                                <div><label class="text-xs" for="ci-et" style="color:var(--muted)">+ atendentes</label><input id="ci-et" name="effect_attendants" x-model="f.rules.effects.attendants" type="number" min="0" class="field w-full num"></div>
                            </div>
                        </div>
                        <div style="max-width:220px">
                            <label class="text-xs font-semibold" for="ci-yv" style="color:var(--muted2)">Vídeos que rende (captação)</label>
                            <input id="ci-yv" name="yields_videos" x-model="f.rules.yields_videos" type="number" min="0" class="field w-full num">
                        </div>
                    </div>

                    <div class="flex gap-5 flex-wrap text-sm">
                        <label class="flex items-center gap-2"><input type="hidden" name="price_is_starting" value="0"><input type="checkbox" name="price_is_starting" value="1" x-model="f.price_is_starting"> Preço "a partir de" (o vendedor ajusta na proposta)</label>
                        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" x-model="f.is_active"> Ativo</label>
                    </div>

                    <div style="max-width:220px">
                        <label class="text-xs font-semibold" for="ci-vs" style="color:var(--muted2)">Valor</label>
                        <select id="ci-vs" name="value_status" x-model="f.value_status" class="field w-full">
                            <option value="decided">Decidido</option>
                            <option value="suggested">Sugerido (ainda a validar)</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2" style="border-top:1px solid var(--border)">
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                        <button type="button" x-show="f.id" class="btn btn-ghost btn-sm" style="color:var(--red)"
                                @click="if (await $store.confirmDialog.ask('Excluir este item do catálogo? Se ele já faz parte de um pacote, desative em vez de excluir.')) $refs.del.submit()">
                            <x-icon name="trash-2" size="14" /> Excluir
                        </button>
                    </div>
                </form>
                <form x-ref="del" method="POST" :action="f.id ? base + '/' + f.id : ''" class="hidden">@csrf @method('DELETE')</form>
            </div>
        </div>
        @endif
    </div>

    <script>
        function catalogEditor(items, storeUrl, base) {
            const blank = (kind) => ({
                id: null, name: '', line: kind === 'package' ? 'Web' : '', kind, package_type: kind === 'package' ? 'open' : null,
                description: '', monthly_price: '', one_time_price: '', price_is_starting: false, value_status: 'suggested', is_active: true,
                rules: {}, components: kind === 'package' ? [{item_id: '', quantity: 1}] : [],
            });
            const normalize = (it) => {
                const f = JSON.parse(JSON.stringify(it));
                f.rules = Array.isArray(f.rules) ? {} : (f.rules || {});
                f.rules.effects = f.rules.effects || {};
                f.rules.not_in_packages = f.rules.not_in_packages || [];
                f.rules.upgrade_to = f.rules.upgrade_to || '';
                f.package_type = f.package_type || 'open';
                f.excess_pct = f.rules.excess_rate != null ? +(f.rules.excess_rate * 100).toFixed(2) : 10;
                f.includes = (f.rules.includes || []).join('\n');
                return f;
            };
            return {
                open: false, f: normalize(blank('monthly')), base,
                get action() { return this.f.id ? `${base}/${this.f.id}` : storeUrl; },
                openNew(kind) { this.f = normalize(blank(kind)); this.open = true; },
                openEdit(id) { this.f = normalize(items[id]); this.open = true; },
                brl(v) { return (v || 0).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}); },
                componentsSum() {
                    let one = 0, month = 0;
                    for (const c of this.f.components || []) {
                        const it = items[c.item_id]; if (!it) continue;
                        one += (+it.one_time_price || 0) * (+c.quantity || 1);
                        month += (+it.monthly_price || 0) * (+c.quantity || 1);
                    }
                    return {one, month};
                },
            };
        }
    </script>
</x-app-layout>
