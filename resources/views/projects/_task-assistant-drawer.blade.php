{{-- Drawer do Assistente de Lançamento de Tarefas — escopado a este Projeto.
     Chrome copiado do "Chat IA" de tasks/show.blade.php:1537-1678 (não
     extraído em componente Blade — os dois casos divergem demais no corpo,
     ver resources/js/ai-chat-drawer.js). Diferenças: (a) seção "Aplicar
     Playbook" (determinístico, sem IA); (b) cards de rascunho editáveis
     antes de confirmar a criação em lote.
     Espera no escopo: $project, $assistantAgent (pode ser null), $playbooks, $chatMessages, $team. --}}
<script>
    window._taskAssistant = {
        chatEndpoint:    '{{ route('projects.chat', $project) }}',
        confirmEndpoint: '{{ route('projects.tasks.confirm-batch', $project) }}',
        clearEndpoint:   '{{ route('projects.chat.clear', $project) }}',
        applyEndpoint:   '{{ route('projects.chat.apply', $project) }}',
        agentName:       @json($assistantAgent?->name),
        messages:        @json($chatMessages),
        team:            @json($team->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])),
        currentUserName: '{{ auth()->user()->name }}',
    };

    document.addEventListener('alpine:init', () => {
        Alpine.store('taskAssistant', { open: false });
    });
</script>

