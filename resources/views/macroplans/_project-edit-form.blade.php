{{-- Formulário de edição de Projeto/Campanha — extraído pra ser reutilizável tanto
     na lista de projetos dentro do macroplanejamento (macroplans/edit.blade.php)
     quanto na própria página do projeto (macroplans/project-show.blade.php, inclusive
     projeto standalone sem macroplano). Espera no escopo:
       $project      — o Project sendo editado
       $formAction   — URL de destino do <form> (macroplans.projects.update ou projects.updateDirect)
       $cancelAction — (opcional) expressão Alpine pro botão Cancelar, default 'editId = null' --}}
<div x-data="{
    tags: {{ json_encode($project->tags ?? []) }},
    newTag: '',
    addTag() {
        const t = this.newTag.trim();
        if (t && !this.tags.includes(t)) this.tags.push(t);
        this.newTag = '';
    },
    removeTag(i) { this.tags.splice(i, 1); },
    ideas: {{ json_encode($project->content_ideas ?? []) }},
    addIdea() { this.ideas.push({ formato: 'video', titulo: '', texto: '' }); },
    removeIdea(i) { this.ideas.splice(i, 1); },
    phases: {{ json_encode($project->traffic_phases ?? []) }},
    addPhase() { this.phases.push({ fase: '', titulo: '', periodo: '', objetivo: '', localizacao: '', publico: '', criativos: '', verba: '' }); },
    removePhase(i) { this.phases.splice(i, 1); },
    briefDetalhado: {{ $project->brief_status === 'detalhado' ? 'true' : 'false' }},
    tomChips: {{ json_encode($project->tom_comunicacao ?? []) }},
    newTom: '',
    addTom() { const t = this.newTom.trim(); if (t && !this.tomChips.includes(t)) this.tomChips.push(t); this.newTom = ''; },
    removeTom(i) { this.tomChips.splice(i, 1); },
    angulos: {{ json_encode($project->angulos ?? []) }},
    addAngulo() { this.angulos.push({ titulo: '', texto: '' }); },
    removeAngulo(i) { this.angulos.splice(i, 1); },
    passos: {{ json_encode($project->mecanica ?? []) }},
    addPasso() { this.passos.push({ titulo: '', texto: '' }); },
    removePasso(i) { this.passos.splice(i, 1); },
    refsVisuais: {{ json_encode($project->referencias_visuais ?? []) }},
    addRef() { this.refsVisuais.push({ label: '', texto: '' }); },
    removeRef(i) { this.refsVisuais.splice(i, 1); },
    pecas: {{ json_encode($project->pecas ?? []) }},
    addPeca() { this.pecas.push({ nome: '', direcionamento: '' }); },
    removePeca(i) { this.pecas.splice(i, 1); },
}" style="background:rgba(100, 59, 142,.04); border-left:3px solid var(--purple)">

