{{-- Seletor + botão "Aplicar Playbook" — aplica um modelo de tarefas a um
     Projeto de forma determinística (sem passar pela IA). Reutilizado em dois
     lugares: dentro do painel do Assistente (_task-assistant-drawer.blade.php,
     $compact=false) e direto no cabeçalho da página do Projeto
     (macroplans/project-show.blade.php, $compact=true) — pedido do Evandro
     pra não precisar abrir o Assistente só pra aplicar um modelo.
     Espera no escopo: $project, $playbooks. Opcional: $compact (default false). --}}
@php($compact = $compact ?? false)

<div x-data="{ playbookId: '' }" class="{{ $compact ? 'flex items-center gap-2' : '' }}">
    @unless($compact)
        <label class="block text-xs font-semibold uppercase tracking-widest mb-2"
               style="color:var(--muted); letter-spacing:.08em">Aplicar um Playbook</label>
    @endunless

    @if($playbooks->isEmpty())
        @unless($compact)
            <p class="text-xs" style="color:var(--muted)">
                Nenhum playbook cadastrado.
                <a href="{{ route('playbooks.create') }}" style="color:var(--purple)">Criar playbook →</a>
            </p>
        @endunless
    @else
        <div class="flex gap-2 {{ $compact ? '' : 'w-full' }}">
            @if($compact)
                <select x-model="playbookId"
                        class="px-2 py-1.5 text-xs focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    <option value="">Aplicar playbook...</option>
                    @foreach($playbooks as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <button type="button"
                        :disabled="!playbookId"
                        @click="if (playbookId) $refs['playbookForm_{{ $project->id }}_' + playbookId].submit()"
                        class="px-3 py-1.5 text-xs font-semibold text-white flex-shrink-0"
                        style="border-radius:6px"
                        :style="!playbookId
                            ? 'background:var(--purple); opacity:.35; cursor:not-allowed; border-radius:6px'
                            : 'background:var(--purple); border-radius:6px'">
                    Aplicar
                </button>
            @else
                <select x-model="playbookId"
                        class="flex-1 px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    <option value="">Selecione um playbook...</option>
                    @foreach($playbooks as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->tasks()->count() }} tarefa(s))</option>
                    @endforeach
                </select>
                <button type="button"
                        :disabled="!playbookId"
                        @click="if (playbookId) $refs['playbookForm_{{ $project->id }}_' + playbookId].submit()"
                        class="px-4 py-2 text-sm font-semibold text-white flex-shrink-0"
                        :style="!playbookId ? 'background:var(--purple); opacity:.35; cursor:not-allowed' : 'background:var(--purple)'">
                    Aplicar
                </button>
            @endif
        </div>

        @foreach($playbooks as $p)
            <form x-ref="playbookForm_{{ $project->id }}_{{ $p->id }}"
                  method="POST" action="{{ route('project-playbooks.apply', [$project, $p]) }}" class="hidden">
                @csrf
            </form>
        @endforeach

        @unless($compact)
            <p class="text-xs mt-1.5" style="color:var(--muted2)">
                Cria de uma vez todas as tarefas do modelo escolhido, sem passar pela IA.
            </p>
        @endunless
    @endif
</div>
