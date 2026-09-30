{{-- Mapa de Projetos — visão do gestor de projetos. Leitura de cima pra baixo, na ordem
     da rotina dele: o que está vindo das reuniões de Macro (radar) → o que pede atenção
     (alertas clicáveis) → cada projeto/campanha no tempo, com onde o trabalho está.
     Clicar numa linha abre o painel lateral com próximas entregas, travas e tarefas
     paradas, e as ações rápidas (Concluir / Stand By). Lógica em ProjectMapService. --}}
<x-app-layout>
    <x-slot name="header">Mapa de Projetos</x-slot>

    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
        <p class="text-sm min-w-0 flex-1" style="color:var(--muted)">
            Todos os projetos e campanhas abertos: se estão no prazo, onde está o trabalho e quem parou de se mexer.
        </p>
        <a href="{{ route('project-map.index', $incluirInativos ? [] : ['inativos' => 1]) }}"
           class="text-xs px-3 py-1.5 rounded-lg flex-shrink-0"
           style="border:1px solid var(--border2); color:{{ $incluirInativos ? 'var(--purple)' : 'var(--muted)' }}">
            {{ $incluirInativos ? '⊙ Ocultar clientes inativos' : '○ Mostrar clientes inativos' }}
        </a>
    </div>

    {{-- ── 1. Radar de Macroplanejamento ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5 min-w-0">
        <div class="card card-body min-w-0">
            <div class="flex items-center justify-between mb-1">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Macro em Revisão Interna</p>
                <span class="text-lg font-black" style="color:{{ count($radar['em_revisao']) ? 'var(--purple)' : 'var(--muted2)' }}">{{ count($radar['em_revisao']) }}</span>
            </div>
            <p class="text-xs mb-3" style="color:var(--muted2)">Reuniões de Macro e Kick-off com a ATA sendo revisada. Depois da revisão, vão pro Despacho.</p>
            @forelse($radar['em_revisao'] as $m)
                <a href="{{ $m['url'] }}" class="flex items-center justify-between gap-3 py-2" style="border-top:1px solid var(--border)">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold truncate" style="color:var(--text)">{{ $m['client'] }}</span>
                        <span class="block text-xs truncate" style="color:var(--muted)">{{ $m['tipo'] }}{{ $m['quando'] ? ' · reunião em ' . $m['quando'] : '' }}</span>
                    </span>
                    @if(($m['dias'] ?? 0) > 0)
                        <span class="text-xs flex-shrink-0" style="color:{{ $m['dias'] >= 5 ? 'var(--orange)' : 'var(--muted)' }}">há {{ $m['dias'] }} dia(s)</span>
                    @endif
                </a>
            @empty
                <p class="text-xs py-2" style="color:var(--muted2); border-top:1px solid var(--border)">Nenhuma ATA de Macro aguardando revisão.</p>
            @endforelse
        </div>

        <div class="card card-body min-w-0">
            <div class="flex items-center justify-between mb-1">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Macro em Despacho</p>
                <span class="text-lg font-black" style="color:{{ count($radar['em_despacho']) ? 'var(--orange)' : 'var(--muted2)' }}">{{ count($radar['em_despacho']) }}</span>
            </div>
            <p class="text-xs mb-3" style="color:var(--muted2)">ATA e planejamento prontos pra distribuir. Lance as tarefas de cada projeto/campanha e marque a reunião como Finalizada.</p>
            @forelse($radar['em_despacho'] as $m)
                @php $falta = $m['total_itens'] - $m['lancados']; @endphp
                <div x-data="{ aberto: false }" class="py-2" style="border-top:1px solid var(--border)">
                    <button type="button" @click="aberto = !aberto" class="w-full flex items-center justify-between gap-3 text-left">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold truncate" style="color:var(--text)">{{ $m['client'] }}</span>
                            <span class="block text-xs truncate" style="color:var(--muted)">
                                @if(! $m['planejamento'])
                                    <span style="color:var(--orange)">sem planejamento vinculado</span>
                                @elseif($m['total_itens'] === 0)
                                    planejamento sem projetos
                                @else
                                    {{ $m['lancados'] }} de {{ $m['total_itens'] }} projeto(s)/campanha(s) com tarefas
                                @endif
                                {{ ($m['dias'] ?? 0) > 0 ? ' · reunião há ' . $m['dias'] . ' dia(s)' : '' }}
                            </span>
                        </span>
                        <span class="flex items-center gap-2 flex-shrink-0">
                            @if($m['total_itens'] > 0)
                                <span class="text-[11px] font-semibold px-1.5 rounded" style="{{ $falta ? 'background:rgba(238,121,25,.12); color:var(--orange)' : 'background:rgba(34,197,94,.12); color:var(--green)' }}">
                                    {{ $falta ? "faltam {$falta}" : 'tudo lançado' }}
                                </span>
                            @endif
                            <x-icon name="chevron-right" size="14" class="transition-transform" x-bind:class="aberto ? 'rotate-90' : ''" style="color:var(--muted)" />
                        </span>
                    </button>
                    <div x-show="aberto" x-cloak class="mt-2 space-y-1 pl-2">
                        @foreach($m['itens'] as $item)
                            <a href="{{ $item['url'] }}" class="flex items-center gap-2 text-xs" style="color:var(--muted)">
                                <x-icon name="{{ $item['type'] === 'campanha' ? 'megaphone' : 'folder-kanban' }}" size="12" class="flex-shrink-0" />
                                <span class="truncate flex-1" style="color:var(--text)">{{ $item['title'] }}</span>
                                <span class="flex-shrink-0" style="color:{{ $item['tarefas'] ? 'var(--green)' : 'var(--orange)' }}">{{ $item['tarefas'] ? $item['tarefas'] . ' tarefa(s)' : 'sem tarefa' }}</span>
                            </a>
                        @endforeach
                        <div class="flex items-center gap-3 mt-1">
                            @if($m['plano_url'])
                                <a href="{{ $m['plano_url'] }}" class="text-xs font-semibold" style="color:var(--purple)">Abrir planejamento →</a>
                            @endif
                            <a href="{{ $m['url'] }}" class="text-xs font-semibold" style="color:var(--muted)">Abrir reunião →</a>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-xs py-2" style="color:var(--muted2); border-top:1px solid var(--border)">Nenhuma reunião de Macro aguardando despacho.</p>
            @endforelse
        </div>
    </div>

    <script>
        function mapaProjetos() {
            return {
                projetos: @json($projetos),
                janela: @json($janela),
                filtro: 'todos',
                tipo: '',
                cliente: '',
                busca: '',
                aberto: null,
                salvando: false,

                cores: { atrasado: 'var(--red)', risco: 'var(--orange)', sem_data: 'var(--muted)', ok: 'var(--green)', pausado: 'var(--muted2)' },
                rotulos: { atrasado: 'Atrasado', risco: 'Em risco', sem_data: 'Sem data', ok: 'No prazo', pausado: 'Stand By' },
                etapas: [
                    ['fora', 'Fora da sprint', 'rgba(105,115,134,.35)'],
                    ['sprint', 'Na sprint, não iniciada', 'var(--slate)'],
                    ['producao', 'Em produção', 'var(--purple)'],
                    ['cliente', 'Com o cliente', 'var(--orange)'],
                    ['despacho', 'Despacho', 'var(--blue)'],
                    ['concluido', 'Concluída', 'var(--green)'],
                ],

                get clientes() {
                    return [...new Set(this.projetos.map(p => p.client_name))].sort((a, b) => a.localeCompare(b));
                },
                passa(p) {
                    if (this.tipo && p.type !== this.tipo) return false;
                    if (this.cliente && p.client_name !== this.cliente) return false;
                    if (this.busca && !(p.title + ' ' + p.client_name).toLowerCase().includes(this.busca.toLowerCase())) return false;
                    switch (this.filtro) {
                        case 'estagnado': return p.estagnado;
                        case 'sem_tarefa': return p.sem_tarefa_aberta;
                        case 'todos': return true;
                        default: return p.farol === this.filtro;
                    }
                },
                get grupos() {
                    const grupos = [];
                    for (const p of this.projetos) {
                        if (!this.passa(p)) continue;
                        let g = grupos.find(g => g.nome === p.client_name);
                        if (!g) grupos.push(g = { nome: p.client_name, itens: [] });
                        g.itens.push(p);
                    }
                    return grupos;
                },
                conta(chave) {
                    if (chave === 'todos') return this.projetos.length;
                    if (chave === 'estagnado') return this.projetos.filter(p => p.estagnado).length;
                    if (chave === 'sem_tarefa') return this.projetos.filter(p => p.sem_tarefa_aberta).length;
                    return this.projetos.filter(p => p.farol === chave).length;
                },

                // ── Linha do tempo ──
                pct(data) {
                    const ini = new Date(this.janela.inicio), fim = new Date(this.janela.fim);
                    return Math.min(100, Math.max(0, (new Date(data) - ini) / (fim - ini) * 100));
                },
                dentro(data) { return data && data >= this.janela.inicio && data <= this.janela.fim; },
                barra(p) {
                    const inicio = [p.start, p.pieces].filter(Boolean).sort()[0] || null;
                    const fim = p.end || p.start || p.pieces;
                    if (!inicio && !fim) return null;
                    const a = inicio || fim, b = fim || inicio;
                    if (b < this.janela.inicio) return { antes: true };
                    if (a > this.janela.fim) return { depois: true };
                    const left = this.pct(a), right = this.pct(b);
                    return { left, width: Math.max(0.8, right - left), corteEsq: a < this.janela.inicio, corteDir: b > this.janela.fim };
                },

                // ── Barra de etapas ──
                segmentos(p) {
                    return this.etapas
                        .map(([k, label, cor]) => ({ k, label, cor, n: p.etapas[k], w: p.total ? p.etapas[k] / p.total * 100 : 0 }))
                        .filter(s => s.n > 0);
                },

                // ── Ações rápidas (reaproveita projects.quickUpdate) ──
                async mudarStatus(p, status) {
                    const msg = {
                        concluido: `Marcar "${p.title}" como Concluído? Ele sai deste mapa.`,
                        stand_by: `Colocar "${p.title}" em Stand By?`,
                        em_execucao: `Retomar "${p.title}" (voltar para Em Execução)?`,
                    }[status];
                    if (!(await Alpine.store('confirmDialog').ask(msg))) return;
                    this.salvando = true;
                    try {
                        const r = await fetch(p.quick_url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ status }),
                        });
                        if (!r.ok) throw new Error();
                        const d = await r.json();
                        if (status === 'concluido') {
                            this.projetos = this.projetos.filter(x => x.id !== p.id);
                            this.aberto = null;
                        } else {
                            p.status = d.status;
                            p.status_label = d.status_label;
                            if (status === 'stand_by') {
                                p.farol = 'pausado'; p.motivo = 'Em Stand By'; p.estagnado = false;
                            } else {
                                // O farol real depende das datas e tarefas — recarregar é o jeito honesto.
                                window.location.reload();
                            }
                        }
                    } catch (e) {
                        alert('Não foi possível salvar. Tente de novo.');
                    } finally {
                        this.salvando = false;
                    }
                },
            };
        }
    </script>

    <div x-data="mapaProjetos()">
        {{-- ── 2. Alertas (clicáveis — filtram a tela) ── --}}
        @php
            $tiles = [
                ['todos',      'Todos',             'projetos e campanhas abertos',                         'var(--text)'],
                ['atrasado',   'Atrasados',         'prazo vencido',                                         'var(--red)'],
                ['risco',      'Em risco',          'tempo andando mais rápido que o trabalho',              'var(--orange)'],
                ['estagnado',  'Estagnados',        "nenhuma tarefa se mexeu em {$limiteProjeto} dias",      'var(--cyan)'],
                ['sem_tarefa', 'Sem tarefa aberta', 'concluir, pausar ou lançar tarefas?',                   'var(--yellow)'],
                ['sem_data',   'Sem data',          'ninguém sabe quando termina / vai ao ar',               'var(--muted)'],
                ['ok',         'No prazo',          'andando bem',                                           'var(--green)'],
                ['pausado',    'Stand By',          'pausados de propósito',                                 'var(--muted2)'],
            ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2 mb-4">
            @foreach($tiles as [$chave, $label, $ajuda, $cor])
                <button type="button" @click="filtro = filtro === '{{ $chave }}' ? 'todos' : '{{ $chave }}'"
                        class="card text-left px-3 py-2.5 transition min-w-0"
                        :style="filtro === '{{ $chave }}' ? 'outline:2px solid {{ $cor }}; outline-offset:-2px' : ''">
                    <p class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--muted)">{{ $label }}</p>
                    <p class="text-2xl font-black" style="color:{{ $cor }}" x-text="conta('{{ $chave }}')"></p>
                    <p class="text-[11px] leading-tight mt-0.5" style="color:var(--muted2)">{{ $ajuda }}</p>
                </button>
            @endforeach
        </div>

        {{-- Filtros de recorte --}}
        <div class="flex items-center gap-2 flex-wrap mb-3">
            <div class="flex rounded-lg overflow-hidden" style="border:1px solid var(--border2)">
                @foreach(['' => 'Tudo', 'projeto' => 'Projetos', 'campanha' => 'Campanhas'] as $valor => $label)
                    <button type="button" @click="tipo = '{{ $valor }}'" class="text-xs px-3 py-1.5"
                            :style="tipo === '{{ $valor }}' ? 'background:var(--purple); color:#fff' : 'color:var(--muted)'">{{ $label }}</button>
                @endforeach
            </div>
            <select x-model="cliente" class="text-xs px-3 py-1.5 rounded-lg"
                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos os clientes</option>
                <template x-for="c in clientes" :key="c"><option :value="c" x-text="c"></option></template>
            </select>
            <input type="search" x-model.debounce.200ms="busca" placeholder="Buscar projeto…"
                   class="text-xs px-3 py-1.5 rounded-lg" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); min-width:180px">
            <span class="text-xs ml-auto hidden md:flex items-center gap-3 flex-wrap" style="color:var(--muted2)">
                <template x-for="[k, label, cor] in etapas" :key="k">
                    <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-sm" :style="'background:' + cor"></span><span x-text="label"></span></span>
                </template>
                <span>📦 peças · 🚀 no ar</span>
            </span>
        </div>

        {{-- ── 3. Linha do tempo por cliente ── --}}
        <div class="card overflow-hidden">
            {{-- Régua de semanas --}}
            <div class="hidden md:flex" style="border-bottom:1px solid var(--border); background:var(--s2)">
                <div class="flex-shrink-0 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider" style="width:42%; color:var(--muted)">Projeto / Campanha</div>
                <div class="relative flex-1" style="height:32px">
                    <template x-for="s in janela.semanas.filter(s => pct(s.data) < 96)" :key="s.data">
                        <span class="absolute top-2 text-[10px] font-mono -translate-x-1/2" :style="'left:' + pct(s.data) + '%; color:var(--muted2)'" x-text="s.label"></span>
                    </template>
                    <span class="absolute bottom-0 text-[10px] font-bold -translate-x-1/2 px-1 rounded" :style="'left:' + pct(janela.hoje) + '%; background:var(--purple); color:#fff'">hoje</span>
                </div>
            </div>

            <template x-if="grupos.length === 0">
                <p class="text-sm px-4 py-8 text-center" style="color:var(--muted2)">Nenhum projeto neste filtro.</p>
            </template>

            <template x-for="g in grupos" :key="g.nome">
                <div>
                    <div class="px-4 py-1.5 text-xs font-bold uppercase tracking-wider" style="background:var(--s1); color:var(--muted); border-bottom:1px solid var(--border)">
                        <span x-text="g.nome"></span> <span class="font-normal" style="color:var(--muted2)" x-text="'· ' + g.itens.length"></span>
                    </div>
                    <template x-for="p in g.itens" :key="p.id">
                        <div class="flex cursor-pointer hover:brightness-110" style="border-bottom:1px solid var(--border)"
                             :style="aberto && aberto.id === p.id ? 'background:rgba(100,59,142,.08)' : ''"
                             @click="aberto = p">
                            {{-- Coluna de informação --}}
                            <div class="flex-shrink-0 w-full md:w-[42%] px-4 py-2.5 min-w-0">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="'background:' + cores[p.farol]" :title="rotulos[p.farol]"></span>
                                    <span class="text-[10px] font-semibold uppercase px-1.5 rounded flex-shrink-0"
                                          :style="p.type === 'campanha' ? 'background:rgba(34,197,94,.12); color:var(--green)' : 'background:rgba(100,59,142,.12); color:var(--purple)'"
                                          x-text="p.type === 'campanha' ? 'Campanha' : 'Projeto'"></span>
                                    <span class="text-sm font-semibold truncate" style="color:var(--text)" x-text="p.title"></span>
                                </div>
                                <p class="text-xs mt-0.5 truncate" :style="'color:' + (p.farol === 'ok' ? 'var(--muted)' : cores[p.farol])" x-text="p.motivo"></p>
                                <p class="text-[11px] truncate" style="color:var(--muted2)" x-text="p.datas_texto.join(' · ')"></p>

                                <div class="flex items-center gap-2 mt-1.5">
                                    <div class="flex-1 flex h-1.5 rounded-full overflow-hidden" style="background:var(--s3); max-width:220px">
                                        <template x-for="s in segmentos(p)" :key="s.k">
                                            <div :style="'width:' + s.w + '%; background:' + s.cor" :title="s.label + ': ' + s.n"></div>
                                        </template>
                                    </div>
                                    <span class="text-[11px] font-mono flex-shrink-0" style="color:var(--muted2)" x-text="p.concluidas + '/' + p.total"></span>
                                </div>

                                <div class="flex items-center gap-1.5 flex-wrap mt-1.5" x-show="p.estagnado || p.sem_tarefa_aberta || p.atrasadas || p.travadas.length">
                                    <span x-show="p.estagnado" class="text-[11px] px-1.5 rounded" style="background:rgba(6,182,212,.12); color:var(--cyan)" x-text="'🧊 parado há ' + p.dias_parado + ' dias'"></span>
                                    <span x-show="p.atrasadas" class="text-[11px] px-1.5 rounded" style="background:rgba(239,68,68,.12); color:var(--red)" x-text="p.atrasadas + ' tarefa(s) vencida(s)'"></span>
                                    <span x-show="p.travadas.length" class="text-[11px] px-1.5 rounded" style="background:rgba(238,121,25,.12); color:var(--orange)" x-text="p.travadas.length + ' travada(s)'"></span>
                                    <template x-if="p.sem_tarefa_aberta">
                                        <span class="flex items-center gap-1.5">
                                            <span class="text-[11px] px-1.5 rounded" style="background:rgba(234,179,8,.14); color:var(--yellow)" x-text="p.nunca_teve_tarefa ? 'nenhuma tarefa lançada' : 'todas as tarefas concluídas'"></span>
                                            <button type="button" @click.stop="mudarStatus(p, 'concluido')" :disabled="salvando" class="text-[11px] px-1.5 rounded" style="border:1px solid var(--green); color:var(--green)">Concluir</button>
                                            <button type="button" @click.stop="mudarStatus(p, 'stand_by')" :disabled="salvando" x-show="p.status !== 'stand_by'" class="text-[11px] px-1.5 rounded" style="border:1px solid var(--border2); color:var(--muted)">Stand By</button>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            {{-- Coluna do tempo --}}
                            <div class="hidden md:block relative flex-1" style="border-left:1px solid var(--border)">
                                <template x-for="s in janela.semanas.filter(s => pct(s.data) < 96)" :key="s.data">
                                    <div class="absolute top-0 bottom-0" :style="'left:' + pct(s.data) + '%; border-left:1px dashed var(--border)'"></div>
                                </template>
                                <div class="absolute top-0 bottom-0" :style="'left:' + pct(janela.hoje) + '%; border-left:2px solid var(--purple); opacity:.6'"></div>

                                <template x-if="barra(p) && !barra(p).antes && !barra(p).depois">
                                    <div class="absolute top-1/2 -translate-y-1/2 h-3 rounded"
                                         :style="'left:' + barra(p).left + '%; width:' + barra(p).width + '%; background:' + cores[p.farol] + '; opacity:.75;'
                                             + (barra(p).corteEsq ? 'border-top-left-radius:0;border-bottom-left-radius:0;' : '')
                                             + (barra(p).corteDir ? 'border-top-right-radius:0;border-bottom-right-radius:0;' : '')"></div>
                                </template>
                                <template x-if="barra(p) && barra(p).antes">
                                    <span class="absolute top-1/2 -translate-y-1/2 left-1 text-[10px]" :style="'color:' + cores[p.farol]">◀ terminou antes</span>
                                </template>
                                <template x-if="barra(p) && barra(p).depois">
                                    <span class="absolute top-1/2 -translate-y-1/2 right-1 text-[10px]" style="color:var(--muted)">começa depois ▶</span>
                                </template>
                                <template x-if="!barra(p)">
                                    <span class="absolute top-1/2 -translate-y-1/2 left-2 text-[10px]" style="color:var(--muted2)">sem datas</span>
                                </template>

                                <template x-if="p.type === 'campanha' && dentro(p.pieces)">
                                    <span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 text-sm" :style="'left:' + pct(p.pieces) + '%'" title="Entrega das peças">📦</span>
                                </template>
                                <template x-if="p.type === 'campanha' && dentro(p.start)">
                                    <span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 text-sm" :style="'left:' + pct(p.start) + '%'" title="Vai ao ar">🚀</span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <p class="text-[11px] mt-2" style="color:var(--muted2)">
            Estagnação: tarefa sem nenhuma movimentação (status, comentário, anexo…) há {{ $limiteTarefa }} dias úteis;
            projeto sem movimentação em nenhuma tarefa há {{ $limiteProjeto }} dias. Tarefa com o cliente ou ainda fora da sprint não conta.
        </p>

        {{-- ── 4. Painel lateral ── --}}
        <div x-show="aberto" x-cloak class="fixed inset-0 z-40" style="background:rgba(0,0,0,.35)" @click="aberto = null" @keydown.escape.window="aberto = null"></div>
        <aside x-show="aberto" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               class="fixed top-0 right-0 bottom-0 z-50 w-full sm:w-[440px] overflow-y-auto" style="background:var(--s1); border-left:1px solid var(--border2)">
            <template x-if="aberto">
                <div class="p-5 space-y-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs" style="color:var(--muted)" x-text="aberto.client_name + (aberto.macroplan_title ? ' · ' + aberto.macroplan_title : '')"></p>
                            <h2 class="text-lg font-bold" style="color:var(--text)" x-text="aberto.title"></h2>
                            <p class="text-xs mt-1 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :style="'background:' + cores[aberto.farol]"></span>
                                <span :style="'color:' + cores[aberto.farol]" x-text="rotulos[aberto.farol] + ' — ' + aberto.motivo"></span>
                            </p>
                            <p class="text-xs mt-1" style="color:var(--muted2)" x-text="aberto.status_label + ' · ' + aberto.datas_texto.join(' · ')"></p>
                        </div>
                        <button type="button" @click="aberto = null" style="color:var(--muted)"><x-icon name="x" size="18" /></button>
                    </div>

                    {{-- Onde está o trabalho --}}
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color:var(--muted)">Onde está o trabalho</p>
                        <div class="flex h-2.5 rounded-full overflow-hidden mb-2" style="background:var(--s3)">
                            <template x-for="s in segmentos(aberto)" :key="s.k"><div :style="'width:' + s.w + '%; background:' + s.cor"></div></template>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                            <template x-for="[k, label, cor] in etapas" :key="k">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5" style="color:var(--muted)"><span class="w-2 h-2 rounded-sm" :style="'background:' + cor"></span><span x-text="label"></span></span>
                                    <span class="font-mono" style="color:var(--text)" x-text="aberto.etapas[k]"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <template x-if="aberto.estagnadas.length">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color:var(--cyan)">🧊 Tarefas paradas</p>
                            <template x-for="t in aberto.estagnadas" :key="t.id">
                                <a :href="t.url" class="block py-1.5" style="border-top:1px solid var(--border)">
                                    <span class="block text-sm truncate" style="color:var(--text)" x-text="t.title"></span>
                                    <span class="block text-xs" style="color:var(--muted)" x-text="t.pessoas + ' · ' + t.status_label + ' · parada há ' + t.parada_dias + ' dia(s) úteis'"></span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <template x-if="aberto.travadas.length">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color:var(--orange)">Travadas esperando alguém</p>
                            <template x-for="t in aberto.travadas" :key="t.id">
                                <a :href="t.url" class="block py-1.5" style="border-top:1px solid var(--border)">
                                    <span class="block text-sm truncate" style="color:var(--text)" x-text="t.title"></span>
                                    <span class="block text-xs" style="color:var(--muted)" x-text="t.trava + ' · ' + t.pessoas"></span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color:var(--muted)">Próximas entregas</p>
                        <template x-if="!aberto.proximas.length">
                            <p class="text-xs" style="color:var(--muted2)">Nenhuma tarefa aberta com data de entrega.</p>
                        </template>
                        <template x-for="t in aberto.proximas" :key="t.id">
                            <a :href="t.url" class="flex items-center justify-between gap-3 py-1.5" style="border-top:1px solid var(--border)">
                                <span class="min-w-0">
                                    <span class="block text-sm truncate" style="color:var(--text)" x-text="t.title"></span>
                                    <span class="block text-xs" style="color:var(--muted)" x-text="t.pessoas + ' · ' + t.status_label"></span>
                                </span>
                                <span class="text-xs font-mono flex-shrink-0" :style="'color:' + (t.vencida ? 'var(--red)' : 'var(--muted)')" x-text="t.due"></span>
                            </a>
                        </template>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap pt-2" style="border-top:1px solid var(--border)">
                        <a :href="aberto.url" class="btn btn-primary btn-sm">Abrir projeto</a>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="salvando" @click="mudarStatus(aberto, 'concluido')">Concluir</button>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="salvando" x-show="aberto.status !== 'stand_by'" @click="mudarStatus(aberto, 'stand_by')">Stand By</button>
                        <button type="button" class="btn btn-ghost btn-sm" :disabled="salvando" x-show="aberto.status === 'stand_by'" @click="mudarStatus(aberto, 'em_execucao')">Retomar</button>
                    </div>
                </div>
            </template>
        </aside>
    </div>
</x-app-layout>
