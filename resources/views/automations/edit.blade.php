<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Editar Automação</span>
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

        <form action="{{ route('automations.update', $automation) }}" method="POST">
            @csrf @method('PATCH')

            {{-- Identificação --}}
            <div class="card" style="padding:1.5rem; margin-bottom:1rem">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Identificação</p>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOME *</label>
                        <input type="text" name="name" value="{{ old('name', $automation->name) }}" required
                               class="w-full px-3 py-2.5 text-sm focus:outline-none"
                               style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DESCRIÇÃO</label>
                        <textarea name="description" rows="2"
                                  class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                  style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ old('description', $automation->description) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">ENTIDADE *</label>
                        <select name="entity_type" x-model="entityType" required
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            @foreach($entityTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('entity_type', $automation->entity_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
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
                            @foreach($triggerTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('trigger_type', $automation->trigger_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
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
                                    <option value="*" {{ ($automation->trigger_config['from'] ?? '') === '*' ? 'selected' : '' }}>Qualquer</option>
                                    @foreach($taskStatuses as $value => $info)
                                        <option value="{{ $value }}" {{ ($automation->trigger_config['from'] ?? '') === $value ? 'selected' : '' }}>{{ $info['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PARA (STATUS)</label>
                                <select name="trigger_config[to]"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">Selecione...</option>
                                    @foreach($taskStatuses as $value => $info)
                                        <option value="{{ $value }}" {{ ($automation->trigger_config['to'] ?? '') === $value ? 'selected' : '' }}>{{ $info['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="triggerType === 'field_updated'" x-cloak>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO MONITORADO *</label>
                                <select name="trigger_config[field]" x-model="fieldUpdatedField"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">Selecione...</option>
                                    <template x-for="[key, meta] in Object.entries(conditionFieldsMap[entityType] || {})" :key="key">
                                        <option :value="key" x-text="meta.label" :selected="key === fieldUpdatedField"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DE (OPCIONAL)</label>
                                <select name="trigger_config[from]"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="*" {{ ($automation->trigger_config['from'] ?? '*') === '*' ? 'selected' : '' }}>Qualquer valor</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[fieldUpdatedField]?.options || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === '{{ $automation->trigger_config['from'] ?? '' }}'"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PARA</label>
                                <select name="trigger_config[to]"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">Qualquer valor</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[fieldUpdatedField]?.options || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === '{{ $automation->trigger_config['to'] ?? '' }}'"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="triggerType === 'date_reached'" x-cloak>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">QUAL DATA *</label>
                        <select name="trigger_config[date_field]"
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">Selecione...</option>
                            @foreach($dateFields as $value => $label)
                                <option value="{{ $value }}" {{ ($automation->trigger_config['date_field'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1" style="color:var(--muted)">Checado 1x por dia (07:00) — dispara pras tarefas cuja data cai em hoje.</p>
                    </div>

                    <div x-show="triggerType === 'executor_added'" x-cloak>
                        <p class="text-sm" style="color:var(--muted)">Dispara quando alguém é adicionado como Responsável, Executor ou Observador de uma tarefa. Pra filtrar só um papel específico, use a condição "Papel (Responsável/Executor)" abaixo.</p>
                    </div>

                    {{-- Condições extras — E/OU, aparece pros gatilhos que fazem sentido combinar com filtro --}}
                    <div x-show="['status_changed','field_updated','date_reached','executor_added'].includes(triggerType)" x-cloak
                         class="pt-3" style="border-top:1px solid var(--border2)">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold" style="color:var(--muted); letter-spacing:.05em">CONDIÇÕES EXTRAS (OPCIONAL)</label>
                            <div x-show="conditions.length > 1" class="flex items-center gap-1.5 text-xs">
                                <button type="button" @click="conditionsLogic = 'and'"
                                        :style="conditionsLogic === 'and' ? 'color:var(--purple); font-weight:700' : 'color:var(--muted)'">E</button>
                                <span style="color:var(--muted)">/</span>
                                <button type="button" @click="conditionsLogic = 'or'"
                                        :style="conditionsLogic === 'or' ? 'color:var(--purple); font-weight:700' : 'color:var(--muted)'">OU</button>
                                <span style="color:var(--muted)">entre as condições</span>
                            </div>
                        </div>
                        <input type="hidden" name="trigger_config[conditions_logic]" :value="conditionsLogic">

                        <template x-for="(cond, idx) in conditions" :key="idx">
                            <div class="flex items-center gap-2 mb-2">
                                <select :name="'trigger_config[conditions][' + idx + '][field]'" x-model="cond.field"
                                        class="flex-1 px-2 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">Campo...</option>
                                    <template x-for="[key, meta] in Object.entries(conditionFieldsMap[entityType] || {})" :key="key">
                                        <option :value="key" x-text="meta.label"></option>
                                    </template>
                                </select>
                                <select :name="'trigger_config[conditions][' + idx + '][operator]'" x-model="cond.operator"
                                        class="px-2 py-1.5 text-xs focus:outline-none" style="width:76px; background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="=">é</option>
                                    <option value="!=">não é</option>
                                </select>
                                <select :name="'trigger_config[conditions][' + idx + '][value]'" x-model="cond.value"
                                        class="flex-1 px-2 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    <option value="">Valor...</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[cond.field]?.options || {})" :key="val">
                                        <option :value="val" x-text="label"></option>
                                    </template>
                                </select>
                                <button type="button" @click="conditions.splice(idx, 1)" class="btn btn-danger btn-xs">✕</button>
                            </div>
                        </template>
                        <button type="button" @click="conditions.push({field: '', operator: '=', value: ''})"
                                class="text-xs font-mono" style="color:var(--purple)">
                            + Adicionar condição
                        </button>
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
                            @foreach($actionTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('action_type', $automation->action_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="actionType === 'run_ai_agent'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">AGENTE DE IA</label>
                            <select name="action_config[agent_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}"
                                        {{ ($automation->action_config['agent_id'] ?? '') === $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">MENSAGEM ADICIONAL</label>
                            <textarea name="action_config[user_message]" rows="2"
                                      class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                      style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $automation->action_config['user_message'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <div x-show="actionType === 'send_webhook'" x-cloak>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">URL DO WEBHOOK</label>
                        <input type="url" name="action_config[url]"
                               value="{{ $automation->action_config['url'] ?? '' }}"
                               class="w-full px-3 py-2.5 text-sm focus:outline-none"
                               style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>

                    <div x-show="actionType === 'update_field'" x-cloak>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO</label>
                                <input type="text" name="action_config[field]"
                                       value="{{ $automation->action_config['field'] ?? '' }}"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOVO VALOR</label>
                                <input type="text" name="action_config[value]"
                                       value="{{ $automation->action_config['value'] ?? '' }}"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                        </div>
                    </div>

                    <div x-show="actionType === 'send_notification'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOTIFICAR QUEM</label>
                            <select name="action_config[to]" x-model="notifyTo"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                @foreach(['executor' => 'Executor', 'creator' => 'Criador', 'all' => 'Todos', 'sector' => 'Um setor', 'role' => 'Um papel funcional'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($automation->action_config['to'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="notifyTo === 'sector'" x-cloak>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">QUAL SETOR *</label>
                            <select name="action_config[sector_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($sectors as $sector)
                                    <option value="{{ $sector->id }}" {{ ($automation->action_config['sector_id'] ?? '') === $sector->id ? 'selected' : '' }}>{{ $sector->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="notifyTo === 'role'" x-cloak>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">QUAL PAPEL FUNCIONAL *</label>
                            <select name="action_config[role]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($functionRoles as $value => $label)
                                    <option value="{{ $value }}" {{ ($automation->action_config['role'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs mt-1" style="color:var(--muted)">Notifica todo mundo que tem esse papel atribuído (Configurações → membros).</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">MENSAGEM</label>
                            <textarea name="action_config[message]" rows="2"
                                      class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                      style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $automation->action_config['message'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">IDENTIFICADOR — OPCIONAL</label>
                            <input type="text" name="action_config[kind]"
                                   value="{{ $automation->action_config['kind'] ?? '' }}"
                                   placeholder="Ex: criativo_pronto_campanha"
                                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <p class="text-xs mt-1" style="color:var(--muted)">Usado pra filtrar esse tipo de notificação em painéis específicos. Deixe em branco se não precisar.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rodapé --}}
            <div class="card" style="padding:1rem 1.5rem">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer text-sm" style="color:var(--text)">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               {{ $automation->is_active ? 'checked' : '' }}
                               style="width:16px; height:16px; accent-color:var(--purple)">
                        Automação ativa
                    </label>
                    <div class="flex gap-3">
                        <a href="{{ route('automations.index') }}" class="btn btn-ghost btn-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Salvar Alterações
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

@push('scripts')
<script>
const conditionFieldsMap = @json($conditionFields);

function automationBuilder() {
    return {
        entityType: '{{ old('entity_type', $automation->entity_type) }}',
        triggerType: '{{ old('trigger_type', $automation->trigger_type) }}',
        actionType: '{{ old('action_type', $automation->action_type) }}',
        notifyTo: '{{ old('action_config.to', $automation->action_config['to'] ?? 'executor') }}',
        fieldUpdatedField: '{{ old('trigger_config.field', $automation->trigger_config['field'] ?? '') }}',
        conditionsLogic: '{{ old('trigger_config.conditions_logic', $automation->trigger_config['conditions_logic'] ?? 'and') }}',
        conditions: {!! json_encode(old('trigger_config.conditions', $automation->trigger_config['conditions'] ?? [])) !!},
    }
}
</script>
@endpush
</x-app-layout>
