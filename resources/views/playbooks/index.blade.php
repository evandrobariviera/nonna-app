<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Playbooks de Projeto</span>
    </x-slot>

    <div style="max-width:960px">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded text-sm font-medium"
                 style="background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.25)">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                Modelos reutilizáveis de estrutura de tarefas — aplique num Projeto pra criar
                o conjunto padrão de uma vez, sem precisar da IA.
            </p>
            <a href="{{ route('playbooks.create') }}"
               class="px-4 py-2 rounded text-xs font-bold flex-shrink-0"
               style="background:var(--purple); color:#fff">
                + Novo Playbook
            </a>
        </div>

        @if($playbooks->isEmpty())
            <div class="text-center py-16" style="border:1px dashed var(--border2); border-radius:8px">
                <div class="mb-3 flex justify-center" style="color:var(--muted)"><x-icon name="list-checks" size="36" /></div>
                <div class="text-sm font-semibold mb-1" style="color:var(--text)">Nenhum playbook criado ainda</div>
                <div class="text-xs mb-4" style="color:var(--muted)">Crie o primeiro modelo (ex: "Site Institucional") pra agilizar o lançamento de tarefas.</div>
                <a href="{{ route('playbooks.create') }}"
                   class="px-4 py-2 rounded text-xs font-bold"
                   style="background:var(--purple); color:#fff">
                    Criar primeiro playbook
                </a>
            </div>
        @else
            <div class="grid gap-3">
                @foreach($playbooks as $playbook)
                <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden"
                     class="{{ $playbook->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-sm" style="color:var(--text)">{{ $playbook->name }}</span>
                                @if(!$playbook->is_active)
                                    <span class="text-xs px-2 py-px rounded"
                                          style="background:var(--s3); color:var(--muted)">inativo</span>
                                @endif
                                <span class="text-xs px-2 py-px rounded"
                                      style="background:rgba(100, 59, 142,.1); color:var(--purple); border:1px solid rgba(100, 59, 142,.2)">
                                    {{ $playbook->tasks_count }} tarefa(s)
                                </span>
                            </div>
                            @if($playbook->description)
                                <div class="text-xs mt-1" style="color:var(--muted)">{{ $playbook->description }}</div>
                            @endif
                            @if(!empty($playbook->disciplines))
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($playbook->disciplineLabels() as $label)
                                        <span class="text-xs px-2 py-px rounded" style="background:var(--s3); color:var(--muted2)">{{ $label }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                            <form method="POST" action="{{ route('playbooks.toggle', $playbook) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="text-xs px-3 py-1.5 rounded transition-colors"
                                        style="color:var(--muted); border:1px solid var(--border2); background:none; cursor:pointer">
                                    {{ $playbook->is_active ? 'Desativar' : 'Ativar' }}
                                </button>
                            </form>
                            <a href="{{ route('playbooks.edit', $playbook) }}"
                               class="text-xs px-3 py-1.5 rounded transition-colors"
                               style="color:var(--text); border:1px solid var(--border2)">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('playbooks.destroy', $playbook) }}"
                                  @submit.prevent="if (await $store.confirmDialog.ask('Remover o playbook {{ addslashes($playbook->name) }}? Projetos já criados a partir dele não são afetados.')) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="text-xs px-3 py-1.5 rounded"
                                        style="color:var(--red); border:1px solid rgba(239,68,68,.25); background:none; cursor:pointer">
                                    Remover
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
