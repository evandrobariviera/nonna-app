<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('clients.show', $client) }}"
           class="transition-colors" style="color:var(--muted)"
           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
            {{ $client->company_name }}
        </a>
        <span style="color:var(--border2)" class="mx-2">/</span>
        <span style="color:var(--text)">Dossiê v{{ $dossier->version }}</span>
    </x-slot>

    @php
        $rPre        = $dossier->roteiro_pesquisa_previa ?? [];
        $rKickNeg    = $dossier->roteiro_kickoff_negocio ?? [];
        $rKickMer    = $dossier->roteiro_kickoff_mercado ?? [];
        $rKickCli    = $dossier->roteiro_kickoff_cliente ?? [];
        $rKickCom    = $dossier->roteiro_kickoff_comunicacao ?? [];
        $b02Obj      = $dossier->bloco02_objetivos ?? [];
        $b03Pos      = $dossier->bloco03_posicionamento_formula ?? [];
        $b03Arq      = $dossier->bloco03_arquetipo ?? [];
        $b03Vals     = $dossier->bloco03_valores ?? [];
        $b04ExTom    = $dossier->bloco04_exemplos_tom ?? [];
        $b04Vis      = $dossier->bloco04_territorio_visual ?? [];
    @endphp

    <div x-data="dossierEditor(
            @json($dossier->competitors->toArray()),
            @json($dossier->personas->toArray()),
            @json($b03Vals),
            @json($b04ExTom)
         )"
         x-init="init()"
         @input.capture="markDirty()"
         @change.capture="markDirty()">

        {{-- ── BARRA TOPO ── --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                {{-- Fase atual --}}
                <span class="px-2 py-1 text-xs font-mono font-semibold"
                      style="background:rgba(106,90,205,.12); color:var(--purple); border:1px solid rgba(106,90,205,.3)">
                    {{ $dossier->faseLabel() }}
                </span>

                {{-- Progresso de fases --}}
                <div class="flex items-center gap-1">
                    @foreach(\App\Models\BrandDossier::$fases as $key => $f)
                        <div class="h-1.5 w-8 rounded-full"
                             style="background: {{ $dossier->faseStep() >= $f['step'] ? $f['color'] : 'var(--border2)' }}"></div>
                    @endforeach
                </div>

                {{-- Avançar fase --}}
                @if(!$dossier->isAprovado())
                    <form method="POST" action="{{ route('clients.dossiers.avancar-fase', [$client, $dossier]) }}">
                        @csrf
                        <button type="submit"
                                class="text-xs font-mono px-3 py-1 transition-colors"
                                style="border:1px solid var(--border2); color:var(--muted2)"
                                onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                                onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'"
                                onclick="return confirm('Avançar para a próxima fase?')">
                            Avançar fase →
                        </button>
                    </form>
                @endif
            </div>

            <div class="flex items-center gap-3">
                {{-- Indicador de save --}}
                <span x-show="saving" class="text-xs font-mono" style="color:var(--muted)">Salvando…</span>
                <span x-show="!saving && savedAt" class="text-xs font-mono" style="color:var(--green)"
                      x-text="'✓ Salvo às ' + savedAt"></span>

                {{-- Salvar manual --}}
                <button type="button" @click="save()"
                        class="px-4 py-1.5 text-xs font-bold text-white transition-opacity"
                        style="background:var(--purple)"
                        :class="{'opacity-50': !dirty && savedAt}">
                    Salvar
                </button>

                {{-- Remover --}}
                <form method="POST" action="{{ route('clients.dossiers.destroy', [$client, $dossier]) }}"
                      onsubmit="return confirm('Remover este dossiê e todos os seus dados?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs font-mono transition-colors"
                            style="color:var(--muted)"
                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                        Remover
                    </button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 text-sm"
                 style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
                {{ session('success') }}
            </div>
        @endif

        {{-- ── MODO TOGGLE ── --}}
        <div class="flex gap-0 mb-4" style="border:1px solid var(--border2); width:fit-content">
            <button type="button" @click="mode = 'roteiro'; section = 'config'"
                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                    :style="mode === 'roteiro'
                        ? 'background:var(--purple); color:#fff'
                        : 'color:var(--muted2); background:none'">
                Roteiro
            </button>
            <button type="button" @click="mode = 'dossie'; section = 'd01_negocio'"
                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                    :style="mode === 'dossie'
                        ? 'background:var(--purple); color:#fff'
                        : 'color:var(--muted2); background:none'">
                Dossiê
            </button>
        </div>

        {{-- ── LAYOUT DOIS PAINÉIS ── --}}
        <div class="flex gap-5 items-start">

            {{-- ── SIDEBAR NAV ── --}}
            <nav class="w-48 shrink-0 sticky top-0" style="border:1px solid var(--border2)">

                {{-- ROTEIRO nav --}}
                <div x-show="mode === 'roteiro'" x-cloak>
                    <div class="px-3 py-2 text-xs font-mono uppercase tracking-widest" style="color:var(--muted); border-bottom:1px solid var(--border2)">Roteiro</div>
                    @php
                        $rNavSections = [
                            ['id'=>'config',          'label'=>'Configuração',           'color'=>'var(--muted2)'],
                            ['id'=>'pre_kickoff',     'label'=>'Pesquisa Prévia',        'color'=>'#60a5fa'],
                            ['id'=>'kickoff_negocio', 'label'=>'Kickoff · O Negócio',   'color'=>'#fbbf24'],
                            ['id'=>'kickoff_mercado', 'label'=>'Kickoff · Mercado',      'color'=>'#fbbf24'],
                            ['id'=>'kickoff_cliente', 'label'=>'Kickoff · Cliente Ideal','color'=>'#fbbf24'],
                            ['id'=>'kickoff_com',     'label'=>'Kickoff · Comunicação',  'color'=>'#fbbf24'],
                            ['id'=>'pesquisa',        'label'=>'Pesquisa Estratégica',   'color'=>'#d63eaa'],
                        ];
                    @endphp
                    @foreach($rNavSections as $nav)
                        <button type="button" @click="section = '{{ $nav['id'] }}'"
                                class="w-full text-left px-3 py-2 text-xs transition-colors"
                                :style="section === '{{ $nav['id'] }}'
                                    ? 'background:var(--s2); color:{{ $nav['color'] }}; border-left:2px solid {{ $nav['color'] }}'
                                    : 'color:var(--muted); border-left:2px solid transparent'">
                            {{ $nav['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- DOSSIÊ nav --}}
                <div x-show="mode === 'dossie'" x-cloak>
                    <div class="px-3 py-2 text-xs font-mono uppercase tracking-widest" style="color:var(--muted); border-bottom:1px solid var(--border2)">Dossiê</div>
                    @php
                        $dNavSections = [
                            ['id'=>'d01_negocio',     'label'=>'01 · Análise do Negócio',   'color'=>'#60a5fa'],
                            ['id'=>'d01_mercado',     'label'=>'01 · Análise de Mercado',    'color'=>'#60a5fa'],
                            ['id'=>'d01_competitiva', 'label'=>'01 · Análise Competitiva',   'color'=>'#60a5fa'],
                            ['id'=>'d01_personas',    'label'=>'01 · Personas',              'color'=>'#60a5fa'],
                            ['id'=>'d01_comunicacao', 'label'=>'01 · Diag. Comunicação',     'color'=>'#60a5fa'],
                            ['id'=>'d02_problema',    'label'=>'02 · Problema Central',      'color'=>'#fbbf24'],
                            ['id'=>'d02_oportunidade','label'=>'02 · Oportunidade',          'color'=>'#fbbf24'],
                            ['id'=>'d02_insight',     'label'=>'02 · Insight Central',       'color'=>'#fbbf24'],
                            ['id'=>'d02_objetivos',   'label'=>'02 · Objetivos',             'color'=>'#fbbf24'],
                            ['id'=>'d03_base',        'label'=>'03 · Propósito/Visão/Missão','color'=>'#d63eaa'],
                            ['id'=>'d03_valores',     'label'=>'03 · Valores',               'color'=>'#d63eaa'],
                            ['id'=>'d03_posicionamento','label'=>'03 · Posicionamento',      'color'=>'#d63eaa'],
                            ['id'=>'d03_personalidade','label'=>'03 · Personalidade',        'color'=>'#d63eaa'],
                            ['id'=>'d03_essencia',    'label'=>'03 · Essência',              'color'=>'#d63eaa'],
                            ['id'=>'d04_tom',         'label'=>'04 · Tom de Voz',            'color'=>'#7b3fe4'],
                            ['id'=>'d04_visual',      'label'=>'04 · Território Visual',     'color'=>'#7b3fe4'],
                            ['id'=>'d04_linguagem',   'label'=>'04 · Linha de Linguagem',    'color'=>'#7b3fe4'],
                            ['id'=>'d04_mandatorios', 'label'=>'04 · Mandatórios',           'color'=>'#7b3fe4'],
                        ];
                    @endphp
                    @foreach($dNavSections as $nav)
                        <button type="button" @click="section = '{{ $nav['id'] }}'"
                                class="w-full text-left px-3 py-2 text-xs transition-colors"
                                :style="section === '{{ $nav['id'] }}'
                                    ? 'background:var(--s2); color:{{ $nav['color'] }}; border-left:2px solid {{ $nav['color'] }}'
                                    : 'color:var(--muted); border-left:2px solid transparent'">
                            {{ $nav['label'] }}
                        </button>
                    @endforeach
                </div>
            </nav>

            {{-- ── CONTEÚDO ── --}}
            <div class="flex-1 min-w-0">
                <form id="dossier-form" @submit.prevent>
                    @csrf

                    {{-- Campos ocultos para arrays Alpine --}}
                    <input type="hidden" name="bloco03_valores"      x-effect="$el.value = JSON.stringify(valores)">
                    <input type="hidden" name="bloco04_exemplos_tom" x-effect="$el.value = JSON.stringify(exemplosTom)">

{{-- ════════════════════════════════════════════════════
     ROTEIRO — CONFIGURAÇÃO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'config'" x-cloak>
    <div class="section-hd">Configuração do Dossiê</div>

    <div class="form-group">
        <label class="form-label">Título</label>
        <input type="text" name="title" class="form-input"
               value="{{ old('title', $dossier->title) }}">
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">Data do Kickoff</label>
            <input type="date" name="data_kickoff" class="form-input"
                   value="{{ old('data_kickoff', $dossier->data_kickoff?->format('Y-m-d')) }}">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">Estrategista (Direção Geral)</label>
            <select name="estrategista_id" class="form-input">
                <option value="">— selecionar —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('estrategista_id', $dossier->estrategista_id) == $u->id ? 'selected' : '' }}>
                        {{ $u->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Redator (Direção Criativa)</label>
            <select name="redator_id" class="form-input">
                <option value="">— selecionar —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('redator_id', $dossier->redator_id) == $u->id ? 'selected' : '' }}>
                        {{ $u->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     ROTEIRO — PESQUISA PRÉVIA
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'pre_kickoff'" x-cloak>
    <div class="section-hd">Fase 1 — Pesquisa Prévia <span class="section-tag" style="color:#60a5fa">Dias 1–2 · Antes do kickoff</span></div>
    <p class="section-desc">Pesquise o cliente antes da reunião. Chegue ao kickoff com contexto — nunca no zero.</p>

    <div class="form-group">
        <label class="form-label">Observações sobre o cliente</label>
        <div class="form-guide">Site, redes sociais, Google, LinkedIn, avaliações, produtos. O que chamou atenção, contradições percebidas, perguntas que surgiram.</div>
        <x-rich-editor name="roteiro_pesquisa_previa[observacoes_cliente]" :value="old('roteiro_pesquisa_previa.observacoes_cliente', $rPre['observacoes_cliente'] ?? '')" min-height="200px" />
    </div>

    <div class="form-group">
        <label class="form-label">Observações sobre o setor e concorrentes óbvios</label>
        <div class="form-guide">Quem são os 3–5 maiores players, como o setor se comunica em geral, quem se destaca.</div>
        <x-rich-editor name="roteiro_pesquisa_previa[observacoes_setor]" :value="old('roteiro_pesquisa_previa.observacoes_setor', $rPre['observacoes_setor'] ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     ROTEIRO — KICKOFF: O NEGÓCIO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'kickoff_negocio'" x-cloak>
    <div class="section-hd">Fase 2 — Kickoff · O Negócio <span class="section-tag" style="color:#fbbf24">Dias 3–4 · Reunião</span></div>
    <p class="section-desc">Entender o que a empresa é, como opera e o que a diferencia de verdade.</p>

    @php
        $kNegPerguntas = [
            'descricao_3_frases'      => ['"Se você tivesse que explicar o que a empresa faz em 3 frases, o que diria?"', 'Captura a auto-percepção — revela o gap de comunicação'],
            'principal_diferencial'   => ['"Qual é a principal coisa que vocês fazem que os clientes não encontram em outra empresa?"', 'Alimenta o diferencial real'],
            'motivo_churn'            => ['"Quando um cliente vai embora, qual costuma ser o motivo real?"', 'Pontos de atenção raramente são declarados diretamente'],
            'o_que_nao_comunica'      => ['"Tem algum aspecto que você sabe que é bom, mas nunca consegue comunicar bem?"', 'Gap de percepção e insight central'],
            'historia_real'           => ['"Conte a história da empresa — não a versão do site. A versão de como realmente aconteceu."', 'Ativos narrativos que a versão oficial esconde'],
            'momento_dificil'         => ['"Qual foi o momento mais difícil que a empresa passou? Como saíram dele?"', 'Crises superadas revelam pontos fortes reais'],
            'melhores_clientes_dizem' => ['"Se eu conversasse com seus três melhores clientes agora, o que eles diriam sobre vocês?"', 'Posicionamento real percebido pelos clientes'],
            'objetivo_3_anos'         => ['"O que você quer ter construído daqui a 3 anos que ainda não existe hoje?"', 'Horizonte estratégico do trabalho'],
        ];
    @endphp

    @foreach($kNegPerguntas as $key => [$pergunta, $por_que])
        <div class="form-group">
            <label class="form-label" style="color:var(--text)">{{ $pergunta }}</label>
            <div class="form-guide">→ {{ $por_que }}</div>
            <x-rich-editor name="roteiro_kickoff_negocio[{{ $key }}]" :value="$rKickNeg[$key] ?? ''" />
        </div>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════
     ROTEIRO — KICKOFF: MERCADO E COMPETIÇÃO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'kickoff_mercado'" x-cloak>
    <div class="section-hd">Fase 2 — Kickoff · Mercado e Competição</div>
    <p class="section-desc">Entender como o cliente percebe o mercado, quem são os concorrentes e qual é a posição da empresa.</p>

    @php
        $kMerPerguntas = [
            'principais_concorrentes'   => ['"Quem vocês consideram seus principais concorrentes? Não apenas os que vendem o mesmo produto — os que disputam o mesmo cliente."', 'Lista de concorrentes para a análise competitiva'],
            'perde_para_quem'           => ['"Quando você perde um cliente para um concorrente, para quem costuma perder? O que fez o cliente escolher o outro?"', 'Critério real de decisão de compra no mercado'],
            'falta_no_mercado'          => ['"O que você acha que falta no mercado? O que nenhum player está oferecendo direito?"', 'Alimenta diretamente a Oportunidade Estratégica'],
            'como_descobrem'            => ['"Como os seus clientes descobrem a empresa? De onde vêm as melhores indicações?"', 'Canais de credibilidade reais'],
            'mudancas_no_mercado'       => ['"O que está mudando no mercado de vocês nos últimos anos? O que vai mudar nos próximos 3?"', 'Análise de Mercado com visão interna do setor'],
            'empresa_admira_fora'       => ['"Tem alguma empresa fora do seu setor que você admira pela comunicação ou posicionamento?"', 'Referências de tom e aspiração'],
        ];
    @endphp

    @foreach($kMerPerguntas as $key => [$pergunta, $por_que])
        <div class="form-group">
            <label class="form-label" style="color:var(--text)">{{ $pergunta }}</label>
            <div class="form-guide">→ {{ $por_que }}</div>
            <x-rich-editor name="roteiro_kickoff_mercado[{{ $key }}]" :value="$rKickMer[$key] ?? ''" />
        </div>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════
     ROTEIRO — KICKOFF: O CLIENTE IDEAL
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'kickoff_cliente'" x-cloak>
    <div class="section-hd">Fase 2 — Kickoff · O Cliente Ideal</div>
    <p class="section-desc">Construir a persona. Conversa qualitativa para entender quem é o cliente ideal, como ele pensa e o que o move.</p>

    @php
        $kCliPerguntas = [
            'melhor_cliente'      => ['"Pensa no seu melhor cliente. Não o maior — o melhor. Como ele é? O que o torna diferente dos outros?"', 'A persona começa aqui'],
            'o_que_valoriza'      => ['"O que esse cliente ideal valoriza que outros clientes não valorizam tanto?"', 'Critérios de decisão não declarados'],
            'como_decide'         => ['"Como esse cliente toma a decisão de comprar? Quem influencia essa decisão?"', 'Processo de compra: quem decide, quem influencia'],
            'maior_dor'           => ['"Qual é a maior dor desse cliente antes de encontrar vocês? O que ele estava tentando resolver?"', 'Alimenta a persona e o Insight Central'],
            'cliente_que_nao_quer' => ['"Existe algum tipo de cliente que vocês não querem? Que tipo não é bom para o negócio?"', 'Negativo da persona — define os limites do público-alvo'],
            'quando_volta'        => ['"Quando um cliente faz o pedido de volta — depois de ter ido para um concorrente — o que costuma ter acontecido?"', 'Ativo de retenção real — onde o insight muitas vezes mora'],
        ];
    @endphp

    @foreach($kCliPerguntas as $key => [$pergunta, $por_que])
        <div class="form-group">
            <label class="form-label" style="color:var(--text)">{{ $pergunta }}</label>
            <div class="form-guide">→ {{ $por_que }}</div>
            <x-rich-editor name="roteiro_kickoff_cliente[{{ $key }}]" :value="$rKickCli[$key] ?? ''" />
        </div>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════
     ROTEIRO — KICKOFF: COMUNICAÇÃO E OBJETIVOS
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'kickoff_com'" x-cloak>
    <div class="section-hd">Fase 2 — Kickoff · Comunicação e Objetivos</div>
    <p class="section-desc">Entender como o cliente enxerga sua própria comunicação e o que espera do trabalho com a Nonna.</p>

    @php
        $kComPerguntas = [
            'o_que_incomoda'     => ['"Quando você olha para a comunicação atual da empresa, o que te incomoda? O que não representa bem o que vocês são?"', 'Gap de percepção — resposta mais honesta sobre o problema'],
            'tres_palavras'      => ['"Se o mercado pudesse usar 3 palavras para descrever a empresa daqui a 2 anos, quais você gostaria que fossem?"', 'Objetivos de comunicação e essência'],
            'empresa_admira'     => ['"Tem alguma empresa cuja comunicação você admira? O que você admira nela especificamente?"', 'Referências de aspiração e território visual'],
            'o_que_nao_funcionou' => ['"O que você já tentou na comunicação que não funcionou? Por que acha que não funcionou?"', 'Restrições implícitas e lições aprendidas'],
            'criterio_sucesso'   => ['"Se a comunicação funcionar como esperado daqui a 12 meses, como você vai saber? O que vai ter mudado?"', 'Critério de sucesso — métrica de avaliação do Roadmap'],
            'algo_nao_perguntei' => ['"Tem algo importante sobre a empresa que eu ainda não perguntei e que você acha que eu precisaria saber?"', 'Sempre faça essa como última pergunta — frequentemente revela o mais importante'],
        ];
    @endphp

    @foreach($kComPerguntas as $key => [$pergunta, $por_que])
        <div class="form-group">
            <label class="form-label" style="color:var(--text)">{{ $pergunta }}</label>
            <div class="form-guide">→ {{ $por_que }}</div>
            <x-rich-editor name="roteiro_kickoff_comunicacao[{{ $key }}]" :value="$rKickCom[$key] ?? ''" />
        </div>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════
     ROTEIRO — PESQUISA ESTRATÉGICA
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'roteiro' && section === 'pesquisa'" x-cloak>
    <div class="section-hd">Fase 3 — Pesquisa Estratégica <span class="section-tag" style="color:#d63eaa">Dias 5–10</span></div>
    <p class="section-desc">Análise de concorrentes, contexto de mercado e o caminho para o insight central. Direção Criativa redige, Direção Geral revisa.</p>

    <div class="form-group">
        <label class="form-label">Notas de pesquisa — concorrentes e mercado</label>
        <div class="form-guide">Para cada concorrente: o que comunicam, como se posicionam, o que não ocupam. Pesquise site, redes, Meta Ad Library, Google Ads Transparency. O espaço disponível é o oposto do que todos estão fazendo.</div>
        <x-rich-editor name="roteiro_pesquisa_notas" :value="old('roteiro_pesquisa_notas', $dossier->roteiro_pesquisa_notas ?? '')" min-height="280px" />
    </div>

    <div class="insight-box mt-4">
        <div class="text-xs font-mono uppercase tracking-widest mb-2" style="color:#fbbf24">O Insight Central emerge da pesquisa</div>
        <p class="text-xs" style="color:var(--muted2); line-height:1.7">
            O insight nasce quando três elementos apontam para o mesmo lugar:
            <strong style="color:var(--text)">o que o cliente disse no kickoff</strong> +
            <strong style="color:var(--text)">o que o mercado mostra</strong> +
            <strong style="color:var(--text)">o que os concorrentes ignoram</strong>.
            Quando tiver o insight, vá para o Dossiê → Bloco 02 → Insight Central.
        </p>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 01 · ANÁLISE DO NEGÓCIO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd01_negocio'" x-cloak>
    <div class="section-hd" style="color:#60a5fa">Bloco 01 — Análise do Negócio</div>

    <div class="form-group">
        <label class="form-label">1.1 · Visão geral do negócio <span class="form-tag">3–5 parágrafos</span></label>
        <div class="form-guide">O que faz, como faz, há quanto tempo, em qual escala e em quais mercados. Evite adjetivos — descreva fatos.</div>
        <x-rich-editor name="bloco01_visao_geral" :value="old('bloco01_visao_geral', $dossier->bloco01_visao_geral ?? '')" min-height="200px" />
    </div>

    <div class="form-group">
        <label class="form-label">1.2 · Histórico e marcos relevantes <span class="form-tag">linha do tempo</span></label>
        <div class="form-guide">Momentos que definem quem a empresa é hoje. Fundação, expansão, crises superadas, reconhecimentos. Fatos com potencial narrativo.</div>
        <x-rich-editor name="bloco01_historico" :value="old('bloco01_historico', $dossier->bloco01_historico ?? '')" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">1.3 · Pontos fortes reais</label>
            <div class="form-guide">O que genuinamente faz melhor — comprovado por dados, histórico ou relatos de clientes.</div>
            <x-rich-editor name="bloco01_pontos_fortes" :value="old('bloco01_pontos_fortes', $dossier->bloco01_pontos_fortes ?? '')" />
        </div>
        <div class="form-group">
            <label class="form-label">1.4 · Pontos de atenção</label>
            <div class="form-guide">O que limita o crescimento ou cria percepção negativa. Honestidade aqui é fundamental.</div>
            <x-rich-editor name="bloco01_pontos_atencao" :value="old('bloco01_pontos_atencao', $dossier->bloco01_pontos_atencao ?? '')" />
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">1.5 · Objetivos estratégicos <span class="form-tag">próximos 12–24 meses</span></label>
        <div class="form-guide">O que o cliente quer construir ou alcançar no horizonte de trabalho com a Nonna.</div>
        <x-rich-editor name="bloco01_objetivos" :value="old('bloco01_objetivos', $dossier->bloco01_objetivos ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 01 · ANÁLISE DE MERCADO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd01_mercado'" x-cloak>
    <div class="section-hd" style="color:#60a5fa">Bloco 01 — Análise de Mercado</div>

    <div class="form-group">
        <label class="form-label">2.1 · Contexto e tendências do setor</label>
        <div class="form-guide">O que está acontecendo no mercado que é relevante para a estratégia da marca.</div>
        <x-rich-editor name="bloco01_contexto_mercado" :value="old('bloco01_contexto_mercado', $dossier->bloco01_contexto_mercado ?? '')" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">2.2 · Como o mercado compra</label>
            <div class="form-guide">Quem decide, quais critérios usa, fontes consultadas, ciclo de compra.</div>
            <x-rich-editor name="bloco01_como_compra" :value="old('bloco01_como_compra', $dossier->bloco01_como_compra ?? '')" />
        </div>
        <div class="form-group">
            <label class="form-label">2.3 · O que gera confiança neste mercado</label>
            <div class="form-guide">Sinais que o mercado interpreta como credibilidade e segurança.</div>
            <x-rich-editor name="bloco01_gera_confianca" :value="old('bloco01_gera_confianca', $dossier->bloco01_gera_confianca ?? '')" />
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 01 · ANÁLISE COMPETITIVA
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd01_competitiva'" x-cloak>
    <div class="section-hd" style="color:#60a5fa">Bloco 01 — Análise Competitiva</div>

    {{-- Tabela de concorrentes --}}
    <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
            <label class="form-label mb-0">3.1 · Mapa competitivo</label>
            <button type="button" @click="addingCompetitor = true"
                    class="text-xs font-mono px-3 py-1"
                    style="border:1px solid rgba(96,165,250,.4); color:#60a5fa">
                + Concorrente
            </button>
        </div>

        {{-- Form novo concorrente --}}
        <div x-show="addingCompetitor" x-cloak class="mb-3 p-4" style="background:var(--s2); border:1px solid var(--border2)">
            <div class="grid grid-cols-2 gap-3 mb-3">
                <input type="text" x-model="newCompetitor.nome" placeholder="Nome do concorrente" class="form-input">
                <div></div>
            </div>
            <div class="grid grid-cols-3 gap-3 mb-3">
                <textarea x-model="newCompetitor.o_que_comunica" placeholder="O que comunica" class="form-textarea" rows="3"></textarea>
                <textarea x-model="newCompetitor.como_se_posiciona" placeholder="Como se posiciona" class="form-textarea" rows="3"></textarea>
                <textarea x-model="newCompetitor.o_que_nao_ocupa" placeholder="O que não ocupa" class="form-textarea" rows="3"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="saveCompetitor()" class="px-4 py-1.5 text-xs font-bold text-white" style="background:var(--purple)">Salvar</button>
                <button type="button" @click="addingCompetitor = false; resetNewCompetitor()" class="px-4 py-1.5 text-xs font-mono" style="color:var(--muted)">Cancelar</button>
            </div>
        </div>

        {{-- Lista de concorrentes --}}
        <template x-if="competitors.length === 0">
            <div class="tab-placeholder">
                <div class="tab-placeholder-title">Nenhum concorrente cadastrado</div>
                <div class="tab-placeholder-desc">Clique em "+ Concorrente" para adicionar.</div>
            </div>
        </template>

        <div x-show="competitors.length > 0">
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th style="width:18%">Concorrente</th>
                        <th>O que comunica</th>
                        <th>Como se posiciona</th>
                        <th>O que não ocupa</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="c in competitors" :key="c.id">
                        <tr>
                            <td>
                                <div x-show="!c._editing" x-text="c.nome" @click="c._editing = true" class="cursor-pointer" style="color:var(--text); font-weight:600"></div>
                                <input x-show="c._editing" type="text" x-model="c.nome" @blur="updateCompetitor(c)" class="form-input text-xs" style="min-width:120px">
                            </td>
                            <td>
                                <div x-show="!c._editing" x-text="c.o_que_comunica || '—'" @click="c._editing = true" class="cursor-pointer text-xs" style="color:var(--muted2)"></div>
                                <textarea x-show="c._editing" x-model="c.o_que_comunica" @blur="updateCompetitor(c)" class="form-textarea text-xs" rows="2"></textarea>
                            </td>
                            <td>
                                <div x-show="!c._editing" x-text="c.como_se_posiciona || '—'" @click="c._editing = true" class="cursor-pointer text-xs" style="color:var(--muted2)"></div>
                                <textarea x-show="c._editing" x-model="c.como_se_posiciona" @blur="updateCompetitor(c)" class="form-textarea text-xs" rows="2"></textarea>
                            </td>
                            <td>
                                <div x-show="!c._editing" x-text="c.o_que_nao_ocupa || '—'" @click="c._editing = true" class="cursor-pointer text-xs" style="color:var(--muted2)"></div>
                                <textarea x-show="c._editing" x-model="c.o_que_nao_ocupa" @blur="updateCompetitor(c)" class="form-textarea text-xs" rows="2"></textarea>
                            </td>
                            <td class="text-right">
                                <button type="button" @click="deleteCompetitor(c.id)"
                                        class="text-xs transition-colors" style="color:var(--muted)"
                                        onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                                    ✕
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-group mt-5">
        <label class="form-label">3.2 · Espaço disponível no mercado <span class="form-tag">conclusão da análise</span></label>
        <div class="form-guide">O que nenhum concorrente está comunicando que esta marca poderia ocupar com credibilidade.</div>
        <x-rich-editor name="bloco01_espaco_disponivel" :value="old('bloco01_espaco_disponivel', $dossier->bloco01_espaco_disponivel ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 01 · PERSONAS
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd01_personas'" x-cloak>
    <div class="section-hd" style="color:#60a5fa">Bloco 01 — Personas</div>

    <div class="flex items-center justify-between mb-4">
        <p class="text-xs" style="color:var(--muted2)">Quanto mais específica, mais útil. Generalizações não funcionam.</p>
        <button type="button" @click="addingPersona = true"
                class="text-xs font-mono px-3 py-1"
                style="border:1px solid rgba(96,165,250,.4); color:#60a5fa">
            + Persona
        </button>
    </div>

    {{-- Form nova persona --}}
    <div x-show="addingPersona" x-cloak class="mb-4 p-4" style="background:var(--s2); border:1px solid var(--border2)">
        <div class="grid grid-cols-3 gap-3 mb-3">
            <div>
                <label class="form-label">Tipo</label>
                <select x-model="newPersona.tipo" class="form-input">
                    <option value="principal">Principal</option>
                    <option value="secundaria">Secundária</option>
                </select>
            </div>
            <div>
                <label class="form-label">Nome fictício</label>
                <input type="text" x-model="newPersona.nome_ficticio" class="form-input" placeholder="Ex: Carlos">
            </div>
            <div>
                <label class="form-label">Idade e gênero</label>
                <input type="text" x-model="newPersona.idade_genero" class="form-input" placeholder="Ex: 42 anos, masc.">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
                <label class="form-label">Cargo e setor</label>
                <input type="text" x-model="newPersona.cargo_setor" class="form-input">
            </div>
            <div>
                <label class="form-label">Renda e contexto</label>
                <input type="text" x-model="newPersona.renda_contexto" class="form-input">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
                <label class="form-label">Como se informa</label>
                <textarea x-model="newPersona.como_se_informa" class="form-textarea" rows="2"></textarea>
            </div>
            <div>
                <label class="form-label">O que valida uma decisão de compra</label>
                <textarea x-model="newPersona.o_que_valida_compra" class="form-textarea" rows="2"></textarea>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
                <label class="form-label">Principais dores</label>
                <textarea x-model="newPersona.dores_principais" class="form-textarea" rows="2"></textarea>
            </div>
            <div>
                <label class="form-label">O que o motiva a comprar</label>
                <textarea x-model="newPersona.o_que_motiva" class="form-textarea" rows="2"></textarea>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
                <label class="form-label">O que ele nunca diria</label>
                <textarea x-model="newPersona.o_que_nunca_diria" class="form-textarea" rows="2"></textarea>
            </div>
            <div>
                <label class="form-label">Insight sobre a persona</label>
                <textarea x-model="newPersona.insight_persona" class="form-textarea" rows="2"></textarea>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" @click="savePersona()" class="px-4 py-1.5 text-xs font-bold text-white" style="background:var(--purple)">Salvar Persona</button>
            <button type="button" @click="addingPersona = false; resetNewPersona()" class="px-4 py-1.5 text-xs font-mono" style="color:var(--muted)">Cancelar</button>
        </div>
    </div>

    {{-- Lista de personas --}}
    <template x-if="personas.length === 0">
        <div class="tab-placeholder">
            <div class="tab-placeholder-title">Nenhuma persona cadastrada</div>
            <div class="tab-placeholder-desc">Clique em "+ Persona" para adicionar.</div>
        </div>
    </template>

    <template x-for="p in personas" :key="p.id">
        <div class="card mb-3">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <span class="text-xs font-mono uppercase tracking-widest px-2 py-0.5 mr-2"
                          :style="p.tipo === 'principal' ? 'background:rgba(96,165,250,.1); color:#60a5fa; border:1px solid rgba(96,165,250,.3)' : 'background:var(--s2); color:var(--muted); border:1px solid var(--border2)'"
                          x-text="p.tipo === 'principal' ? 'Principal' : 'Secundária'"></span>
                    <span class="font-bold" style="color:var(--text)" x-text="p.nome_ficticio || 'Persona'"></span>
                    <span class="text-xs ml-2" style="color:var(--muted)" x-text="p.idade_genero ? '· ' + p.idade_genero : ''"></span>
                </div>
                <button type="button" @click="deletePersona(p.id)"
                        class="text-xs font-mono transition-colors" style="color:var(--muted)"
                        onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                    Remover
                </button>
            </div>
            <div class="grid grid-cols-3 gap-3 text-xs">
                <div><span style="color:var(--muted)">Cargo/Setor:</span> <span style="color:var(--text)" x-text="p.cargo_setor || '—'"></span></div>
                <div><span style="color:var(--muted)">Renda/Contexto:</span> <span style="color:var(--text)" x-text="p.renda_contexto || '—'"></span></div>
                <div><span style="color:var(--muted)">Como se informa:</span> <span style="color:var(--muted2)" x-text="p.como_se_informa || '—'"></span></div>
            </div>
            <template x-if="p.insight_persona">
                <div class="mt-3 px-3 py-2 text-xs italic" style="border-left:3px solid #60a5fa; background:var(--s2); color:var(--muted2)" x-text="p.insight_persona"></div>
            </template>
        </div>
    </template>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 01 · DIAGNÓSTICO DE COMUNICAÇÃO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd01_comunicacao'" x-cloak>
    <div class="section-hd" style="color:#60a5fa">Bloco 01 — Diagnóstico de Comunicação Atual</div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">5.1 · Como a marca se comunica hoje</label>
            <div class="form-guide">Análise objetiva: canais usados, tipo de conteúdo, tom de voz, consistência visual, frequência.</div>
            <x-rich-editor name="bloco01_comunicacao_atual" :value="old('bloco01_comunicacao_atual', $dossier->bloco01_comunicacao_atual ?? '')" />
        </div>
        <div class="form-group">
            <label class="form-label">5.2 · Como a marca é percebida hoje</label>
            <div class="form-guide">O que alguém de fora perceberia ao ver a comunicação atual pela primeira vez.</div>
            <x-rich-editor name="bloco01_percepcao_atual" :value="old('bloco01_percepcao_atual', $dossier->bloco01_percepcao_atual ?? '')" />
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">5.3 · O gap de percepção <span class="form-tag">síntese crítica</span></label>
        <div class="form-guide">A diferença entre o que a empresa realmente é e o que parece ser pela comunicação. Este é o problema a resolver.</div>
        <x-rich-editor name="bloco01_gap_percepcao" :value="old('bloco01_gap_percepcao', $dossier->bloco01_gap_percepcao ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 02 · PROBLEMA CENTRAL
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd02_problema'" x-cloak>
    <div class="section-hd" style="color:#fbbf24">Bloco 02 — Problema Central de Comunicação</div>

    <div class="form-group">
        <label class="form-label">O problema central <span class="form-tag">uma frase</span></label>
        <div class="form-guide">Começa com "Como fazer com que..." ou "Como transformar a percepção de...". Se não couber em uma frase, ainda não está suficientemente claro.</div>
        <x-rich-editor name="bloco02_problema" :value="old('bloco02_problema', $dossier->bloco02_problema ?? '')" />
    </div>

    <div class="form-group">
        <label class="form-label">Por que esse é o problema real</label>
        <div class="form-guide">A justificativa que conecta o problema ao diagnóstico. Por que resolver isso é mais importante do que qualquer outro problema identificado.</div>
        <x-rich-editor name="bloco02_problema_justificativa" :value="old('bloco02_problema_justificativa', $dossier->bloco02_problema_justificativa ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 02 · OPORTUNIDADE
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd02_oportunidade'" x-cloak>
    <div class="section-hd" style="color:#fbbf24">Bloco 02 — Oportunidade Estratégica</div>

    <div class="form-group">
        <label class="form-label">O território disponível</label>
        <div class="form-guide">Formulado como: "Existe um espaço não ocupado relacionado a [território] que esta marca tem credibilidade para ocupar porque [razão]."</div>
        <x-rich-editor name="bloco02_oportunidade" :value="old('bloco02_oportunidade', $dossier->bloco02_oportunidade ?? '')" min-height="200px" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 02 · INSIGHT CENTRAL
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd02_insight'" x-cloak>
    <div class="section-hd" style="color:#fbbf24">Bloco 02 — Insight Central</div>

    <div class="insight-box mb-4">
        <div class="text-xs font-mono uppercase tracking-widest mb-3" style="color:#fbbf24">Insight Central</div>
        <x-rich-editor name="bloco02_insight" :value="old('bloco02_insight', $dossier->bloco02_insight ?? '')" />
    </div>

    <div class="form-group">
        <label class="form-label">Por que esse insight é verdadeiro</label>
        <div class="form-guide">A evidência que sustenta o insight — algo do kickoff, comportamento observado no mercado ou dado da pesquisa.</div>
        <x-rich-editor name="bloco02_insight_sustentacao" :value="old('bloco02_insight_sustentacao', $dossier->bloco02_insight_sustentacao ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 02 · OBJETIVOS
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd02_objetivos'" x-cloak>
    <div class="section-hd" style="color:#fbbf24">Bloco 02 — Objetivos de Comunicação</div>

    <div class="form-group">
        <label class="form-label">O que o mercado deve pensar</label>
        <div class="form-guide">A mudança de percepção: "Antes pensava X, depois deve pensar Y."</div>
        <x-rich-editor name="bloco02_objetivos[pensar]" :value="$b02Obj['pensar'] ?? ''" />
    </div>
    <div class="form-group">
        <label class="form-label">O que o mercado deve sentir</label>
        <div class="form-guide">O estado emocional que a marca deve provocar — confiança, desejo, identificação, admiração.</div>
        <x-rich-editor name="bloco02_objetivos[sentir]" :value="$b02Obj['sentir'] ?? ''" />
    </div>
    <div class="form-group">
        <label class="form-label">O que o mercado deve fazer <span class="form-tag">ação concreta</span></label>
        <div class="form-guide">O comportamento concreto que a comunicação deve estimular.</div>
        <x-rich-editor name="bloco02_objetivos[fazer]" :value="$b02Obj['fazer'] ?? ''" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 03 · PROPÓSITO, VISÃO, MISSÃO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd03_base'" x-cloak>
    <div class="section-hd" style="color:#d63eaa">Bloco 03 — Propósito, Visão e Missão</div>

    <div class="form-group">
        <label class="form-label">Propósito <span class="form-tag">por que existe</span></label>
        <div class="form-guide">A razão de existir além do lucro. Começa com "Existimos para..." ou "Acreditamos que...". Não pode ser sobre o produto.</div>
        <x-rich-editor name="bloco03_proposito" :value="old('bloco03_proposito', $dossier->bloco03_proposito ?? '')" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">Visão <span class="form-tag">onde quer chegar</span></label>
            <div class="form-guide">O futuro desejado. Ambicioso mas alcançável.</div>
            <x-rich-editor name="bloco03_visao" :value="old('bloco03_visao', $dossier->bloco03_visao ?? '')" />
        </div>
        <div class="form-group">
            <label class="form-label">Missão <span class="form-tag">o que faz hoje</span></label>
            <div class="form-guide">O que a marca faz de concreto para avançar em direção à visão.</div>
            <x-rich-editor name="bloco03_missao" :value="old('bloco03_missao', $dossier->bloco03_missao ?? '')" />
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 03 · VALORES
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd03_valores'" x-cloak>
    <div class="section-hd" style="color:#d63eaa">Bloco 03 — Valores</div>
    <p class="section-desc mb-4">Cada valor deve ter nome e definição específica. "Qualidade" e "Inovação" são genéricos — especifique o que significam para esta empresa.</p>

    <template x-for="(v, i) in valores" :key="i">
        <div class="card mb-3">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <input type="text" x-model="v.nome" @input="markDirty()" placeholder="Nome do valor"
                           class="form-input mb-2 font-bold" style="color:var(--text)">
                    <textarea x-model="v.descricao" @input="markDirty()" placeholder="O que esse valor significa na prática para esta marca..."
                              class="form-textarea" rows="3"></textarea>
                </div>
                <button type="button" @click="removeValor(i)" class="text-xs mt-1 transition-colors"
                        style="color:var(--muted)" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
            </div>
        </div>
    </template>

    <button type="button" @click="addValor()"
            class="w-full py-2 text-xs font-mono transition-colors"
            style="border:1px dashed var(--border2); color:var(--muted)"
            onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
            onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted)'">
        + Adicionar Valor
    </button>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 03 · POSICIONAMENTO E PROMESSA
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd03_posicionamento'" x-cloak>
    <div class="section-hd" style="color:#d63eaa">Bloco 03 — Posicionamento e Promessa</div>

    <div class="card mb-4">
        <div class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Fórmula de Posicionamento</div>
        <div class="text-sm leading-loose" style="color:var(--muted2)">
            Para <input type="text" name="bloco03_posicionamento_formula[publico]" class="form-input-inline" placeholder="público-alvo" value="{{ $b03Pos['publico'] ?? '' }}">,
            a <input type="text" name="bloco03_posicionamento_formula[marca]" class="form-input-inline" placeholder="nome da marca" value="{{ $b03Pos['marca'] ?? '' }}">
            é a <input type="text" name="bloco03_posicionamento_formula[categoria]" class="form-input-inline" placeholder="categoria" value="{{ $b03Pos['categoria'] ?? '' }}">
            que <input type="text" name="bloco03_posicionamento_formula[diferencial]" class="form-input-inline" style="width:200px" placeholder="diferencial real" value="{{ $b03Pos['diferencial'] ?? '' }}">
            porque <input type="text" name="bloco03_posicionamento_formula[razao]" class="form-input-inline" style="width:200px" placeholder="razão para acreditar" value="{{ $b03Pos['razao'] ?? '' }}">.
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Posicionamento em texto corrido</label>
        <div class="form-guide">Com o contexto necessário para que qualquer membro do time entenda onde a marca se coloca e por quê.</div>
        <x-rich-editor name="bloco03_posicionamento_texto" :value="old('bloco03_posicionamento_texto', $dossier->bloco03_posicionamento_texto ?? '')" />
    </div>

    <div class="form-group">
        <label class="form-label">Promessa central <span class="form-tag">o benefício mais importante</span></label>
        <div class="form-guide">O benefício mais importante que a marca entrega. Não é slogan — é interno, orienta a criação.</div>
        <x-rich-editor name="bloco03_promessa" :value="old('bloco03_promessa', $dossier->bloco03_promessa ?? '')" />
    </div>

    <div class="form-group">
        <label class="form-label">Razões para acreditar <span class="form-tag">provas da promessa</span></label>
        <div class="form-guide">Fatos concretos que tornam a promessa crível. Dados, histórico, certificações, estrutura, comportamento verificável.</div>
        <x-rich-editor name="bloco03_razoes" :value="old('bloco03_razoes', $dossier->bloco03_razoes ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 03 · PERSONALIDADE E ARQUÉTIPO
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd03_personalidade'" x-cloak>
    <div class="section-hd" style="color:#d63eaa">Bloco 03 — Personalidade e Arquétipo</div>

    <div class="form-group">
        <label class="form-label">Personalidade da marca <span class="form-tag">4–6 adjetivos com exemplos</span></label>
        <div class="form-guide">Cada adjetivo com exemplo concreto. "Confiável" é genérico. "Confiável como um veterinário de zona rural — direto, fala o que precisa ser feito" é específico.</div>
        <x-rich-editor name="bloco03_personalidade" :value="old('bloco03_personalidade', $dossier->bloco03_personalidade ?? '')" min-height="200px" />
    </div>

    <div class="card">
        <div class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Arquétipo da Marca</div>
        <div class="form-guide mb-3">Os 12 arquétipos de Pearson e Mark: Herói, Fora-da-lei, Mago, Homem Comum, Inocente, Explorador, Sábio, Amante, Bobo da Corte, Prestativo, Criador, Governante.</div>
        <div class="grid grid-cols-4 gap-3 mb-3">
            <div class="col-span-1">
                <label class="form-label">Ícone / Símbolo</label>
                <input type="text" name="bloco03_arquetipo[icone]" class="form-input" placeholder="🏛" value="{{ $b03Arq['icone'] ?? '' }}">
            </div>
            <div class="col-span-3">
                <label class="form-label">Nome do Arquétipo</label>
                <input type="text" name="bloco03_arquetipo[nome]" class="form-input" placeholder="Ex: O Sábio" value="{{ $b03Arq['nome'] ?? '' }}">
            </div>
        </div>
        <div>
            <label class="form-label">Justificativa e como se manifesta</label>
            <x-rich-editor name="bloco03_arquetipo[descricao]" :value="$b03Arq['descricao'] ?? ''" />
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 03 · ESSÊNCIA
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd03_essencia'" x-cloak>
    <div class="section-hd" style="color:#d63eaa">Bloco 03 — Essência da Marca</div>

    <div class="insight-box mb-4" style="border-left-color:#d63eaa">
        <div class="text-xs font-mono uppercase tracking-widest mb-3" style="color:#d63eaa">Essência</div>
        <input type="text" name="bloco03_essencia" class="w-full bg-transparent border-0 outline-none text-2xl font-black"
               style="color:var(--text); font-family:'Syne',sans-serif"
               placeholder="A essência em uma palavra ou frase curta"
               value="{{ old('bloco03_essencia', $dossier->bloco03_essencia) }}">
    </div>

    <div class="form-group">
        <label class="form-label">O que essa essência significa</label>
        <div class="form-guide">Como ela conecta todos os elementos — propósito, posicionamento, personalidade e arquétipo. Esta é a âncora criativa: se uma peça não reflete essa essência, está errada.</div>
        <x-rich-editor name="bloco03_essencia_descricao" :value="old('bloco03_essencia_descricao', $dossier->bloco03_essencia_descricao ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 04 · TOM DE VOZ
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd04_tom'" x-cloak>
    <div class="section-hd" style="color:#7b3fe4">Bloco 04 — Tom de Voz</div>

    <div class="form-group">
        <label class="form-label">Definição do tom de voz</label>
        <div class="form-guide">3 a 5 dimensões: registro, velocidade, emoção, autoridade. Use contrastes: "É X, mas não é Y".</div>
        <x-rich-editor name="bloco04_tom_voz" :value="old('bloco04_tom_voz', $dossier->bloco04_tom_voz ?? '')" />
    </div>

    <div class="mb-4">
        <label class="form-label">Exemplos correto vs incorreto</label>
        <div class="form-guide mb-3">Use a mesma mensagem escrita de duas formas — dentro e fora do tom.</div>

        <template x-for="(ex, i) in exemplosTom" :key="i">
            <div class="grid grid-cols-2 gap-3 mb-2">
                <div class="p-3" style="background:rgba(52,211,153,.05); border:1px solid rgba(52,211,153,.2)">
                    <div class="text-xs font-mono uppercase tracking-widest mb-2" style="color:#34d399">✓ Dentro do tom</div>
                    <textarea x-model="ex.correto" @input="markDirty()" class="w-full bg-transparent border-0 outline-none text-xs resize-none" rows="3" style="color:var(--text)"></textarea>
                </div>
                <div class="p-3 relative" style="background:rgba(248,113,113,.05); border:1px solid rgba(248,113,113,.2)">
                    <div class="text-xs font-mono uppercase tracking-widest mb-2" style="color:#f87171">✗ Fora do tom</div>
                    <textarea x-model="ex.incorreto" @input="markDirty()" class="w-full bg-transparent border-0 outline-none text-xs resize-none" rows="3" style="color:var(--text)"></textarea>
                    <button type="button" @click="removeExemplo(i)" class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                </div>
            </div>
        </template>

        <button type="button" @click="addExemplo()"
                class="w-full py-2 text-xs font-mono transition-colors mt-1"
                style="border:1px dashed var(--border2); color:var(--muted)">
            + Adicionar par de exemplos
        </button>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 04 · TERRITÓRIO VISUAL
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd04_visual'" x-cloak>
    <div class="section-hd" style="color:#7b3fe4">Bloco 04 — Território Visual</div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">Paleta de cores</label>
            <div class="form-guide">Cores primárias com códigos. Cores de apoio. Como usar cada uma.</div>
            <x-rich-editor name="bloco04_territorio_visual[cores]" :value="$b04Vis['cores'] ?? ''" />
        </div>
        <div class="form-group">
            <label class="form-label">Tipografia</label>
            <div class="form-guide">Família tipográfica principal e secundária. Hierarquia de uso.</div>
            <x-rich-editor name="bloco04_territorio_visual[tipografia]" :value="$b04Vis['tipografia'] ?? ''" />
        </div>
        <div class="form-group">
            <label class="form-label">Estilo fotográfico</label>
            <div class="form-guide">Enquadramento, luz, paleta, tipo de cena. O que mostrar e o que evitar.</div>
            <x-rich-editor name="bloco04_territorio_visual[foto]" :value="$b04Vis['foto'] ?? ''" />
        </div>
        <div class="form-group">
            <label class="form-label">Linguagem gráfica</label>
            <div class="form-guide">Ícones, ilustrações, grafismos, textura. Estilo geral das peças.</div>
            <x-rich-editor name="bloco04_territorio_visual[linguagem]" :value="$b04Vis['linguagem'] ?? ''" />
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 04 · LINHA DE LINGUAGEM
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd04_linguagem'" x-cloak>
    <div class="section-hd" style="color:#7b3fe4">Bloco 04 — Linha de Linguagem</div>

    <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="form-group">
            <label class="form-label" style="color:#7b3fe4">Palavras prioritárias</label>
            <div class="form-guide">Termos que carregam o posicionamento. Devem aparecer com frequência.</div>
            <x-rich-editor name="bloco04_palavras_prioritarias" :value="old('bloco04_palavras_prioritarias', $dossier->bloco04_palavras_prioritarias ?? '')" min-height="200px" />
        </div>
        <div class="form-group">
            <label class="form-label" style="color:var(--muted2)">Palavras secundárias</label>
            <div class="form-guide">Relevantes para a categoria, mas não são o foco da narrativa.</div>
            <x-rich-editor name="bloco04_palavras_secundarias" :value="old('bloco04_palavras_secundarias', $dossier->bloco04_palavras_secundarias ?? '')" min-height="200px" />
        </div>
        <div class="form-group">
            <label class="form-label" style="color:#f87171">Palavras a evitar</label>
            <div class="form-guide">Genéricas, saturadas ou que contradizem o posicionamento.</div>
            <x-rich-editor name="bloco04_palavras_evitar" :value="old('bloco04_palavras_evitar', $dossier->bloco04_palavras_evitar ?? '')" min-height="200px" />
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Frases-âncora</label>
        <div class="form-guide">3 a 5 frases que encapsulam o posicionamento. Não são slogans — são o padrão que outras frases devem seguir.</div>
        <x-rich-editor name="bloco04_frases_ancora" :value="old('bloco04_frases_ancora', $dossier->bloco04_frases_ancora ?? '')" />
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     DOSSIÊ — 04 · MANDATÓRIOS
═══════════════════════════════════════════════════════ --}}
<div x-show="mode === 'dossie' && section === 'd04_mandatorios'" x-cloak>
    <div class="section-hd" style="color:#7b3fe4">Bloco 04 — Mandatórios e Restrições</div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">O que a comunicação deve ter</label>
            <div class="form-guide">O que não pode faltar em nenhuma peça desta marca.</div>
            <x-rich-editor name="bloco04_mandatorios" :value="old('bloco04_mandatorios', $dossier->bloco04_mandatorios ?? '')" min-height="200px" />
        </div>
        <div class="form-group">
            <label class="form-label">O que a comunicação não deve fazer</label>
            <div class="form-guide">Temas, abordagens, formatos ou associações que contradizem o posicionamento.</div>
            <x-rich-editor name="bloco04_restricoes" :value="old('bloco04_restricoes', $dossier->bloco04_restricoes ?? '')" min-height="200px" />
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">O teste final</label>
        <div class="form-guide">Uma ou duas perguntas que qualquer pessoa do time pode fazer para saber se uma peça está dentro do território da marca.</div>
        <x-rich-editor name="bloco04_teste_final" :value="old('bloco04_teste_final', $dossier->bloco04_teste_final ?? '')" />
    </div>
</div>

                </form>
            </div>{{-- /flex-1 --}}
        </div>{{-- /flex gap --}}
    </div>{{-- /x-data --}}

@push('scripts')
<script>
function dossierEditor(initialCompetitors, initialPersonas, initialValores, initialExemplos) {
    return {
        mode: 'roteiro',
        section: 'config',
        saving: false,
        savedAt: '',
        dirty: false,
        timer: null,

        competitors: initialCompetitors.map(c => ({ ...c, _editing: false })),
        personas:    initialPersonas,
        addingCompetitor: false,
        addingPersona:    false,

        valores:     initialValores.length ? initialValores : [],
        exemplosTom: initialExemplos.length ? initialExemplos : [],

        newCompetitor: { nome: '', o_que_comunica: '', como_se_posiciona: '', o_que_nao_ocupa: '' },
        newPersona:    { tipo: 'principal', nome_ficticio: '', idade_genero: '', cargo_setor: '', renda_contexto: '', como_se_informa: '', o_que_valida_compra: '', dores_principais: '', o_que_motiva: '', o_que_nunca_diria: '', insight_persona: '' },

        init() {
            // no-op: auto-save on input/change via @input.capture
        },

        markDirty() {
            this.dirty = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 3000);
        },

        async save() {
            if (this.saving) return;
            this.saving = true;
            const form = document.getElementById('dossier-form');
            const data = new FormData(form);
            data.append('_method', 'PATCH');
            try {
                const res = await fetch('{{ route("clients.dossiers.update", [$client, $dossier]) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: data,
                });
                if (res.ok) {
                    const json = await res.json();
                    this.savedAt = json.saved_at;
                    this.dirty = false;
                } else {
                    const err = await res.json().catch(() => ({}));
                    console.error('[dossier save] HTTP ' + res.status, err.error ?? '');
                }
            } catch(e) { console.error('save error', e); }
            this.saving = false;
        },

        addValor() {
            this.valores.push({ nome: '', descricao: '' });
            this.markDirty();
        },
        removeValor(i) {
            this.valores.splice(i, 1);
            this.markDirty();
        },

        addExemplo() {
            this.exemplosTom.push({ correto: '', incorreto: '' });
            this.markDirty();
        },
        removeExemplo(i) {
            this.exemplosTom.splice(i, 1);
            this.markDirty();
        },

        resetNewCompetitor() {
            this.newCompetitor = { nome: '', o_que_comunica: '', como_se_posiciona: '', o_que_nao_ocupa: '' };
        },
        resetNewPersona() {
            this.newPersona = { tipo: 'principal', nome_ficticio: '', idade_genero: '', cargo_setor: '', renda_contexto: '', como_se_informa: '', o_que_valida_compra: '', dores_principais: '', o_que_motiva: '', o_que_nunca_diria: '', insight_persona: '' };
        },

        async saveCompetitor() {
            if (!this.newCompetitor.nome.trim()) return;
            const res = await fetch('{{ route("dossiers.competitors.store", [$client, $dossier]) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify(this.newCompetitor),
            });
            if (res.ok) {
                const c = await res.json();
                this.competitors.push({ ...c, _editing: false });
                this.resetNewCompetitor();
                this.addingCompetitor = false;
            }
        },

        async updateCompetitor(c) {
            c._editing = false;
            await fetch('{{ route("dossiers.competitors.update", [$client, $dossier, '__ID__']) }}'.replace('__ID__', c.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ ...c, _method: 'PATCH' }),
            });
        },

        async deleteCompetitor(id) {
            if (!confirm('Remover este concorrente?')) return;
            const res = await fetch('{{ route("dossiers.competitors.destroy", [$client, $dossier, '__ID__']) }}'.replace('__ID__', id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ _method: 'DELETE' }),
            });
            if (res.ok) this.competitors = this.competitors.filter(c => c.id !== id);
        },

        async savePersona() {
            const res = await fetch('{{ route("dossiers.personas.store", [$client, $dossier]) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify(this.newPersona),
            });
            if (res.ok) {
                const p = await res.json();
                this.personas.push(p);
                this.resetNewPersona();
                this.addingPersona = false;
            }
        },

        async deletePersona(id) {
            if (!confirm('Remover esta persona?')) return;
            const res = await fetch('{{ route("dossiers.personas.destroy", [$client, $dossier, '__ID__']) }}'.replace('__ID__', id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ _method: 'DELETE' }),
            });
            if (res.ok) this.personas = this.personas.filter(p => p.id !== id);
        },
    };
}
</script>