<form method="POST" action="{{ $formAction }}"
      class="px-5 py-5 space-y-5">
    @csrf @method('PATCH')

    {{-- Linha 1: título + tipo + status --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="col-span-1">
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                Nome <span style="color:var(--orange)">*</span>
            </label>
            <input type="text" name="title" value="{{ $project->title }}" required
                class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tipo</label>
            <select name="type"
                class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                @foreach(\App\Models\Project::$types as $key => $t)
                    <option value="{{ $key }}" {{ $project->type === $key ? 'selected' : '' }}>{{ $t['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Status</label>
            <select name="status"
                class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                @foreach(\App\Models\Project::$statuses as $key => $s)
                    @continue($key === 'cancelado')
                    <option value="{{ $key }}" {{ $project->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Objetivo --}}
    <div>
        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Objetivo</label>
        <textarea name="objective" rows="2"
            class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $project->objective }}</textarea>
    </div>

    {{-- Datas + Budget --}}
    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Início</label>
            <input type="date" name="start_date"
                value="{{ $project->start_date?->format('Y-m-d') }}"
                class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Término</label>
            <input type="date" name="end_date"
                value="{{ $project->end_date?->format('Y-m-d') }}"
                class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Verba (R$)</label>
            <input type="number" name="budget" min="0" step="0.01"
                value="{{ $project->budget }}"
                placeholder="0,00"
                class="w-full px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
        </div>
    </div>

    {{-- Tags --}}
    <div>
        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tags</label>
        <div class="flex flex-wrap gap-1.5 mb-2">
            <template x-for="(tag, i) in tags" :key="i">
                <span class="flex items-center gap-1 px-2.5 py-1 text-xs font-mono rounded-full"
                      style="background:rgba(100, 59, 142,.12); color:var(--purple)">
                    <input type="hidden" :name="'tags[]'" :value="tag">
                    <span x-text="tag"></span>
                    <button type="button" @click="removeTag(i)"
                        class="ml-0.5 font-bold" style="line-height:1">×</button>
                </span>
            </template>
        </div>
        <div class="flex gap-2">
            <input type="text" x-model="newTag" @keydown.enter.prevent="addTag()"
                placeholder="Digite uma tag e pressione Enter..."
                class="flex-1 px-3 py-1.5 text-xs focus:outline-none"
                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
            <button type="button" @click="addTag()"
                class="px-3 py-1.5 text-xs font-mono"
                style="border:1px solid var(--border2); color:var(--muted2)">+ Tag</button>
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
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $project->{'briefing_' . $key} }}</textarea>
            </div>
        @endif
    @endforeach

    {{-- Ideias de Conteúdo --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <label class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Ideias de Conteúdo</label>
            <button type="button" @click="addIdea()"
                class="text-xs font-mono px-3 py-1.5"
                style="border:1px solid var(--border2); color:var(--muted2)">+ Ideia</button>
        </div>
        <div class="space-y-2">
            <template x-for="(idea, i) in ideas" :key="i">
                <div class="px-4 py-3 rounded relative" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                    <button type="button" @click="removeIdea(i)"
                        class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                    <div class="grid grid-cols-4 gap-2 mb-2">
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Formato</p>
                            <select :name="'content_ideas['+i+'][formato]'" x-model="idea.formato"
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                <option value="video">Vídeo</option>
                                <option value="card">Card</option>
                                <option value="carrossel">Carrossel</option>
                                <option value="stories">Stories</option>
                                <option value="reels">Reels</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-span-3">
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Título</p>
                            <input type="text" :name="'content_ideas['+i+'][titulo]'" x-model="idea.titulo"
                                placeholder="Título da peça..."
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        </div>
                    </div>
                    <textarea :name="'content_ideas['+i+'][texto]'" x-model="idea.texto" rows="2"
                        placeholder="Descrição, roteiro, referências..."
                        class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                        style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                </div>
            </template>
            <p x-show="ideas.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">
                Nenhuma ideia adicionada.
            </p>
        </div>
    </div>

    {{-- Fases de Tráfego --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <label class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Fases de Tráfego</label>
            <button type="button" @click="addPhase()"
                class="text-xs font-mono px-3 py-1.5"
                style="border:1px solid var(--border2); color:var(--muted2)">+ Fase</button>
        </div>
        <div class="space-y-3">
            <template x-for="(ph, i) in phases" :key="i">
                <div class="px-4 py-4 rounded relative" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                    <button type="button" @click="removePhase(i)"
                        class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Fase / Label</p>
                            <input type="text" :name="'traffic_phases['+i+'][fase]'" x-model="ph.fase"
                                placeholder="Ex: Fase 1 — Reconhecimento"
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        </div>
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Período</p>
                            <input type="text" :name="'traffic_phases['+i+'][periodo]'" x-model="ph.periodo"
                                placeholder="Ex: 01/07 a 31/07"
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        </div>
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Verba (R$)</p>
                            <input type="text" :name="'traffic_phases['+i+'][verba]'" x-model="ph.verba"
                                placeholder="Ex: 800"
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Objetivo</p>
                            <input type="text" :name="'traffic_phases['+i+'][objetivo]'" x-model="ph.objetivo"
                                placeholder="Reconhecimento, Conversão..."
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        </div>
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Localização</p>
                            <input type="text" :name="'traffic_phases['+i+'][localizacao]'" x-model="ph.localizacao"
                                placeholder="Ex: Belo Horizonte / MG"
                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        </div>
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Público-Alvo</p>
                            <textarea :name="'traffic_phases['+i+'][publico]'" x-model="ph.publico" rows="2"
                                placeholder="Interesses, faixa etária, comportamento..."
                                class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                        </div>
                        <div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">Criativos</p>
                            <textarea :name="'traffic_phases['+i+'][criativos]'" x-model="ph.criativos" rows="2"
                                placeholder="Formatos, CTAs, referências..."
                                class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                                style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="phases.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">
                Nenhuma fase de tráfego adicionada.
            </p>
        </div>
    </div>

    {{-- Brief Criativo (só campanhas) --}}
    @if($project->type === 'campanha')
        <div style="border-top:1px solid var(--border2)" class="pt-5">
            <input type="hidden" name="brief_status" :value="briefDetalhado ? 'detalhado' : 'basico'">
            <label class="flex items-center gap-2 mb-4 cursor-pointer">
                <input type="checkbox" x-model="briefDetalhado" style="accent-color:var(--purple)">
                <span class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                    Ativar Brief Detalhado (Big Idea, racional, mecânica, moodboard...)
                </span>
            </label>

            <div x-show="briefDetalhado" x-cloak class="space-y-5">
                {{-- Big Idea --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Big Idea — Título</label>
                        <input type="text" name="big_idea_titulo" value="{{ $project->big_idea_titulo }}"
                            placeholder="A frase-conceito da campanha"
                            class="w-full px-3 py-2 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Big Idea — Manifesto</label>
                        <textarea name="big_idea_manifesto" rows="3"
                            placeholder="O parágrafo que expande a Big Idea..."
                            class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $project->big_idea_manifesto }}</textarea>
                    </div>
                </div>

                {{-- Território Alternativo --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Território Alternativo (rota B)</label>
                    <textarea name="territorio_alternativo" rows="2"
                        placeholder="Rota criativa de reserva, caso a principal não seja aprovada..."
                        class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $project->territorio_alternativo }}</textarea>
                </div>

                {{-- Racional Estratégico --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Racional Estratégico</label>
                    <textarea name="racional_estrategico" rows="4"
                        placeholder="Por que essa ideia funciona para esse público/desafio..."
                        class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $project->racional_estrategico }}</textarea>
                </div>

                {{-- Linha de Comunicação --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tom de Comunicação</label>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <template x-for="(tom, i) in tomChips" :key="i">
                                <span class="flex items-center gap-1 px-2.5 py-1 text-xs font-mono rounded-full"
                                      style="background:rgba(100, 59, 142,.12); color:var(--purple)">
                                    <input type="hidden" :name="'tom_comunicacao[]'" :value="tom">
                                    <span x-text="tom"></span>
                                    <button type="button" @click="removeTom(i)" class="ml-0.5 font-bold" style="line-height:1">×</button>
                                </span>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" x-model="newTom" @keydown.enter.prevent="addTom()"
                                placeholder="Ex: caloroso, direto..."
                                class="flex-1 px-3 py-1.5 text-xs focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            <button type="button" @click="addTom()"
                                class="px-3 py-1.5 text-xs font-mono" style="border:1px solid var(--border2); color:var(--muted2)">+ Tom</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Frase de Voz (exemplo)</label>
                        <input type="text" name="frase_voz" value="{{ $project->frase_voz }}"
                            placeholder="Uma frase que ilustra o tom da campanha"
                            class="w-full px-3 py-2 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                </div>

                {{-- Ângulos --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Ângulos de Comunicação</label>
                        <button type="button" @click="addAngulo()"
                            class="text-xs font-mono px-3 py-1.5" style="border:1px solid var(--border2); color:var(--muted2)">+ Ângulo</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(ang, i) in angulos" :key="i">
                            <div class="px-4 py-3 rounded relative" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                <button type="button" @click="removeAngulo(i)" class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                                <input type="text" :name="'angulos['+i+'][titulo]'" x-model="ang.titulo"
                                    placeholder="Ex: Ângulo 01 — O dado que incomoda"
                                    class="w-full px-2 py-1.5 text-xs font-bold focus:outline-none mb-2"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                <textarea :name="'angulos['+i+'][texto]'" x-model="ang.texto" rows="2"
                                    placeholder="O que esse ângulo explora..."
                                    class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                            </div>
                        </template>
                        <p x-show="angulos.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">Nenhum ângulo adicionado.</p>
                    </div>
                </div>

                {{-- Assinatura --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Assinatura</label>
                    <input type="text" name="assinatura" value="{{ $project->assinatura }}"
                        placeholder="A linha de fechamento da campanha"
                        class="w-full px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Mecânica --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Mecânica da Campanha</label>
                        <button type="button" @click="addPasso()"
                            class="text-xs font-mono px-3 py-1.5" style="border:1px solid var(--border2); color:var(--muted2)">+ Passo</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(passo, i) in passos" :key="i">
                            <div class="px-4 py-3 rounded relative flex gap-3" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                <span class="text-xs font-mono flex-shrink-0 pt-1.5" style="color:var(--purple)" x-text="(i + 1) + '.'"></span>
                                <div class="flex-1">
                                    <input type="text" :name="'mecanica['+i+'][titulo]'" x-model="passo.titulo"
                                        placeholder="Nome do passo"
                                        class="w-full px-2 py-1.5 text-xs font-bold focus:outline-none mb-2"
                                        style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                    <textarea :name="'mecanica['+i+'][texto]'" x-model="passo.texto" rows="2"
                                        placeholder="O que acontece nesse passo..."
                                        class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                                        style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                                </div>
                                <button type="button" @click="removePasso(i)" class="text-xs flex-shrink-0" style="color:var(--muted)">✕</button>
                            </div>
                        </template>
                        <p x-show="passos.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">Nenhum passo adicionado.</p>
                    </div>
                </div>

                {{-- Ponto de Atenção --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Ponto de Atenção</label>
                    <textarea name="ponto_atencao" rows="2"
                        placeholder="Algo que precisa de decisão/confirmação do cliente antes de executar..."
                        class="w-full px-3 py-2 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $project->ponto_atencao }}</textarea>
                </div>

                {{-- Referências Visuais --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Referências Visuais / Moodboard</label>
                        <button type="button" @click="addRef()"
                            class="text-xs font-mono px-3 py-1.5" style="border:1px solid var(--border2); color:var(--muted2)">+ Referência</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(ref, i) in refsVisuais" :key="i">
                            <div class="px-4 py-3 rounded relative" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                <button type="button" @click="removeRef(i)" class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                                <input type="text" :name="'referencias_visuais['+i+'][label]'" x-model="ref.label"
                                    placeholder="Ex: Paleta, Estilo de imagem, Tipografia..."
                                    class="w-full px-2 py-1.5 text-xs font-bold focus:outline-none mb-2"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                <textarea :name="'referencias_visuais['+i+'][texto]'" x-model="ref.texto" rows="2"
                                    placeholder="Descrição da referência..."
                                    class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                            </div>
                        </template>
                        <p x-show="refsVisuais.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">Nenhuma referência adicionada.</p>
                    </div>
                </div>

                {{-- Peças / Desdobramento --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Desdobramento em Peças</label>
                        <button type="button" @click="addPeca()"
                            class="text-xs font-mono px-3 py-1.5" style="border:1px solid var(--border2); color:var(--muted2)">+ Peça</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(peca, i) in pecas" :key="i">
                            <div class="px-4 py-3 rounded relative" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                <button type="button" @click="removePeca(i)" class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                                <input type="text" :name="'pecas['+i+'][nome]'" x-model="peca.nome"
                                    placeholder="Nome da peça (ex: Vídeo institucional)"
                                    class="w-full px-2 py-1.5 text-xs font-bold focus:outline-none mb-2"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                <textarea :name="'pecas['+i+'][direcionamento]'" x-model="peca.direcionamento" rows="2"
                                    placeholder="Direcionamento dessa peça..."
                                    class="w-full px-2 py-1.5 text-xs focus:outline-none resize-none"
                                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text)"></textarea>
                            </div>
                        </template>
                        <p x-show="pecas.length === 0" class="text-xs font-mono py-2" style="color:var(--muted)">Nenhuma peça adicionada.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary btn-sm">
            Salvar Projeto
        </button>
        <button type="button" @click="{{ $cancelAction ?? 'editId = null' }}" class="btn btn-ghost btn-sm">
            Cancelar
        </button>
    </div>
</form>
</div>
