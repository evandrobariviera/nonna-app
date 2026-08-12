<x-app-layout>
    <x-slot name="header">Planejamento — {{ $macroplan->client->displayName() }}</x-slot>

    {{-- BREADCRUMB + STATUS --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('macroplans.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Planejamentos</a>
            <span style="color:var(--border2)">/</span>
            <a href="{{ route('clients.show', [$macroplan->client, 'tab' => 'planejamentos']) }}"
               class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                {{ $macroplan->client->displayName() }}
            </a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">{{ $macroplan->title }}</span>
        </div>
        <div class="flex items-center gap-2">
            <x-status-dropdown-inline :options="\App\Models\MacroPlan::$statuses" :current="$macroplan->status"
                :action="route('macroplans.update-status', $macroplan)" />
            <span class="text-xs font-mono" style="color:var(--muted)">{{ $macroplan->periodLabel() }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @if(session('import_warnings') && count(session('import_warnings')) > 0)
        <div class="mb-5 px-4 py-3 text-xs"
             style="background:rgba(238, 121, 25,.06); border:1px solid rgba(238, 121, 25,.25); color:var(--orange)">
            <p class="font-semibold mb-2">Itens do HTML que não foram importados automaticamente — revise e adicione manualmente onde fizer sentido:</p>
            <ul class="space-y-1 list-disc pl-4">
                @foreach(session('import_warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- LAYOUT: SIDEBAR + CONTEÚDO --}}
    <div x-data="{ block: '{{ $currentBlock }}' }" class="flex gap-6">

        {{-- SIDEBAR --}}
        <div class="flex-shrink-0" style="width:220px">
            <div class="card overflow-hidden">
                <div class="px-4 py-3" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Blocos</p>
                </div>

                @foreach(\App\Models\MacroPlan::$blocks as $key => $meta)
                    <button type="button"
                            @click="block = '{{ $key }}'"
                            :class="block === '{{ $key }}' ? 'active' : ''"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left transition-all"
                            style="border:none; border-bottom:1px solid var(--border2); background:none; cursor:pointer"
                            :style="block === '{{ $key }}'
                                ? 'background:rgba(100, 59, 142,.12); color:var(--purple)'
                                : 'color:var(--muted2)'">
                        <span class="text-xs font-mono font-bold opacity-40">{{ $meta['num'] }}</span>
                        <span class="text-xs font-semibold leading-tight">{{ $meta['label'] }}</span>
                        @if($key === 'bloco3')
                            <span class="ml-auto text-xs font-mono" style="color:var(--muted)">
                                {{ $macroplan->projects->count() }}
                            </span>
                        @endif
                    </button>
                @endforeach

                <div style="border-bottom:1px solid var(--border2)"></div>

                {{-- Meta / configurações --}}
                <button type="button"
                        @click="block = 'meta'"
                        :class="block === 'meta' ? 'active' : ''"
                        class="w-full flex items-center gap-3 px-4 py-3 text-left transition-all"
                        style="border:none; background:none; cursor:pointer"
                        :style="block === 'meta'
                            ? 'background:rgba(100, 59, 142,.12); color:var(--purple)'
                            : 'color:var(--muted2)'">
                    <span class="text-xs font-mono font-bold opacity-40">⚙</span>
                    <span class="text-xs font-semibold">Configurações</span>
                </button>
            </div>

            {{-- Reuniões ligadas --}}
            <div class="card mt-3 p-3">
                <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                    Reuniões
                    @if($macroplan->meetings->count() > 0)
                        <span class="ml-1 px-1.5 py-0.5" style="background:var(--s3); border:1px solid var(--border2); color:var(--muted2)">{{ $macroplan->meetings->count() }}</span>
                    @endif
                </p>
                @if($macroplan->meetings->isEmpty())
                    <p class="text-xs py-1" style="color:var(--muted)">Nenhuma reunião ligada ainda.</p>
                @else
                    @foreach($macroplan->meetings as $m)
                        <button type="button" @click="$store.sidePanel.open('{{ route('meetings.preview', $m) }}')"
                                class="w-full flex flex-col gap-1 px-2 py-1.5 mb-1 text-left transition-colors"
                                style="background:var(--s2); border:none; cursor:pointer"
                                onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                            <span class="text-xs font-semibold truncate" style="color:var(--text)">{{ $m->title }}</span>
                            <span class="flex items-center gap-1.5 flex-wrap">
                                <span class="badge badge-{{ $m->statusColor() }}">{{ $m->statusLabel() }}</span>
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $m->scheduled_at->format('d/m/y H:i') }}</span>
                            </span>
                        </button>
                    @endforeach
                @endif
            </div>

            {{-- Anexos --}}
            <div class="card mt-3 p-3">
                <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                    Anexos
                    @if($macroplan->attachments->count() > 0)
                        <span class="ml-1 px-1.5 py-0.5" style="background:var(--s3); border:1px solid var(--border2); color:var(--muted2)">{{ $macroplan->attachments->count() }}</span>
                    @endif
                </p>
                <form method="POST" action="{{ route('macroplans.attachments.store', $macroplan) }}"
                      enctype="multipart/form-data" class="mb-2">
                    @csrf
                    <label class="flex items-center justify-center w-full py-3 cursor-pointer text-center transition-colors"
                           style="border:1px dashed var(--border2); color:var(--muted); font-size:11px">
                        + Anexar arquivo (ex: HTML de origem)
                        <input type="file" name="file" class="hidden" @change="$el.closest('form').submit()">
                    </label>
                </form>
                @foreach($macroplan->attachments as $attachment)
                    <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs" style="background:var(--s2)">
                        <a href="{{ $attachment->url() }}" target="_blank" class="truncate hover:underline" style="color:var(--text)">
                            {{ $attachment->icon() }} {{ $attachment->filename }}
                        </a>
                        <form method="POST" action="{{ route('macroplans.attachments.destroy', [$macroplan, $attachment]) }}"
                              @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) $el.submit()">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">✕</button>
                        </form>
                    </div>
                @endforeach
            </div>

            {{-- Remover --}}
            <div class="mt-3">
                <form method="POST" action="{{ route('macroplans.destroy', $macroplan) }}"
                      @submit.prevent="if (await $store.confirmDialog.ask('Remover este planejamento e todos os projetos?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm w-full">
                        Remover Planejamento
                    </button>
                </form>
            </div>
        </div>

        {{-- CONTEÚDO --}}
        <div class="flex-1 min-w-0">

            {{-- ══ BLOCO 1: VISÃO GERAL E METAS ══ --}}
            <div x-show="block === 'bloco1'" x-cloak>
                @php $b1 = $macroplan->bloco1 ?? []; @endphp
                @php
                    $defaultKpis = [
                        ['label' => 'KPI Principal',     'title' => '', 'desc' => ''],
                        ['label' => 'KPI de Tráfego',    'title' => '', 'desc' => ''],
                        ['label' => 'KPI de Marca',      'title' => '', 'desc' => ''],
                        ['label' => 'KPI de Resultado',  'title' => '', 'desc' => ''],
                    ];
                    $kpisJson = json_encode($b1['kpis'] ?? $defaultKpis);
                @endphp
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 01</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Visão Geral e Metas</h2>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-6">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="bloco1">

                        {{-- Foco Principal --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Foco Principal do Ciclo</label>
                            <textarea name="foco_principal" rows="3"
                                placeholder="Qual é a grande prioridade deste trimestre?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b1['foco_principal'] ?? '' }}</textarea>
                        </div>

                        {{-- Ponto de Partida --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Ponto de Partida</label>
                            <textarea name="contexto_anterior" rows="3"
                                placeholder="Histórico recente relevante que embasa as decisões deste ciclo (ex: resultado da campanha anterior, situação de mercado do cliente)"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b1['contexto_anterior'] ?? '' }}</textarea>
                        </div>

                        {{-- Verba Estruturada --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Verba de Anúncios</label>
                            @php $currentBudget = $macroplan->client->currentAdBudget(); @endphp
                            @if($currentBudget)
                                <p class="text-xs mb-2" style="color:var(--muted)">
                                    Orçamento atual cadastrado no cliente:
                                    <span style="color:var(--text)">R$ {{ number_format($currentBudget->monthly_budget, 2, ',', '.') }}</span>
                                    <span>(desde {{ $currentBudget->start_date->format('d/m/Y') }})</span>
                                </p>
                            @endif
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <p class="text-xs font-mono mb-1.5" style="color:var(--muted)">Total Mensal (R$)</p>
                                    <input type="text" name="verba_total"
                                        value="{{ $b1['verba_total'] ?? ($b1['verba_anuncios'] ?? ($currentBudget->monthly_budget ?? '')) }}"
                                        placeholder="Ex: 2000"
                                        class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                    <p class="text-xs mt-1" style="color:var(--muted)">Se mudar este valor, o histórico de orçamento do cliente é atualizado automaticamente ao salvar.</p>
                                </div>
                                <div>
                                    <p class="text-xs font-mono mb-1.5" style="color:var(--muted)">Meta Ads (%)</p>
                                    <input type="number" name="meta_pct" min="0" max="100"
                                        value="{{ $b1['meta_pct'] ?? '' }}"
                                        placeholder="Ex: 70"
                                        class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                </div>
                                <div>
                                    <p class="text-xs font-mono mb-1.5" style="color:var(--muted)">Google Ads (%)</p>
                                    <input type="number" name="google_pct" min="0" max="100"
                                        value="{{ $b1['google_pct'] ?? '' }}"
                                        placeholder="Ex: 30"
                                        class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                </div>
                            </div>
                            <p class="text-xs font-mono mt-1.5" style="color:var(--muted)">Observação sobre a verba (distribuição, timing)</p>
                            <textarea name="verba_obs" rows="2"
                                placeholder="Ex: Mês 1 = 100% Meta. A partir do Mês 2 = 70/30..."
                                class="w-full px-3 py-2 text-sm focus:outline-none resize-none mt-1"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b1['verba_obs'] ?? '' }}</textarea>
                        </div>

                        {{-- KPIs estruturados --}}
                        <div x-data="{ kpis: {{ $kpisJson }} }">
                            <label class="block text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">KPIs do Período</label>
                            <div class="grid grid-cols-2 gap-3">
                                <template x-for="(kpi, i) in kpis" :key="i">
                                    <div class="px-4 py-3 rounded" style="background:var(--s3); border:1px solid var(--border2)">
                                        <input type="hidden" :name="'kpis['+i+'][label]'" :value="kpi.label">
                                        <p class="text-xs font-bold font-mono uppercase tracking-widest mb-2" style="color:var(--purple)" x-text="kpi.label"></p>
                                        <input type="text" :name="'kpis['+i+'][title]'" x-model="kpi.title"
                                            placeholder="Nome do KPI..."
                                            class="w-full px-3 py-1.5 text-sm focus:outline-none mb-1.5"
                                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"
                                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                        <textarea :name="'kpis['+i+'][desc]'" x-model="kpi.desc" rows="2"
                                            placeholder="Como medir, baseline, meta..."
                                            class="w-full px-3 py-1.5 text-xs focus:outline-none resize-none"
                                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"
                                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'"></textarea>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                                Salvar Bloco 01
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ══ BLOCO 2: CONTEXTO E ESTRATÉGIA ══ --}}
            <div x-show="block === 'bloco2'" x-cloak>
                @php
                    $b2 = $macroplan->bloco2 ?? [];
                    $defaultPilares = [['nome' => '', 'desc' => ''], ['nome' => '', 'desc' => ''], ['nome' => '', 'desc' => '']];
                    $pilaresJson = json_encode($b2['pilares'] ?? (isset($b2['pilares_comunicacao']) ? [] : $defaultPilares));
                @endphp
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 02</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Contexto e Estratégia</h2>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-6">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="bloco2">

                        {{-- Desafio Atual --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">O Desafio Atual</label>
                            <textarea name="desafio_atual" rows="4"
                                placeholder="Qual é o principal problema ou oportunidade que estamos endereçando?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['desafio_atual'] ?? '' }}</textarea>
                        </div>

                        {{-- O Que Muda --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">O Que Muda Neste Ciclo</label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs font-mono mb-1.5" style="color:var(--muted)">Antes (situação atual)</p>
                                    <textarea name="o_que_muda_antes" rows="4"
                                        placeholder="Como está hoje..."
                                        class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['o_que_muda_antes'] ?? '' }}</textarea>
                                </div>
                                <div>
                                    <p class="text-xs font-mono mb-1.5" style="color:var(--muted)">Agora (o que queremos)</p>
                                    <textarea name="o_que_muda_agora" rows="4"
                                        placeholder="O que mudará com este planejamento..."
                                        class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['o_que_muda_agora'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Estratégia --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">A Nossa Estratégia</label>
                            <textarea name="estrategia" rows="4"
                                placeholder="Como a equipe vai agir para resolver o desafio?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['estrategia'] ?? '' }}</textarea>
                        </div>

                        {{-- Migração do campo legado (se existir) --}}
                        @if(!empty($b2['pilares_comunicacao']) && empty($b2['pilares']))
                            <div class="px-4 py-3 text-xs rounded" style="background:rgba(238, 121, 25,.06); border:1px solid rgba(238, 121, 25,.2); color:var(--orange)">
                                Campo antigo "Pilares de Comunicação" ainda em texto livre. Substitua pelos pilares estruturados abaixo e salve para migrar.
                                <p class="mt-1 font-mono" style="color:var(--muted)">{{ $b2['pilares_comunicacao'] }}</p>
                            </div>
                        @endif

                        {{-- Pilares Estruturados --}}
                        <div x-data="{
                            pilares: {{ $pilaresJson }},
                            addPilar() { this.pilares.push({ nome: '', desc: '' }); },
                            removePilar(i) { this.pilares.splice(i, 1); }
                        }">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Pilares de Comunicação</label>
                                <button type="button" @click="addPilar()" class="btn btn-ghost btn-xs">
                                    + Pilar
                                </button>
                            </div>
                            <div class="space-y-3">
                                <template x-for="(pilar, i) in pilares" :key="i">
                                    <div class="px-4 py-3 rounded relative" style="background:var(--s3); border:1px solid var(--border2)">
                                        <button type="button" @click="removePilar(i)" class="btn btn-danger btn-xs absolute top-2 right-2">✕</button>
                                        <input type="text" :name="'pilares['+i+'][nome]'" x-model="pilar.nome"
                                            placeholder="Nome do pilar (ex: Autoridade e Prova Social)"
                                            class="w-full px-3 py-1.5 text-sm font-bold focus:outline-none mb-2"
                                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"
                                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                        <textarea :name="'pilares['+i+'][desc]'" x-model="pilar.desc" rows="2"
                                            placeholder="Descrição do pilar, mensagem-chave..."
                                            class="w-full px-3 py-1.5 text-xs focus:outline-none resize-none"
                                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"
                                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'"></textarea>
                                    </div>
                                </template>
                                <p x-show="pilares.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">
                                    Nenhum pilar adicionado. Clique em "+ Pilar" para começar.
                                </p>
                            </div>
                        </div>

                        {{-- Linha do Tempo --}}
                        @php $linhaTempoJson = json_encode($b2['linha_tempo'] ?? []); @endphp
                        <div x-data="{
                            meses: {{ $linhaTempoJson }},
                            addMes() { this.meses.push({ mes: '', itens: [] }); },
                            removeMes(i) { this.meses.splice(i, 1); },
                            addItem(i) { this.meses[i].itens.push({ texto: '', tipo: 'geral' }); },
                            removeItem(i, j) { this.meses[i].itens.splice(j, 1); }
                        }">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Linha do Tempo</label>
                                <button type="button" @click="addMes()" class="btn btn-ghost btn-xs">
                                    + Mês
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <template x-for="(mesItem, i) in meses" :key="i">
                                    <div class="px-4 py-3 rounded relative" style="background:var(--s3); border:1px solid var(--border2)">
                                        <button type="button" @click="removeMes(i)" class="btn btn-danger btn-xs absolute top-2 right-2">✕</button>
                                        <input type="text" :name="'linha_tempo['+i+'][mes]'" x-model="mesItem.mes"
                                            placeholder="Ex: Julho"
                                            class="w-full px-3 py-1.5 text-sm font-bold focus:outline-none mb-2"
                                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"
                                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                        <template x-for="(item, j) in mesItem.itens" :key="j">
                                            <div class="flex items-center gap-2 mb-2">
                                                <select :name="'linha_tempo['+i+'][itens]['+j+'][tipo]'" x-model="item.tipo"
                                                    class="text-xs px-2 py-1.5 focus:outline-none"
                                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                                    <option value="geral">Geral</option>
                                                    <option value="projeto">Projeto</option>
                                                    <option value="campanha">Campanha</option>
                                                </select>
                                                <input type="text" :name="'linha_tempo['+i+'][itens]['+j+'][texto]'" x-model="item.texto"
                                                    placeholder="Ex: Lançamento da campanha X"
                                                    class="flex-1 px-3 py-1.5 text-xs focus:outline-none"
                                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"
                                                    onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                                                <button type="button" @click="removeItem(i, j)" class="btn btn-danger btn-xs flex-shrink-0">✕</button>
                                            </div>
                                        </template>
                                        <button type="button" @click="addItem(i)" class="btn btn-ghost btn-xs">
                                            + Item
                                        </button>
                                    </div>
                                </template>
                                <p x-show="meses.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">
                                    Nenhum mês adicionado. Clique em "+ Mês" para começar.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                                Salvar Bloco 02
                            </button>
                        </div>
                    </form>

                    {{-- Resumo do Período (read-only, derivado dos projetos/campanhas já cadastrados) --}}
                    @if($macroplan->projects->isNotEmpty())
                        <div class="px-5 pb-5">
                            <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Resumo do Período</p>
                            <div class="flex flex-col gap-1" style="border-top:1px solid var(--border2)">
                                @foreach($macroplan->projects as $proj)
                                    <div class="flex items-start gap-3 py-2.5" style="border-bottom:1px solid var(--border2)">
                                        <span class="badge badge-{{ $proj->typeColor() }} flex-shrink-0">{{ $proj->typeLabel() }}</span>
                                        <span class="text-xs font-bold flex-shrink-0" style="color:var(--text); min-width:180px">{{ $proj->title }}</span>
                                        <span class="text-xs" style="color:var(--muted)">{{ \Illuminate\Support\Str::limit($proj->objective, 100) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ══ BLOCO 3: PROJETOS ══ --}}
            <div x-show="block === 'bloco3'" x-cloak x-data="{ editId: null, addOpen: false }">
                <div class="card">
                    <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                        <div>
                            <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 03</p>
                            <h2 class="text-base font-bold" style="color:var(--text)">Arquitetura de Projetos</h2>
                        </div>
                        <button @click="addOpen = !addOpen" class="btn btn-primary btn-sm">
                            + Novo Projeto
                        </button>
                    </div>

                    {{-- Formulário adicionar projeto --}}
                    <div x-show="addOpen" x-cloak style="border-bottom:1px solid var(--border2)">
                        <form method="POST" action="{{ route('macroplans.projects.store', $macroplan) }}"
                              class="px-5 py-5 space-y-4">
                            @csrf

                            {{-- Tipo + Nome --}}
                            <div class="grid grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tipo</label>
                                    <select name="type"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                        @foreach(\App\Models\Project::$types as $key => $t)
                                            <option value="{{ $key }}">{{ $t['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                        Nome <span style="color:var(--orange)">*</span>
                                    </label>
                                    <input type="text" name="title" required
                                        placeholder="Ex: Captação B2B — Landing Page"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                </div>
                            </div>

                            {{-- Objetivo --}}
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Objetivo</label>
                                <textarea name="objective" rows="2"
                                    placeholder="O que este projeto precisa alcançar?"
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                            </div>

                            {{-- Datas --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Início</label>
                                    <input type="date" name="start_date"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Término</label>
                                    <input type="date" name="end_date"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                </div>
                            </div>

                            {{-- Disciplinas --}}
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Disciplinas Envolvidas</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(\App\Models\Project::$disciplines as $key => $label)
                                        <label class="flex items-center gap-1.5 text-xs cursor-pointer px-3 py-1.5"
                                               style="border:1px solid var(--border2); color:var(--muted2)">
                                            <input type="checkbox" name="disciplines[]" value="{{ $key }}"
                                                style="accent-color:var(--purple)">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Adicionar Projeto
                                </button>
                                <button type="button" @click="addOpen = false" class="btn btn-ghost btn-sm">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Lista de projetos --}}
                    @if($macroplan->projects->isEmpty())
                        <div class="px-5 py-10 text-center" style="color:var(--muted)">
                            <p class="text-sm">Nenhum projeto criado ainda.</p>
                        </div>
                    @else
                        @foreach($macroplan->projects as $project)
                            <div style="border-bottom:1px solid var(--border2)" x-data="{}">

                                {{-- View do projeto --}}
                                @php
                                    $pct   = $project->progressPercent();
                                    $total = $project->tasks->where('status', '!=', 'cancelado')->count();
                                    $done  = $project->tasks->where('status', 'concluido')->count();
                                    $isOverdueDeadline = $project->end_date
                                        && $project->end_date->isPast()
                                        && !in_array($project->status, ['concluido', 'cancelado']);
                                    $typeColor = $project->type === 'campanha' ? 'var(--green)' : 'var(--purple)';
                                @endphp
                                <div x-show="editId !== '{{ $project->id }}'" class="px-5 py-4">

                                    {{-- Linha superior: tipo + número + título + status + ações --}}
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="text-xs font-mono font-bold flex-shrink-0" style="color:var(--muted)">#{{ $loop->iteration }}</span>
                                            <span class="text-xs font-mono px-2 py-0.5 rounded flex-shrink-0"
                                                  style="background:color-mix(in srgb, {{ $typeColor }} 12%, transparent); color:{{ $typeColor }}">
                                                {{ $project->typeLabel() }}
                                            </span>
                                            <a href="{{ route('macroplans.projects.show', [$macroplan, $project]) }}"
                                               class="font-bold text-sm truncate transition-colors"
                                               style="color:var(--text)"
                                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--text)'">
                                                {{ $project->title }}
                                            </a>
                                            <span class="badge badge-{{ $project->statusColor() }} flex-shrink-0">{{ $project->statusLabel() }}</span>
                                            @if($isOverdueDeadline)
                                                <span class="text-xs font-mono px-2 py-0.5 rounded-full flex-shrink-0"
                                                      style="background:rgba(220,38,38,.1); color:var(--red)">prazo vencido</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <a href="{{ route('macroplans.projects.show', [$macroplan, $project]) }}" class="btn btn-ghost btn-xs">Ver →</a>
                                            <button type="button" @click="editId = '{{ $project->id }}'" class="btn btn-ghost btn-xs">Editar</button>
                                            <form method="POST" action="{{ route('macroplans.projects.destroy', [$macroplan, $project]) }}"
                                                  @submit.prevent="if (await $store.confirmDialog.ask('Excluir o projeto {{ addslashes($project->title) }} e todas as suas tarefas vinculadas? Essa ação não pode ser desfeita.')) $el.submit()">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs">Remover</button>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- Objetivo --}}
                                    @if($project->objective)
                                        <p class="text-xs mb-2.5" style="color:var(--muted2)">{{ $project->objective }}</p>
                                    @endif

                                    {{-- Linha de metadados: datas · disciplinas · progresso --}}
                                    <div class="flex items-center gap-4 flex-wrap">
                                        @if($project->start_date || $project->end_date)
                                            <span class="text-xs font-mono flex items-center gap-1" style="color:var(--muted)">
                                                <x-icon name="calendar" size="11" />
                                                @if($project->start_date){{ $project->start_date->format('d/m/y') }}@endif
                                                @if($project->start_date && $project->end_date)→@endif
                                                @if($project->end_date){{ $project->end_date->format('d/m/y') }}@endif
                                            </span>
                                        @endif
                                        @if($project->disciplines)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($project->disciplineLabels() as $d)
                                                    <span class="badge">{{ $d }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($total > 0)
                                            <div class="flex items-center gap-2 ml-auto">
                                                <div class="h-1.5 rounded-full overflow-hidden" style="background:var(--border2); width:100px">
                                                    <div class="h-1.5 rounded-full"
                                                         style="width:{{ $pct }}%; background:{{ $pct >= 100 ? 'var(--green)' : 'var(--grad)' }}"></div>
                                                </div>
                                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $done }}/{{ $total }} · {{ $pct }}%</span>
                                            </div>
                                        @else
                                            <span class="text-xs font-mono ml-auto" style="color:var(--muted)">Sem tarefas</span>
                                        @endif
                                    </div>

                                </div>

                                {{-- Formulário de edição inline --}}
                                <template x-if="editId === '{{ $project->id }}'">
                                    @include('macroplans._project-edit-form', [
                                        'project' => $project,
                                        'formAction' => route('macroplans.projects.update', [$macroplan, $project]),
                                        'cancelAction' => 'editId = null',
                                    ])
                                </template>

                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- ══ BLOCO 4: TAREFAS ISOLADAS E ROTINA ══ --}}
            <div x-show="block === 'bloco4'" x-cloak>
                @php $b4 = $macroplan->bloco4 ?? []; @endphp
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 04</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Tarefas Isoladas e Rotina</h2>
                        <p class="text-xs mt-1" style="color:var(--muted)">
                            Demandas contínuas — trabalho de manutenção e otimização realizados constantemente durante o ciclo.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="bloco4">

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Tráfego Contínuo
                            </label>
                            <textarea name="trafego_continuo" rows="4"
                                placeholder="Acompanhamento, otimização de campanhas, gestão de budget..."
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b4['trafego_continuo'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Social Media Contínuo
                            </label>
                            <textarea name="social_continuo" rows="4"
                                placeholder="Publicações, stories, engajamento, calendário editorial..."
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b4['social_continuo'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Outras Demandas Recorrentes
                            </label>
                            <textarea name="outras_demandas" rows="4"
                                placeholder="Relatórios, calls de alinhamento, revisões..."
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b4['outras_demandas'] ?? '' }}</textarea>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                                Salvar Bloco 04
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ══ BLOCO 5: INFRAESTRUTURA E ACESSOS ══ --}}
            <div x-show="block === 'bloco5'" x-cloak>
                @php $b5 = $macroplan->bloco5 ?? []; @endphp
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 05</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Checklist de Infraestrutura e Acessos</h2>
                        <p class="text-xs mt-1" style="color:var(--muted)">
                            Validações e solicitações que o Atendimento deve confirmar antes da execução.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="bloco5">

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Acessos e Integrações Necessárias
                            </label>
                            <textarea name="acessos" rows="5"
                                placeholder="BM, pixel, GA4, GTM, contas de anúncios, drives, credenciais..."
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b5['acessos'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Materiais e Insumos Necessários
                            </label>
                            <textarea name="materiais" rows="4"
                                placeholder="Fotos, vídeos, logos, catálogos, textos do cliente..."
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b5['materiais'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Observações e Pendências
                            </label>
                            <textarea name="pendencias" rows="4"
                                placeholder="Itens pendentes, dependências externas, alertas para a equipe..."
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b5['pendencias'] ?? '' }}</textarea>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                                Salvar Bloco 05
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ══ CONFIGURAÇÕES / META ══ --}}
            <div x-show="block === 'meta'" x-cloak>
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Configurações</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Metadados do Planejamento</h2>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="meta">

                        {{-- Título + Versão --}}
                        <div class="grid grid-cols-4 gap-3">
                            <div class="col-span-3">
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                    Título <span style="color:var(--orange)">*</span>
                                </label>
                                <input type="text" name="title" value="{{ $macroplan->title }}" required
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Versão</label>
                                <input type="text" name="version" value="{{ $macroplan->version ?? '1.0' }}"
                                    placeholder="1.0"
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                        </div>

                        {{-- Período --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Início do Ciclo</label>
                                <input type="date" name="period_start" value="{{ $macroplan->period_start->format('Y-m-d') }}" required
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Fim do Ciclo</label>
                                <input type="date" name="period_end" value="{{ $macroplan->period_end->format('Y-m-d') }}" required
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                        </div>

                        {{-- Responsável + Status --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Responsável</label>
                                <select name="responsible_id"
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">— sem responsável —</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ $macroplan->responsible_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Status</label>
                                <select name="status" required
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    @foreach(\App\Models\MacroPlan::$statuses as $key => $s)
                                        <option value="{{ $key }}" {{ $macroplan->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Disciplinas Ativas --}}
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Disciplinas Ativas neste Ciclo</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach(\App\Models\MacroPlan::$disciplineOptions as $key => $label)
                                    <label class="flex items-center gap-1.5 text-xs cursor-pointer px-3 py-1.5"
                                           style="border:1px solid var(--border2); color:var(--muted2)">
                                        <input type="checkbox" name="disciplines[]" value="{{ $key }}"
                                            {{ in_array($key, $macroplan->disciplines ?? []) ? 'checked' : '' }}
                                            style="accent-color:var(--purple)">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                                Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>{{-- /conteúdo --}}
    </div>{{-- /layout --}}

</x-app-layout>
