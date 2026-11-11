{{-- Central de Comércio → Montar proposta (ProposalController create/edit).
     Proposta = pacote opcional (fechado PlaNo C ou aberto da Nonna) + itens livres do catálogo,
     com opção de vender os itens avulsos como um pacote personalizado. Toda conta vem do
     servidor (proposals.preview → ProposalCalculator); a tela só monta o "state". --}}
@php
    $config = [
        'packages'      => $packages,
        'items'         => $items,
        'state'         => $state,
        'title'         => $proposal->title ?? '',
        'opportunityId' => $opportunity?->id,
        'urls'          => [
            'preview' => route('proposals.preview'),
            'save'    => $proposal->exists ? route('proposals.update', $proposal) : route('proposals.store'),
            'method'  => $proposal->exists ? 'PATCH' : 'POST',
        ],
        'freePct'       => (float) config('proposals.free_discount_percent'),
        'maxPct'        => (float) config('proposals.max_discount_percent'),
        'installments'  => config('proposals.installments'),
        'months'        => (int) config('proposals.contract_months'),
    ];
@endphp
<x-app-layout>
    <x-slot name="header">{{ $proposal->exists ? 'Proposta v' . $proposal->version : 'Nova proposta' }}</x-slot>

    <div x-data="proposalBuilder(@js($config))">

        {{-- Oportunidade + título --}}
        <div class="card card-body mb-4 grid gap-4" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)">
            <div>
                <label class="text-xs font-semibold" for="pb-opp" style="color:var(--muted2)">Oportunidade</label>
                @if($opportunity)
                    <p class="text-sm font-semibold mt-1" style="color:var(--text)">
                        <a href="{{ route('opportunities.show', $opportunity) }}" style="color:var(--purple)">{{ $opportunity->title }}</a>
                        <span class="font-normal" style="color:var(--muted)">· {{ $opportunity->contact?->name }}</span>
                    </p>
                @else
                    <select id="pb-opp" x-model="opportunityId" class="field w-full">
                        <option value="">Escolha a oportunidade…</option>
                        @foreach($opportunities as $o)
                            <option value="{{ $o->id }}">{{ $o->title }} · {{ $o->contact?->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label class="text-xs font-semibold" for="pb-title" style="color:var(--muted2)">Nome da proposta <span style="color:var(--muted)">(em branco = automático)</span></label>
                <input id="pb-title" x-model="title" class="field w-full" maxlength="160" placeholder="Ex.: Caminho com site novo">
            </div>
        </div>

        <div class="grid gap-4 items-start" :style="{gridTemplateColumns: wide ? 'minmax(0,1fr) 340px' : '1fr'}">
            <div class="flex flex-col gap-4 min-w-0">

                {{-- 1. Como começar --}}
                <div class="card card-body">
                    <div class="flex items-baseline gap-3 mb-3 flex-wrap">
                        <h3 class="text-sm font-bold" style="color:var(--text)">1. Como começar</h3>
                        <span class="text-xs" style="color:var(--muted)">dá pra trocar depois; os itens avulsos ficam</span>
                    </div>
                    <template x-for="grp in startGroups()" :key="grp.label">
                        <div class="mb-3">
                            <p class="text-xs font-semibold uppercase mb-2" style="color:var(--muted); letter-spacing:.06em" x-text="grp.label"></p>
                            <div class="grid gap-2" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                                <template x-for="p in grp.items" :key="p.id">
                                    <button type="button" @click="choosePackage(p.id)" class="text-left p-3 flex flex-col gap-0.5"
                                            :style="`border:1px solid ${s.package_id === p.id ? 'var(--purple)' : 'var(--border)'}; border-radius:10px; background:${s.package_id === p.id ? 'rgba(100,59,142,.07)' : 'var(--s1)'}`">
                                        <span class="text-sm font-bold" style="color:var(--text)"><span x-text="p.name.replace('PlaNo C · ', '')"></span>
                                            <span x-show="p.rules.recommended" class="badge badge-purple" style="font-size:9px">Recomendado</span></span>
                                        <span class="text-sm font-semibold num" style="color:var(--text)" x-text="p.monthly ? brl(p.monthly) + '/mês' : brl(p.one_time)"></span>
                                        <span class="text-xs" style="color:var(--muted)" x-text="p.monthly && p.one_time ? '+ ' + brl(p.one_time) + (p.type === 'closed' ? ' implantação' : '') : (p.description || '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p class="text-xs font-semibold uppercase mb-2" style="color:var(--muted); letter-spacing:.06em">Sem pacote</p>
                    <button type="button" @click="choosePackage(null)" class="text-left p-3 w-full"
                            :style="`border:1px solid ${!s.package_id ? 'var(--purple)' : 'var(--border)'}; border-radius:10px; background:${!s.package_id ? 'rgba(100,59,142,.07)' : 'var(--s1)'}`">
                        <span class="text-sm font-bold" style="color:var(--text)">Do zero</span>
                        <span class="text-xs block" style="color:var(--muted)">Escolha produtos e serviços soltos e, se quiser, venda como um pacote personalizado.</span>
                    </button>
                </div>

                {{-- 2. Itens --}}
                <div class="card card-body">
                    <div class="flex items-baseline gap-3 mb-3">
                        <h3 class="text-sm font-bold" style="color:var(--text)">2. Itens da proposta</h3>
                    </div>

                    <template x-if="pkg()">
                        <div class="p-3 mb-2 flex gap-3 items-start" style="border:1px solid rgba(100,59,142,.3); background:rgba(100,59,142,.06); border-radius:10px">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold" style="color:var(--text)" x-text="pkg().name"></p>
                                <p class="text-xs" style="color:var(--muted)" x-text="pkg().type === 'closed' ? 'Pacote fechado: os itens do nível não saem, só dá pra somar' : 'Pacote da Nonna com preço próprio'"></p>
                                <ul class="text-xs mt-1.5 pl-4" style="color:var(--muted2); list-style:disc; columns:2; column-gap:20px">
                                    <template x-for="inc in (pkg().rules.includes || [])"><li x-text="inc"></li></template>
                                </ul>
                            </div>
                            <div class="text-right text-sm num" style="white-space:nowrap">
                                <p class="font-semibold" x-text="pkg().monthly ? brl(pkg().monthly) + '/mês' : brl(pkg().one_time)"></p>
                                <p class="text-xs" style="color:var(--muted)" x-show="pkg().monthly && pkg().one_time" x-text="'+ ' + brl(pkg().one_time)"></p>
                            </div>
                            <button type="button" class="btn-icon" @click="choosePackage(null)" aria-label="Tirar o pacote"><x-icon name="x" size="15" /></button>
                        </div>
                    </template>

                    <template x-if="!pkg() && !s.lines.length">
                        <div class="text-sm text-center py-5" style="color:var(--muted); border:1px dashed var(--border); border-radius:10px">Nenhum item ainda. Comece por um pacote acima ou adicione produtos e serviços.</div>
                    </template>

                    <template x-for="(l, i) in s.lines" :key="l.catalog_item_id">
                        <div class="flex gap-3 items-center py-2.5 flex-wrap" style="border-bottom:1px dashed var(--border)"
                             :style="group().on && s.lines.length >= 2 ? {borderLeft: '3px solid var(--orange)', paddingLeft: '10px'} : {borderLeft: 'none', paddingLeft: '0'}">
                            <div class="flex-1 min-w-0" style="min-width:180px">
                                <p class="text-sm font-semibold" style="color:var(--text)">
                                    <span x-text="item(l.catalog_item_id)?.name"></span>
                                    <span class="badge" :class="item(l.catalog_item_id)?.kind === 'monthly' ? 'badge-blue' : 'badge-orange'" style="font-size:9px" x-text="item(l.catalog_item_id)?.kind === 'monthly' ? 'mensal' : 'único'"></span>
                                    <span x-show="item(l.catalog_item_id)?.value_status === 'suggested'" class="badge badge-yellow" style="font-size:9px">sugerido</span>
                                </p>
                                <p class="text-xs" style="color:var(--muted)">
                                    <span x-text="item(l.catalog_item_id)?.description || ''"></span>
                                    <span x-show="blockReason(item(l.catalog_item_id))" style="color:var(--red)" x-text="' · ' + blockReason(item(l.catalog_item_id))"></span>
                                </p>
                            </div>
                            <div class="flex items-center" style="border:1px solid var(--border); border-radius:7px; overflow:hidden">
                                <button type="button" class="px-2 py-1" style="background:var(--s2)" @click="l.quantity = Math.max(1, l.quantity - 1); changed()" :disabled="l.quantity <= 1" aria-label="Menos">−</button>
                                <span class="px-2 text-sm font-semibold num" x-text="l.quantity"></span>
                                <button type="button" class="px-2 py-1" style="background:var(--s2)" @click="l.quantity++; changed()" aria-label="Mais">+</button>
                            </div>
                            <div class="text-right text-sm num" style="width:110px">
                                <template x-if="item(l.catalog_item_id)?.starting">
                                    <div>
                                        <input type="number" step="50" min="0" class="field num text-right" style="width:100px; padding:3px 6px" x-model.number="l.unit_price" @change="changed()" :aria-label="'Preço de ' + item(l.catalog_item_id)?.name">
                                        <p class="text-xs" style="color:var(--muted)" x-text="'a partir de ' + brl(item(l.catalog_item_id)?.price)"></p>
                                    </div>
                                </template>
                                <template x-if="!item(l.catalog_item_id)?.starting"><span x-text="brl(item(l.catalog_item_id)?.price)"></span></template>
                            </div>
                            <div class="text-right text-sm font-semibold num" style="width:110px" x-text="brl(unitPrice(l) * l.quantity) + (item(l.catalog_item_id)?.kind === 'monthly' ? '/mês' : '')"></div>
                            <button type="button" class="btn-icon" @click="s.lines.splice(i, 1); changed()" aria-label="Tirar item"><x-icon name="x" size="15" /></button>
                        </div>
                    </template>

                    <button type="button" class="btn btn-sm mt-3 self-start" style="border-style:dashed; color:var(--purple)" @click="picker = !picker; $nextTick(() => picker && $refs.pickQ.focus())">
                        <x-icon name="plus" size="14" /> Adicionar produto ou serviço
                    </button>

                    <div x-show="picker" x-cloak class="mt-3 p-3" style="border:1px solid rgba(100,59,142,.3); border-radius:10px">
                        <input x-ref="pickQ" x-model="pickQ" class="field w-full" placeholder="Buscar no catálogo… (site, vídeo, hospedagem)" aria-label="Buscar no catálogo">
                        <div class="flex gap-1.5 flex-wrap my-2">
                            <template x-for="ln in ['Todas', ...lines()]">
                                <button type="button" class="badge" :class="pickLine === ln ? 'badge-purple' : 'badge-muted'" style="padding:3px 10px" @click="pickLine = ln" x-text="ln"></button>
                            </template>
                        </div>
                        <div style="max-height:320px; overflow-y:auto">
                            <template x-for="it in pickList()" :key="it.id">
                                <div class="flex items-center gap-3 py-2" style="border-bottom:1px solid var(--border)" :style="{opacity: blockReason(it) ? .5 : 1}">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold" style="color:var(--text)" x-text="it.name"></p>
                                        <p class="text-xs" style="color:var(--muted)"><span x-text="it.line + ' · ' + (it.kind === 'monthly' ? 'mensal' : 'único')"></span><span x-show="blockReason(it)" style="color:var(--red)" x-text="' · ' + blockReason(it)"></span></p>
                                    </div>
                                    <span class="text-sm num" style="white-space:nowrap" x-text="(it.starting ? 'a partir de ' : '') + brl(it.price)"></span>
                                    <button type="button" class="btn btn-sm" :disabled="!!blockReason(it)" @click="addItem(it)" x-text="hasLine(it.id) ? '+1' : 'Adicionar'"></button>
                                </div>
                            </template>
                            <p x-show="!pickList().length" class="text-xs py-3" style="color:var(--muted)">Nada encontrado. Se for algo novo, cadastre em Produtos e serviços.</p>
                        </div>
                    </div>
                </div>

                {{-- Pacote personalizado --}}
                <div x-show="s.lines.length >= 2" class="card card-body flex flex-col gap-3" style="border-color:rgba(238,121,25,.35); background:rgba(238,121,25,.05)">
                    <label class="flex items-center gap-2 text-sm font-bold" style="color:var(--text)">
                        <input type="checkbox" x-model="s.group.on" @change="changed()"> Vender os itens avulsos como um pacote
                    </label>
                    <p x-show="!s.group.on" class="text-xs" style="color:var(--muted2)">O cliente vê um pacote com nome e preço único, com os itens listados dentro. Bom pra dar um preço redondo ou um desconto no conjunto.</p>
                    <div x-show="s.group.on" class="flex flex-col gap-3">
                        <div class="grid gap-3" style="grid-template-columns:2fr 1fr 1fr">
                            <div><label class="text-xs font-semibold" for="pb-gn" style="color:var(--muted2)">Nome do pacote (o cliente vê)</label><input id="pb-gn" x-model="s.group.name" @change="changed()" class="field w-full" maxlength="160"></div>
                            <div><label class="text-xs font-semibold" for="pb-gu" style="color:var(--muted2)">Preço único</label><input id="pb-gu" type="number" step="10" min="0" x-model.number="s.group.one_time" @change="changed()" :disabled="s.group.mode !== 'fixed'" class="field w-full num"></div>
                            <div><label class="text-xs font-semibold" for="pb-gm" style="color:var(--muted2)">Preço mensal</label><input id="pb-gm" type="number" step="10" min="0" x-model.number="s.group.monthly" @change="changed()" :disabled="s.group.mode !== 'fixed'" class="field w-full num"></div>
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="flex" style="border:1px solid var(--border); border-radius:7px; overflow:hidden">
                                <button type="button" class="px-3 py-1.5 text-xs" :style="s.group.mode === 'sum' ? 'background:var(--purple); color:#fff' : ''" @click="setGroupMode('sum')">Soma dos itens</button>
                                <button type="button" class="px-3 py-1.5 text-xs" :style="s.group.mode === 'fixed' ? 'background:var(--purple); color:#fff' : ''" @click="setGroupMode('fixed')">Preço fechado</button>
                            </div>
                            <span class="text-xs" style="color:var(--muted2)" x-show="calc">
                                Soma dos itens: <span x-text="brl(calc?.loose_one_time)"></span><span x-show="calc?.loose_monthly"> + <span x-text="brl(calc?.loose_monthly)"></span>/mês</span>
                                <span x-show="s.group.mode === 'fixed' && calc?.group_below_percent" x-text="' · pacote ' + Math.abs(calc?.group_below_percent).toLocaleString('pt-BR') + (calc?.group_below_percent > 0 ? '% abaixo' : '% acima')"></span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Verba --}}
                <div x-show="pkg()?.type === 'closed'" class="card card-body">
                    <h3 class="text-sm font-bold mb-3" style="color:var(--text)">3. Verba de anúncios <span class="text-xs font-normal" style="color:var(--muted)">· paga pelo cliente direto às plataformas</span></h3>
                    <div class="grid gap-4 items-end" style="grid-template-columns:220px 1fr">
                        <div><label class="text-xs font-semibold" for="pb-ab" style="color:var(--muted2)">Média mensal prevista (R$)</label><input id="pb-ab" type="number" step="500" min="0" x-model.number="s.ad_budget" @input.debounce.400ms="changed()" class="field w-full num"></div>
                        <p class="text-sm" style="color:var(--muted2)" x-show="calc">
                            <template x-if="calc?.excess > 0"><span>Passa <b class="num" x-text="brl(calc.excess)"></b> do teto (<span x-text="brl(pkg().rules.ad_budget_cap)"></span>). Entra <b class="num" x-text="brl(calc.excess_value)"></b> por mês, sem desconto.</span></template>
                            <template x-if="!(calc?.excess > 0)"><span>Dentro do teto do nível (<span x-text="brl(pkg()?.rules.ad_budget_cap)"></span>). Nada a cobrar a mais.</span></template>
                        </p>
                    </div>
                </div>

                {{-- Condições --}}
                <div class="card card-body">
                    <h3 class="text-sm font-bold mb-3" style="color:var(--text)">Condições</h3>
                    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
                        <div>
                            <label class="text-xs font-semibold" for="pb-disc" style="color:var(--muted2)">Desconto sobre a mensalidade (%)</label>
                            <input id="pb-disc" type="number" step="0.5" min="0" x-model.number="s.discount" @input.debounce.400ms="changed()" class="field w-full num">
                            <p class="text-xs mt-1" style="color:var(--muted)">Até <span x-text="cfg.freePct"></span>% livre · até <span x-text="cfg.maxPct"></span>% com liberação · acima disso, bloqueado</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold mb-1" style="color:var(--muted2)">Parcelas dos valores únicos</p>
                            <div class="inline-flex" style="border:1px solid var(--border); border-radius:7px; overflow:hidden">
                                <template x-for="n in cfg.installments">
                                    <button type="button" class="px-3 py-1.5 text-xs" :style="s.installments === n ? 'background:var(--purple); color:#fff' : ''" @click="s.installments = n; changed()" x-text="n === 1 ? 'À vista' : n + 'x'"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div x-show="calc?.approval === 'needs_release'" class="mt-3 px-3 py-2 text-xs" style="background:rgba(245,158,11,.1); color:var(--yellow); border-radius:7px">
                        <span x-text="'Fica ' + calc?.effective_discount.toLocaleString('pt-BR') + '% abaixo da tabela: dá pra salvar, mas pra enviar precisa da liberação de um administrador.'"></span>
                    </div>
                    <div x-show="calc?.approval === 'blocked'" class="mt-3 px-3 py-2 text-xs" style="background:rgba(248,113,113,.1); color:var(--red); border-radius:7px">
                        <span x-text="'Fica ' + calc?.effective_discount.toLocaleString('pt-BR') + '% abaixo da tabela, acima do máximo de ' + cfg.maxPct + '%. Ajuste o valor.'"></span>
                    </div>
                </div>
            </div>

            {{-- Resumo --}}
            <aside class="flex flex-col gap-3" style="position:sticky; top:16px">
                <div class="card card-body">
                    <p class="text-xs font-semibold uppercase mb-1" style="color:var(--muted); letter-spacing:.06em">Mensalidade</p>
                    <p class="text-2xl font-black num" style="color:var(--text)" x-text="calc ? (calc.monthly > 0 ? brl(calc.monthly) + '/mês' : 'Sem mensalidade') : '…'"></p>
                    <div class="mt-1 flex flex-col gap-0.5 text-xs" style="color:var(--muted2)">
                        <template x-for="row in monthlyRows()"><div class="flex justify-between gap-2"><span x-text="row[0]"></span><b class="num" :style="row[2] ? 'color:var(--green)' : 'color:var(--text)'" x-text="row[1]"></b></div></template>
                    </div>

                    <p class="text-xs font-semibold uppercase mt-4 mb-1" style="color:var(--muted); letter-spacing:.06em">Valores únicos</p>
                    <div class="flex flex-col gap-0.5 text-xs" style="color:var(--muted2)">
                        <template x-for="row in oneTimeRows()"><div class="flex justify-between gap-2"><span x-text="row[0]"></span><b class="num" style="color:var(--text)" x-text="row[1]"></b></div></template>
                        <div class="flex justify-between gap-2 font-semibold" style="color:var(--text)" x-show="calc">
                            <span x-text="calc?.installments > 1 ? 'Total em ' + calc.installments + 'x de ' + brl(calc.installment) : 'Total à vista'"></span><span class="num" x-text="brl(calc?.one_time)"></span>
                        </div>
                        <p x-show="calc && calc.installments > 1 && calc.last_installment !== calc.installment" class="text-xs" style="color:var(--muted)" x-text="'A última parcela fica em ' + brl(calc?.last_installment) + ' pra fechar os centavos.'"></p>
                    </div>

                    <p class="text-xs font-semibold uppercase mt-4 mb-1" style="color:var(--muted); letter-spacing:.06em">Quanto o cliente paga por mês</p>
                    <div class="flex flex-col gap-0.5 text-xs" style="color:var(--muted2)" x-show="calc">
                        <div class="flex justify-between"><span x-text="calc?.installments > 1 ? 'Meses 1 a ' + calc.installments : 'Mês 1'"></span><b class="num" style="color:var(--text)" x-text="brl(calc?.installments > 1 ? calc?.first_months : (calc?.monthly + calc?.one_time))"></b></div>
                        <div class="flex justify-between"><span x-text="'Meses ' + ((calc?.installments || 1) + 1) + ' a ' + cfg.months"></span><b class="num" style="color:var(--text)" x-text="brl(calc?.monthly)"></b></div>
                    </div>
                    <div class="flex justify-between text-sm font-bold mt-3 pt-2" style="border-top:1px solid var(--border); color:var(--text)">
                        <span x-text="'Total do contrato (' + cfg.months + ' meses)'"></span><span class="num" x-text="brl(calc?.total)"></span>
                    </div>
                    <div x-show="pkg()?.type === 'closed'" class="flex justify-between text-xs mt-1" style="color:var(--muted2)">
                        <span>Verba às plataformas (fora da conta)</span><span class="num" x-text="brl(s.ad_budget) + '/mês'"></span>
                    </div>
                </div>

                <div class="card card-body" x-show="calc?.deliverables">
                    <p class="text-xs font-semibold uppercase mb-2" style="color:var(--muted); letter-spacing:.06em">O que o cliente recebe por mês</p>
                    <div class="grid grid-cols-4 gap-1.5 text-center">
                        <template x-for="d in [['ads','anúncios'],['videos','vídeos'],['campaigns','campanhas ao mesmo tempo'],['attendants','atendentes avisados']]">
                            <div class="py-1.5 px-1" style="background:var(--s2); border-radius:7px">
                                <b class="block text-base num" style="color:var(--text)" x-text="calc?.deliverables?.[d[0]] ?? '∞'"></b>
                                <span class="text-[10px] leading-tight block" style="color:var(--muted2)" x-text="d[1]"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-for="w in warnings"><div class="px-3 py-2 text-xs" style="background:rgba(245,158,11,.1); color:var(--yellow); border-radius:7px" x-text="w"></div></template>
                <template x-for="e in errors"><div class="px-3 py-2 text-xs" style="background:rgba(248,113,113,.1); color:var(--red); border-radius:7px" x-text="e"></div></template>

                <div class="card card-body" x-show="upgrade" style="border-color:rgba(238,121,25,.4); background:rgba(238,121,25,.06)">
                    <p class="text-sm font-bold" style="color:var(--orange)" x-text="upgrade ? (upgrade.monthly_diff <= 0 ? upgrade.package_name.replace('PlaNo C · ', 'O ') + ' sai mais barato' : 'Por mais ' + brl(upgrade.monthly_diff) + '/mês, o cliente leva o ' + upgrade.package_name.replace('PlaNo C · ', '')) : ''"></p>
                    <p class="text-xs mt-1 mb-2" style="color:var(--text)" x-text="upgradeText()"></p>
                    <button type="button" class="btn btn-sm self-start" style="background:var(--orange); color:#fff" @click="applyUpgrade()">Trocar para o nível de cima</button>
                </div>

                <div class="card card-body flex flex-col gap-2">
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary btn-sm" @click="save()" :disabled="saving || calc?.approval === 'blocked' || errors.length > 0">
                            <span x-text="saving ? 'Salvando…' : 'Salvar proposta'"></span>
                        </button>
                        <button type="button" class="btn btn-sm" @click="copy()"><x-icon name="copy" size="13" /> Copiar resumo</button>
                    </div>
                    <p class="text-xs" style="color:var(--muted)">Depois de salvar, você revisa e marca como enviada. Enviada, esta versão congela.</p>
                    <p x-show="msg" class="text-xs font-semibold" :style="msgError ? 'color:var(--red)' : 'color:var(--green)'" x-text="msg"></p>
                </div>
            </aside>
        </div>
    </div>

    <script>
        function proposalBuilder(cfg) {
            const brlFmt = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
            return {
                cfg, s: JSON.parse(JSON.stringify(cfg.state)), title: cfg.title, opportunityId: cfg.opportunityId || '',
                calc: null, upgrade: null, errors: [], warnings: [], picker: false, pickQ: '', pickLine: 'Todas',
                saving: false, msg: '', msgError: false, wide: window.innerWidth >= 1100, timer: null, seq: 0,
                init() {
                    this.s.lines = this.s.lines || [];
                    this.s.group = Object.assign({on: false, name: '', mode: 'sum', monthly: 0, one_time: 0}, this.s.group || {});
                    if (this.s.ad_budget == null) this.s.ad_budget = 0;
                    window.addEventListener('resize', () => this.wide = window.innerWidth >= 1100);
                    this.refresh();
                },
                brl(v) { return brlFmt.format(+v || 0); },
                pkg() { return this.cfg.packages.find(p => p.id === this.s.package_id) || null; },
                item(id) { return this.cfg.items.find(i => i.id === id); },
                lines() { return [...new Set(this.cfg.items.map(i => i.line))]; },
                hasLine(id) { return this.s.lines.some(l => l.catalog_item_id === id); },
                group() { return this.s.group; },
                unitPrice(l) { const it = this.item(l.catalog_item_id); return it?.starting && l.unit_price !== '' && l.unit_price != null ? +l.unit_price : (it?.price || 0); },
                startGroups() {
                    const closed = this.cfg.packages.filter(p => p.type === 'closed'), open = this.cfg.packages.filter(p => p.type !== 'closed');
                    return [closed.length && {label: 'PlaNo C', items: closed}, open.length && {label: 'Pacotes da Nonna', items: open}].filter(Boolean);
                },
                blockReason(it) {
                    if (!it) return '';
                    const p = this.pkg(), r = it.rules || {};
                    if (r.requires_closed_package && p?.type !== 'closed') return 'Só junto de um nível do PlaNo C';
                    if (p && (r.not_in_packages || []).includes(p.id)) return r.not_in_reason || 'Não combina com ' + p.name;
                    return '';
                },
                pickList() {
                    const q = this.pickQ.toLowerCase();
                    return this.cfg.items.filter(i => (this.pickLine === 'Todas' || i.line === this.pickLine) && (!q || i.name.toLowerCase().includes(q)));
                },
                choosePackage(id) {
                    this.s.package_id = id;
                    this.s.lines = this.s.lines.filter(l => !this.blockReason(this.item(l.catalog_item_id)));
                    this.changed();
                },
                addItem(it) {
                    const l = this.s.lines.find(x => x.catalog_item_id === it.id);
                    l ? l.quantity++ : this.s.lines.push({catalog_item_id: it.id, quantity: 1, unit_price: it.price});
                    this.changed();
                },
                setGroupMode(m) {
                    if (m === 'fixed' && this.s.group.mode !== 'fixed' && this.calc) {
                        this.s.group.one_time = this.calc.loose_one_time; this.s.group.monthly = this.calc.loose_monthly;
                    }
                    this.s.group.mode = m; this.changed();
                },
                changed() { clearTimeout(this.timer); this.timer = setTimeout(() => this.refresh(), 150); },
                payload() {
                    return {
                        package_id: this.s.package_id || null, ad_budget: this.s.ad_budget || 0, discount: this.s.discount || 0,
                        installments: this.s.installments || 1, group: this.s.group,
                        lines: this.s.lines.map(l => ({catalog_item_id: l.catalog_item_id, quantity: l.quantity, unit_price: this.unitPrice(l)})),
                    };
                },
                async post(url, body, method = 'POST') {
                    const res = await fetch(url, {method, headers: {'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify(body)});
                    return {ok: res.ok, data: await res.json().catch(() => ({}))};
                },
                async refresh() {
                    const mine = ++this.seq;
                    const {ok, data} = await this.post(this.cfg.urls.preview, {state: this.payload()});
                    if (mine !== this.seq || !ok) return;
                    this.calc = data.calc; this.upgrade = data.upgrade; this.errors = data.errors || []; this.warnings = data.warnings || [];
                },
                monthlyRows() {
                    const c = this.calc; if (!c) return [];
                    const rows = [], p = this.pkg();
                    if (p && c.package_monthly) rows.push([p.name, this.brl(c.package_monthly)]);
                    if (c.grouped) { if (c.group_monthly) rows.push([this.s.group.name || 'Pacote personalizado', this.brl(c.group_monthly)]); }
                    else this.s.lines.forEach(l => { const it = this.item(l.catalog_item_id); if (it?.kind === 'monthly') rows.push([l.quantity + '× ' + it.name, this.brl(this.unitPrice(l) * l.quantity)]); });
                    if (c.discount_value) rows.push(['Desconto ' + this.s.discount + '%', '−' + this.brl(c.discount_value), true]);
                    if (c.excess_value) rows.push(['Gestão da verba acima do teto', this.brl(c.excess_value)]);
                    return rows;
                },
                oneTimeRows() {
                    const c = this.calc; if (!c) return [];
                    const rows = [], p = this.pkg();
                    if (p && c.package_one_time) rows.push([p.type === 'closed' ? 'Implantação ' + p.name.replace('PlaNo C · ', '') : p.name, this.brl(c.package_one_time)]);
                    if (c.grouped) {
                        if (c.group_one_time) rows.push([this.s.group.name || 'Pacote personalizado', this.brl(c.group_one_time)]);
                        this.s.lines.forEach(l => rows.push(['· ' + (l.quantity > 1 ? l.quantity + '× ' : '') + this.item(l.catalog_item_id)?.name, 'incluso']));
                    } else this.s.lines.forEach(l => { const it = this.item(l.catalog_item_id); if (it?.kind === 'one_time') rows.push([l.quantity + '× ' + it.name, this.brl(this.unitPrice(l) * l.quantity)]); });
                    return rows;
                },
                upgradeText() {
                    const u = this.upgrade; if (!u) return '';
                    const abs = u.absorbed.length ? 'Ele já inclui ' + u.absorbed.join(', ') + '. ' : '';
                    const impl = u.one_time_diff ? ', valores únicos ' + (u.one_time_diff > 0 ? '+' : '') + this.brl(u.one_time_diff) : '';
                    return abs + 'Mensalidade ' + this.brl(u.monthly) + impl + '.';
                },
                applyUpgrade() {
                    const u = this.upgrade; if (!u) return;
                    const keep = Object.fromEntries(u.lines.map(l => [l.id, l.quantity]));
                    this.s.package_id = u.package_id;
                    this.s.lines = this.s.lines.filter(l => keep[l.catalog_item_id]).map(l => ({...l, quantity: keep[l.catalog_item_id]}));
                    this.changed();
                },
                async save() {
                    if (!this.opportunityId) { this.msg = 'Escolha a oportunidade desta proposta.'; this.msgError = true; return; }
                    this.saving = true; this.msg = '';
                    const {ok, data} = await this.post(this.cfg.urls.save, {opportunity_id: this.opportunityId, title: this.title, state: this.payload()}, this.cfg.urls.method);
                    this.saving = false;
                    if (ok && data.redirect) { window.location = data.redirect; return; }
                    this.msg = (data.errors || ['Não deu pra salvar. Tente de novo.']).join(' '); this.msgError = true;
                },
                async copy() {
                    const c = this.calc; if (!c) return;
                    const base = this.pkg()?.name || (c.grouped ? this.s.group.name : 'Itens avulsos');
                    const txt = `${this.title || 'Proposta'}\n${base}\n${c.monthly > 0 ? 'Mensalidade: ' + this.brl(c.monthly) + '\n' : ''}Valores únicos: ${this.brl(c.one_time)}${c.installments > 1 ? ' em ' + c.installments + 'x' : ''}\nTotal ${this.cfg.months} meses: ${this.brl(c.total)}`;
                    try { await navigator.clipboard.writeText(txt); this.msg = 'Resumo copiado.'; this.msgError = false; }
                    catch { this.msg = 'Não deu pra copiar neste navegador.'; this.msgError = true; }
                },
            };
        }
    </script>
</x-app-layout>
