<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Nova Automação</span>
    </x-slot>

    <div style="max-width:720px" x-data="automationBuilder()">

        <div class="mb-5">
            <a href="{{ route('automations.index') }}"
               class="text-xs transition-colors" style="color:var(--muted)"
               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                ← Automações
            </a>
        </div>

        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm"
                 style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form action="{{ route('automations.store') }}" method="POST">
            @csrf

            {{-- Identificação --}}
            <div class="card" style="padding:1.5rem; margin-bottom:1rem">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Identificação</p>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOME *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               placeholder="Ex: Verificar ortografia ao entrar em revisão"
                               class="w-full px-3 py-2.5 text-sm focus:outline-none"
                               style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DESCRIÇÃO</label>
                        <textarea name="description" rows="2"
                                  class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                  style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">ENTIDADE *</label>
                        <select name="entity_type" x-model="entityType" required
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">Selecione...</option>
                            @foreach($entityTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('entity_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- SE --}}
            <div class="card" style="padding:1.5rem; margin-bottom:1rem; border-left:3px solid var(--purple)">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--purple); letter-spacing:.1em">SE — Quando disparar</p>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TIPO DE GATILHO *</label>
                        <select name="trigger_type" x-model="triggerType" required
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">Selecione...</option>
                            @foreach($triggerTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('trigger_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="triggerType === 'status_changed'" x-cloak>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DE (STATUS)</label>
                                <select name="trigger_config[from]"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="*">Qualquer status</option>
                                    @foreach($taskStatuses as $value => $info)
                                        <option value="{{ $value }}">{{ $info['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PARA (STATUS) *</label>
                                <select name="trigger_config[to]"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">Selecione...</option>
                                    @foreach($taskStatuses as $value => $info)
                                        <option value="{{ $value }}">{{ $info['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="triggerType === 'field_updated'" x-cloak>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO MONITORADO</label>
                        <input type="text" name="trigger_config[field]"
                               placeholder="Ex: situation"
                               class="w-full px-3 py-2.5 text-sm focus:outline-none"
                               style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>

                    <div x-show="triggerType === 'created'" x-cloak>
                        <p class="text-sm" style="color:var(--muted)">Dispara sempre que um novo registro for criado.</p>
                    </div>
                    <div x-show="triggerType === 'manual'" x-cloak>
                        <p class="text-sm" style="color:var(--muted)">Acionada manualmente via botão na interface.</p>
                    </div>
                </div>
            </div>

            {{-- ENTÃO --}}
            <div class="card" style="padding:1.5rem; margin-bottom:1rem; border-left:3px solid var(--orange)">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--orange); letter-spacing:.1em">ENTÃO — O que fazer</p>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TIPO DE AÇÃO *</label>
                        <select name="action_type" x-model="actionType" required
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">Selecione...</option>
                            @foreach($actionTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('action_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="actionType === 'run_ai_agent'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">AGENTE DE IA *</label>
                            <select name="action_config[agent_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <option value="">Selecione um agente...</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">MENSAGEM ADICIONAL (OPCIONAL)</label>
                            <textarea name="action_config[user_message]" rows="2"
                                      placeholder="Deixe em branco para usar o prompt padrão do agente"
                                      class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                      style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                            <p class="text-xs mt-1" style="color:var(--muted)">Variáveis disponíveis: {task_title}, {client_name}, {project_name}...</p>
                        </div>
                    </div>

                    <div x-show="actionType === 'send_webhook'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">URL DO WEBHOOK *</label>
                            <input type="url" name="action_config[url]"
                                   placeholder="https://..."
                                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">MÉTODO</label>
                            <select name="action_config[method]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="actionType === 'update_field'" x-cloak>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO *</label>
                                <input type="text" name="action_config[field]"
                                       placeholder="Ex: status"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOVO VALOR *</label>
                                <input type="text" name="action_config[value]"
                                       placeholder="Ex: revisao"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                        </div>
                    </div>

                    <div x-show="actionType === 'send_notification'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOTIFICAR QUEM</label>
                            <select name="action_config[to]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <option value="executor">Executor da tarefa</option>
                                <option value="creator">Criador da tarefa</option>
                                <option value="all">Todos os envolvidos</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">MENSAGEM</label>
                            <textarea name="action_config[message]" rows="2"
                                      class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                      style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rodapé --}}
            <div class="card" style="padding:1rem 1.5rem">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer text-sm" style="color:var(--text)">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked
                               style="width:16px; height:16px; accent-color:var(--purple)">
                        Ativar imediatamente
                    </label>
                    <div class="flex gap-3">
                        <a href="{{ route('automations.index') }}"
                           class="px-4 py-2 text-sm font-semibold transition-colors"
                           style="color:var(--muted)"
                           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="px-5 py-2 text-sm font-semibold text-white"
                                style="background:var(--purple)">
                            Salvar Automação
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

@push('scripts')
<script>
function automationBuilder() {
    return {
        entityType: '{{ old('entity_type', '') }}',
        triggerType: '{{ old('trigger_type', '') }}',
        actionType: '{{ old('action_type', '') }}',
    }
}
</script>
@endpush
</x-app-layout>