<div x-data="taskAssistantDrawer">

    {{-- Backdrop --}}
    <div x-show="$store.taskAssistant.open"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="$store.taskAssistant.open = false"
         class="fixed inset-0 z-40"
         style="background:rgba(0,0,0,.22)">
    </div>

    {{-- Painel --}}
    <div x-show="$store.taskAssistant.open"
         x-cloak
         x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 h-screen z-50 flex flex-col"
         style="width:460px; background:var(--s1); border-left:1px solid var(--border2); box-shadow:-8px 0 40px rgba(0,0,0,.12)">

        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between px-5 py-4 flex-shrink-0"
             style="border-bottom:1px solid var(--border2); background:var(--s2)">
            <div class="flex items-center gap-2.5">
                <x-icon name="sparkles" size="16" class="flex-shrink-0" style="color:var(--purple)" />
                <span class="text-sm font-semibold" style="color:var(--text)">Assistente de Lançamento de Tarefas</span>
            </div>
            <div class="flex items-center gap-3">
                <button @click="newConversation()"
                        x-show="messages.length > 0"
                        x-cloak
                        class="text-xs font-semibold transition-colors"
                        style="color:var(--muted)"
                        onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                    Nova conversa
                </button>
                <button @click="$store.taskAssistant.open = false"
                        class="flex items-center justify-center h-7 w-7 text-sm transition-colors"
                        style="color:var(--muted)"
                        onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    ✕
                </button>
            </div>
        </div>

        {{-- Aplicar Playbook (determinístico, sem IA) — mesmo parcial usado no
             cabeçalho da página do projeto, ver _apply-playbook-picker.blade.php --}}
        <div class="px-4 py-3.5 flex-shrink-0" style="border-bottom:1px solid var(--border2); background:var(--s2)">
            @include('projects._apply-playbook-picker', ['project' => $project, 'playbooks' => $playbooks, 'compact' => false])
        </div>

        {{-- Agente fixo (slug task-assistant) — sem seletor, os outros agentes não
             respondem no formato de rascunho de tarefas --}}
        @unless($assistantAgent)
            <div class="px-4 py-3 flex-shrink-0 text-xs" style="border-bottom:1px solid var(--border2); background:var(--s2); color:var(--red)">
                O agente "Assistente de Lançamento de Tarefas" não está configurado ou está inativo — o chat fica desligado até alguém ativá-lo em Agentes de IA.
            </div>
        @endunless

        {{-- Mensagens --}}
        <div x-ref="msgContainer" class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3" style="scroll-behavior:smooth">
            <template x-if="messages.length === 0">
                <div class="flex flex-col items-center justify-center flex-1 py-16 text-center">
                    <x-icon name="message-circle" size="36" stroke="1" class="mb-3" style="color:var(--border2)" />
                    <p class="text-sm font-medium" style="color:var(--muted2)">Crie ou reorganize as tarefas deste projeto</p>
                    <p class="text-xs mt-1 px-6" style="color:var(--muted)">Ex: "cria uma tarefa de briefing pra sexta", "o que está atrasado aqui?" ou "espalha as datas de aprovação dos posts até 24/10, no máximo 2 por dia pro Andreo".</p>
                    <p class="text-xs mt-3 px-6" style="color:var(--muted2)">O assistente vê todas as tarefas do projeto e a carga do time. Nada é criado nem alterado antes de você revisar e confirmar.</p>
                </div>
            </template>

            <template x-for="msg in messages" :key="msg.id">
                <div :class="msg.role === 'user' ? 'items-end' : 'items-start'" class="flex flex-col gap-1">
                    <span class="text-xs" style="color:var(--muted); font-size:.68rem"
                          x-text="msg.role === 'user'
                              ? ((msg.user_name || 'Você') + ' · ' + msg.time)
                              : ((msg.agent_name || 'IA') + ' · ' + msg.time)"></span>
                    <div class="text-sm leading-relaxed whitespace-pre-wrap break-words px-4 py-2.5"
                         :class="msg.role === 'user' ? 'self-end' : 'self-start'"
                         :style="msg.role === 'user'
                             ? 'background:var(--purple); color:#fff; max-width:85%; border-radius:14px 14px 2px 14px'
                             : 'background:var(--s3); border:1px solid var(--border); color:var(--text); max-width:92%; border-radius:2px 14px 14px 14px'"
                         x-text="msg.content"></div>
                </div>
            </template>

            <template x-if="thinking">
                <div class="flex flex-col items-start gap-1">
                    <span class="text-xs" style="color:var(--muted); font-size:.68rem"
                          x-text="agentName ?? 'IA'"></span>
                    <div class="px-4 py-2.5 text-sm" style="background:var(--s3); border:1px solid var(--border); border-radius:2px 14px 14px 14px">
                        <span class="animate-pulse" style="color:var(--muted)">pensando...</span>
                    </div>
                </div>
            </template>

            {{-- Alterações em tarefas que JÁ existem — propostas pela IA, revisadas aqui. Só as
                 marcadas são aplicadas (ProjectTaskAssistantController::applyChanges revalida tudo). --}}
            <div x-show="changes.length > 0" x-cloak class="mt-2 pt-3" style="border-top:1px dashed var(--border2)">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">
                        Alterações propostas — revise antes de aplicar
                    </p>
                    <button type="button" @click="discardChanges()" class="text-xs font-semibold" style="color:var(--muted)">Descartar</button>
                </div>
                <div class="space-y-2">
                    <template x-for="g in changeGroups" :key="g.task_id">
                        <div class="px-3 py-2.5 rounded" style="background:var(--s2); border:1px solid var(--border2)">
                            <p class="text-xs font-semibold mb-1.5 flex items-center gap-1.5 flex-wrap" style="color:var(--text)">
                                <span class="font-mono" style="color:var(--muted)" x-text="g.ref"></span>
                                <template x-if="g.blocked">
                                    <span class="font-mono font-bold px-1" style="font-size:9px; color:#fff; background:var(--red); border-radius:4px">TRAVA</span>
                                </template>
                                <span x-text="g.title"></span>
                                <span class="font-normal" style="color:var(--muted)" x-text="'· ' + g.status"></span>
                            </p>
                            <template x-for="c in g.items" :key="c.key">
                                <label class="flex items-start gap-2 py-1 text-xs cursor-pointer">
                                    <input type="checkbox" x-model="c.checked" class="mt-0.5 flex-shrink-0" style="accent-color:var(--purple)">
                                    <span class="min-w-0" :style="c.checked ? '' : 'opacity:.5'">
                                        <span class="font-semibold" style="color:var(--text)" x-text="c.field_label + ':'"></span>
                                        <span style="color:var(--muted); text-decoration:line-through" x-text="c.from_label"></span>
                                        <span style="color:var(--muted)">→</span>
                                        <span class="font-semibold" style="color:var(--purple)" x-text="c.to_label"></span>
                                        <span x-show="c.reason" class="block mt-0.5" style="color:var(--muted2)" x-text="c.reason"></span>
                                    </span>
                                </label>
                            </template>
                        </div>
                    </template>
                </div>
                <button @click="applyChanges()"
                        :disabled="applying || checkedChanges.length === 0"
                        class="w-full mt-3 px-4 py-2.5 text-sm font-semibold text-white transition-opacity"
                        :style="(applying || checkedChanges.length === 0) ? 'background:var(--purple); opacity:.5' : 'background:var(--purple)'">
                    <span x-text="applying ? 'Aplicando...' : ('Aplicar ' + checkedChanges.length + ' alteração(ões)')"></span>
                </button>
            </div>

            <p x-show="appliedNote" x-cloak class="text-xs mt-1" style="color:var(--muted)">
                <span x-text="appliedNote"></span>
                <button type="button" @click="reloadPage()" class="font-semibold" style="color:var(--purple)">Atualizar a página</button>
            </p>

            {{-- Cards de rascunho editáveis --}}
            <div x-show="drafts.length > 0" x-cloak class="mt-2 pt-3" style="border-top:1px dashed var(--border2)">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted)">
                    Rascunho — revise antes de criar
                </p>
                <div class="space-y-2">
                    <template x-for="(d, i) in drafts" :key="i">
                        <div class="px-3 py-3 rounded relative" style="background:var(--s2); border:1px solid var(--border2)">
                            <button type="button" @click="removeDraft(i)" class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>
                            <input type="text" x-model="d.title"
                                   class="field px-2 py-1.5 text-xs font-semibold mb-2 focus:outline-none"
                                   style="width:calc(100% - 22px)">
                            {{-- Descrição visível e editável — antes a IA escrevia e ela era
                                 gravada sem ninguém ter lido --}}
                            <textarea x-model="d.description" rows="3" placeholder="Descrição (opcional)"
                                      class="field w-full px-2 py-1.5 text-xs mb-2 focus:outline-none resize-y"
                                      style="line-height:1.5"></textarea>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <select x-model="d.task_type" class="px-2 py-1.5 text-xs rounded focus:outline-none"
                                        :style="!d.task_type
                                            ? 'background:var(--s3); border:1px solid var(--red); color:var(--red)'
                                            : 'background:var(--s3); border:1px solid var(--border); color:var(--text)'">
                                    <option value="">Escolha o tipo *</option>
                                    @foreach(\App\Models\Task::$types as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select x-model="d.priority" class="field px-2 py-1.5 text-xs focus:outline-none">
                                    <option value="">Prioridade —</option>
                                    @foreach(\App\Models\Task::$priorities as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div>
                                    <input type="date" x-model="d.due_date" title="Prazo"
                                           class="field w-full px-2 py-1.5 text-xs focus:outline-none">
                                    <p class="mt-0.5" style="color:var(--muted); font-size:.68rem"
                                       x-text="d.due_date ? weekdayLabel(d.due_date) : 'Sem prazo'"></p>
                                </div>
                                <select x-model="d.executor_user_id" title="Executor (quem faz)"
                                        class="field self-start px-2 py-1.5 text-xs focus:outline-none">
                                    <option value="">Executor —</option>
                                    <template x-for="u in team" :key="u.id">
                                        <option :value="u.id" x-text="u.name" :selected="u.id == d.executor_user_id"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select x-model="d.responsavel_user_id" title="Responsável"
                                        class="field px-2 py-1.5 text-xs focus:outline-none">
                                    <option value="">Responsável —</option>
                                    <template x-for="u in team" :key="u.id">
                                        <option :value="u.id" x-text="u.name" :selected="u.id == d.responsavel_user_id"></option>
                                    </template>
                                </select>
                                <select x-model="d.destination" class="field px-2 py-1.5 text-xs focus:outline-none">
                                    <option value="">Destino —</option>
                                    @foreach(\App\Models\Task::$destinations as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </template>
                </div>
                <button @click="confirmDrafts()"
                        :disabled="confirming"
                        class="w-full mt-3 px-4 py-2.5 text-sm font-semibold text-white transition-opacity"
                        :style="confirming ? 'background:var(--purple); opacity:.5' : 'background:var(--purple)'">
                    <span x-text="confirming ? 'Criando...' : ('Confirmar e criar ' + drafts.length + ' tarefa(s)')"></span>
                </button>
            </div>
        </div>

        {{-- Input --}}
        <div class="flex-shrink-0 px-4 py-4" style="border-top:1px solid var(--border2); background:var(--s2)">
            <textarea x-model="input"
                      @keydown.meta.enter.prevent="send()"
                      @keydown.ctrl.enter.prevent="send()"
                      :disabled="!agentName || thinking"
                      rows="3"
                      placeholder="Peça pra criar tarefas ou reorganizar as que já existem..."
                      class="field w-full px-4 py-3 text-sm focus:outline-none resize-none"
                      style="line-height:1.6"></textarea>
            <div class="flex items-center justify-between mt-3">
                <span class="text-xs" style="color:var(--muted2)">⌘+Enter envia</span>
                <button @click="send()"
                        :disabled="!agentName || !input.trim() || thinking"
                        class="px-5 py-2 text-sm font-semibold text-white transition-opacity"
                        :style="(!agentName || !input.trim() || thinking)
                            ? 'background:var(--purple); opacity:.35; cursor:not-allowed'
                            : 'background:var(--purple)'">
                    Enviar
                </button>
            </div>
            <p x-show="error" x-cloak class="text-xs mt-2" style="color:var(--red)" x-text="error"></p>
        </div>

    </div>{{-- /painel --}}
</div>{{-- /taskAssistantDrawer --}}
