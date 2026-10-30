{{-- Formulário de Projeto/Campanha — cadastro enxuto (2026-10): só o que o sistema precisa
     entender. O conteúdo (briefing por área, big idea, peças, mecânica, tráfego...) mora no
     DOCUMENTO HTML da campanha/projeto, anexado na página dele e lido pelo Assistente.
     Os campos antigos não são enviados e não são apagados (ProjectController::applyUpdate só
     grava o que veio). Usado na lista do planejamento (macroplans/edit) e na página do
     projeto (macroplans/project-show). Espera no escopo:
       $project      — o Project sendo editado
       $formAction   — URL do <form> (macroplans.projects.update ou projects.updateDirect)
       $cancelAction — (opcional) expressão Alpine do Cancelar, default 'editId = null' --}}
@php $field = 'w-full px-3 py-2 text-sm focus:outline-none'; $fieldStyle = 'background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)'; @endphp
<div x-data="{ tipo: '{{ $project->type ?? 'projeto' }}' }" style="background:rgba(100, 59, 142,.04); border-left:3px solid var(--purple)">

<form method="POST" action="{{ $formAction }}" class="px-5 py-5 space-y-4">
    @csrf @method('PATCH')

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                Nome <span style="color:var(--orange)">*</span>
            </label>
            <input type="text" name="title" value="{{ $project->title }}" required class="{{ $field }}" style="{{ $fieldStyle }}">
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tipo</label>
            <select name="type" x-model="tipo" class="{{ $field }}" style="{{ $fieldStyle }}">
                @foreach(\App\Models\Project::$types as $key => $t)
                    <option value="{{ $key }}" {{ $project->type === $key ? 'selected' : '' }}>{{ $t['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Campanha: "Início" = quando vai ao ar (decisão 2026-10-13); entrega das peças é o
         marco antes disso (Mapa de Projetos). --}}
    <div class="grid grid-cols-1 gap-3" :class="tipo === 'campanha' ? 'md:grid-cols-3' : 'md:grid-cols-2'">
        <div x-show="tipo === 'campanha'" x-cloak>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Entrega das peças</label>
            <input type="date" name="pieces_due_date" value="{{ $project->pieces_due_date?->format('Y-m-d') }}" class="{{ $field }}" style="{{ $fieldStyle }}">
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)" x-text="tipo === 'campanha' ? 'Início (vai ao ar)' : 'Início'">Início</label>
            <input type="date" name="start_date" value="{{ $project->start_date?->format('Y-m-d') }}" class="{{ $field }}" style="{{ $fieldStyle }}">
        </div>
        <div>
            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)" x-text="tipo === 'campanha' ? 'Fim da campanha' : 'Término'">Término</label>
            <input type="date" name="end_date" value="{{ $project->end_date?->format('Y-m-d') }}" class="{{ $field }}" style="{{ $fieldStyle }}">
        </div>
    </div>

    <div>
        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Objetivo</label>
        <textarea name="objective" rows="2" class="{{ $field }} resize-none" style="{{ $fieldStyle }}"
                  placeholder="Em 1–2 frases: o que esta campanha/projeto precisa alcançar.">{{ $project->objective }}</textarea>
        <p class="text-xs mt-1" style="color:var(--muted)">Briefing, peças, mecânica e o resto ficam no documento HTML — anexe na página {{ $project->type === 'campanha' ? 'da campanha' : 'do projeto' }}.</p>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
        <button type="button" @click="{{ $cancelAction ?? 'editId = null' }}" class="btn btn-ghost btn-sm">Cancelar</button>
    </div>
</form>
</div>
