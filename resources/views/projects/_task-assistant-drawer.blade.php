{{-- Drawer do Assistente de Lançamento de Tarefas — escopado a este Projeto.
     Chrome copiado do "Chat IA" de tasks/show.blade.php:1537-1678 (não
     extraído em componente Blade — os dois casos divergem demais no corpo,
     ver resources/js/ai-chat-drawer.js). Diferenças: (a) seção "Aplicar
     Playbook" (determinístico, sem IA); (b) cards de rascunho editáveis
     antes de confirmar a criação em lote.
     Espera no escopo: $project, $agents, $playbooks, $chatMessages, $functionalRoles. --}}
<script>
    window._taskAssistant = {
        chatEndpoint:    '{{ route('projects.chat', $project) }}',
        confirmEndpoint: '{{ route('projects.tasks.confirm-batch', $project) }}',
        agents:          @json($agents->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])),
        messages:        @json($chatMessages),
        functionalRoles: @json($functionalRoles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])),
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
            <button @click="$store.taskAssistant.open = false"
                    class="flex items-center justify-center h-7 w-7 text-sm transition-colors"
                    style="color:var(--muted)"
                    onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                ✕
            </button>
        </div>

        {{-- Aplicar Playbook (determinístico, sem IA) --}}
        <div class="px-4 py-3.5 flex-shrink-0" style="border-bottom:1px solid var(--border2); background:var(--s2)"
             x-data="{ playbookId: '' }">
            <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                   style="color:var(--muted); letter-spacing:.08em">Aplicar um Playbook</label>
            @if($playbooks->isEmpty())
                <p class="text-xs" style="color:var(--muted)">
                    Nenhum playbook cadastrado.
                    <a href="{{ route('playbooks.create') }}" style="color:var(--purple)">Criar playbook →</a>
                </p>
            @else
                <div class="flex gap-2">
                    <select x-model="playbookId"
                            class="flex-1 px-3 py-2 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">Selecione um playbook...</option>
                        @foreach($playbooks as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->tasks()->count() }} tarefa(s))</option>
                        @endforeach
                    </select>
                    <button type="button"
                            :disabled="!playbookId"
                            @click="if (playbookId) $refs['playbookForm_' + playbookId].submit()"
                            class="px-4 py-2 text-sm font-semibold text-white flex-shrink-0"
                            :style="!playbookId ? 'background:var(--purple); opacity:.35; cursor:not-allowed' : 'background:var(--purple)'">
                        Aplicar
                    </button>
                </div>
                @foreach($playbooks as $p)
                    <form x-ref="playbookForm_{{ $p->id }}"
                          method="POST" action="{{ route('project-playbooks.apply', [$project, $p]) }}" class="hidden">
                        @csrf
                    </form>
                @endforeach
                <p class="text-xs mt-1.5" style="color:var(--muted2)">
                    Cria de uma vez todas as tarefas do modelo escolhido, sem passar pela IA.
                </p>
            @endif
        </div>

        {{-- Seletor de especialista --}}
        <div class="px-4 py-3.5 flex-shrink-0" style="border-bottom:1px solid var(--border2); background:var(--s2)">
            <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
                   style="color:var(--muted); letter-spacing:.08em">Especialista</label>
            <select x-model="selectedAgent"
                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                O contexto deste projeto (cliente, macroplanejamento, tarefas já existentes) é enviado automaticamente.
            </p>
        </div>

        {{-- Mensagens --}}
        <div x-ref="msgContainer" class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3" style="scroll-behavior:smooth">
            <template x-if="messages.length === 0">
                <div class="flex flex-col items-center justify-center flex-1 py-16 text-center">
                    <x-icon name="message-circle" size="36" stroke="1" class="mb-3" style="color:var(--border2)" />
                    <p class="text-sm font-medium" style="color:var(--muted2)">Descreva as tarefas que quer criar</p>
                    <p class="text-xs mt-1" style="color:var(--muted)">Ex: "cria uma tarefa de briefing pra sexta e uma de wireframe pra próxima terça".</p>
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
                          x-text="agents.find(a => a.id === selectedAgent)?.name ?? 'IA'"></span>
                    <div class="px-4 py-2.5 text-sm" style="background:var(--s3); border:1px solid var(--border); border-radius:2px 14px 14px 14px">
                        <span class="animate-pulse" style="color:var(--muted)">pensando...</span>
                    </div>
                </div>
            </template>

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
                                   class="w-full px-2 py-1.5 text-xs font-semibold rounded mb-2 focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <select x-model="d.task_type" class="px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                    @foreach(\App\Models\Task::$types as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select x-model="d.priority" class="px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                    <option value="">Prioridade —</option>
                                    @foreach(\App\Models\Task::$priorities as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" min="0" max="3650" x-model="d.due_offset_days"
                                       placeholder="Prazo (dias)"
                                       class="px-2 py-1.5 text-xs rounded focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                <select x-model="d.functional_role_id" class="px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                    <option value="">Responsável —</option>
                                    <template x-for="role in functionalRoles" :key="role.id">
                                        <option :value="role.id" x-text="role.name"></option>
                                    </template>
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
                      :disabled="!selectedAgent || thinking"
                      rows="3"
                      placeholder="Descreva as tarefas que quer lançar..."
                      class="w-full px-4 py-3 text-sm focus:outline-none resize-none"
                      style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text); line-height:1.6"></textarea>
            <div class="flex items-center justify-between mt-3">
                <span class="text-xs" style="color:var(--muted2)">⌘+Enter envia</span>
                <button @click="send()"
                        :disabled="!selectedAgent || !input.trim() || thinking"
                        class="px-5 py-2 text-sm font-semibold text-white transition-opacity"
                        :style="(!selectedAgent || !input.trim() || thinking)
                            ? 'background:var(--purple); opacity:.35; cursor:not-allowed'
                            : 'background:var(--purple)'">
                    Enviar
                </button>
            </div>
            <p x-show="error" x-cloak class="text-xs mt-2" style="color:var(--red)" x-text="error"></p>
        </div>

    </div>{{-- /painel --}}
</div>{{-- /taskAssistantDrawer --}}
