{{-- Revisão das sugestões "Atualizar documento com reunião" (DocumentUpdateController::show).
     Enquanto a IA analisa, a página pergunta o status a cada 5s e recarrega quando termina.
     Cada sugestão: motivo + trecho da reunião + antes/depois em texto; a equipe marca quais
     aceita e o servidor aplica no documento atual (versão nova, anterior guardada). --}}
@php
    use App\Services\DocumentUpdate\HtmlPatch;
    $isPlan = $owner instanceof \App\Models\MacroPlan;
    $suggestions = collect($run->suggestions ?? []);
    $retryUrl = $isPlan ? route('macroplans.document.update-store', $owner) : route('projects.document.update-store', $owner);
    $tipoLabel = ['alterar' => 'Alterar', 'incluir' => 'Incluir', 'remover' => 'Remover'];
    $tipoColor = ['alterar' => 'purple', 'incluir' => 'green', 'remover' => 'red'];
    $verdicts = [
        'alinhado'        => ['label' => 'Na linha', 'icon' => 'circle-check', 'color' => 'var(--green)', 'bg' => 'rgba(52,211,153,.12)',
                              'hint' => 'A reunião mantém a estratégia e a comunicação do documento.'],
        'ajustes'         => ['label' => 'Ajustes e novidades', 'icon' => 'wrench', 'color' => 'var(--purple)', 'bg' => 'rgba(100,59,142,.1)',
                              'hint' => 'A estratégia continua a mesma; entram ajustes de execução, demandas novas ou mudanças criativas que cabem na linha.'],
        'mudanca_de_rota' => ['label' => 'Mudança de rota', 'icon' => 'compass', 'color' => 'var(--orange)', 'bg' => 'rgba(238,121,25,.12)',
                              'hint' => 'O time decidiu mudar algo central (objetivo, público, mensagem, tom ou prioridade). Atualize o documento e avise quem executa.'],
        'risco_de_desvio' => ['label' => 'Risco de desvio', 'icon' => 'triangle-alert', 'color' => 'var(--red)', 'bg' => 'rgba(248,113,113,.12)',
                              'hint' => 'A conversa puxou a comunicação para fora da linha sem ninguém decidir isso. Volte à linha ou assuma a mudança de forma explícita.'],
    ];
    $moveTypes = [
        'mudanca_de_rota' => ['label' => 'Mudança de rota', 'icon' => 'compass', 'color' => 'var(--orange)', 'bg' => 'rgba(238,121,25,.06)', 'border' => 'rgba(238,121,25,.3)'],
        'desvio'          => ['label' => 'Desvio', 'icon' => 'triangle-alert', 'color' => 'var(--red)', 'bg' => 'rgba(248,113,113,.06)', 'border' => 'rgba(248,113,113,.3)'],
        'nova_demanda'    => ['label' => 'Nova demanda', 'icon' => 'plus', 'color' => 'var(--blue)', 'bg' => 'rgba(59,130,246,.05)', 'border' => 'rgba(59,130,246,.3)'],
        'criativo'        => ['label' => 'Criativo', 'icon' => 'palette', 'color' => 'var(--purple)', 'bg' => 'rgba(100,59,142,.05)', 'border' => 'rgba(100,59,142,.25)'],
        'ajuste'          => ['label' => 'Ajuste', 'icon' => 'wrench', 'color' => 'var(--purple)', 'bg' => 'transparent', 'border' => 'var(--border2)'],
        'reforco'         => ['label' => 'Reforça a linha', 'icon' => 'circle-check', 'color' => 'var(--green)', 'bg' => 'transparent', 'border' => 'var(--border2)'],
    ];
    $lineLabels = [
        'objetivo' => 'Objetivo', 'publico' => 'Público', 'posicionamento' => 'Posicionamento',
        'mensagem_central' => 'Mensagem central', 'tom' => 'Tom', 'linha_criativa' => 'Linha criativa', 'mecanica_canais' => 'Mecânica / canais', 'prioridades' => 'Prioridades',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">Atualizar com reunião: {{ $owner->title }}</x-slot>

    <div class="max-w-4xl xl:max-w-[80rem] xl:grid xl:grid-cols-[minmax(0,1fr)_22rem] xl:gap-6 xl:items-start"
         x-data="{
             status: @js($run->status),
             comments: @js((object) ($run->comments ?? [])),
             saved: @js((object) ($run->comments ?? [])),
             saveState: {},
             get commentCount() { return Object.values(this.comments).filter(c => (c || '').trim() !== '').length },
             async post(url, body) {
                 const res = await fetch(url, {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                     body: JSON.stringify(body),
                 });
                 const json = await res.json().catch(() => ({}));
                 if (!res.ok) throw new Error(json.message || 'Não foi possível salvar.');
                 return json;
             },
             async saveComment(key) {
                 const text = (this.comments[key] || '').trim();
                 if ((this.saved[key] || '') === text) return;
                 this.saveState[key] = 'Salvando…';
                 try {
                     await this.post(@js(route('document-updates.comment', $run)), { key, text });
                     this.saved[key] = text;
                     this.saveState[key] = text ? 'Salvo.' : 'Comentário removido.';
                 } catch (e) { this.saveState[key] = e.message; }
             },
             async flushComments() {
                 for (const key of Object.keys(this.comments)) await this.saveComment(key);
             },
             init() {
                 if (this.status !== 'processando') return;
                 const timer = setInterval(async () => {
                     try {
                         const res = await fetch(@js(route('document-updates.status', $run)), { headers: { 'Accept': 'application/json' } });
                         if (res.ok && (await res.json()).status !== 'processando') { clearInterval(timer); window.location.reload(); }
                     } catch (e) {}
                 }, 5000);
             },
         }">
    {{-- Desktop largo: conteúdo à esquerda + caixa de comentários fixa à direita (pedido 06/11:
         comentar sem ficar rolando até o fim). Abaixo de xl vira uma coluna, como antes. --}}
    <div class="min-w-0">

        <a href="{{ $backUrl }}" class="text-xs font-semibold flex items-center gap-1 mb-3" style="color:var(--muted)">
            <x-icon name="arrow-left" size="12" /> Voltar para {{ $isPlan ? 'o planejamento' : 'a ' . $kindLabel }}
        </a>

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">
                {{ session('error') }}
            </div>
        @endif

        <div class="card p-5 mb-5">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">{{ ucfirst($kindLabel) }} × reunião</p>
                    <h1 class="text-base font-bold" style="color:var(--text)">{{ $owner->title }}</h1>
                    <p class="text-xs mt-1" style="color:var(--muted)">
                        @if($run->meeting)
                            Reunião <a href="{{ route('meetings.show', $run->meeting) }}" class="underline" style="color:var(--purple)">{{ $run->meeting->title }}</a>
                            de {{ $run->meeting->scheduled_at->format('d/m/Y') }}
                        @else
                            Reunião excluída
                        @endif
                        · pedido por {{ $run->requestedBy?->name ?? '—' }} em {{ $run->created_at->format('d/m H:i') }}
                    </p>
                    @if($run->instruction)
                        <p class="text-xs mt-2" style="color:var(--muted2)"><b>Foco pedido:</b> {{ $run->instruction }}</p>
                    @endif
                </div>
                <x-document-update-status :status="$run->status" />
            </div>
        </div>

        @if($run->isProcessing())
            <div class="card p-6 text-sm flex items-center gap-3" style="color:var(--purple)">
                <x-icon name="sparkles" size="16" class="animate-pulse" />
                <span>
                    @if($run->revision > 0)
                        A IA está refazendo a análise com os seus comentários (revisão {{ $run->revision }} de {{ \App\Services\DocumentUpdate\DocumentUpdateService::MAX_REVISIONS }})…
                    @else
                        A IA está lendo a reunião e conferindo o documento…
                    @endif
                    costuma levar de 1 a 4 minutos. A página atualiza sozinha (e você recebe um aviso no sino).
                </span>
            </div>

        @elseif($run->status === 'erro')
            <div class="card p-5">
                <p class="text-sm font-semibold mb-1" style="color:var(--red)">Não foi possível analisar o documento.</p>
                <p class="text-xs mb-4" style="color:var(--muted)">{{ $run->error }}</p>
                @if($run->meeting)
                    <form method="POST" action="{{ $retryUrl }}">
                        @csrf
                        <input type="hidden" name="meeting_id" value="{{ $run->meeting_id }}">
                        <input type="hidden" name="instruction" value="{{ $run->instruction }}">
                        <button type="submit" class="btn btn-primary btn-xs flex items-center gap-1"><x-icon name="refresh-cw" size="12" /> Tentar de novo</button>
                    </form>
                @endif
            </div>

        @else
            @if($run->isReady() && $run->error)
                <div class="mb-5 px-4 py-3 text-sm flex items-start gap-2" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); color:var(--orange); border-radius:var(--radius)">
                    <x-icon name="triangle-alert" size="14" class="mt-0.5 flex-shrink-0" /><span>{{ $run->error }}</span>
                </div>
            @endif
            @if($run->revision > 0)
                <p class="text-xs mb-3 flex items-center gap-1" style="color:var(--muted)">
                    <x-icon name="history" size="12" /> Esta é a versão {{ $run->revision + 1 }}: a IA já refez {{ $run->revision }} vez(es) com os comentários da equipe.
                </p>
            @endif
            {{-- Leitura do guardião da linha: veredito + resumo --}}
            @php
                $review  = $run->strategic_review ?? [];
                $verdict = $verdicts[$review['veredito'] ?? ''] ?? null;
                $moves   = collect($review['movimentos'] ?? []);
                $line    = $review['linha_estrategica'] ?? [];
            @endphp
            <div class="card p-5 mb-5" style="border-left:3px solid {{ $verdict['color'] ?? 'var(--purple)' }}">
                <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                    <h2 class="card-title flex items-center gap-1"><x-icon name="shield-check" size="14" /> Leitura estratégica da reunião</h2>
                    @if($verdict)
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1"
                              style="background:{{ $verdict['bg'] }}; color:{{ $verdict['color'] }}">
                            <x-icon :name="$verdict['icon']" size="12" /> {{ $verdict['label'] }}
                        </span>
                    @endif
                </div>
                <p class="text-sm whitespace-pre-line" style="color:var(--text)">{{ $run->summary ?: '—' }}</p>
                @if($verdict)
                    <p class="text-xs mt-2" style="color:var(--muted)">{{ $verdict['hint'] }}</p>
                @endif
            </div>

            {{-- Movimentos: o que a reunião fez com a linha (mudança de rota e desvio primeiro) --}}
            @if($moves->isNotEmpty())
                <div class="card p-5 mb-5">
                    <h2 class="card-title mb-1">O que a reunião fez com a linha</h2>
                    <p class="text-xs mb-4" style="color:var(--muted)">Não é comparação de texto: é para onde a estratégia e a comunicação estão indo.</p>
                    <div class="space-y-3">
                        @foreach($moves as $m)
                            @php $mt = $moveTypes[$m['tipo']]; @endphp
                            <div class="px-4 py-3 rounded-lg" style="background:{{ $mt['bg'] }}; border:1px solid {{ $mt['border'] }}">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="text-xs font-bold flex items-center gap-1" style="color:{{ $mt['color'] }}">
                                        <x-icon :name="$mt['icon']" size="12" /> {{ $mt['label'] }}
                                    </span>
                                    <span class="text-sm font-semibold" style="color:var(--text)">{{ $m['titulo'] }}</span>
                                </div>
                                @if($m['tipo'] !== 'reforco' && ($m['documento_diz'] || $m['reuniao_indica']))
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2 text-xs">
                                        @if($m['documento_diz'])
                                            <p style="color:var(--muted2)"><b style="color:var(--muted)">O documento define:</b> {{ $m['documento_diz'] }}</p>
                                        @endif
                                        @if($m['reuniao_indica'])
                                            <p style="color:var(--muted2)"><b style="color:var(--muted)">A reunião trouxe:</b> {{ $m['reuniao_indica'] }}</p>
                                        @endif
                                    </div>
                                @elseif($m['reuniao_indica'])
                                    <p class="text-xs mt-1" style="color:var(--muted2)">{{ $m['reuniao_indica'] }}</p>
                                @endif
                                @if($m['por_que_importa'])
                                    <p class="text-xs mt-2" style="color:var(--text)"><b>Por que importa:</b> {{ $m['por_que_importa'] }}</p>
                                @endif
                                @if($m['recomendacao'])
                                    <p class="text-xs mt-1 font-semibold" style="color:{{ $mt['color'] }}">→ {{ $m['recomendacao'] }}</p>
                                @endif
                                @if($m['evidencia'])
                                    <p class="text-xs mt-2 flex items-start gap-1.5 italic" style="color:var(--muted)">
                                        <x-icon name="quote" size="12" class="mt-0.5 flex-shrink-0" /> {{ $m['evidencia'] }}
                                    </p>
                                @endif
                                @include('documents._update-comment', [
                                    'key' => 'm:' . $loop->index,
                                    'placeholder' => match ($m['tipo']) {
                                        'nova_demanda' => 'Ex.: são só 2 cards, entrega até 20/11 — ou: isso fica para o próximo ciclo.',
                                        'desvio'       => 'Ex.: não é desvio, foi decidido — pode assumir a mudança.',
                                        default        => 'Ex.: a leitura está certa, mas o motivo foi outro… / não foi bem isso que decidimos.',
                                    },
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Secretário: todos os assuntos da reunião com o destino de cada um (nada se perde) --}}
            @php $agenda = collect($review['pauta_coberta'] ?? []); @endphp
            @if($agenda->isNotEmpty())
                @php
                    $destinos = [
                        'documento' => ['label' => 'No documento', 'badge' => 'green'],
                        'aberto'    => ['label' => 'Em aberto', 'badge' => 'yellow'],
                        'alerta'    => ['label' => 'Alerta', 'badge' => 'red'],
                        'fora'      => ['label' => 'Fora deste documento', 'badge' => 'blue'],
                        'ja_esta'   => ['label' => 'Já estava', 'badge' => 'muted'],
                    ];
                    $counts = $agenda->countBy('destino');
                @endphp
                <details class="card p-5 mb-5" @if($agenda->where('destino', 'fora')->isNotEmpty()) open @endif>
                    <summary class="cursor-pointer flex items-center justify-between gap-3 flex-wrap">
                        <span class="card-title flex items-center gap-1"><x-icon name="list-checks" size="14" /> Pauta coberta · {{ $agenda->count() }} assunto(s)</span>
                        <span class="flex items-center gap-1 flex-wrap">
                            @foreach($destinos as $key => $d)
                                @if($counts->get($key))
                                    <span class="badge badge-{{ $d['badge'] }}">{{ $counts->get($key) }} {{ Str::lower($d['label']) }}</span>
                                @endif
                            @endforeach
                        </span>
                    </summary>
                    <p class="text-xs mt-2 mb-3" style="color:var(--muted)">Tudo o que foi tratado na reunião e para onde foi. Confira se nada importante ficou de fora — os "Fora deste documento" precisam de alguém para levar adiante.</p>
                    <ul class="space-y-2">
                        @foreach($agenda as $p)
                            @php $d = $destinos[$p['destino']] ?? $destinos['aberto']; @endphp
                            <li class="flex items-start gap-2 text-sm">
                                <span class="badge badge-{{ $d['badge'] }} flex-shrink-0">{{ $d['label'] }}</span>
                                <span style="color:var(--text)">{{ $p['assunto'] }}
                                    @if($p['observacao'])<span class="text-xs" style="color:var(--muted)"> — {{ $p['observacao'] }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            {{-- Linha estratégica que a IA leu no documento (referência, fechada por padrão) --}}
            @if($line)
                <details class="card p-5 mb-5">
                    <summary class="card-title cursor-pointer flex items-center gap-1"><x-icon name="compass" size="14" /> Linha estratégica do documento</summary>
                    <p class="text-xs mt-2 mb-3" style="color:var(--muted)">É contra isto que a reunião foi conferida. Se algo aqui estiver errado, a leitura também estará.</p>
                    <dl class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-x-4 gap-y-2 text-sm">
                        @foreach($lineLabels as $key => $label)
                            @if(!empty($line[$key]))
                                <dt class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $label }}</dt>
                                <dd style="color:var(--text)">{{ $line[$key] }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </details>
            @endif

            @if($suggestions->isNotEmpty())
                <h2 class="card-title mb-1 mt-2">Mudanças sugeridas no documento</h2>
                <p class="text-xs mb-3" style="color:var(--muted)">Só o que a reunião decidiu mudar. Desvios não viram sugestão: o documento segue valendo até o time decidir.</p>
            @endif

            @if($stale)
                <div class="mb-5 px-4 py-3 text-sm flex items-start gap-2" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); color:var(--orange); border-radius:var(--radius)">
                    <x-icon name="triangle-alert" size="14" class="mt-0.5 flex-shrink-0" />
                    <span>O documento ganhou uma versão nova depois desta análise. Dá para aplicar mesmo assim: cada trecho é procurado de novo no documento atual, e o que não for encontrado fica de fora (avisamos qual).</span>
                </div>
            @endif

            @if(!empty($run->open_points))
                <div class="card p-5 mb-5">
                    <h2 class="card-title mb-2">Pontos para conferir</h2>
                    <p class="text-xs mb-2" style="color:var(--muted)">Conversados na reunião, mas sem decisão clara. Não viraram sugestão. Se você já sabe a resposta, responda aqui: na revisão, a IA leva para o documento.</p>
                    <ul class="text-sm space-y-3" style="color:var(--text)">
                        @foreach($run->open_points as $point)
                            <li>
                                • {{ $point }}
                                @include('documents._update-comment', ['key' => 'p:' . $loop->index, 'label' => 'Responder',
                                    'placeholder' => 'Ex.: confirmado com o cliente — verba de R$ 36/dia no Meta e R$ 30/dia no Google.'])
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif


            <form method="POST" action="{{ route('document-updates.apply', $run) }}"
                  x-data="{ accepted: @js($suggestions->filter(fn ($s) => $s['aplicavel'])->pluck('id')->values()), withData: @js((bool) $dataDiff), sending: false }"
                  @submit="sending = true">
                @csrf

                @if($suggestions->isEmpty() && empty($dataDiff))
                    <div class="card p-5 text-sm" style="color:var(--muted)">
                        Nada nesta reunião pede mudança no documento.
                    </div>
                @endif

                @foreach($suggestions as $s)
                    @php
                        $applied = $s['status'] ?? null;
                        $border = match ($applied) { 'aplicada' => 'var(--green)', 'falhou' => 'var(--red)', default => 'var(--border2)' };
                    @endphp
                    <div class="card p-5 mb-4" style="border-left:3px solid {{ $border }}">
                        <div class="flex items-start gap-3">
                            @if($run->isReady())
                                <input type="checkbox" name="accepted[]" value="{{ $s['id'] }}" x-model.number="accepted" class="mt-1"
                                       @disabled(!$s['aplicavel']) title="{{ $s['aplicavel'] ? 'Aceitar esta mudança' : 'Trecho não encontrado no documento' }}">
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    @if(($s['impacto'] ?? null) === 'estrategico')
                                        <span class="badge badge-orange">Estratégica</span>
                                    @elseif(($s['impacto'] ?? null) === 'criativo')
                                        <span class="badge badge-purple">Criativa</span>
                                    @elseif(isset($s['impacto']))
                                        <span class="badge badge-muted">Tática</span>
                                    @endif
                                    <span class="badge badge-{{ $tipoColor[$s['tipo']] ?? 'purple' }}">{{ $tipoLabel[$s['tipo']] ?? 'Alterar' }}</span>
                                    @if($s['secao'])<span class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $s['secao'] }}</span>@endif
                                    @if($applied === 'aplicada')<span class="badge badge-green">Aplicada</span>@endif
                                    @if($applied === 'falhou')<span class="badge badge-red">Não aplicada: trecho mudou</span>@endif
                                </div>
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $s['titulo'] }}</p>
                                @if($s['motivo'])<p class="text-sm mt-1" style="color:var(--muted2)">{{ $s['motivo'] }}</p>@endif
                                @if($s['evidencia'])
                                    <p class="text-xs mt-2 flex items-start gap-1.5 italic" style="color:var(--muted)">
                                        <x-icon name="quote" size="12" class="mt-0.5 flex-shrink-0" /> {{ $s['evidencia'] }}
                                    </p>
                                @endif

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                    <div class="px-3 py-2 text-xs whitespace-pre-line rounded-lg" style="background:rgba(248,113,113,.06); border:1px solid rgba(248,113,113,.2); color:var(--text)">
                                        <p class="font-mono uppercase mb-1" style="color:var(--red)">Antes</p>{{ HtmlPatch::readable($s['trecho_atual']) ?: '—' }}
                                    </div>
                                    {{-- "Depois" editável: editor visual (o HTML do trecho, sem o estilo do documento) ou código --}}
                                    <div class="px-3 py-2 text-xs rounded-lg" style="background:rgba(52,211,153,.06); border:1px solid rgba(52,211,153,.25); color:var(--text)"
                                         :class="editing && 'md:col-span-2'"
                                         x-data="{
                                             editing: false, code: false, saving: false, err: '',
                                             edited: @js((bool) ($s['editada'] ?? false)),
                                             html: @js($s['trecho_novo']),
                                             readable: @js($s['trecho_novo'] === '' ? '(removido)' : (HtmlPatch::readable($s['trecho_novo']) ?: '—')),
                                             start() { this.editing = true; this.code = false; this.err = ''; this.$nextTick(() => this.$refs.ed.innerHTML = this.html) },
                                             toggleCode() {
                                                 if (!this.code) { this.html = this.$refs.ed.innerHTML; this.code = true }
                                                 else { this.code = false; this.$nextTick(() => this.$refs.ed.innerHTML = this.html) }
                                             },
                                             async save() {
                                                 if (!this.code) this.html = this.$refs.ed.innerHTML;
                                                 this.saving = true; this.err = '';
                                                 try {
                                                     const r = await post(@js(route('document-updates.suggestion', [$run, $s['id']])), { html: this.html });
                                                     this.readable = r.readable || '(removido)'; this.edited = true; this.editing = false;
                                                 } catch (e) { this.err = e.message }
                                                 this.saving = false;
                                             },
                                         }">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <p class="font-mono uppercase flex items-center gap-1.5" style="color:var(--green)">
                                                Depois <span x-show="edited" x-cloak class="badge badge-purple normal-case font-sans">editado pela equipe</span>
                                            </p>
                                            @if($run->isReady())
                                                <button type="button" x-show="!editing" @click="start()" class="text-xs font-semibold flex items-center gap-1" style="color:var(--purple)">
                                                    <x-icon name="pencil" size="12" /> Editar
                                                </button>
                                            @endif
                                        </div>
                                        <div x-show="!editing" class="whitespace-pre-line" x-text="readable"></div>
                                        @if($run->isReady())
                                            <div x-show="editing" x-cloak>
                                                <div x-show="!code" x-ref="ed" contenteditable="true"
                                                     class="px-3 py-2 rounded-lg text-sm space-y-2 focus:outline-none"
                                                     style="background:var(--surface, #fff); border:1px solid var(--border2); min-height:80px; color:var(--text)"></div>
                                                <textarea x-show="code" x-model="html" rows="8" class="field w-full px-3 py-2 text-xs font-mono" spellcheck="false"></textarea>
                                                <p class="text-[11px] mt-1" style="color:var(--muted)">
                                                    Escreva direto no texto. A formatação final segue o estilo do documento. Para apagar o trecho todo, deixe vazio.
                                                </p>
                                                <p x-show="err" x-text="err" class="text-xs mt-1" style="color:var(--red)"></p>
                                                <div class="flex items-center justify-between gap-2 mt-2">
                                                    <button type="button" @click="toggleCode()" class="text-xs flex items-center gap-1" style="color:var(--muted)">
                                                        <x-icon name="code" size="12" /> <span x-text="code ? 'Voltar ao texto' : 'Ver código HTML'"></span>
                                                    </button>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" @click="editing = false" class="btn btn-ghost btn-xs">Cancelar</button>
                                                        <button type="button" @click="save()" :disabled="saving" class="btn btn-primary btn-xs flex items-center gap-1">
                                                            <x-icon name="save" size="12" /> <span x-text="saving ? 'Salvando…' : 'Salvar'"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @include('documents._update-comment', ['key' => 's:' . $s['id'],
                                    'placeholder' => 'Ex.: mantenha os 70 m do parque e o parágrafo da chuva — tire só os 4 anos.'])

                                @if(!$s['aplicavel'] && $run->isReady())
                                    <p class="text-xs mt-2" style="color:var(--orange)">
                                        A IA citou um trecho que não foi encontrado exatamente no documento. Se a mudança fizer sentido, faça pelo
                                        <a href="{{ $editUrl }}" class="underline">editor</a>.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($dataDiff)
                    <div class="card p-5 mb-4" style="border-left:3px solid var(--orange)">
                        <div class="flex items-start gap-3">
                            @if($run->isReady())
                                <input type="checkbox" name="with_data" value="1" x-model="withData" class="mt-1">
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold" style="color:var(--text)">Dados do documento (datas, verba, peças)</p>
                                <p class="text-xs mt-1" style="color:var(--muted)">
                                    {{ $run->data_reason ?: 'A IA ajustou o bloco de dados para bater com as mudanças.' }}
                                    Ao aplicar, as datas e a verba também mudam no App.
                                </p>
                                <div class="mt-3 space-y-2">
                                    @foreach($dataDiff as [$field, $before, $after])
                                        <div class="grid grid-cols-1 md:grid-cols-[140px_1fr_1fr] gap-2 text-xs">
                                            <span class="font-mono" style="color:var(--muted)">{{ $field }}</span>
                                            <span class="whitespace-pre-line px-2 py-1 rounded" style="background:rgba(248,113,113,.06); color:var(--text)">{{ $before }}</span>
                                            <span class="whitespace-pre-line px-2 py-1 rounded" style="background:rgba(52,211,153,.06); color:var(--text)">{{ $after }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($run->isReady() && ($suggestions->isNotEmpty() || $dataDiff))
                    <div class="card px-5 py-4 flex items-center justify-between gap-3 flex-wrap lg:sticky lg:bottom-4">
                        <p class="text-xs" style="color:var(--muted)">
                            <span x-text="accepted.length"></span> de {{ $suggestions->count() }} sugestão(ões) marcada(s).
                            Vira uma versão nova do documento; a anterior fica guardada.
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="submit" form="discard-form" class="btn btn-ghost btn-sm">Descartar</button>
                            <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1" :disabled="sending || (accepted.length === 0 && !withData)">
                                <x-icon name="check" size="14" /> <span x-text="sending ? 'Aplicando…' : 'Aplicar no documento'"></span>
                            </button>
                        </div>
                    </div>
                @endif
            </form>

            @if($run->isReady())
                <form id="discard-form" method="POST" action="{{ route('document-updates.discard', $run) }}">@csrf</form>
            @endif

            {{-- Pendências e demandas da reunião: aqui só o resumo. Registrar/dispensar fica no painel
                 Pendências da campanha/projeto/planejamento (pedido 06/11: é lá que o time volta). --}}
            @if(!empty($pendencyItems))
                @php
                    $origins = ['aberto' => 'Ponto em aberto', 'nova_demanda' => 'Nova demanda', 'fora' => 'Fora deste documento'];
                    $toRegister = collect($pendencyItems)->whereNull('pendency_id')->where('dismissed', false)->count();
                @endphp
                <div class="card p-5 mb-5 mt-5" id="pendencias-reuniao" style="border-left:3px solid var(--orange)">
                    <div class="flex items-center justify-between gap-3 flex-wrap mb-1">
                        <h2 class="card-title flex items-center gap-1"><x-icon name="hourglass" size="14" /> Pendências e demandas da reunião</h2>
                        <a href="{{ $backUrl }}#pendencias" class="btn btn-ghost btn-sm flex items-center gap-1">
                            Registrar {{ $isPlan ? 'no planejamento' : 'na ' . $kindLabel }} <x-icon name="chevron-right" size="12" />
                        </a>
                    </div>
                    <p class="text-xs mb-3" style="color:var(--muted)">
                        O que alguém precisa fazer, entregar ou confirmar depois da reunião. Não muda o documento:
                        {{ $toRegister ? 'aparece como sugestão no painel Pendências ' . ($isPlan ? 'do planejamento' : 'da ' . $kindLabel) . ', onde você registra (com quem deve e quem cobra) ou dispensa.' : 'tudo já foi registrado ou dispensado.' }}
                    </p>
                    <ul class="flex flex-col text-sm">
                        @foreach($pendencyItems as $item)
                            @php $pStatus = $item['pendency_id'] ? ($pendencyStatus[$item['pendency_id']] ?? null) : null; @endphp
                            <li class="py-2 flex items-start justify-between gap-3 {{ $loop->last ? '' : 'border-b' }}" style="border-color:var(--border)">
                                <span class="min-w-0" style="color:{{ $item['dismissed'] ? 'var(--muted2)' : 'var(--text)' }}">
                                    {{ $item['titulo'] }}
                                    <span class="text-[11px]" style="color:var(--muted)"> · {{ $origins[$item['origem']] ?? '' }}</span>
                                </span>
                                <span class="text-xs shrink-0" style="color:{{ $item['pendency_id'] ? 'var(--green, #16a34a)' : 'var(--muted)' }}">
                                    @if($item['pendency_id'])
                                        {{ $pStatus ? 'Pendência ' . (\App\Models\Pendency::$statuses[$pStatus]['label'] ?? $pStatus) : 'Pendência excluída' }}
                                    @elseif($item['dismissed'])
                                        Dispensada
                                    @else
                                        A registrar
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($run->status === 'aplicado')
                <div class="card px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs" style="color:var(--muted)">
                        Aplicado por {{ $run->appliedBy?->name ?? '—' }} em {{ $run->applied_at?->format('d/m/Y H:i') }}.
                        Quer ajustar alguma frase? Use o editor.
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ $editUrl }}" class="btn btn-ghost btn-sm flex items-center gap-1"><x-icon name="pencil" size="14" /> Abrir no editor</a>
                        <a href="{{ $backUrl }}" class="btn btn-primary btn-sm">Ver documento</a>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Lateral fixa (xl): comentário geral + "Refazer" sempre à mão enquanto rola --}}
    @if($run->isReady())
        <aside class="min-w-0 xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:overflow-y-auto">
        @php $maxRevisions = \App\Services\DocumentUpdate\DocumentUpdateService::MAX_REVISIONS; @endphp
            <div class="card p-5 mb-5 xl:mb-0" style="border-left:3px solid var(--purple)">
                <div class="flex items-center justify-between gap-3 flex-wrap mb-1">
                    <h2 class="card-title flex items-center gap-1"><x-icon name="message-circle" size="14" /> Comentários para a IA refazer</h2>
                    <span class="text-xs" style="color:var(--muted)">{{ $maxRevisions - $run->revision }} de {{ $maxRevisions }} revisão(ões) disponível(is)</span>
                </div>
                <p class="text-xs mb-3" style="color:var(--muted)">
                    Comente cada item (botão <b>Comentar</b> / <b>Responder</b>) e, se quiser, deixe uma orientação geral aqui.
                    A IA refaz a análise levando tudo em conta, e o que você não comentou fica igual. Para trocar só uma frase, é mais rápido editar a sugestão direto.
                </p>
                <textarea rows="3" maxlength="3000" class="field w-full px-3 py-2 text-sm xl:min-h-[9rem]"
                          x-model="comments['geral']" @input.debounce.800ms="saveComment('geral')" @blur="saveComment('geral')"
                          placeholder="Ex.: o público distante não entra nesta campanha, fica para o planejamento de 2027. Não mexa no tráfego pago."></textarea>
                <div class="flex items-center justify-between gap-3 flex-wrap mt-2">
                    <p class="text-[11px]" style="color:var(--muted)" x-text="saveState['geral'] || (commentCount + ' comentário(s) para a IA')"></p>
                    @if($run->revision < $maxRevisions)
                        <form method="POST" action="{{ route('document-updates.revise', $run) }}" x-data="{ sending: false }" @submit.prevent="sending = true; await flushComments(); $el.submit()">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1" :disabled="sending || commentCount === 0"
                                    :title="commentCount === 0 ? 'Escreva pelo menos um comentário' : ''">
                                <x-icon name="refresh-cw" size="14" /> <span x-text="sending ? 'Enviando…' : 'Refazer com meus comentários'"></span>
                            </button>
                        </form>
                    @else
                        <span class="text-xs" style="color:var(--muted)">As {{ $maxRevisions }} revisões já foram usadas. Edite as sugestões ou use o editor do documento.</span>
                    @endif
                </div>
            </div>
        </aside>
    @endif
    </div>
</x-app-layout>
