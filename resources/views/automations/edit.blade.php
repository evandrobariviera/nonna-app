@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('automations.index') }}" class="back-link">← Automações</a>
        <h1 class="page-title">Editar Automação</h1>
    </div>
</div>

<div style="max-width:720px">
    <form action="{{ route('automations.update', $automation) }}" method="POST" x-data="automationBuilder()">
        @csrf @method('PATCH')

        <div class="card" style="padding:1.5rem; margin-bottom:1rem">
            <h2 class="section-title" style="margin-bottom:1rem">Identificação</h2>

            <div class="form-group">
                <label class="form-label">Nome da Automação <span style="color:var(--red)">*</span></label>
                <input type="text" name="name" class="form-input" value="{{ old('name', $automation->name) }}" required>
            </div>

            <div class="form-group" style="margin-top:.75rem">
                <label class="form-label">Descrição (opcional)</label>
                <textarea name="description" class="form-input" rows="2">{{ old('description', $automation->description) }}</textarea>
            </div>

            <div class="form-group" style="margin-top:.75rem">
                <label class="form-label">Entidade <span style="color:var(--red)">*</span></label>
                <select name="entity_type" x-model="entityType" class="form-select" required>
                    @foreach($entityTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('entity_type', $automation->entity_type) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- SE --}}
        <div class="card" style="padding:1.5rem; margin-bottom:1rem; border-left:3px solid var(--purple)">
            <h2 class="section-title" style="margin-bottom:1rem; color:var(--purple)">SE — Quando disparar</h2>

            <div class="form-group">
                <label class="form-label">Tipo de Gatilho</label>
                <select name="trigger_type" x-model="triggerType" class="form-select" required>
                    @foreach($triggerTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('trigger_type', $automation->trigger_type) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div x-show="triggerType === 'status_changed'" x-cloak style="margin-top:.75rem">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
                    <div class="form-group">
                        <label class="form-label">De (status)</label>
                        <select name="trigger_config[from]" class="form-select">
                            <option value="*" {{ ($automation->trigger_config['from'] ?? '') === '*' ? 'selected' : '' }}>Qualquer</option>
                            @foreach($taskStatuses as $value => $info)
                                <option value="{{ $value }}" {{ ($automation->trigger_config['from'] ?? '') === $value ? 'selected' : '' }}>
                                    {{ $info['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Para (status)</label>
                        <select name="trigger_config[to]" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($taskStatuses as $value => $info)
                                <option value="{{ $value }}" {{ ($automation->trigger_config['to'] ?? '') === $value ? 'selected' : '' }}>
                                    {{ $info['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-show="triggerType === 'field_updated'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">Campo monitorado</label>
                    <input type="text" name="trigger_config[field]" class="form-input"
                           value="{{ $automation->trigger_config['field'] ?? '' }}">
                </div>
            </div>
        </div>

        {{-- ENTÃO --}}
        <div class="card" style="padding:1.5rem; margin-bottom:1rem; border-left:3px solid var(--orange)">
            <h2 class="section-title" style="margin-bottom:1rem; color:var(--orange)">ENTÃO — O que fazer</h2>

            <div class="form-group">
                <label class="form-label">Tipo de Ação</label>
                <select name="action_type" x-model="actionType" class="form-select" required>
                    @foreach($actionTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('action_type', $automation->action_type) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div x-show="actionType === 'run_ai_agent'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">Agente de IA</label>
                    <select name="action_config[agent_id]" class="form-select">
                        <option value="">Selecione...</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}"
                                {{ ($automation->action_config['agent_id'] ?? '') === $agent->id ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Mensagem para o agente (opcional)</label>
                    <textarea name="action_config[user_message]" class="form-input" rows="2">{{ $automation->action_config['user_message'] ?? '' }}</textarea>
                </div>
            </div>

            <div x-show="actionType === 'send_webhook'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">URL do Webhook</label>
                    <input type="url" name="action_config[url]" class="form-input"
                           value="{{ $automation->action_config['url'] ?? '' }}">
                </div>
            </div>

            <div x-show="actionType === 'update_field'" x-cloak style="margin-top:.75rem">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
                    <div class="form-group">
                        <label class="form-label">Campo</label>
                        <input type="text" name="action_config[field]" class="form-input"
                               value="{{ $automation->action_config['field'] ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Novo valor</label>
                        <input type="text" name="action_config[value]" class="form-input"
                               value="{{ $automation->action_config['value'] ?? '' }}">
                    </div>
                </div>
            </div>

            <div x-show="actionType === 'send_notification'" x-cloak style="margin-top:.75rem">
                <div class="form-group">
                    <label class="form-label">Notificar quem</label>
                    <select name="action_config[to]" class="form-select">
                        @foreach(['executor' => 'Executor', 'creator' => 'Criador', 'all' => 'Todos'] as $v => $l)
                            <option value="{{ $v }}" {{ ($automation->action_config['to'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Mensagem</label>
                    <textarea name="action_config[message]" class="form-input" rows="2">{{ $automation->action_config['message'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        <div class="card" style="padding:1rem 1.5rem">
            <div style="display:flex; align-items:center; justify-content:space-between">
                <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer; font-size:.9rem">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           {{ $automation->is_active ? 'checked' : '' }}
                           style="width:16px; height:16px; accent-color:var(--purple)">
                    Automação ativa
                </label>
                <div style="display:flex; gap:.75rem">
                    <a href="{{ route('automations.index') }}" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function automationBuilder() {
    return {
        entityType: '{{ old('entity_type', $automation->entity_type) }}',
        triggerType: '{{ old('trigger_type', $automation->trigger_type) }}',
        actionType: '{{ old('action_type', $automation->action_type) }}',
    }
}
</script>
@endpush
@endsection
