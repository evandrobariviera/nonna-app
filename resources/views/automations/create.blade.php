@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('automations.index') }}" class="back-link">← Automações</a>
        <h1 class="page-title">Nova Automação</h1>
    </div>
</div>

<div style="max-width:720px">
    <form action="{{ route('automations.store') }}" method="POST" x-data="automationBuilder()">
        @csrf

        <div class="card" style="padding:1.5rem; margin-bottom:1rem">
            <h2 class="section-title" style="margin-bottom:1rem">Identificação</h2>

            <div class="form-group">
                <label class="form-label">Nome da Automação <span style="color:var(--red)">*</span></label>
                <input type="text" name="name" class="form-input" placeholder="Ex: Verificar ortografia ao entrar em revisão"
                       value="{{ old('name') }}" required>
            </div>

            <div class="form-group" style="margin-top:.75rem">
                <label class="form-label">Descrição (opcional)</label>
                <textarea name="description" class="form-input" rows="2"
                          placeholder="Descreva o que esta automação faz">{{ old('description') }}</textarea>
            </div>

            <div class="form-group" style="margin-top:.75rem">
                <label class="form-label">Entidade <span style="color:var(--red)">*</span></label>
                <select name="entity_type" x-model="entityType" class="form-select" required>
                    <option value="">Selecione...</option>
                    @foreach($entityTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('entity_type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <p class="form-hint">Em qual tipo de objeto essa automação opera</p>
            </div>
        </div>

        {{-- SE ─ Gatilho --}}
        <div class="card" style="padding:1.5rem; margin-bottom:1rem; border-left:3px solid var(--purple)">
            <h2 class="section-title" style="margin-bottom:1rem; color:var(--purple)">
                SE — Quando disparar
            </h2>

            <div class="form-group">
                <label class="form-label">Tipo de Gatilho <span style="color:var(--red)">*</span></label>
                <select name="trigger_type" x-model="triggerType" class="form-select" required>
                    <option value="">Selecione...</option>
                    @foreach($triggerTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('trigger_type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- status_changed --}}
            <div x-show="triggerType === 'status_changed'" x-cloak style="margin-top:.75rem">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
                    <div class="form-group">
                        <label class="form-label">De (status)</label>
                        <select name="trigger_config[from]" class="form-select">
                            <option value="*">Qualquer status</option>
                            @foreach($taskStatuses as $value => $info)
                                <option value="{{ $value }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Para (status) <span style="color:var(--red)">*</span></label>
                        <select name="trigger_config[to]" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($taskStatuses as $value => $info)
                                <option value="{{ $value }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- field_updated --}}
            <div x-show="triggerType === 'field_updated'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">Campo monitorado</label>
                    <input type="text" name="trigger_config[field]" class="form-input"
                           placeholder="Ex: situation">
                    <p class="form-hint">Nome do campo no banco de dados</p>
                </div>
            </div>

            {{-- created / manual: sem config adicional --}}
            <div x-show="triggerType === 'created'" x-cloak style="margin-top:.75rem">
                <p style="color:var(--muted); font-size:.85rem">Dispara sempre que um novo registro for criado.</p>
            </div>
            <div x-show="triggerType === 'manual'" x-cloak style="margin-top:.75rem">
                <p style="color:var(--muted); font-size:.85rem">Será acionada manualmente via botão na interface.</p>
            </div>
        </div>

        {{-- ENTÃO ─ Ação --}}
        <div class="card" style="padding:1.5rem; margin-bottom:1rem; border-left:3px solid var(--orange)">
            <h2 class="section-title" style="margin-bottom:1rem; color:var(--orange)">
                ENTÃO — O que fazer
            </h2>

            <div class="form-group">
                <label class="form-label">Tipo de Ação <span style="color:var(--red)">*</span></label>
                <select name="action_type" x-model="actionType" class="form-select" required>
                    <option value="">Selecione...</option>
                    @foreach($actionTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('action_type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- run_ai_agent --}}
            <div x-show="actionType === 'run_ai_agent'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">Agente de IA <span style="color:var(--red)">*</span></label>
                    <select name="action_config[agent_id]" class="form-select">
                        <option value="">Selecione um agente...</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Mensagem para o agente (opcional)</label>
                    <textarea name="action_config[user_message]" class="form-input" rows="2"
                              placeholder="Deixe em branco para usar o prompt padrão do agente"></textarea>
                    <p class="form-hint">Você pode usar variáveis como {task_title}, {client_name}, etc.</p>
                </div>
            </div>

            {{-- send_webhook --}}
            <div x-show="actionType === 'send_webhook'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">URL do Webhook <span style="color:var(--red)">*</span></label>
                    <input type="url" name="action_config[url]" class="form-input"
                           placeholder="https://...">
                </div>
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Método</label>
                    <select name="action_config[method]" class="form-select">
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
                    </select>
                </div>
            </div>

            {{-- update_field --}}
            <div x-show="actionType === 'update_field'" x-cloak style="margin-top:.75rem">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
                    <div class="form-group">
                        <label class="form-label">Campo <span style="color:var(--red)">*</span></label>
                        <input type="text" name="action_config[field]" class="form-input"
                               placeholder="Ex: status">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Novo valor <span style="color:var(--red)">*</span></label>
                        <input type="text" name="action_config[value]" class="form-input"
                               placeholder="Ex: revisao">
                    </div>
                </div>
            </div>

            {{-- send_notification --}}
            <div x-show="actionType === 'send_notification'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">Notificar quem</label>
                    <select name="action_config[to]" class="form-select">
                        <option value="executor">Executor da tarefa</option>
                        <option value="creator">Criador da tarefa</option>
                        <option value="all">Todos os envolvidos</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Mensagem</label>
                    <textarea name="action_config[message]" class="form-input" rows="2"
                              placeholder="Mensagem da notificação"></textarea>
                </div>
            </div>
        </div>

        {{-- Rodapé --}}
        <div class="card" style="padding:1rem 1.5rem">
            <div style="display:flex; align-items:center; justify-content:space-between">
                <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer; font-size:.9rem">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked
                           style="width:16px; height:16px; accent-color:var(--purple)">
                    Ativar automação imediatamente
                </label>
                <div style="display:flex; gap:.75rem">
                    <a href="{{ route('automations.index') }}" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Automação</button>
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
@endsection
