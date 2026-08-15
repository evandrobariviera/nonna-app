<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-xs flex-wrap">
            @if($task->project)
                <a href="{{ route('projects.dashboard') }}" class="font-semibold transition-colors" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">Projetos</a>
                @if($task->project->macro_plan_id)
                    <span style="color:var(--border2)">/</span>
                    <a href="{{ route('macroplans.edit', $task->project->macro_plan_id) }}" class="font-semibold transition-colors" style="color:var(--muted)"
                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                        {{ $task->project->macroPlan?->title }}
                    </a>
                @endif
                <span style="color:var(--border2)">/</span>
                <a href="{{ $task->project->macro_plan_id ? route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) : route('projects.showDirect', $task->project) }}"
                   class="font-semibold transition-colors" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    {{ $task->project->title }}
                </a>
            @elseif($task->is_ticket)
                <a href="{{ route('tickets.index') }}" class="font-semibold transition-colors" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">Tickets</a>
            @endif
            @if($task->project || $task->is_ticket)
                <span style="color:var(--border2)">/</span>
            @endif
            <span class="font-semibold" style="color:var(--text)">{{ $task->title }}</span>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(238, 121, 25,.08); border:1px solid rgba(238, 121, 25,.25); color:var(--orange)">
            ⚠ {{ session('warning') }}
        </div>
    @endif

    {{-- Todos os dados e componentes Alpine registrados AQUI antes dos x-data --}}
    <script>
        window._tChat = {
            messages: @json($chatMessages),
            agents:   @json($agents),
            endpoint: '{{ route('tasks.chat', $task) }}',
        };

        // Lightbox de anexo — JS puro (sem Alpine store), pra ficar simples e
        // independente de timing de inicialização do Alpine.
        function openAttachmentLightbox(src, filename) {
            // Reancora o modal como filho direto do <body>: como ele nasce dentro
            // do <main> (o container que realmente rola, já que o <body> é
            // overflow-hidden), "position:fixed" ficava relativo ao topo do
            // documento em vez do viewport visível — abrindo sempre lá em cima.
            const lightbox = document.getElementById('attachment-lightbox');
            if (lightbox.parentElement !== document.body) document.body.appendChild(lightbox);

            document.getElementById('attachment-lightbox-img').src = src;
            document.getElementById('attachment-lightbox-img').alt = filename;
            document.getElementById('attachment-lightbox-filename').textContent = filename;
            lightbox.style.display = 'flex';
        }
        function closeAttachmentLightbox() {
            document.getElementById('attachment-lightbox').style.display = 'none';
            document.getElementById('attachment-lightbox-img').src = '';
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAttachmentLightbox();
        });

        document.addEventListener('alpine:init', () => {
            Alpine.store('ui', { chatOpen: false });

            Alpine.data('observerPicker', (initial = []) => ({
                selected: initial,
                add(event) {
                    const id   = event.target.value;
                    const name = event.target.selectedOptions[0]?.dataset?.name;
                    if (!id || this.selected.find(s => s.id == id)) { event.target.value = ''; return; }
                    this.selected.push({ id, name });
                    event.target.value = '';
                },
                remove(idx) { this.selected.splice(idx, 1); }
            }));

            Alpine.data('taskChat', () => ({
                endpoint:      window._tChat?.endpoint ?? '',
                agents:        window._tChat?.agents   ?? [],
                messages:      window._tChat?.messages ?? [],
                selectedAgent: '',
                input:         '',
                thinking:      false,
                error:         '',

                init() {
                    this.$watch('$store.ui.chatOpen', (open) => {
                        if (open) this.scrollBottom();
                    });
                },

                scrollBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.msgContainer;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },

                async send() {
                    if (!this.selectedAgent || !this.input.trim() || this.thinking) return;

                    const text = this.input.trim();
                    this.input    = '';
                    this.error    = '';
                    this.thinking = true;

                    this.messages.push({
                        id:         Date.now(),
                        role:       'user',
                        content:    text,
                        user_name:  '{{ auth()->user()->name }}',
                        agent_name: null,
                        time:       new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                    });

                    this.scrollBottom();

                    try {
                        const res = await fetch(this.endpoint, {
                            method:  'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept':       'application/json',
                            },
                            body: JSON.stringify({ agent_id: this.selectedAgent, message: text }),
                        });

                        const data = await res.json();

                        if (!res.ok) {
                            this.error = data.error || 'Erro ao processar.';
                            this.messages.pop();
                            return;
                        }

                        this.messages.push(data);
                        this.scrollBottom();
                    } catch (e) {
                        this.error = 'Erro de conexão.';
                        this.messages.pop();
                    } finally {
                        this.thinking = false;
                    }
                }
            }));
        });
    </script>

    <div x-data="{ editing: false }" class="flex gap-5" style="align-items: start;">

        {{-- ══════════════════════════════════════════════════════════
             COLUNA PRINCIPAL
        ══════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- CABEÇALHO --}}
            <div class="card card-body-lg">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <span class="badge badge-{{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                            @if($task->is_ticket)
                                <span class="badge badge-orange">Ticket</span>
                            @endif
                            @if($task->isOverdue())
                                <span class="badge" style="background:rgba(239,68,68,.08); color:var(--red); border-color:rgba(239,68,68,.25)">Atrasada</span>
                            @endif
                        </div>
                        <h1 class="text-2xl leading-tight" style="color:var(--text); font-weight:700; letter-spacing:-0.02em">{{ $task->title }}</h1>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button @click="$store.ui.chatOpen = true"
                                class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold transition-all"
                                style="border:1px solid rgba(100, 59, 142,.35); color:var(--purple); background:rgba(100, 59, 142,.06)"
                                onmouseover="this.style.background='rgba(100, 59, 142,.12)'" onmouseout="this.style.background='rgba(100, 59, 142,.06)'">
                            <x-icon name="sparkles" size="12" />
                            Chat IA
                        </button>
                        @if($task->sprint_id)
                            @php $sprintLocked = $task->sprint?->isLocked(); @endphp
                            <form method="POST" action="{{ route('sprints.remove-task', [$task->sprint_id, $task]) }}"
                                  @submit.prevent="if (await $store.confirmDialog.ask('Devolver esta tarefa pra Fila?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit" {{ $sprintLocked ? 'disabled' : '' }}
                                    class="px-4 py-2 text-xs font-semibold transition-colors"
                                    style="border:1px solid var(--orange); color:var(--orange); {{ $sprintLocked ? 'opacity:.4; cursor:not-allowed' : '' }}"
                                    title="{{ $sprintLocked ? 'Sprint travada' : 'Devolver pra Fila' }}">
                                    ← Fila
                                </button>
                            </form>
                        @elseif($activeSprint)
                            <form method="POST" action="{{ route('sprints.add-task', [$activeSprint, $task]) }}">
                                @csrf
                                <button type="submit"
                                    class="px-4 py-2 text-xs font-semibold text-white transition-colors"
                                    style="background:var(--green); border:1px solid var(--green)"
                                    title="Enviar para: {{ $activeSprint->title }}">
                                    → Sprint
                                </button>
                            </form>
                        @endif
                        <button @click="editing = !editing"
                            :style="editing ? 'background:var(--purple); color:#fff; border-color:var(--purple)' : ''"
                            class="px-4 py-2 text-xs font-semibold transition-colors"
                            style="border:1px solid var(--border2); color:var(--muted2)">
                            <span x-text="editing ? '✕  Cancelar' : '✎  Editar'"></span>
                        </button>
                    </div>
                </div>

                {{-- Pessoas (Responsável / Executor) — só avatar, sem texto --}}
                @php $responsavelTopo = $task->responsibles->first(); @endphp
                @if($responsavelTopo || $task->executor)
                    <div class="flex items-center gap-2 mb-1">
                        @if($responsavelTopo)
                            <x-user-avatar :user="$responsavelTopo" size="7" color="var(--orange)"
                                title="Responsável: {{ $responsavelTopo->name }}" />
                        @endif
                        @if($task->executor)
                            <x-user-avatar :user="$task->executor" size="7" color="var(--purple)"
                                title="Executor: {{ $task->executor->name }}" />
                        @endif
                    </div>
                @endif

                {{-- Breadcrumb + Meta (Criada por / Lançada — antes na seção Informações da lateral) --}}
                <div class="flex items-center gap-2 text-xs flex-wrap mt-3 pt-3" style="border-top:1px solid var(--border2); color:var(--muted)">
                    @if($task->client)
                        <a href="{{ route('clients.show', $task->client) }}"
                           @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open('{{ route('clients.preview', $task->client) }}') }"
                           style="color:var(--purple); font-weight:500"
                           onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                            {{ $task->client->displayName() }}
                        </a>
                    @endif
                    @if($task->project)
                        @if($task->project->macro_plan_id)
                            <span style="color:var(--border2)">›</span>
                            <a href="{{ route('macroplans.edit', $task->project->macro_plan_id) }}"
                               @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open('{{ route('macroplans.preview', $task->project->macro_plan_id) }}') }"
                               style="color:var(--muted2)"
                               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted2)'">
                                {{ $task->project->macroPlan?->title }}
                            </a>
                        @endif
                        <span style="color:var(--border2)">›</span>
                        <a href="{{ $task->project->macro_plan_id ? route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) : route('projects.showDirect', $task->project) }}"
                           @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open('{{ route('projects.preview', $task->project) }}') }"
                           style="color:var(--muted2)"
                           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted2)'">
                            {{ $task->project->title }}
                        </a>
                    @endif
                    @if($task->client || $task->project)
                        <span style="color:var(--border2)">•</span>
                    @endif
                    <span>Criada por <strong style="color:var(--muted2); font-weight:600">{{ explode(' ', $task->createdBy?->name ?? '—')[0] }}</strong> em {{ $task->created_at->format('d/m/Y') }} ({{ $task->created_at->diffForHumans() }})</span>
                    @if($task->launched_at)
                        <span style="color:var(--border2)">•</span>
                        <span style="color:var(--green); font-weight:600">Lançada {{ $task->launched_at->format('d/m/Y') }}</span>
                    @endif
                </div>
            </div>

            {{-- BARRA DE STATUS: fluxo em flecha (clip-path) com os 7 status que avançam pra
                 frente, acende na cor real do status ativo. "Cancelado" fica de fora da corrente
                 de propósito — é saída/exceção, não uma estação do fluxo — e vira um botão à
                 parte, menor, ao lado. Clique já salva (mesmo endpoint tasks.update-inline). --}}
            @php
                $statusShort = [
                    'backlog'              => 'Backlog',
                    'em_producao'          => 'Produção',
                    'revisao_interna'      => 'Revisão',
                    'ajuste_alteracao'     => 'Ajuste',
                    'aprovacao'            => 'Aprovação',
                    'despacho_agendamento' => 'Agendamento',
                    'concluido'            => 'Concluído',
                ];
                $flowStatuses     = collect(\App\Models\Task::$statuses)->except('cancelado');
                $canceladoStatus  = \App\Models\Task::$statuses['cancelado'];
                $canceladoActive  = $task->status === 'cancelado';
                $chevronPt        = 9;
                // Traço de 1px branco ao redor da forma já recortada pelo clip-path (border
                // normal ignora o clip-path) — só pra marcar a subdivisão entre as flechas,
                // inclusive na ponta.
                $chevronOutline   = "filter:drop-shadow(1px 0 0 #fff) drop-shadow(-1px 0 0 #fff) drop-shadow(0 1px 0 #fff) drop-shadow(0 -1px 0 #fff);";
            @endphp
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Status</p>
                <div class="flex items-center gap-1.5">
                    <div class="flex flex-1 min-w-0" style="overflow-x:auto">
                        @foreach($flowStatuses as $key => $s)
                            @php
                                $active = $task->status === $key;
                                $clip = $loop->first
                                    ? "polygon(0 0, calc(100% - {$chevronPt}px) 0, 100% 50%, calc(100% - {$chevronPt}px) 100%, 0 100%)"
                                    : ($loop->last
                                        ? "polygon(0 0, 100% 0, 100% 100%, 0 100%, {$chevronPt}px 50%)"
                                        : "polygon(0 0, calc(100% - {$chevronPt}px) 0, 100% 50%, calc(100% - {$chevronPt}px) 100%, 0 100%, {$chevronPt}px 50%)");
                            @endphp
                            <form method="POST" action="{{ route('tasks.update-inline', $task) }}" style="display:contents">
                                @csrf @method('PATCH')
                                @foreach(['title','task_type','origin'] as $f)
                                    <input type="hidden" name="{{ $f }}" value="{{ $task->$f }}">
                                @endforeach
                                <input type="hidden" name="status" value="{{ $key }}">
                                <button type="submit"
                                    class="flex-shrink-0 py-3 text-xs font-bold uppercase text-center transition-colors"
                                    style="clip-path:{{ $clip }}; min-width:82px; {{ $chevronOutline }} cursor:pointer;
                                           padding-left:{{ $loop->first ? '13px' : '15px' }}; padding-right:{{ $loop->last ? '13px' : '15px' }};
                                           margin-left:{{ $loop->first ? '0' : '-'.$chevronPt.'px' }}; letter-spacing:.01em;
                                           {{ $active ? 'background:var(--'.$s['color'].'); color:#fff;' : 'background:var(--s2); color:var(--muted2);' }}"
                                    {{ !$active ? 'onmouseover="this.style.background=\'var(--s3)\'" onmouseout="this.style.background=\'var(--s2)\'"' : '' }}>
                                    {{ $statusShort[$key] ?? $s['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Cancelado — separado da corrente, mesma mecânica de sempre --}}
                    <form method="POST" action="{{ route('tasks.update-inline', $task) }}" class="flex-shrink-0">
                        @csrf @method('PATCH')
                        @foreach(['title','task_type','origin'] as $f)
                            <input type="hidden" name="{{ $f }}" value="{{ $task->$f }}">
                        @endforeach
                        <input type="hidden" name="status" value="cancelado">
                        <button type="submit"
                            class="px-4 py-3 text-xs font-bold uppercase transition-colors"
                            style="letter-spacing:.01em; cursor:pointer; border:1px solid {{ $canceladoActive ? 'var(--'.$canceladoStatus['color'].')' : 'var(--border2)' }};
                                   {{ $canceladoActive ? 'background:var(--'.$canceladoStatus['color'].'); color:#fff;' : 'background:transparent; color:var(--muted2);' }}"
                            {{ !$canceladoActive ? 'onmouseover="this.style.background=\'var(--s2)\'" onmouseout="this.style.background=\'transparent\'"' : '' }}>
                            Cancelado
                        </button>
                    </form>
                </div>
            </div>

            {{-- Situação — mesma mecânica de edição de sempre (mesmo endpoint). Prioridade saiu
                 daqui e foi pro card Datas da lateral (primeiro item) — fazia mais sentido junto
                 dos outros metadados de "visão rápida" do que solto sozinho aqui em cima da
                 Situação. Responsável e Executor saíram porque já aparecem como avatar no
                 cabeçalho, logo acima. --}}
            <div class="card card-body">

                {{-- Situação — mesma dinâmica de grid do Status: botão por valor real
                     (Task::$situations), acende na cor própria (Task::$situationColors),
                     clique já salva (mesmo endpoint tasks.update-situation de sempre).
                     7 colunas fecha os 14 valores em 2 linhas exatas, sem sobra. Chips
                     arredondados com espaço entre si (em vez de linha de grade dura) —
                     o contraste do próprio preenchimento contra o card já separa. --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Situação</p>
                    <div class="grid grid-cols-7 gap-1.5">
                        @foreach(\App\Models\Task::$situations as $key => $label)
                            @php
                                $active = ($task->situation ?? '') === $key;
                                $color  = \App\Models\Task::$situationColors[$key] ?? null;
                            @endphp
                            <form method="POST" action="{{ route('tasks.update-situation', $task) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="situation" value="{{ $key }}">
                                <button type="submit"
                                    class="w-full h-full px-2 py-2.5 text-[11px] font-bold uppercase text-center leading-tight transition-colors rounded-lg"
                                    style="letter-spacing:.01em; cursor:pointer; {{ $active ? 'background:'.($color ?? 'var(--muted2)').'; color:#fff;' : 'background:var(--s2); color:var(--muted2); border:1px solid var(--border);' }}"
                                    {{ !$active ? 'onmouseover="this.style.background=\'var(--s3)\'" onmouseout="this.style.background=\'var(--s2)\'"' : '' }}>
                                    {{ $key === '' ? 'Sem situação' : $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

            </div>

            {{-- CAMPOS (sem título — some espaço): Sprint/Tipo em cima, Origem/Destino
                 embaixo, Solicitante por último (só ticket com nome preenchido). --}}
            <div x-show="!editing" class="card card-body-lg">
                <div class="grid grid-cols-2 gap-x-10 gap-y-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Sprint</p>
                        @if($task->sprint)
                            <a href="{{ route('sprints.show', $task->sprint) }}"
                               class="text-sm font-semibold transition-colors" style="color:var(--orange)"
                               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                                {{ $task->sprint->title }}
                            </a>
                        @else
                            <p class="text-sm" style="color:var(--muted)">Backlog</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Tipo</p>
                        <p class="text-sm flex items-center gap-1.5" style="color:var(--text); font-weight:500; line-height:1.4">
                            <x-icon :name="$task->typeIcon()" size="14" style="color:var(--muted)" />
                            {{ $task->typeLabel() }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Origem</p>
                        <span class="badge mt-0.5">{{ $task->originLabel() }}</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Destino</p>
                        <p class="text-sm" style="color:var(--text); font-weight:500; line-height:1.4">{{ $task->destination ? $task->destinationLabel() : '—' }}</p>
                    </div>
                    @if($task->is_ticket && $task->requester_name)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Solicitante</p>
                            <p class="text-sm" style="color:var(--text); font-weight:500; line-height:1.4">{{ $task->requester_name }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- REATRIBUIR CLIENTE / PROJETO — só aparece em modo de edição (antes vivia na
                 seção Contexto da lateral, removida por duplicar os links do cabeçalho).
                 Continuam como forms próprios (auto-submit, mesmos endpoints/validações de
                 sempre) porque não dá pra aninhar <form> dentro do form de edição abaixo. --}}
            <div x-show="editing" x-cloak class="card card-body-lg flex flex-col gap-5">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Reatribuir Cliente / Projeto</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
                        <form method="POST" action="{{ route('tasks.update-client', $task) }}">
                            @csrf @method('PATCH')
                            <select name="client_id"
                                @change="if ({{ $task->project_id ? 'true' : 'false' }} && !(await $store.confirmDialog.ask('Trocar o cliente vai desvincular a tarefa do projeto atual ({{ addslashes($task->project?->title) }}). Continuar?'))) { $el.value = '{{ $task->client_id }}'; return; } $el.form.submit()"
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" {{ $task->client_id === $c->id ? 'selected' : '' }}>
                                        {{ $c->displayName() }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Projeto</label>
                        <form method="POST" action="{{ route('tasks.update-project', $task) }}">
                            @csrf @method('PATCH')
                            <select name="project_id" onchange="this.form.submit()"
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">— nenhum —</option>
                                @foreach($clientProjects as $p)
                                    <option value="{{ $p->id }}" {{ $task->project_id === $p->id ? 'selected' : '' }}>
                                        {{ $p->title }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
                <p class="text-xs" style="color:var(--muted2); line-height:1.5">A lista de Projetos reflete o Cliente atual — troque o Cliente e salve/recarregue pra ver os projetos do novo cliente.</p>
            </div>

            {{-- FORMULÁRIO DE EDIÇÃO --}}
            <form method="POST" action="{{ route('tasks.update-inline', $task) }}" x-show="editing" x-cloak>
                @csrf @method('PATCH')
                <div class="card card-body-lg flex flex-col gap-5">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Editando tarefa</p>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Título</label>
                        <input type="text" name="title" value="{{ $task->title }}" required
                            class="w-full px-4 py-3 text-sm font-semibold focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                    </div>

                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                        @php
                            $editFields = [
                                ['name'=>'status',          'label'=>'Status',              'opts'=>\App\Models\Task::$statuses,        'val'=>$task->status,              'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v['label']]],
                                ['name'=>'task_type',       'label'=>'Tipo',                'opts'=>\App\Models\Task::$types,           'val'=>$task->task_type,           'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v]],
                                ['name'=>'priority',        'label'=>'Prioridade',          'opts'=>\App\Models\Task::$priorities,      'val'=>$task->priority ?? 'normal','map'=>fn($k,$v)=>['v'=>$k,'l'=>$v['label']]],
                                ['name'=>'destination',     'label'=>'Destino',             'opts'=>\App\Models\Task::$destinations,    'val'=>$task->destination,         'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v], 'blank'=>true],
                                ['name'=>'origin',          'label'=>'Origem',              'opts'=>\App\Models\Task::$origins,         'val'=>$task->origin,              'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v]],
                                ['name'=>'approval_method', 'label'=>'Método de Aprovação', 'opts'=>\App\Models\Task::$approvalMethods, 'val'=>$task->approval_method,     'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v], 'blank'=>true],
                            ];
                        @endphp
                        @foreach($editFields as $f)
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">{{ $f['label'] }}</label>
                                <select name="{{ $f['name'] }}" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    @if($f['blank'] ?? false)
                                        <option value="">— nenhum —</option>
                                    @endif
                                    @foreach($f['opts'] as $k => $v)
                                        @php $item = ($f['map'])($k,$v); @endphp
                                        <option value="{{ $item['v'] }}" {{ $f['val'] === $k ? 'selected' : '' }}>{{ $item['l'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Situação</label>
                            <select name="situation" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                @foreach(\App\Models\Task::$situations as $key => $label)
                                    <option value="{{ $key }}" {{ ($task->situation ?? '') === $key ? 'selected' : '' }}
                                        @if($key === 'enviar_para_cliente') style="color:var(--orange); font-weight:600" @endif>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        @foreach([['due_date','Vencimento'],['approval_date','Dt. Aprovação'],['publish_date','Dt. Publicação']] as [$fname,$flabel])
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">{{ $flabel }}</label>
                                <input type="date" name="{{ $fname }}" value="{{ $task->$fname?->format('Y-m-d') }}"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            </div>
                        @endforeach
                    </div>

                    {{-- Pessoas --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Responsável</label>
                            <select name="responsavel_id" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">— nenhum —</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $task->responsibles->first()?->id === $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Executor</label>
                            <select name="executor_id" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">— nenhum —</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $task->executor?->id === $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Observadores --}}
                    <div x-data="observerPicker({{ json_encode($task->observers->map(fn($u) => ['id' => $u->id, 'name' => $u->name])) }})">
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Observadores</label>
                        <div class="flex flex-wrap gap-2 mb-2" x-show="selected.length > 0">
                            <template x-for="(item, idx) in selected" :key="item.id">
                                <div class="flex items-center gap-2 px-3 py-1.5 text-sm"
                                     style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                    <span x-text="item.name" style="color:var(--text); font-weight:500"></span>
                                    <input type="hidden" :name="'observer_ids[]'" :value="item.id">
                                    <button type="button" @click="remove(idx)" class="btn btn-danger btn-xs">✕</button>
                                </div>
                            </template>
                        </div>
                        <select @change="add($event)" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            <option value="">+ Adicionar observador</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Descrição / Briefing</label>
                        <x-rich-editor name="description" :value="$task->description" min-height="180px" />
                    </div>

                    <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border2)">
                        <button type="submit"
                            class="px-5 py-2.5 text-sm font-semibold text-white"
                            style="background:var(--purple)">
                            Salvar Alterações
                        </button>
                        <button type="button" @click="editing = false"
                            class="text-sm" style="color:var(--muted)">Cancelar</button>
                    </div>
                </div>
            </form>

            {{-- DESCRIÇÃO --}}
            @if($task->description)
                <div x-show="!editing" class="card card-body-lg">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                        <span class="icon-badge">
                            <x-icon name="file-text" size="16" />
                        </span>
                        Briefing / Descrição
                    </p>
                    <div class="rich-editor" style="padding:0; margin:0; min-height:0; cursor:default; resize:none; overflow:visible">
                        <div class="ProseMirror">{!! $task->description !!}</div>
                    </div>
                </div>
            @endif

            {{-- LEGENDA (sempre visível, com edição própria — é o que o cliente avalia) --}}
            <div class="card card-body-lg" x-data="{ editingCaption: false }">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold uppercase tracking-widest flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                        <span class="icon-badge">
                            <x-icon name="tag" size="16" />
                        </span>
                        Legenda
                    </p>
                    <button type="button" @click="editingCaption = !editingCaption" class="text-xs font-semibold" style="color:var(--purple)">
                        <span x-text="editingCaption ? 'Cancelar' : (@js((bool) $task->caption) ? 'Editar' : '+ Adicionar')"></span>
                    </button>
                </div>
                <p class="text-xs mb-4" style="color:var(--muted2)">Texto que vai junto do material pra aprovação do cliente — diferente do Briefing acima, que é interno.</p>

                <div x-show="!editingCaption">
                    @if($task->caption)
                        <div class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.75">{{ $task->caption }}</div>
                    @else
                        <p class="text-sm" style="color:var(--muted)">Nenhuma legenda ainda.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('tasks.update-caption', $task) }}" x-show="editingCaption" x-cloak>
                    @csrf @method('PATCH')
                    <textarea name="caption" rows="5"
                        placeholder="Texto que vai junto do material pro cliente aprovar..."
                        class="w-full px-4 py-3 text-sm focus:outline-none resize-none leading-relaxed"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $task->caption }}</textarea>
                    <button type="submit" class="mt-3 px-4 py-2 text-sm font-semibold text-white" style="background:var(--purple)">
                        Salvar Legenda
                    </button>
                </form>
            </div>

            {{-- INSUMOS --}}
            @php $insumos = $task->attachments->where('kind', 'insumo'); @endphp
            <div class="card card-body-lg">
                <p class="text-xs font-semibold uppercase tracking-widest mb-1 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                    <span class="icon-badge">
                        <x-icon name="paperclip" size="16" />
                    </span>
                    Insumos
                    @if($insumos->count() > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $insumos->count() }}</span>
                    @endif
                </p>
                <p class="text-xs mb-4" style="color:var(--muted2)">Referências, briefings e materiais recebidos — não entram na aprovação do cliente.</p>

                <form method="POST" action="{{ route('task-attachments.store', $task) }}"
                      enctype="multipart/form-data"
                      x-data="{ dragging: false }"
                      @dragover.prevent="dragging = true"
                      @dragleave.prevent="dragging = false"
                      @drop.prevent="dragging = false; $refs.fileInputInsumo.files = $event.dataTransfer.files; $el.submit()">
                    @csrf
                    <input type="hidden" name="kind" value="insumo">
                    <label
                        :style="dragging ? 'border-color:var(--purple); background:rgba(100, 59, 142,.04)' : ''"
                        class="flex flex-col items-center justify-center w-full py-7 cursor-pointer transition-colors"
                        style="border:2px dashed var(--border2); color:var(--muted)">
                        <x-icon name="upload" size="24" class="mb-2.5" />
                        <span class="text-sm font-medium">Clique para anexar ou arraste arquivos</span>
                        <span class="text-xs mt-1" style="color:var(--muted2)">Vários de uma vez · Máx. 50 MB por arquivo</span>
                        <input type="file" name="files[]" multiple x-ref="fileInputInsumo" class="hidden"
                               @change="$el.closest('form').submit()">
                    </label>
                </form>

                @if($insumos->count() > 0)
                    <div class="mt-3 overflow-x-auto">
                        <table class="nonna-table">
                            <thead>
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th>Tamanho</th>
                                    <th>Enviado por</th>
                                    <th>Data</th>
                                    <th class="text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($insumos as $attachment)
                                    @include('partials._task-attachment-row', ['task' => $task, 'attachment' => $attachment])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Arquivos importados do ClickUp --}}
                @if(!empty($task->clickup_attachments))
                    <div class="mt-4 pt-4" style="border-top:1px solid var(--border2)">
                        <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">
                            Arquivos do ClickUp
                            <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ count($task->clickup_attachments) }}</span>
                        </p>
                        <div class="flex flex-col gap-1.5">
                            @foreach($task->clickup_attachments as $file)
                                <div class="flex items-center justify-between gap-3 px-4 py-3"
                                     style="background:var(--s2); border:1px solid var(--border2)">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex-shrink-0" style="color:var(--muted)"><x-icon name="link" size="18" /></span>
                                        <div class="min-w-0">
                                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener"
                                               class="text-sm font-medium truncate block hover:underline"
                                               style="color:var(--text)">
                                                {{ $file['name'] ?? 'Arquivo' }}
                                            </a>
                                            @if(!empty($file['type']))
                                                <p class="text-xs mt-0.5" style="color:var(--muted)">{{ strtoupper($file['type']) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="btn btn-ghost btn-xs flex-shrink-0">
                                        Abrir →
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ENTREGÁVEIS --}}
            @php $entregaveis = $task->attachments->where('kind', 'entregavel'); @endphp
            <div class="card card-body-lg">
                <p class="text-xs font-semibold uppercase tracking-widest mb-1 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                    <span class="icon-badge">
                        <x-icon name="package" size="16" />
                    </span>
                    Entregáveis
                    @if($entregaveis->count() > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $entregaveis->count() }}</span>
                    @endif
                    @if($entregaveis->count() > 0)
                        <a href="{{ route('task-attachments.zip', $task) }}" class="ml-auto btn btn-ghost btn-xs flex items-center gap-1" style="text-transform:none; letter-spacing:normal; font-weight:600">
                            <x-icon name="download" size="13" />
                            Baixar tudo (.zip)
                        </a>
                    @endif
                </p>
                <p class="text-xs mb-4" style="color:var(--muted2)">Material produzido pela equipe — é isso que vai para a aprovação do cliente.</p>

                <form method="POST" action="{{ route('task-attachments.store', $task) }}"
                      enctype="multipart/form-data"
                      x-data="{ dragging: false }"
                      @dragover.prevent="dragging = true"
                      @dragleave.prevent="dragging = false"
                      @drop.prevent="dragging = false; $refs.fileInputEntregavel.files = $event.dataTransfer.files; $el.submit()">
                    @csrf
                    <input type="hidden" name="kind" value="entregavel">
                    <label
                        :style="dragging ? 'border-color:var(--purple); background:rgba(100, 59, 142,.04)' : ''"
                        class="flex flex-col items-center justify-center w-full py-7 cursor-pointer transition-colors"
                        style="border:2px dashed var(--border2); color:var(--muted)">
                        <x-icon name="upload" size="24" class="mb-2.5" />
                        <span class="text-sm font-medium">Clique para anexar ou arraste arquivos</span>
                        <span class="text-xs mt-1" style="color:var(--muted2)">Vários de uma vez (ex: carrossel) · Máx. 50 MB por arquivo</span>
                        <input type="file" name="files[]" multiple x-ref="fileInputEntregavel" class="hidden"
                               @change="$el.closest('form').submit()">
                    </label>
                </form>

                @if($entregaveis->count() > 0)
                    <div class="mt-3 overflow-x-auto">
                        <table class="nonna-table">
                            <thead>
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th>Tamanho</th>
                                    <th>Enviado por</th>
                                    <th>Data</th>
                                    <th class="text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entregaveis as $attachment)
                                    @include('partials._task-attachment-row', ['task' => $task, 'attachment' => $attachment])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ══ RETORNO DO CLIENTE ══ --}}
            @if($task->approvalRounds->count() > 0)
                @php
                    $allRounds     = $task->approvalRounds->sortByDesc('round_number');
                    $latestReview  = $allRounds->first();
                    $hasOpenChange = $latestReview && $latestReview->status === 'changes_requested';
                @endphp

                {{-- Banner de alerta quando há ajustes pendentes --}}
                @if($hasOpenChange)
                    <div class="px-4 py-3 flex items-start gap-3 text-sm font-semibold"
                         style="background:rgba(238, 121, 25,.07); border:1px solid rgba(238, 121, 25,.3); color:var(--orange)">
                        <span class="flex-shrink-0 text-base">✎</span>
                        <span>O cliente solicitou ajustes na Rodada #{{ $latestReview->round_number }}. Revise os feedbacks abaixo antes de reenviar.</span>
                    </div>
                @endif

                <div class="card card-body-lg">
                    <div class="flex items-center justify-between mb-5">
                        <p class="text-xs font-semibold uppercase tracking-widest flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                            <span class="icon-badge">
                                <x-icon name="circle-check" size="16" />
                            </span>
                            Retorno do Cliente
                        </p>
                        <span class="px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">
                            {{ $allRounds->count() }} {{ $allRounds->count() === 1 ? 'rodada' : 'rodadas' }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-3">
                        @foreach($allRounds as $round)
                            @php
                                $rHasChanges = $round->status === 'changes_requested';
                                $rApproved   = $round->status === 'approved';
                                $rPending    = $round->status === 'pending';
                                $borderColor = $rHasChanges ? 'rgba(238, 121, 25,.3)' : ($rApproved ? 'rgba(34,197,94,.3)' : 'var(--border2)');
                                $bgHeader    = $rHasChanges ? 'rgba(238, 121, 25,.04)' : ($rApproved ? 'rgba(34,197,94,.04)' : 'var(--s2)');
                            @endphp

                            <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                                 style="border:1px solid {{ $borderColor }}">

                                {{-- Cabeçalho da rodada (clicável) --}}
                                <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-4 py-3 text-left"
                                    style="background:{{ $bgHeader }}">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-bold" style="color:var(--text)">Rodada #{{ $round->round_number }}</span>
                                        <span class="text-xs" style="color:var(--muted)">{{ $round->submitted_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @php $rNotSent = $rPending && !$round->sent_at; @endphp
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-semibold px-2 py-0.5"
                                              style="background:{{ $rNotSent ? 'var(--s3)' : ($rApproved ? 'rgba(34,197,94,.12)' : ($rHasChanges ? 'rgba(238, 121, 25,.12)' : 'rgba(100, 59, 142,.12)')) }};
                                                     color:{{ $rNotSent ? 'var(--muted)' : ($rApproved ? '#22c55e' : ($rHasChanges ? 'var(--orange)' : 'var(--purple)')) }}">
                                            {{ $round->displayStatusLabel() }}
                                        </span>
                                        <span x-text="open ? '▴' : '▾'" style="color:var(--muted); font-size:10px"></span>
                                    </div>
                                </button>

                                {{-- Corpo da rodada --}}
                                <div x-show="open" x-cloak class="flex flex-col gap-3 px-4 pb-4 pt-3">
                                    @foreach($round->tokens as $token)
                                        @if($token->reviewed_at)
                                            <div style="border:1px solid var(--border2)">
                                                {{-- Cabeçalho do aprovador --}}
                                                <div class="flex items-center justify-between px-4 py-2.5"
                                                     style="border-bottom:1px solid var(--border2); background:var(--s2)">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0"
                                                             style="background:var(--grad)">
                                                            {{ strtoupper(substr($token->contact->name, 0, 1)) }}
                                                        </div>
                                                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $token->contact->name }}</span>
                                                        <span class="text-xs font-semibold px-1.5 py-0.5"
                                                              style="background:{{ $token->status === 'approved' ? 'rgba(34,197,94,.12)' : 'rgba(238, 121, 25,.12)' }};
                                                                     color:{{ $token->status === 'approved' ? '#22c55e' : 'var(--orange)' }}">
                                                            {{ $token->status === 'approved' ? '✓ Aprovado' : '✎ Ajustes' }}
                                                        </span>
                                                    </div>
                                                    <span class="text-xs" style="color:var(--muted)">
                                                        {{ $token->reviewed_at->format('d/m H:i') }}
                                                    </span>
                                                </div>

                                                <div class="px-4 py-3 flex flex-col gap-2.5">
                                                    {{-- Nunca deixar isso parecer clique real do cliente --}}
                                                    @if($token->manually_decided_by)
                                                        <p class="text-xs italic" style="color:var(--muted)">
                                                            Decisão registrada manualmente por {{ $token->manuallyDecidedBy?->name }}, em nome do cliente.
                                                        </p>
                                                    @endif

                                                    {{-- Comentário geral do aprovador --}}
                                                    @if($token->overall_comment)
                                                        <div class="px-3 py-2.5 mb-1"
                                                             style="background:rgba(100, 59, 142,.04); border-left:3px solid var(--purple)">
                                                            <p class="text-xs font-semibold uppercase tracking-widest mb-1.5"
                                                               style="color:var(--muted); letter-spacing:.07em">Comentário Geral</p>
                                                            <p class="text-sm whitespace-pre-wrap"
                                                               style="color:var(--text); line-height:1.65">{{ $token->overall_comment }}</p>
                                                        </div>
                                                    @endif

                                                    @if(!$token->overall_comment)
                                                        <p class="text-xs" style="color:var(--muted)">Sem comentários.</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            {{-- Aprovador ainda não respondeu --}}
                                            <div class="flex flex-col gap-2 px-4 py-3"
                                                 style="background:var(--s2); border:1px dashed var(--border2)">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0"
                                                             style="background:var(--grad); opacity:.4">
                                                            {{ strtoupper(substr($token->contact->name, 0, 1)) }}
                                                        </div>
                                                        <span class="text-sm" style="color:var(--muted)">{{ $token->contact->name }}</span>
                                                    </div>
                                                    <span class="text-xs" style="color:var(--muted)">Aguardando resposta…</span>
                                                </div>

                                                {{-- Aprovação manual: só faz sentido enquanto a rodada segue aberta
                                                     e o contato não foi desligado dela (will_notify). --}}
                                                @if($rPending && $token->will_notify)
                                                    <div class="flex items-center gap-2 pl-8">
                                                        <form method="POST" action="{{ route('approvals.manual-decision', $token) }}"
                                                              @submit.prevent="if (await $store.confirmDialog.ask('Marcar {{ addslashes($token->contact->name) }} como aprovado manualmente? Isso conta como se o cliente tivesse aprovado.')) $el.submit()">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="decision" value="approved">
                                                            <button type="submit" class="text-xs font-semibold px-2 py-1 transition-colors"
                                                                    style="border:1px solid rgba(34,197,94,.4); color:#22c55e"
                                                                    onmouseover="this.style.background='rgba(34,197,94,.08)'" onmouseout="this.style.background='transparent'">
                                                                ✓ Aprovar manualmente
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('approvals.manual-decision', $token) }}"
                                                              @submit.prevent="if (await $store.confirmDialog.ask('Marcar {{ addslashes($token->contact->name) }} como pedido de alteração manualmente?')) $el.submit()">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="decision" value="changes_requested">
                                                            <button type="submit" class="text-xs font-semibold px-2 py-1 transition-colors"
                                                                    style="border:1px solid rgba(238, 121, 25,.4); color:var(--orange)"
                                                                    onmouseover="this.style.background='rgba(238, 121, 25,.08)'" onmouseout="this.style.background='transparent'">
                                                                ✎ Pedir alteração manualmente
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach

                                    {{-- Se rodada pendente mas ninguém revisou ainda --}}
                                    @if($round->tokens->filter(fn($t) => $t->reviewed_at)->isEmpty() && $rPending)
                                        <p class="text-sm text-center py-3" style="color:var(--muted)">
                                            Nenhum aprovador respondeu ainda.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>{{-- /coluna principal --}}

        {{-- ══════════════════════════════════════════════════════════
             SIDEBAR DIREITA
        ══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col gap-4" style="width:320px; flex-shrink:0">

            {{-- DATAS: Prioridade / Aprovação / Publicação / Vencimento — vira o card de "visão
                 rápida" da lateral. Prioridade entrou aqui como primeiro item (antes ficava
                 solta em cima da Situação, sem contexto); Vencimento já tinha saído da barra de
                 campos rápidos antes. Aprovação um pouco mais destacada porque é a data que
                 coordena a entrega da operação. --}}
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                    <span class="icon-badge">
                        <x-icon name="calendar" size="16" />
                    </span>
                    Datas
                </p>
                <div class="flex flex-col gap-4">
                    <div class="pb-3" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Prioridade</p>
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex items-center gap-1.5 text-sm font-semibold" style="color:var(--text)">
                                <x-icon name="flag" size="14" style="color:{{ \App\Models\Task::colorHex((\App\Models\Task::$priorities[$task->priority ?? 'normal']['color'])) }}" />
                                <span class="truncate">{{ $task->priorityLabel() }}</span>
                                <x-icon name="chevron-down" size="12" style="color:var(--muted)" />
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 class="absolute left-0 mt-1 z-20 py-1" style="min-width:180px; background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1)">
                                @foreach(\App\Models\Task::$priorities as $key => $p)
                                    <form method="POST" action="{{ route('tasks.update-priority', $task) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="priority" value="{{ $key }}">
                                        <button type="submit" @click="open = false"
                                            class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-left transition-colors"
                                            style="color:{{ ($task->priority ?? 'normal') === $key ? 'var(--purple)' : 'var(--muted2)' }}; font-weight:{{ ($task->priority ?? 'normal') === $key ? '600' : '400' }}"
                                            onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                            <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:{{ \App\Models\Task::colorHex($p['color']) }}"></span>
                                            {{ $p['label'] }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--orange)"></span>
                            <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--orange); letter-spacing:.08em">Aprovação</p>
                        </div>
                        <p class="text-base" style="color:var(--orange); font-weight:800">
                            {{ $task->approval_date?->format('d/m/Y') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--green)"></span>
                            <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Publicação</p>
                        </div>
                        <p class="text-sm" style="color:var(--text); font-weight:600">
                            {{ $task->publish_date?->format('d/m/Y') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background:var(--red)"></span>
                            <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.08em">Vencimento</p>
                        </div>
                        <p class="text-sm" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--text)' }}; font-weight:600">
                            {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                        </p>
                        @if($task->due_date)
                            <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $task->due_date->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- APROVAÇÃO DO CLIENTE --}}
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                    <span class="icon-badge">
                        <x-icon name="shield-check" size="16" />
                    </span>
                    Aprovação
                </p>

                @php
                    $latestRound = $task->latestApprovalRound;
                    $latestNotSent = $latestRound && $latestRound->status === 'pending' && !$latestRound->sent_at;
                @endphp

                @if($latestRound)
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Rodada</span>
                            <span class="text-sm font-semibold" style="color:var(--text)">#{{ $latestRound->round_number }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Status</span>
                            <span class="text-xs font-semibold px-2 py-0.5"
                                  style="background:{{ $latestNotSent ? 'var(--s3)' : ($latestRound->status === 'approved' ? 'rgba(34,197,94,.12)' : ($latestRound->status === 'changes_requested' ? 'rgba(238, 121, 25,.12)' : 'rgba(100, 59, 142,.12)')) }};
                                         color:{{ $latestNotSent ? 'var(--muted)' : ($latestRound->status === 'approved' ? '#22c55e' : ($latestRound->status === 'changes_requested' ? 'var(--orange)' : 'var(--purple)')) }}">
                                {{ $latestRound->displayStatusLabel() }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Enviado em</span>
                            <span class="text-xs font-medium" style="color:var(--muted2)">{{ $latestRound->submitted_at->format('d/m H:i') }}</span>
                        </div>
                        @php $tokens = $latestRound->tokens; @endphp
                        @if($tokens->count())
                            <div style="border-top:1px solid var(--border2); padding-top:10px; margin-top:4px">
                                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Aprovadores</p>
                                @foreach($tokens as $token)
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs truncate" style="color:var(--muted2); max-width:120px">{{ $token->contact->name }}</span>
                                        <span class="text-xs font-semibold"
                                              style="color:{{ $token->status === 'approved' ? '#22c55e' : ($token->status === 'changes_requested' ? 'var(--orange)' : 'var(--muted)') }}">
                                            {{ $token->status === 'approved' ? '✓ Aprovado' : ($token->status === 'changes_requested' ? '✎ Ajustes' : '· Pendente') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Método</span>
                            <span class="text-sm font-medium" style="color:var(--text)">{{ $task->approvalMethodLabel() ?: '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Aprov. Interna</span>
                            <span class="text-sm font-semibold"
                                  style="color:{{ $task->internal_approval ? 'var(--purple)' : 'var(--muted2)' }}">
                                {{ $task->internal_approval ? 'Sim' : 'Não' }}
                            </span>
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted2); line-height:1.5">
                            Para enviar ao cliente, altere a <strong>Situação</strong> para <em>Enviar para o Cliente</em>.
                        </p>
                    </div>
                @endif
            </div>

            {{-- OBSERVADORES (só avatares — nomes completos ficavam na antiga sessão Pessoas
                 da coluna principal, removida por ocupar espaço demais) --}}
            @if($task->observers->count() > 0)
                <div class="card card-body">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                        <span class="icon-badge">
                            <x-icon name="users" size="16" />
                        </span>
                        Observadores
                    </p>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        @foreach($task->observers as $obs)
                            <x-user-avatar :user="$obs" size="7" color="var(--slate, #64748b)" title="{{ $obs->name }}" />
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- SOLICITANTE --}}
            @if($task->is_ticket && $task->requester_name)
                <div class="card card-body">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                        <span class="icon-badge">
                            <x-icon name="id-card" size="16" />
                        </span>
                        Solicitante
                    </p>
                    <div class="flex flex-col gap-3">
                        <div>
                            <p class="text-xs" style="color:var(--muted)">Nome</p>
                            <p class="text-sm font-semibold mt-0.5" style="color:var(--text)">{{ $task->requester_name }}</p>
                        </div>
                        @if($task->requester_whatsapp)
                            <div>
                                <p class="text-xs" style="color:var(--muted)">WhatsApp</p>
                                <a href="https://wa.me/55{{ preg_replace('/\D/', '', $task->requester_whatsapp) }}"
                                   target="_blank"
                                   class="text-sm font-semibold mt-0.5 block transition-colors" style="color:var(--green)"
                                   onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                                    {{ $task->requester_whatsapp }}
                                </a>
                            </div>
                        @endif
                        @if($task->requester_channel)
                            <div>
                                <p class="text-xs" style="color:var(--muted)">Canal</p>
                                <p class="text-sm font-medium mt-0.5" style="color:var(--text)">
                                    {{ \App\Models\Task::$requesterChannels[$task->requester_channel] ?? '' }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ══ COMENTÁRIOS (antes na coluna principal, movido pra lateral) ══ --}}
            <div class="card card-body-lg" id="comentarios" x-data="{ editId: null }">
                <p class="text-xs font-semibold uppercase tracking-widest mb-5 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                    <span class="icon-badge">
                        <x-icon name="message-circle" size="16" />
                    </span>
                    Comentários
                    @if($task->comments->count() > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $task->comments->count() }}</span>
                    @endif
                </p>

                {{-- Lista --}}
                @if($task->comments->count() > 0)
                    <div class="flex flex-col mb-6">
                        @foreach($task->comments as $comment)
                            <div class="flex gap-3 py-4" style="{{ !$loop->last ? 'border-bottom:1px solid var(--border2)' : '' }}">
                                <x-user-avatar :user="$comment->user" size="7" class="mt-0.5" />
                                <div class="flex-1 min-w-0">
                                    {{-- Exibição --}}
                                    <div x-show="editId !== '{{ $comment->id }}'">
                                        <div class="flex items-baseline gap-2 mb-1.5 flex-wrap">
                                            <span class="text-sm font-semibold" style="color:var(--text)">{{ $comment->user->name }}</span>
                                            <span class="text-xs" style="color:var(--muted2)">{{ $comment->created_at->diffForHumans() }}</span>
                                            @if($comment->updated_at->ne($comment->created_at))
                                                <span class="text-xs" style="color:var(--muted2)">(editado)</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5 mb-1.5">
                                            @if($comment->visible_to_client)
                                                <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full" style="background:rgba(5,150,105,.1); color:var(--green)">Visível pro cliente</span>
                                            @else
                                                <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full" style="background:var(--s3); color:var(--muted)">Interno</span>
                                            @endif
                                        </div>
                                        @php
                                            // Comentário antigo (anterior ao editor rico) é texto puro, sem
                                            // tag nenhuma — converte pra <p>/<br> só na exibição, sem precisar
                                            // de migration/backfill (mesma lógica de tiptap-editor.js:normalizeContent).
                                            $commentHtml = $comment->body;
                                            if (!preg_match('/<[a-z][\s\S]*>/i', $commentHtml)) {
                                                $commentHtml = collect(preg_split('/\n\n+/', $commentHtml))
                                                    ->map(fn ($p) => '<p>' . nl2br(e($p)) . '</p>')
                                                    ->implode('');
                                            }
                                        @endphp
                                        <div class="ProseMirror" style="color:var(--text); line-height:1.65">{!! $commentHtml !!}</div>
                                        @if($comment->user_id === auth()->id())
                                            <div class="flex items-center gap-3 mt-1.5">
                                                <button type="button" @click="editId = '{{ $comment->id }}'" class="text-xs font-mono" style="color:var(--muted)">Editar</button>
                                                <form method="POST"
                                                      action="{{ route('task-comments.destroy', [$task, $comment]) }}"
                                                      @submit.prevent="if (await $store.confirmDialog.ask('Remover comentário?')) $el.submit()">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs font-mono" style="color:var(--red)">Remover</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Edição --}}
                                    @if($comment->user_id === auth()->id())
                                        <div x-show="editId === '{{ $comment->id }}'" x-cloak>
                                            <form method="POST" action="{{ route('task-comments.update', [$task, $comment]) }}">
                                                @csrf @method('PATCH')
                                                <x-rich-editor name="body" :value="$comment->body" min-height="100px" />
                                                <div class="flex items-center gap-3 mt-2">
                                                    <button type="submit" class="text-xs font-mono text-[var(--purple)] hover:underline">Salvar</button>
                                                    <button type="button" @click="editId = null" class="text-xs font-mono" style="color:var(--muted)">Cancelar</button>
                                                </div>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-5 py-7 text-center" style="border:1px dashed var(--border2)">
                        <p class="text-sm" style="color:var(--muted)">Nenhum comentário ainda.</p>
                    </div>
                @endif

                {{-- Novo comentário --}}
                <form method="POST" action="{{ route('task-comments.store', $task) }}">
                    @csrf
                    <div class="flex flex-col gap-2">
                        <x-rich-editor name="body" min-height="80px" />
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-xs" style="color:var(--muted)">
                                <input type="checkbox" name="visible_to_client" value="1">
                                Visível pro cliente
                            </label>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white"
                                style="background:var(--purple)"
                                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                Comentar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- ══ HISTÓRICO — só ações (criação, mudança de status/situação/prioridade/
                 destino/tipo/cliente/projeto/sprint, atribuição de responsável/executor),
                 nunca conteúdo de texto (ver TaskActivity, TaskObserver). ══ --}}
            @if($task->activities->isNotEmpty())
                <div class="card card-body">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
                        <span class="icon-badge">
                            <x-icon name="history" size="16" />
                        </span>
                        Histórico
                    </p>
                    <div class="flex flex-col" style="max-height:360px; overflow-y:auto">
                        @foreach($task->activities as $activity)
                            <div class="flex gap-3">
                                {{-- Trilha: ponto + linha conectando ao próximo (o próprio ponto
                                     mais recente vem preenchido em roxo, os demais só contorno). --}}
                                <div class="flex flex-col items-center flex-shrink-0">
                                    <span class="h-2.5 w-2.5 rounded-full flex-shrink-0"
                                          style="{{ $loop->first ? 'background:var(--purple)' : 'background:var(--s1); border:2px solid var(--border2)' }}"></span>
                                    @if(!$loop->last)
                                        <span class="flex-1" style="width:1px; min-height:10px; background:var(--border2)"></span>
                                    @endif
                                </div>
                                <div class="text-xs {{ !$loop->last ? 'pb-4' : '' }}">
                                    <p style="color:var(--text); font-weight:500; line-height:1.4">
                                        {{ $activity->actionLabel() }}
                                        @if($activity->from_label && $activity->to_label)
                                            <span style="color:var(--muted2)">— {{ $activity->from_label }} → {{ $activity->to_label }}</span>
                                        @elseif($activity->to_label)
                                            <span style="color:var(--muted2)">— {{ $activity->to_label }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5" style="color:var(--muted)">
                                        {{ $activity->user?->name ? explode(' ', $activity->user->name)[0] : 'Sistema' }}
                                        · {{ $activity->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>{{-- /sidebar --}}
    </div>

    {{-- ══════════════════════════════════════════════════════════
         LIGHTBOX DE IMAGEM — abre ao clicar na miniatura/Visualizar de um anexo
         que é imagem. JS puro (funções openAttachmentLightbox/closeAttachmentLightbox
         no <script> do topo), mesmo visual do confirm-dialog-modal.
    ══════════════════════════════════════════════════════════ --}}
    <div id="attachment-lightbox"
         onclick="if (event.target === this) closeAttachmentLightbox()"
         class="fixed inset-0 z-[60] items-center justify-center p-6"
         style="display:none; background:rgba(0,0,0,.75)">
        <div class="flex flex-col items-center gap-3" style="max-width:90vw; max-height:90vh">
            <img id="attachment-lightbox-img" src="" alt=""
                 style="max-width:90vw; max-height:80vh; object-fit:contain; border-radius:8px">
            <div class="flex items-center gap-4">
                <span id="attachment-lightbox-filename" class="text-sm" style="color:#fff"></span>
                <button type="button" onclick="closeAttachmentLightbox()"
                        class="text-xs font-bold uppercase tracking-widest px-3 py-1.5"
                        style="background:rgba(255,255,255,.15); color:#fff">
                    ✕ Fechar
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         PAINEL CHAT IA — deslizante pela direita (estilo ClickUp Brain)
    ══════════════════════════════════════════════════════════ --}}
    <div x-data="taskChat">

        {{-- Backdrop --}}
        <div x-show="$store.ui.chatOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="$store.ui.chatOpen = false"
             class="fixed inset-0 z-40"
             style="background:rgba(0,0,0,.22)">
        </div>

        {{-- Painel --}}
        <div x-show="$store.ui.chatOpen"
             x-cloak
             x-transition:enter="transform transition ease-out duration-200"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="fixed top-0 right-0 h-screen z-50 flex flex-col"
             style="width:440px; background:var(--s1); border-left:1px solid var(--border2); box-shadow:-8px 0 40px rgba(0,0,0,.12)">

            {{-- Cabeçalho do painel --}}
            <div class="flex items-center justify-between px-5 py-4 flex-shrink-0"
                 style="border-bottom:1px solid var(--border2); background:var(--s2)">
                <div class="flex items-center gap-2.5">
                    <x-icon name="sparkles" size="16" class="flex-shrink-0" style="color:var(--purple)" />
                    <span class="text-sm font-semibold" style="color:var(--text)">Chat IA</span>
                    <span x-show="messages.length > 0" x-cloak x-text="messages.length"
                          class="text-xs px-1.5 py-0.5 font-semibold"
                          style="background:rgba(100, 59, 142,.1); color:var(--purple); border:1px solid rgba(100, 59, 142,.2)"></span>
                </div>
                <button @click="$store.ui.chatOpen = false"
                        class="flex items-center justify-center h-7 w-7 text-sm transition-colors"
                        style="color:var(--muted)"
                        onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    ✕
                </button>
            </div>

            {{-- Seletor de especialista --}}
            <div class="px-4 py-3.5 flex-shrink-0" style="border-bottom:1px solid var(--border2); background:var(--s2)">
                <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                       style="color:var(--muted); letter-spacing:.08em">Especialista</label>
                <select x-model="selectedAgent"
                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                    <option value="">Selecione um especialista...</option>
                    <template x-for="agent in agents" :key="agent.id">
                        <option :value="agent.id" x-text="agent.name"></option>
                    </template>
                </select>
                <p x-show="agents.length === 0" x-cloak class="text-xs mt-2" style="color:var(--muted)">
                    Nenhum agente ativo.
                    <a href="{{ route('ai.agents.create') }}" style="color:var(--purple)">Criar agente →</a>
                </p>
                <p x-show="agents.length > 0 && !selectedAgent" x-cloak class="text-xs mt-1.5" style="color:var(--muted2)">
                    O contexto desta tarefa é enviado automaticamente.
                </p>
            </div>

            {{-- Área de mensagens --}}
            <div x-ref="msgContainer"
                 class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3"
                 style="scroll-behavior:smooth">

                <template x-if="messages.length === 0">
                    <div class="flex flex-col items-center justify-center flex-1 py-16 text-center">
                        <x-icon name="message-circle" size="36" stroke="1" class="mb-3" style="color:var(--border2)" />
                        <p class="text-sm font-medium" style="color:var(--muted2)">Inicie a conversa</p>
                        <p class="text-xs mt-1" style="color:var(--muted)">Selecione um especialista e envie sua mensagem.</p>
                    </div>
                </template>

                <template x-for="msg in messages" :key="msg.id">
                    <div :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                         class="flex flex-col gap-1">
                        <span class="text-xs" style="color:var(--muted); font-size:.68rem"
                              x-text="msg.role === 'user'
                                  ? ((msg.user_name || 'Você') + ' · ' + msg.time)
                                  : ((msg.agent_name || 'IA') + ' · ' + msg.time)"></span>
                        <div class="text-sm leading-relaxed whitespace-pre-wrap break-words px-4 py-2.5"
                             :class="msg.role === 'user' ? 'self-end' : 'self-start'"
                             :style="msg.role === 'user'
                                 ? 'background:var(--purple); color:#fff; max-width:85%; border-radius:14px 14px 2px 14px'
                                 : 'background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text); max-width:92%; border-radius:2px 14px 14px 14px'"
                             x-text="msg.content">
                        </div>
                    </div>
                </template>

                <template x-if="thinking">
                    <div class="flex flex-col items-start gap-1">
                        <span class="text-xs" style="color:var(--muted); font-size:.68rem"
                              x-text="agents.find(a => a.id === selectedAgent)?.name ?? 'IA'"></span>
                        <div class="px-4 py-2.5 text-sm"
                             style="background:var(--s3); border:1px solid var(--border); border-radius:8px; border-radius:2px 14px 14px 14px">
                            <span class="animate-pulse" style="color:var(--muted)">digitando...</span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Área de input --}}
            <div class="flex-shrink-0 px-4 py-4" style="border-top:1px solid var(--border2); background:var(--s2)">
                <textarea x-model="input"
                          @keydown.meta.enter.prevent="send()"
                          @keydown.ctrl.enter.prevent="send()"
                          :disabled="!selectedAgent || thinking"
                          rows="3"
                          placeholder="Mensagem para o especialista..."
                          class="w-full px-4 py-3 text-sm focus:outline-none resize-none"
                          style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text); line-height:1.6"
                          onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'"></textarea>
                <div class="flex items-center justify-between mt-3">
                    <span class="text-xs" style="color:var(--muted2)">⌘+Enter envia</span>
                    <button @click="send()"
                            :disabled="!selectedAgent || !input.trim() || thinking"
                            class="px-5 py-2 text-sm font-semibold text-white transition-opacity"
                            :style="(!selectedAgent || !input.trim() || thinking)
                                ? 'background:var(--purple); opacity:.35; cursor:not-allowed'
                                : 'background:var(--purple)'"
                            onmouseover="if(!this.disabled) this.style.opacity='.85'"
                            onmouseout="if(!this.disabled) this.style.opacity='1'">
                        Enviar
                    </button>
                </div>
                <p x-show="error" x-cloak class="text-xs mt-2" style="color:var(--red)" x-text="error"></p>
            </div>

        </div>{{-- /painel --}}
    </div>{{-- /chat ia wrapper --}}

</x-app-layout>

