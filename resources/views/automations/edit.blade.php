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
                               style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DESCRIÇÃO</label>
                        <textarea name="description" rows="2"
                                  class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                  style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ old('description', $automation->description) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">ENTIDADE *</label>
                        <select name="entity_type" x-model="entityType" required
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            @foreach($triggerTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('trigger_type', $automation->trigger_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- IMPORTANTE: trigger_config[from]/[to] existem duas vezes (aqui e no bloco
                         "field_updated" logo abaixo) — MESMO name nos dois, porque só um bloco
                         fica visível por vez (x-show). x-show só esconde com CSS, não remove do
                         formulário: sem :disabled, os dois sempre eram enviados juntos no submit,
                         e o PHP fica com o ÚLTIMO valor do corpo da request — o campo escondido
                         sempre vencia e apagava o valor certo do campo visível (causa real de
                         "salvei e o PARA sumiu", mesmo a tela mostrando o valor certo antes de
                         salvar). --}}
                    <div x-show="triggerType === 'status_changed'" x-cloak>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DE (OPCIONAL)</label>
                                <select name="trigger_config[from]" :disabled="triggerType !== 'status_changed'"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="*" {{ ($automation->trigger_config['from'] ?? '*') === '*' ? 'selected' : '' }}>Qualquer</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[primaryField()]?.options || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === '{{ $automation->trigger_config['from'] ?? '' }}'"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PARA</label>
                                <select name="trigger_config[to]" :disabled="triggerType !== 'status_changed'"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Selecione...</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[primaryField()]?.options || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === '{{ $automation->trigger_config['to'] ?? '' }}'"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="triggerType === 'field_updated'" x-cloak>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO MONITORADO *</label>
                                {{-- x-init força o valor de novo depois que o x-for (abaixo) já criou as
                                     <option>s — sem isso, o x-model roda ANTES das options existirem
                                     (select ainda só com "Selecione..."), o navegador ignora o valor
                                     silenciosamente, e o campo salvo (ex: "situation") não aparece
                                     selecionado ao editar, mesmo a automação continuando correta por baixo. --}}
                                <select name="trigger_config[field]" x-model="fieldUpdatedField"
                                        x-init="$nextTick(() => $el.value = fieldUpdatedField)"
                                        :disabled="triggerType !== 'field_updated'"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Selecione...</option>
                                    <template x-for="[key, meta] in Object.entries(conditionFieldsMap[entityType] || {})" :key="key">
                                        <option :value="key" x-text="meta.label" :selected="key === fieldUpdatedField"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DE (OPCIONAL)</label>
                                <select name="trigger_config[from]" :disabled="triggerType !== 'field_updated'"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="*" {{ ($automation->trigger_config['from'] ?? '*') === '*' ? 'selected' : '' }}>Qualquer valor</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[fieldUpdatedField]?.options || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === '{{ $automation->trigger_config['from'] ?? '' }}'"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PARA</label>
                                <select name="trigger_config[to]" :disabled="triggerType !== 'field_updated'"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Qualquer valor</option>
                                    <template x-for="[val, label] in Object.entries((conditionFieldsMap[entityType]||{})[fieldUpdatedField]?.options || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === '{{ $automation->trigger_config['to'] ?? '' }}'"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="triggerType === 'date_reached'" x-cloak>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">QUAL DATA *</label>
                                <select name="trigger_config[date_field]" x-model="dateField"
                                        x-init="$nextTick(() => $el.value = dateField)"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Selecione...</option>
                                    <template x-for="[val, label] in Object.entries(dateFieldsMap[entityType] || {})" :key="val">
                                        <option :value="val" x-text="label" :selected="val === dateField"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DIAS DE ANTECEDÊNCIA</label>
                                <input type="number" name="trigger_config[offset_days]" min="0"
                                       value="{{ $automation->trigger_config['offset_days'] ?? 0 }}"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            </div>
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted)">Checado 1x por dia (07:00) — dispara quando faltam N dias pra data escolhida (0 = dispara no próprio dia).</p>
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
                                {{-- mesmo motivo do x-init em CAMPO MONITORADO (acima) — options dinâmicas
                                     via x-for, x-model sozinho não reaplica o valor salvo ao editar --}}
                                <select :name="'trigger_config[conditions][' + idx + '][field]'" x-model="cond.field"
                                        x-init="$nextTick(() => $el.value = cond.field)"
                                        class="flex-1 px-2 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Campo...</option>
                                    <template x-for="[key, meta] in Object.entries(conditionFieldsMap[entityType] || {})" :key="key">
                                        <option :value="key" x-text="meta.label"></option>
                                    </template>
                                </select>
                                <select :name="'trigger_config[conditions][' + idx + '][operator]'" x-model="cond.operator"
                                        class="px-2 py-1.5 text-xs focus:outline-none" style="width:76px; background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="=">é</option>
                                    <option value="!=">não é</option>
                                </select>
                                <select :name="'trigger_config[conditions][' + idx + '][value]'" x-model="cond.value"
                                        x-init="$nextTick(() => $el.value = cond.value)"
                                        class="flex-1 px-2 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                                      style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $automation->action_config['user_message'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <div x-show="actionType === 'send_webhook'" x-cloak>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">URL DO WEBHOOK</label>
                        <input type="url" name="action_config[url]"
                               value="{{ $automation->action_config['url'] ?? '' }}"
                               class="w-full px-3 py-2.5 text-sm focus:outline-none"
                               style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>

                    <div x-show="actionType === 'update_field'" x-cloak>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO</label>
                                {{-- mesmo motivo do x-init em CAMPO MONITORADO acima --}}
                                <select name="action_config[field]" x-model="actionField"
                                        x-init="$nextTick(() => $el.value = actionField)"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Selecione...</option>
                                    <template x-for="[key, meta] in Object.entries(conditionFieldsMap[entityType] || {})" :key="key">
                                        <option :value="key" x-text="meta.label"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOVO VALOR</label>
                                {{-- mesmo motivo do x-init em CAMPO MONITORADO acima --}}
                                <select name="action_config[value]" x-model="actionValue"
                                        x-init="$nextTick(() => $el.value = actionValue)"
                                        x-show="Object.keys(actionFieldOptions()).length > 0"
                                        :disabled="Object.keys(actionFieldOptions()).length === 0"
                                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                    <option value="">Selecione...</option>
                                    <template x-for="[val, label] in Object.entries(actionFieldOptions())" :key="val">
                                        <option :value="val" x-text="label"></option>
                                    </template>
                                </select>
                                <input type="text" name="action_config[value]" x-model="actionValue"
                                       x-show="Object.keys(actionFieldOptions()).length === 0"
                                       :disabled="Object.keys(actionFieldOptions()).length > 0"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            </div>
                        </div>
                        <p class="text-xs mt-1" style="color:var(--muted)">Campos sem lista fixa (título, datas, e-mails...) ficam como texto livre.</p>
                    </div>

                    <div x-show="actionType === 'send_notification'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOTIFICAR QUEM</label>
                            <select name="action_config[to]" x-model="notifyTo"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                @foreach(['executor' => 'Executor', 'creator' => 'Criador', 'all' => 'Todos', 'participants' => 'Participantes da Reunião', 'sector' => 'Um setor', 'role' => 'Um papel funcional'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($automation->action_config['to'] ?? '') === $v ? 'selected' : '' }} @if($v === 'participants') x-show="entityType === 'meeting'" @endif>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="notifyTo === 'sector'" x-cloak>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">QUAL SETOR *</label>
                            <select name="action_config[sector_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
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
                                      style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $automation->action_config['message'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">IDENTIFICADOR — OPCIONAL</label>
                            <input type="text" name="action_config[kind]"
                                   value="{{ $automation->action_config['kind'] ?? '' }}"
                                   placeholder="Ex: criativo_pronto_campanha"
                                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            <p class="text-xs mt-1" style="color:var(--muted)">Usado pra filtrar esse tipo de notificação em painéis específicos. Deixe em branco se não precisar.</p>
                        </div>
                    </div>

                    <div x-show="actionType === 'create_record'" x-cloak class="grid gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TIPO DE REGISTRO</label>
                            <select name="action_config[record_type]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="ticket" {{ ($automation->action_config['record_type'] ?? 'ticket') === 'ticket' ? 'selected' : '' }}>Ticket</option>
                                <option value="task" {{ ($automation->action_config['record_type'] ?? 'ticket') === 'task' ? 'selected' : '' }}>Tarefa avulsa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TÍTULO *</label>
                            <input type="text" name="action_config[title]"
                                   value="{{ $automation->action_config['title'] ?? '' }}"
                                   placeholder="Ex: Follow-up — {opportunity_title}"
                                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            <p class="text-xs mt-1" style="color:var(--muted)">Variáveis disponíveis: {task_title}, {client_name}, {project_name}, {opportunity_title}...</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DESCRIÇÃO (OPCIONAL)</label>
                            <textarea name="action_config[description]" rows="2"
                                      class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none"
                                      style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $automation->action_config['description'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TIPO</label>
                            <select name="action_config[task_type]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                @foreach($taskTypes as $value => $label)
                                    <option value="{{ $value }}" {{ ($automation->action_config['task_type'] ?? 'estrategia') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CLIENTE</label>
                            <select name="action_config[client_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="inherit" {{ ($automation->action_config['client_id'] ?? 'inherit') === 'inherit' ? 'selected' : '' }}>— usar cliente da tarefa/oportunidade que disparou —</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ ($automation->action_config['client_id'] ?? '') === $client->id ? 'selected' : '' }}>{{ $client->displayName() }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs mt-1" style="color:var(--muted)">Sem cliente resolvido, o registro criado entra na fila de Pendências de Cadastro.</p>
                        </div>

                        <div class="pt-3" style="border-top:1px solid var(--border2)">
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">RESPONSÁVEL/EXECUTOR</label>
                            <select name="action_config[assignee_source]" x-model="assigneeSource"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="none">Não atribuir</option>
                                <option value="fixed_user">Pessoa fixa</option>
                                <option value="trigger_organizer" x-show="entityType === 'meeting'">Organizador da Reunião que disparou</option>
                            </select>
                            <p class="text-xs mt-1" style="color:var(--muted)">A pessoa escolhida vira Responsável e Executor da tarefa ao mesmo tempo.</p>
                        </div>
                        <div x-show="assigneeSource === 'fixed_user'" x-cloak>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">QUAL PESSOA *</label>
                            <select name="action_config[user_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ ($automation->action_config['user_id'] ?? '') === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">SPRINT</label>
                            <select name="action_config[sprint_target]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="none" {{ ($automation->action_config['sprint_target'] ?? 'none') === 'none' ? 'selected' : '' }}>Fica no Backlog</option>
                                <option value="active_sprint" {{ ($automation->action_config['sprint_target'] ?? '') === 'active_sprint' ? 'selected' : '' }}>Já nasce na Sprint ativa</option>
                            </select>
                        </div>

                        <div class="pt-3" style="border-top:1px solid var(--border2)">
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">BASE DO PRAZO</label>
                            <select name="action_config[due_base]" x-model="dueBase"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="now">A partir de agora (quando a automação dispara)</option>
                                <option value="entity_field" x-show="entityType === 'meeting'">A partir de uma data da Reunião que disparou</option>
                            </select>
                        </div>
                        <div x-show="dueBase === 'entity_field'" x-cloak>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">CAMPO DE DATA DE ORIGEM</label>
                            <select name="action_config[due_base_field]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="scheduled_at" {{ ($automation->action_config['due_base_field'] ?? 'scheduled_at') === 'scheduled_at' ? 'selected' : '' }}>Data/hora da Reunião</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DIAS A SOMAR</label>
                                <input type="number" name="action_config[due_in_days]" min="0"
                                       value="{{ $automation->action_config['due_in_days'] ?? 0 }}"
                                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                       style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <p class="text-xs mt-1" style="color:var(--muted)">Ex: 1 = "dia seguinte" à base escolhida acima.</p>
                            </div>
                            <div class="flex items-end pb-2.5">
                                <label class="flex items-center gap-2 cursor-pointer text-xs" style="color:var(--text)">
                                    <input type="hidden" name="action_config[due_skip_weekends]" value="0">
                                    <input type="checkbox" name="action_config[due_skip_weekends]" value="1"
                                           {{ !empty($automation->action_config['due_skip_weekends']) ? 'checked' : '' }}
                                           style="width:16px; height:16px; accent-color:var(--purple)">
                                    Rolar pro próximo dia útil se cair em fim de semana
                                </label>
                            </div>
                        </div>
                    </div>

                    <div x-show="actionType === 'create_macroplan_from_meeting'" x-cloak class="grid gap-3">
                        <p class="text-sm" style="color:var(--muted)">
                            Cria um Macroplanejamento vinculado à Reunião que disparou (responsável = primeiro
                            usuário do papel funcional abaixo) e já cria a Tarefa de checklist "Criar
                            macroplanejamento" vinculada ao Macro, atribuída ao organizador da reunião, com prazo
                            no próximo dia útil. Pensado pro gatilho "Status mudou" → Realizada numa Reunião do
                            tipo Macroplanejamento ou Kickoff Estratégico.
                        </p>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PAPEL FUNCIONAL RESPONSÁVEL PELO MACRO *</label>
                            <select name="action_config[responsible_role]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($functionRoles as $value => $label)
                                    <option value="{{ $value }}" {{ ($automation->action_config['responsible_role'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TÍTULO DA TAREFA (OPCIONAL)</label>
                            <input type="text" name="action_config[task_title]"
                                   value="{{ $automation->action_config['task_title'] ?? '' }}"
                                   placeholder="Criar macroplanejamento"
                                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                   style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        </div>
                    </div>

                    <div x-show="actionType === 'create_macroplan_review'" x-cloak class="grid gap-3">
                        <p class="text-sm" style="color:var(--muted)">
                            Cria um Macroplanejamento vinculado à Reunião que disparou, agenda automaticamente uma
                            Reunião de Revisão Interna pro próximo dia útil (já linkada ao mesmo Macro) e notifica
                            o papel funcional abaixo. Pensado pro gatilho "Status mudou" de Pós-Reunião → Realizada
                            numa Reunião do tipo Macroplanejamento.
                        </p>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PAPEL FUNCIONAL A NOTIFICAR *</label>
                            <select name="action_config[role]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($functionRoles as $value => $label)
                                    <option value="{{ $value }}" {{ ($automation->action_config['role'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs mt-1" style="color:var(--muted)">Notifica todo mundo com esse papel quando o Macro e a reunião de revisão forem criados.</p>
                        </div>
                    </div>

                    <div x-show="actionType === 'create_internal_review_pauta'" x-cloak class="grid gap-3">
                        <p class="text-sm" style="color:var(--muted)">
                            Lê a ATA da Reunião que disparou, chama o agente de IA abaixo pra gerar a pauta da
                            Reunião Interna, e já cria essa Reunião Interna pro próximo dia útil com a pauta
                            preenchida. Exige que a Reunião já tenha ATA — pensado pro gatilho "Status mudou" →
                            Realizada numa Reunião do tipo Kickoff Estratégico ou Macroplanejamento.
                        </p>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">AGENTE DE IA *</label>
                            <select name="action_config[agent_id]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">Selecione um agente...</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ ($automation->action_config['agent_id'] ?? '') === $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">PAPEL FUNCIONAL A NOTIFICAR *</label>
                            <select name="action_config[role]"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">Selecione...</option>
                                @foreach($functionRoles as $value => $label)
                                    <option value="{{ $value }}" {{ ($automation->action_config['role'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs mt-1" style="color:var(--muted)">Notifica todo mundo com esse papel quando a Reunião Interna for criada.</p>
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
const dateFieldsMap = @json($dateFields);

function automationBuilder() {
    return {
        entityType: '{{ old('entity_type', $automation->entity_type) }}',
        triggerType: '{{ old('trigger_type', $automation->trigger_type) }}',
        actionType: '{{ old('action_type', $automation->action_type) }}',
        actionField: '{{ old('action_config.field', $automation->action_config['field'] ?? '') }}',
        actionValue: '{{ old('action_config.value', $automation->action_config['value'] ?? '') }}',
        notifyTo: '{{ old('action_config.to', $automation->action_config['to'] ?? 'executor') }}',
        fieldUpdatedField: '{{ old('trigger_config.field', $automation->trigger_config['field'] ?? '') }}',
        dateField: '{{ old('trigger_config.date_field', $automation->trigger_config['date_field'] ?? '') }}',
        conditionsLogic: '{{ old('trigger_config.conditions_logic', $automation->trigger_config['conditions_logic'] ?? 'and') }}',
        conditions: {!! json_encode(old('trigger_config.conditions', $automation->trigger_config['conditions'] ?? [])) !!},
        assigneeSource: '{{ old('action_config.assignee_source', $automation->action_config['assignee_source'] ?? 'none') }}',
        dueBase: '{{ old('action_config.due_base', $automation->action_config['due_base'] ?? 'now') }}',
        primaryField() {
            const fields = conditionFieldsMap[this.entityType] || {};
            if (fields.status) return 'status';
            if (fields.stage) return 'stage';
            return Object.keys(fields)[0] || '';
        },
        actionFieldOptions() {
            return (conditionFieldsMap[this.entityType] || {})[this.actionField]?.options || {};
        },
    }
}
</script>
@endpush
</x-app-layout>
