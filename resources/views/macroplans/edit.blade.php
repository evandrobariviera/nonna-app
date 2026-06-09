<x-app-layout>
    <x-slot name="header">Planejamento — {{ $macroplan->client->company_name }}</x-slot>

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
                {{ $macroplan->client->company_name }}
            </a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">{{ $macroplan->title }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-{{ $macroplan->statusColor() }}">{{ $macroplan->statusLabel() }}</span>
            <span class="text-xs font-mono" style="color:var(--muted)">{{ $macroplan->periodLabel() }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
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
                                ? 'background:rgba(106,90,205,.12); color:var(--purple)'
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
                            ? 'background:rgba(106,90,205,.12); color:var(--purple)'
                            : 'color:var(--muted2)'">
                    <span class="text-xs font-mono font-bold opacity-40">⚙</span>
                    <span class="text-xs font-semibold">Configurações</span>
                </button>
            </div>

            {{-- Remover --}}
            <div class="mt-3">
                <form method="POST" action="{{ route('macroplans.destroy', $macroplan) }}"
                      onsubmit="return confirm('Remover este planejamento e todos os projetos?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="w-full px-3 py-2 text-xs font-mono transition-colors"
                        style="border:1px solid var(--border2); color:var(--muted)"
                        onmouseover="this.style.borderColor='var(--red)'; this.style.color='var(--red)'"
                        onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted)'">
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
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 01</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Visão Geral e Metas</h2>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="bloco1">

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Foco Principal do Ciclo
                            </label>
                            <textarea name="foco_principal" rows="4"
                                placeholder="Qual é a grande prioridade deste trimestre?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b1['foco_principal'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Verba de Anúncios (Budget)
                            </label>
                            <input type="text" name="verba_anuncios"
                                value="{{ $b1['verba_anuncios'] ?? '' }}"
                                placeholder="Ex: R$ 2.000,00 / mês"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Metas e Indicadores de Sucesso
                            </label>
                            <textarea name="metas_indicadores" rows="5"
                                placeholder="Como mediremos o sucesso deste ciclo?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b1['metas_indicadores'] ?? '' }}</textarea>
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
                @php $b2 = $macroplan->bloco2 ?? []; @endphp
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Bloco 02</p>
                        <h2 class="text-base font-bold" style="color:var(--text)">Contexto e Estratégia</h2>
                    </div>
                    <form method="POST" action="{{ route('macroplans.update', $macroplan) }}" class="px-5 py-5 space-y-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_block" value="bloco2">

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                O Desafio Atual
                            </label>
                            <textarea name="desafio_atual" rows="5"
                                placeholder="Qual é o principal problema ou oportunidade que estamos endereçando?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['desafio_atual'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                A Nossa Estratégia
                            </label>
                            <textarea name="estrategia" rows="6"
                                placeholder="Como a equipe vai agir para resolver o desafio?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['estrategia'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Pilares de Comunicação
                            </label>
                            <textarea name="pilares_comunicacao" rows="5"
                                placeholder="Quais são as mensagens-chave que guiarão todas as criações?"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ $b2['pilares_comunicacao'] ?? '' }}</textarea>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                                Salvar Bloco 02
                            </button>
                        </div>
                    </form>
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
                        <button @click="addOpen = !addOpen"
                            class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                            style="border:1px solid var(--border2); color:var(--muted2)"
                            onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                            onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                            + Novo Projeto
                        </button>
                    </div>

                    {{-- Formulário adicionar projeto --}}
                    <div x-show="addOpen" x-cloak style="border-bottom:1px solid var(--border2)">
                        <form method="POST" action="{{ route('macroplans.projects.store', $macroplan) }}"
                              class="px-5 py-5 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                    Nome do Projeto <span style="color:var(--orange)">*</span>
                                </label>
                                <input type="text" name="title" required
                                    placeholder="Ex: Captação B2B — Landing Page"
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                    Objetivo do Projeto
                                </label>
                                <textarea name="objective" rows="2"
                                    placeholder="O que este projeto precisa alcançar?"
                                    class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                    Disciplinas Envolvidas
                                </label>
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
                                <button type="submit"
                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                    style="background:var(--purple)">
                                    Adicionar Projeto
                                </button>
                                <button type="button" @click="addOpen = false"
                                    class="text-xs font-mono transition-colors" style="color:var(--muted)">
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
                                <div x-show="editId !== '{{ $project->id }}'" class="px-5 py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                <span class="text-xs font-mono font-bold" style="color:var(--muted)">
                                                    #{{ $loop->iteration }}
                                                </span>
                                                <a href="{{ route('macroplans.projects.show', [$macroplan, $project]) }}"
                                                   class="font-bold text-sm transition-colors"
                                                   style="color:var(--text)"
                                                   onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--text)'">
                                                    {{ $project->title }}
                                                </a>
                                                <span class="badge badge-{{ $project->statusColor() }}">{{ $project->statusLabel() }}</span>
                                            </div>
                                            @if($project->objective)
                                                <p class="text-xs mb-2" style="color:var(--muted2)">{{ $project->objective }}</p>
                                            @endif
                                            @if($project->disciplines)
                                                <div class="flex flex-wrap gap-1 mb-2">
                                                    @foreach($project->disciplineLabels() as $d)
                                                        <span class="badge">{{ $d }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            {{-- Barra de progresso --}}
                                            @php
                                                $pct = $project->progressPercent();
                                                $total = $project->tasks->where('status', '!=', 'cancelado')->count();
                                                $done  = $project->tasks->where('status', 'concluido')->count();
                                            @endphp
                                            @if($total > 0)
                                                <div class="flex items-center gap-2 mt-1">
                                                    <div class="flex-1 h-1.5 rounded-full overflow-hidden" style="background:var(--border2); max-width:160px">
                                                        <div class="h-1.5 rounded-full"
                                                             style="width:{{ $pct }}%; background:{{ $pct >= 100 ? 'var(--green)' : 'var(--grad)' }}"></div>
                                                    </div>
                                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ $done }}/{{ $total }} · {{ $pct }}%</span>
                                                </div>
                                            @else
                                                <span class="text-xs font-mono" style="color:var(--muted)">Sem tarefas</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <a href="{{ route('macroplans.projects.show', [$macroplan, $project]) }}"
                                               class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                                Ver →
                                            </a>
                                            <button type="button" @click="editId = '{{ $project->id }}'"
                                                class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                                onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                                Editar
                                            </button>
                                            <form method="POST" action="{{ route('macroplans.projects.destroy', [$macroplan, $project]) }}"
                                                  onsubmit="return confirm('Remover projeto {{ addslashes($project->title) }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="text-xs font-mono transition-colors" style="color:var(--muted)"
                                                    onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                                                    Remover
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                {{-- Formulário de edição inline --}}
                                <template x-if="editId === '{{ $project->id }}'">
                                    <form method="POST" action="{{ route('macroplans.projects.update', [$macroplan, $project]) }}"
                                          class="px-5 py-5 space-y-4"
                                          style="background:rgba(106,90,205,.04); border-left:3px solid var(--purple)">
                                        @csrf @method('PATCH')

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                                    Nome do Projeto <span style="color:var(--orange)">*</span>
                                                </label>
                                                <input type="text" name="title" value="{{ $project->title }}" required
                                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Status</label>
                                                <select name="status"
                                                    class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                                    @foreach(\App\Models\Project::$statuses as $key => $s)
                                                        <option value="{{ $key }}" {{ $project->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Objetivo do Projeto</label>
                                            <textarea name="objective" rows="2"
                                                class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $project->objective }}</textarea>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Disciplinas Envolvidas</label>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach(\App\Models\Project::$disciplines as $key => $label)
                                                    <label class="flex items-center gap-1.5 text-xs cursor-pointer px-3 py-1.5"
                                                           style="border:1px solid var(--border2); color:var(--muted2)">
                                                        <input type="checkbox" name="disciplines[]" value="{{ $key }}"
                                                            {{ in_array($key, $project->disciplines ?? []) ? 'checked' : '' }}
                                                            style="accent-color:var(--purple)">
                                                        {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Briefings por disciplina --}}
                                        @foreach(\App\Models\Project::$disciplines as $key => $label)
                                            @if(in_array($key, $project->disciplines ?? []))
                                                <div>
                                                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                                        Briefing — {{ $label }}
                                                    </label>
                                                    <textarea name="briefing_{{ $key }}" rows="3"
                                                        placeholder="Instruções específicas para {{ $label }}..."
                                                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $project->{'briefing_' . $key} }}</textarea>
                                                </div>
                                            @endif
                                        @endforeach

                                        <div class="flex gap-3">
                                            <button type="submit"
                                                class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                                style="background:var(--purple)">
                                                Salvar Projeto
                                            </button>
                                            <button type="button" @click="editId = null"
                                                class="text-xs font-mono transition-colors" style="color:var(--muted)">
                                                Cancelar
                                            </button>
                                        </div>
                                    </form>
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

                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                Título <span style="color:var(--orange)">*</span>
                            </label>
                            <input type="text" name="title" value="{{ $macroplan->title }}" required
                                class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        </div>

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