<style>
.section-hd {
    font-family: 'Syne', sans-serif;
    font-size: 1.125rem;
    font-weight: 800;
    letter-spacing: -.02em;
    color: var(--text);
    margin-bottom: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border2);
    margin-bottom: 20px;
}
.section-tag {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .16em;
    text-transform: uppercase;
    margin-left: 10px;
    opacity: .7;
}
.section-desc {
    font-size: 13px;
    color: var(--muted2);
    line-height: 1.65;
    margin-bottom: 20px;
    margin-top: -12px;
}
.form-group { margin-bottom: 20px; }
.form-group:last-child { margin-bottom: 0; }
.form-label {
    display: block;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
}
.form-tag {
    font-size: 8px;
    letter-spacing: .1em;
    padding: 1px 6px;
    border: 1px solid var(--border2);
    color: var(--muted);
    margin-left: 6px;
    vertical-align: middle;
}
.form-guide {
    font-size: 11px;
    color: var(--muted);
    font-style: italic;
    line-height: 1.55;
    margin-bottom: 8px;
    padding: 8px 12px;
    background: var(--s2);
    border-left: 2px solid var(--border2);
}
.form-input {
    width: 100%;
    background: var(--s2);
    border: 1px solid var(--border2);
    color: var(--text);
    font-size: 13px;
    padding: 9px 12px;
    transition: border-color .15s;
}
.form-input:focus { outline: none; border-color: var(--purple); }
.form-textarea {
    width: 100%;
    background: var(--s2);
    border: 1px solid var(--border2);
    color: var(--text);
    font-size: 13px;
    padding: 10px 12px;
    line-height: 1.65;
    resize: vertical;
    transition: border-color .15s;
}
.form-textarea:focus { outline: none; border-color: var(--purple); }
.form-input-inline {
    display: inline;
    background: var(--s2);
    border: none;
    border-bottom: 1px solid var(--border2);
    color: var(--text);
    font-size: 13px;
    padding: 2px 6px;
    width: 140px;
    transition: border-color .15s;
}
.form-input-inline:focus { outline: none; border-bottom-color: var(--purple); }
.insight-box {
    background: var(--s2);
    border: 1px solid rgba(251,191,36,.2);
    border-left: 4px solid #fbbf24;
    padding: 20px;
}
</style>
@endpush
</x-app-layout>
