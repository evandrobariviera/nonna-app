<x-app-layout>
    <x-slot name="header">Diagnóstico — {{ $client->company_name }}</x-slot>

    {{-- BREADCRUMB + STATUS --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('clients.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Clientes</a>
            <span style="color:var(--border2)">/</span>
            <a href="{{ route('clients.show', [$client, 'tab' => 'diagnosticos']) }}"
               class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                {{ $client->company_name }}
            </a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">
                Diagnóstico v{{ $diagnostic->version }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-{{ $diagnostic->statusColor() }}">{{ $diagnostic->statusLabel() }}</span>
            @if($diagnostic->status === 'draft')
                <form method="POST" action="{{ route('clients.diagnostics.complete', [$client, $diagnostic]) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 text-xs font-bold transition-colors"
                            style="border:1px solid var(--green); color:var(--green)"
                            onmouseover="this.style.background='rgba(52,211,153,.1)'"
                            onmouseout="this.style.background='transparent'">
                        Marcar Concluído
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('clients.diagnostics.reopen', [$client, $diagnostic]) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 text-xs font-bold transition-colors"
                            style="border:1px solid var(--border2); color:var(--muted2)"
                            onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                            onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                        Reabrir Edição
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @php
        $sec01 = $diagnostic->sec01_briefing ?? [];
        $sec02 = $diagnostic->sec02_marketing ?? [];
        $sec03 = $diagnostic->sec03_audit ?? [];
        $sec04 = $diagnostic->sec04_competition ?? [];
        $sec05 = $diagnostic->sec05_persona ?? [];
        $sec06 = $diagnostic->sec06_synthesis ?? [];
        $sections = \App\Models\ClientDiagnostic::$sections;
    @endphp

    {{-- LAYOUT: SIDEBAR + CONTENT --}}
    <div x-data="{ section: '{{ $currentSection }}' }" class="flex gap-6">

        {{-- SIDEBAR NAV --}}
        <div class="flex-shrink-0" style="width:220px">
            <div class="card overflow-hidden">
                <div class="px-4 py-3" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Seções</p>
                </div>
                @foreach($sections as $key => $meta)
                    <button type="button"
                            @click="section = '{{ $key }}'"
                            :class="section === '{{ $key }}' ? 'active' : ''"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left transition-all"
                            style="border-bottom:1px solid var(--border)"
                            :style="section === '{{ $key }}' ? 'background:rgba(106,90,205,.08); border-left:2px solid var(--purple)' : 'border-left:2px solid transparent'">
                        <span class="text-base" style="line-height:1">{{ $meta['icon'] }}</span>
                        <div>
                            <p class="text-xs font-mono" style="color:var(--muted)">{{ $meta['num'] }}</p>
                            <p class="text-xs font-semibold leading-tight"
                               :style="section === '{{ $key }}' ? 'color:var(--purple)' : 'color:var(--muted2)'">
                                {{ $meta['label'] }}
                            </p>
                        </div>
                        @if($diagnostic->isSectionFilled($key))
                            <span class="ml-auto text-xs" style="color:var(--green)">✓</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- MAIN CONTENT --}}
        <div class="flex-1 min-w-0">

            {{-- ════ SEÇÃO 01 — BRIEFING DE NEGÓCIO ════ --}}
            <div x-show="section === 'sec01'" x-cloak>
                <div class="card p-6 mb-4" style="border-left:3px solid var(--purple)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">01 — Entrevista de Imersão</p>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text)">Briefing de Negócio</h2>
                    <p class="text-xs" style="color:var(--muted)">Conduzir com o fundador ou decisor. Objetivo: entender o negócio antes de falar de marketing.</p>
                </div>

                <form method="POST" action="{{ route('clients.diagnostics.update', [$client, $diagnostic]) }}" class="space-y-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_section" value="sec01">

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">História e Identidade</p>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Como e por que a empresa foi fundada? <span style="color:var(--orange)">*</span></label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Peça para contar desde o início. O que motivou — pode ser o propósito.</p>
                            <textarea name="sec01[historia]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['historia'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">O que vocês vendem / entregam?</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Produtos, serviços, formatos, preços, diferenciais técnicos.</p>
                            <textarea name="sec01[produto]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['produto'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">O que diferencia dos concorrentes?</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Aprofunde além de "qualidade e atendimento" — o que especificamente é diferente?</p>
                            <textarea name="sec01[diferencial]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['diferencial'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Momento Atual</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Faturamento mensal aprox.</label>
                                <input type="text" name="sec01[faturamento]" value="{{ $sec01['faturamento'] ?? '' }}"
                                       placeholder="Faixa aproximada"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Funcionários / colaboradores</label>
                                <input type="text" name="sec01[colaboradores]" value="{{ $sec01['colaboradores'] ?? '' }}"
                                       placeholder="Ex: 5 fixos + 2 freelancers"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Principal desafio do negócio hoje</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Não marketing — o negócio em si. Pode ser operacional, financeiro, de pessoas.</p>
                            <textarea name="sec01[desafio]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['desafio'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Como a empresa gera clientes hoje?</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Indicação, Google orgânico, redes sociais, feiras, parceiros...</p>
                            <textarea name="sec01[aquisicao]" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['aquisicao'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Visão e Objetivos</p>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Onde quer estar em 2 anos?</label>
                            <textarea name="sec01[visao]" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Faturamento, presença, equipe, expansão...">{{ $sec01['visao'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Objetivo número 1 com o marketing agora</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Gerar leads? Reposicionar a marca? Aumentar ticket médio? Entrar em novo mercado?</p>
                            <textarea name="sec01[obj_mkt]" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['obj_mkt'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Como saberiam que o marketing está funcionando?</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Revela como o cliente mede valor — essencial para alinhar expectativas.</p>
                            <textarea name="sec01[sucesso]" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec01['sucesso'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="card p-5">
                        <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Checklist — Perguntas cobertas</p>
                        @php
                            $cl01 = $sec01['checklist'] ?? [];
                            $checklist01 = [
                                'historia_fundacao'       => 'História e fundação da empresa',
                                'portfolio_completo'      => 'Portfólio completo de produtos/serviços',
                                'melhor_cliente'          => 'Melhor cliente atual (quem é, por que compra)',
                                'conquistas_12m'          => 'Maiores conquistas dos últimos 12 meses',
                                'tentativas_mkt'          => 'O que já tentaram em marketing e não funcionou',
                                'concorrentes_admirados'  => 'Concorrentes que admiram',
                                'marcas_referencia'       => 'Marcas de referência visual / comunicação',
                                'restricoes_comunicacao'  => 'Restrições de comunicação (o que NÃO fazer)',
                            ];
                        @endphp
                        <div class="space-y-1">
                            @foreach($checklist01 as $key => $label)
                                <label class="flex items-center gap-3 px-3 py-2.5 cursor-pointer transition-colors"
                                       style="border:1px solid var(--border); background:var(--s3)"
                                       onmouseover="this.style.borderColor='var(--border2)'"
                                       onmouseout="this.style.borderColor='var(--border)'">
                                    <input type="hidden" name="sec01[checklist][{{ $key }}]" value="0">
                                    <input type="checkbox" name="sec01[checklist][{{ $key }}]" value="1"
                                           {{ ($cl01[$key] ?? false) ? 'checked' : '' }}
                                           class="w-4 h-4 accent-[var(--purple)]">
                                    <span class="text-sm" style="color:var(--muted2)">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                            Salvar Seção 01 →
                        </button>
                    </div>
                </form>
            </div>

            {{-- ════ SEÇÃO 02 — CENÁRIO DE MARKETING ════ --}}
            <div x-show="section === 'sec02'" x-cloak>
                <div class="card p-6 mb-4" style="border-left:3px solid var(--purple)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">02 — Análise Interna</p>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text)">Cenário de Marketing Atual</h2>
                    <p class="text-xs" style="color:var(--muted)">O que já existe em comunicação e estrutura digital. Parte com o cliente, parte análise interna.</p>
                </div>

                <form method="POST" action="{{ route('clients.diagnostics.update', [$client, $diagnostic]) }}" class="space-y-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_section" value="sec02">

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Presença Digital Atual</p>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach([
                                'url_site'     => ['URL do Site', 'https://...'],
                                'instagram'    => ['Instagram', '@usuario · N° de seguidores'],
                                'facebook'     => ['Facebook', 'URL ou nome da página'],
                                'linkedin'     => ['LinkedIn', 'URL da empresa'],
                                'gmb'          => ['Google Meu Negócio', 'Nota atual + nº de avaliações'],
                                'outros_canais'=> ['Outros Canais', 'YouTube, TikTok, WhatsApp Business...'],
                            ] as $field => [$label, $placeholder])
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">{{ $label }}</label>
                                    <input type="text" name="sec02[{{ $field }}]" value="{{ $sec02[$field] ?? '' }}"
                                           placeholder="{{ $placeholder }}"
                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Histórico de Tráfego Pago</p>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Se já rodaram: qual foi o resultado?</label>
                            <textarea name="sec02[hist_trafego]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Experiência anterior com tráfego pago...">{{ $sec02['hist_trafego'] ?? '' }}</textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Verba mensal disponível para mídia</label>
                                <input type="text" name="sec02[verba_midia]" value="{{ $sec02['verba_midia'] ?? '' }}"
                                       placeholder="Ex: R$ 2.000 / mês"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Status das contas de anúncio</label>
                                <input type="text" name="sec02[acesso_contas]" value="{{ $sec02['acesso_contas'] ?? '' }}"
                                       placeholder="Ex: Meta existente, Google criar"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                    </div>

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Estrutura de Vendas</p>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Como é o processo de venda hoje?</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">Como o lead chega, como é atendido, como fecha...</p>
                            <textarea name="sec02[processo_venda]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui...">{{ $sec02['processo_venda'] ?? '' }}</textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Ferramenta de CRM / funil</label>
                                <input type="text" name="sec02[crm_ferramenta]" value="{{ $sec02['crm_ferramenta'] ?? '' }}"
                                       placeholder="Ex: RD Station, planilha, WhatsApp..."
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Taxa de conversão de lead (estimativa)</label>
                                <input type="text" name="sec02[taxa_conversao]" value="{{ $sec02['taxa_conversao'] ?? '' }}"
                                       placeholder="Ex: 1 em cada 5 contatos fecha"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                    </div>

                    <div class="card p-5">
                        <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Checklist — Informações coletadas</p>
                        @php
                            $cl02 = $sec02['checklist'] ?? [];
                            $checklist02 = [
                                'urls_mapeados'            => 'URLs de todos os canais digitais mapeados',
                                'acesso_contas_confirmado' => 'Acesso ou status das contas de mídia confirmados',
                                'historico_trafego_doc'    => 'Histórico de tráfego pago documentado',
                                'verba_confirmada'         => 'Verba de mídia confirmada e alinhada',
                                'processo_venda_mapeado'   => 'Processo de venda e jornada do lead mapeados',
                                'pixel_google_instalados'  => 'Pixel do Meta e tag do Google instalados (ou tarefa criada)',
                            ];
                        @endphp
                        <div class="space-y-1">
                            @foreach($checklist02 as $key => $label)
                                <label class="flex items-center gap-3 px-3 py-2.5 cursor-pointer transition-colors"
                                       style="border:1px solid var(--border); background:var(--s3)">
                                    <input type="hidden" name="sec02[checklist][{{ $key }}]" value="0">
                                    <input type="checkbox" name="sec02[checklist][{{ $key }}]" value="1"
                                           {{ ($cl02[$key] ?? false) ? 'checked' : '' }}
                                           class="w-4 h-4 accent-[var(--purple)]">
                                    <span class="text-sm" style="color:var(--muted2)">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                            Salvar Seção 02 →
                        </button>
                    </div>
                </form>
            </div>

            {{-- ════ SEÇÃO 03 — AUDITORIA DE COMUNICAÇÃO ════ --}}
            <div x-show="section === 'sec03'" x-cloak>
                <div class="card p-6 mb-4" style="border-left:3px solid var(--purple)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">03 — Análise Interna da Nonna</p>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text)">Auditoria de Comunicação</h2>
                    <p class="text-xs" style="color:var(--muted)">Conduzida pela equipe Nonna após a reunião. Avalie cada canal com imparcialidade.</p>
                </div>

                <form method="POST" action="{{ route('clients.diagnostics.update', [$client, $diagnostic]) }}" class="space-y-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_section" value="sec03">

                    @php
                        $auditChannels = [
                            'site'      => ['label' => 'Site', 'items' => [
                                'clareza_valor'      => 'Clareza da proposta de valor',
                                'velocidade_mobile'  => 'Velocidade de carregamento (mobile)',
                                'ctas'               => 'CTAs — chamadas para ação',
                                'consistencia_visual'=> 'Consistência visual com a marca',
                            ]],
                            'instagram' => ['label' => 'Instagram', 'items' => [
                                'consistencia_visual'=> 'Consistência visual do feed',
                                'tom_voz'            => 'Tom de voz nas legendas',
                                'engajamento'        => 'Taxa de engajamento',
                                'frequencia'         => 'Frequência de publicação',
                                'stories_reels'      => 'Uso de Stories e Reels',
                                'bio'                => 'Bio e link otimizados',
                            ]],
                            'facebook'  => ['label' => 'Facebook',               'items' => ['score' => 'Avaliação geral']],
                            'linkedin'  => ['label' => 'LinkedIn',                'items' => ['score' => 'Avaliação geral']],
                            'youtube'   => ['label' => 'YouTube',                 'items' => ['score' => 'Avaliação geral']],
                            'gmb'       => ['label' => 'Google Meu Negócio',      'items' => ['score' => 'Avaliação geral']],
                        ];
                    @endphp

                    @foreach($auditChannels as $channel => $meta)
                        <div class="card p-5 space-y-3">
                            <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">{{ $meta['label'] }}</p>
                            @foreach($meta['items'] as $itemKey => $itemLabel)
                                @php $current = $sec03[$channel][$itemKey] ?? ''; @endphp
                                <div x-data="{ val: '{{ $current }}' }">
                                    <input type="hidden" name="sec03[{{ $channel }}][{{ $itemKey }}]" :value="val">
                                    <div class="flex items-center justify-between gap-4 px-3 py-2.5"
                                         style="background:var(--s3); border:1px solid var(--border)">
                                        <span class="text-sm" style="color:var(--muted2)">{{ $itemLabel }}</span>
                                        <div class="flex gap-1.5 flex-shrink-0">
                                            <button type="button" @click="val='ok'"
                                                    :style="val==='ok' ? 'background:rgba(52,211,153,.12);border-color:var(--green);color:var(--green)' : 'color:var(--muted)'"
                                                    style="padding:4px 10px; border:1px solid var(--border2); font-size:11px; font-weight:700; text-transform:uppercase; transition:all .15s">
                                                OK
                                            </button>
                                            <button type="button" @click="val='mediano'"
                                                    :style="val==='mediano' ? 'background:rgba(251,191,36,.12);border-color:#fbbf24;color:#fbbf24' : 'color:var(--muted)'"
                                                    style="padding:4px 10px; border:1px solid var(--border2); font-size:11px; font-weight:700; text-transform:uppercase; transition:all .15s">
                                                Mediano
                                            </button>
                                            <button type="button" @click="val='ruim'"
                                                    :style="val==='ruim' ? 'background:rgba(248,113,113,.12);border-color:var(--red);color:var(--red)' : 'color:var(--muted)'"
                                                    style="padding:4px 10px; border:1px solid var(--border2); font-size:11px; font-weight:700; text-transform:uppercase; transition:all .15s">
                                                Ruim
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Observações</label>
                                <textarea name="sec03[{{ $channel }}][notes]" rows="2"
                                          class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                          placeholder="O que mais se destaca positiva ou negativamente...">{{ $sec03[$channel]['notes'] ?? '' }}</textarea>
                            </div>
                        </div>
                    @endforeach

                    <div class="card p-5 space-y-4">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Síntese da Auditoria</p>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Maior problema de comunicação identificado</label>
                            <textarea name="sec03[maior_problema]" rows="3"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Seja direto e específico...">{{ $sec03['maior_problema'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">O que a comunicação atual faz bem</label>
                            <textarea name="sec03[pontos_pos]" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Pontos positivos para preservar e amplificar...">{{ $sec03['pontos_pos'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                            Salvar Seção 03 →
                        </button>
                    </div>
                </form>
            </div>

            {{-- ════ SEÇÃO 04 — ANÁLISE DE CONCORRÊNCIA ════ --}}
            <div x-show="section === 'sec04'" x-cloak x-data="{ addForm: false, editId: null }">
                <div class="card p-6 mb-4" style="border-left:3px solid var(--purple)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">04 — Análise de Mercado</p>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text)">Análise de Concorrência</h2>
                    <p class="text-xs" style="color:var(--muted)">Mapeie concorrentes diretos. Objetivo: encontrar o <strong style="color:var(--text)">espaço vazio</strong> que o cliente pode ocupar.</p>
                </div>

                {{-- Espaço em aberto --}}
                <form method="POST" action="{{ route('clients.diagnostics.update', [$client, $diagnostic]) }}" class="mb-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_section" value="sec04">
                    <div class="card p-5 space-y-3">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Mapa de Posicionamento</p>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">Qual espaço está em aberto no mercado?</label>
                            <p class="text-xs italic mb-2" style="color:var(--muted)">O que nenhum concorrente comunica? O cliente pode e deve ocupar.</p>
                            <textarea name="sec04[espaco_aberto]" rows="4"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Descreva o território de comunicação disponível...">{{ $sec04['espaco_aberto'] ?? '' }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                    style="background:var(--purple)">
                                Salvar
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Lista de concorrentes --}}
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Concorrentes Mapeados</p>
                    <button @click="addForm = !addForm"
                            class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background:var(--purple)">
                        + Adicionar
                    </button>
                </div>

                {{-- Form novo concorrente --}}
                <div x-show="addForm" x-cloak class="card p-5 mb-4">
                    <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Novo Concorrente</p>
                    <form method="POST" action="{{ route('diagnostics.competitors.store', [$client, $diagnostic]) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Nome <span style="color:var(--orange)">*</span></label>
                                <input type="text" name="name" required placeholder="Nome do concorrente"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Canal Principal</label>
                                <input type="text" name="main_channels" placeholder="Instagram, Google, indicação..."
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Posicionamento percebido</label>
                            <textarea name="positioning" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Qual é a mensagem central deles?"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Pontos Fortes</label>
                                <textarea name="strengths" rows="2"
                                          class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                          placeholder="O que fazem bem..."></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--orange)">Vulnerabilidade Estratégica</label>
                                <textarea name="vulnerability" rows="2"
                                          class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                          placeholder="O que ignoram, o que prometem e não entregam..."></textarea>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit"
                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                    style="background:var(--purple)">Salvar</button>
                            <button type="button" @click="addForm = false"
                                    class="px-4 py-2 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)]">Cancelar</button>
                        </div>
                    </form>
                </div>

                @if($diagnostic->competitors->isEmpty())
                    <div class="card p-6 text-center">
                        <p class="text-xs" style="color:var(--muted)">Nenhum concorrente mapeado ainda.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($diagnostic->competitors as $i => $comp)
                            <div class="card overflow-hidden" style="border-top:2px solid {{ ['var(--purple)', 'var(--orange)', 'var(--green)', '#60a5fa', '#f472b6'][$i % 5] }}">
                                <div x-show="editId !== '{{ $comp->id }}'">
                                    <div class="p-5">
                                        <div class="flex items-start justify-between mb-3">
                                            <div>
                                                <p class="font-bold text-sm" style="color:var(--text)">{{ $comp->name }}</p>
                                                @if($comp->main_channels)
                                                    <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $comp->main_channels }}</p>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="editId = '{{ $comp->id }}'"
                                                        class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">
                                                    Editar
                                                </button>
                                                <form method="POST"
                                                      action="{{ route('diagnostics.competitors.destroy', [$client, $diagnostic, $comp]) }}"
                                                      onsubmit="return confirm('Remover este concorrente?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="text-xs font-mono text-[var(--muted)] hover:text-[var(--red)] transition-colors">
                                                        Remover
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        @if($comp->positioning)
                                            <p class="text-xs mb-3" style="color:var(--muted2)">{{ $comp->positioning }}</p>
                                        @endif
                                        <div class="grid grid-cols-2 gap-3">
                                            @if($comp->strengths)
                                                <div class="p-3" style="background:var(--s3); border:1px solid var(--border)">
                                                    <p class="text-xs font-bold mb-1" style="color:var(--green)">Pontos Fortes</p>
                                                    <p class="text-xs" style="color:var(--muted2)">{{ $comp->strengths }}</p>
                                                </div>
                                            @endif
                                            @if($comp->vulnerability)
                                                <div class="p-3" style="background:var(--s3); border:1px solid var(--border)">
                                                    <p class="text-xs font-bold mb-1" style="color:var(--orange)">Vulnerabilidade</p>
                                                    <p class="text-xs" style="color:var(--muted2)">{{ $comp->vulnerability }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div x-show="editId === '{{ $comp->id }}'" x-cloak class="p-5">
                                    <form method="POST" action="{{ route('diagnostics.competitors.update', [$client, $diagnostic, $comp]) }}" class="space-y-3">
                                        @csrf @method('PATCH')
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Nome</label>
                                                <input type="text" name="name" value="{{ $comp->name }}" required
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Canal Principal</label>
                                                <input type="text" name="main_channels" value="{{ $comp->main_channels }}"
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Posicionamento</label>
                                            <textarea name="positioning" rows="2"
                                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y">{{ $comp->positioning }}</textarea>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Pontos Fortes</label>
                                                <textarea name="strengths" rows="2"
                                                          class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y">{{ $comp->strengths }}</textarea>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--orange)">Vulnerabilidade</label>
                                                <textarea name="vulnerability" rows="2"
                                                          class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y">{{ $comp->vulnerability }}</textarea>
                                            </div>
                                        </div>
                                        <div class="flex gap-3">
                                            <button type="submit"
                                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                                    style="background:var(--purple)">Salvar</button>
                                            <button type="button" @click="editId = null"
                                                    class="px-4 py-2 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)]">Cancelar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ════ SEÇÃO 05 — PÚBLICO & PERSONAS ════ --}}
            <div x-show="section === 'sec05'" x-cloak x-data="{ addForm: false, editId: null }">
                <div class="card p-6 mb-4" style="border-left:3px solid var(--purple)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">05 — Mapeamento de Público</p>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text)">Público & Personas</h2>
                    <p class="text-xs" style="color:var(--muted)">Além de idade e localização — comportamento, frustrações e processo de decisão de quem compra.</p>
                </div>

                {{-- Checklist da seção --}}
                <form method="POST" action="{{ route('clients.diagnostics.update', [$client, $diagnostic]) }}" class="mb-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_section" value="sec05">
                    <div class="card p-5">
                        <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Checklist — Personas</p>
                        @php
                            $cl05 = $sec05['checklist'] ?? [];
                            $checklist05 = [
                                'dados_demograficos'       => 'Dados demográficos básicos definidos (idade, renda, localização, profissão)',
                                'necessidade_emocional'    => 'Necessidade emocional / funcional profunda identificada',
                                'frustracoes_mapeadas'     => 'Frustrações com a categoria mapeadas',
                                'processo_decisao'         => 'Processo de decisão de compra descrito',
                                'objecoes_listadas'        => 'Principais objeções listadas',
                                'validada_cliente'         => 'Persona validada com o cliente (ele reconhece como real)',
                            ];
                        @endphp
                        <div class="space-y-1 mb-4">
                            @foreach($checklist05 as $key => $label)
                                <label class="flex items-center gap-3 px-3 py-2.5 cursor-pointer"
                                       style="border:1px solid var(--border); background:var(--s3)">
                                    <input type="hidden" name="sec05[checklist][{{ $key }}]" value="0">
                                    <input type="checkbox" name="sec05[checklist][{{ $key }}]" value="1"
                                           {{ ($cl05[$key] ?? false) ? 'checked' : '' }}
                                           class="w-4 h-4 accent-[var(--purple)]">
                                    <span class="text-sm" style="color:var(--muted2)">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                    style="background:var(--purple)">Salvar</button>
                        </div>
                    </div>
                </form>

                {{-- Personas --}}
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Personas Mapeadas</p>
                    <button @click="addForm = !addForm"
                            class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background:var(--purple)">
                        + Adicionar Persona
                    </button>
                </div>

                <div x-show="addForm" x-cloak class="card p-5 mb-4">
                    <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Nova Persona</p>
                    <form method="POST" action="{{ route('diagnostics.personas.store', [$client, $diagnostic]) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Nome fictício <span style="color:var(--orange)">*</span></label>
                                <input type="text" name="name" required placeholder="Ex: Marina, Rafael..."
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Faixa etária</label>
                                <input type="text" name="age_range" placeholder="Ex: 28–38 anos"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Profissão / renda</label>
                                <input type="text" name="profession" placeholder="Ex: Profissional liberal, R$6k–12k"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Localização</label>
                                <input type="text" name="location" placeholder="Ex: Batel, Curitiba"
                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">O que busca de verdade?</label>
                            <p class="text-xs italic mb-1" style="color:var(--muted)">A necessidade emocional ou funcional profunda por trás do produto.</p>
                            <textarea name="what_they_seek" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Registre aqui..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Maiores frustrações com a categoria</label>
                            <textarea name="frustrations" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="O que já tentou e não funcionou..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Processo de decisão de compra</label>
                            <textarea name="decision_process" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="Pesquisa, pede indicação, impulsivo..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Objeções antes de fechar</label>
                            <textarea name="objections" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="É caro, não tenho tempo, já tentei antes..."></textarea>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit"
                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                    style="background:var(--purple)">Salvar</button>
                            <button type="button" @click="addForm = false"
                                    class="px-4 py-2 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)]">Cancelar</button>
                        </div>
                    </form>
                </div>

                @if($diagnostic->personas->isEmpty())
                    <div class="card p-6 text-center">
                        <p class="text-xs" style="color:var(--muted)">Nenhuma persona mapeada ainda.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($diagnostic->personas as $persona)
                            <div class="card p-5">
                                <div x-show="editId !== '{{ $persona->id }}'">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <p class="font-bold" style="color:var(--text)">{{ $persona->name }}</p>
                                            <p class="text-xs mt-0.5" style="color:var(--muted)">
                                                {{ collect([$persona->age_range, $persona->profession, $persona->location])->filter()->implode(' · ') }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="editId = '{{ $persona->id }}'"
                                                    class="text-xs font-mono text-[var(--muted)] hover:text-[var(--purple)] transition-colors">Editar</button>
                                            <form method="POST"
                                                  action="{{ route('diagnostics.personas.destroy', [$client, $diagnostic, $persona]) }}"
                                                  onsubmit="return confirm('Remover esta persona?')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="text-xs font-mono text-[var(--muted)] hover:text-[var(--red)] transition-colors">Remover</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        @foreach([
                                            ['O que busca de verdade', $persona->what_they_seek, 'var(--purple)'],
                                            ['Frustrações', $persona->frustrations, null],
                                            ['Processo de decisão', $persona->decision_process, null],
                                            ['Objeções', $persona->objections, 'var(--orange)'],
                                        ] as [$label, $value, $color])
                                            @if($value)
                                                <div class="p-3" style="background:var(--s3); border:1px solid var(--border)">
                                                    <p class="text-xs font-bold mb-1" style="color:{{ $color ?? 'var(--muted2)' }}">{{ $label }}</p>
                                                    <p class="text-xs" style="color:var(--muted2)">{{ $value }}</p>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div x-show="editId === '{{ $persona->id }}'" x-cloak>
                                    <form method="POST" action="{{ route('diagnostics.personas.update', [$client, $diagnostic, $persona]) }}" class="space-y-3">
                                        @csrf @method('PATCH')
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Nome</label>
                                                <input type="text" name="name" value="{{ $persona->name }}" required
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Faixa etária</label>
                                                <input type="text" name="age_range" value="{{ $persona->age_range }}"
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Profissão / renda</label>
                                                <input type="text" name="profession" value="{{ $persona->profession }}"
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Localização</label>
                                                <input type="text" name="location" value="{{ $persona->location }}"
                                                       class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                            </div>
                                        </div>
                                        @foreach([
                                            ['what_they_seek', 'O que busca de verdade'],
                                            ['frustrations', 'Frustrações'],
                                            ['decision_process', 'Processo de decisão'],
                                            ['objections', 'Objeções'],
                                        ] as [$field, $label])
                                            <div>
                                                <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">{{ $label }}</label>
                                                <textarea name="{{ $field }}" rows="2"
                                                          class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)] resize-y">{{ $persona->$field }}</textarea>
                                            </div>
                                        @endforeach
                                        <div class="flex gap-3">
                                            <button type="submit"
                                                    class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                                    style="background:var(--purple)">Salvar</button>
                                            <button type="button" @click="editId = null"
                                                    class="px-4 py-2 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)]">Cancelar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ════ SEÇÃO 06 — SÍNTESE ESTRATÉGICA ════ --}}
            <div x-show="section === 'sec06'" x-cloak>
                <div class="card p-6 mb-4" style="border-left:3px solid var(--purple)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--purple)">06 — Consolidação</p>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text)">Síntese Estratégica</h2>
                    <p class="text-xs" style="color:var(--muted)">Transforme dados em raciocínio estratégico — a base que alimentará o Macroplanejamento.</p>
                </div>

                <form method="POST" action="{{ route('clients.diagnostics.update', [$client, $diagnostic]) }}" class="space-y-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_section" value="sec06">

                    @php
                        $synthFields = [
                            ['forcas',                'Forças reais da marca', 'O que este negócio genuinamente tem que a concorrência não tem?', 'var(--green)'],
                            ['problema_central',      'O problema central de comunicação', 'Em 1–2 frases: qual é o problema real?', 'var(--orange)'],
                            ['territorio_comunicacao','Espaço estratégico disponível', 'Qual é o território de posicionamento em aberto?', 'var(--purple)'],
                            ['insight',               'O insight estratégico', 'A grande observação que orienta tudo. Verdade sobre o público que justifica o posicionamento.', 'var(--purple)'],
                            ['hipotese_posicionamento','Hipótese de posicionamento', 'Para [público], [marca] é a única [categoria] que [diferencial] — porque [razão].', null],
                        ];
                    @endphp

                    @foreach($synthFields as [$field, $label, $placeholder, $color])
                        <div class="card p-5" style="border-left:3px solid {{ $color ?? 'var(--border2)' }}">
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2"
                                   style="color:{{ $color ?? 'var(--muted)' }}">
                                {{ $label }}
                            </label>
                            <textarea name="sec06[{{ $field }}]" rows="4"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-y"
                                      placeholder="{{ $placeholder }}">{{ $sec06[$field] ?? '' }}</textarea>
                        </div>
                    @endforeach

                    <div class="card p-5">
                        <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Checklist de Qualidade</p>
                        @php
                            $cl06 = $sec06['checklist'] ?? [];
                            $checklist06 = [
                                'todas_secoes_preenchidas'   => 'Todas as 5 seções anteriores preenchidas',
                                'insight_baseado_dados'      => 'O insight estratégico está baseado em dados coletados (não achismo)',
                                'hipotese_validada_equipe'   => 'Hipótese de posicionamento validada internamente com a equipe',
                                'diagnostico_aprovado'       => 'Diagnóstico aprovado e pronto para alimentar o Macroplanejamento',
                            ];
                        @endphp
                        <div class="space-y-1">
                            @foreach($checklist06 as $key => $label)
                                <label class="flex items-center gap-3 px-3 py-2.5 cursor-pointer"
                                       style="border:1px solid var(--border); background:var(--s3)">
                                    <input type="hidden" name="sec06[checklist][{{ $key }}]" value="0">
                                    <input type="checkbox" name="sec06[checklist][{{ $key }}]" value="1"
                                           {{ ($cl06[$key] ?? false) ? 'checked' : '' }}
                                           class="w-4 h-4 accent-[var(--purple)]">
                                    <span class="text-sm" style="color:var(--muted2)">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background:var(--purple)">
                            Salvar Seção 06 →
                        </button>
                    </div>
                </form>
            </div>

        </div>{{-- /main content --}}
    </div>{{-- /layout --}}

</x-app-layout>
