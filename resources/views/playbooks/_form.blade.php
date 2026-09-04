{{-- Form de criação/edição de Playbook, compartilhado entre create.blade.php e
     edit.blade.php (mesmo padrão de resources/views/ai/agents/_form.blade.php).
     Espera no escopo: $action, $method, $playbook (null na criação),
     $functionalRoles. --}}
<div style="max-width:900px"
     x-data="{
        tasks: {{ json_encode(($playbook?->tasks ?? collect())->map(fn ($t) => [
            'title'               => $t->title,
            'description'         => $t->description,
            'task_type'           => $t->task_type,
            'destination'         => $t->destination,
            'priority'            => $t->priority,
            'due_offset_days'     => $t->due_offset_days,
            'functional_role_id'  => $t->functional_role_id,
        ])->values()) }},
        addTask() {
            this.tasks.push({ title: '', description: '', task_type: '', destination: '', priority: '', due_offset_days: '', functional_role_id: '' });
        },
        removeTask(i) { this.tasks.splice(i, 1); },
     }">

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded text-sm"
             style="background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.25)">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="grid gap-5">

            {{-- Nome + Descrição --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">NOME DO PLAYBOOK *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $playbook?->name) }}"
                           placeholder="ex: Site Institucional"
                           required
                           class="w-full rounded px-3 py-2 text-sm"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DESCRIÇÃO</label>
                    <input type="text" name="description"
                           value="{{ old('description', $playbook?->description) }}"
                           placeholder="Quando usar este modelo…"
                           class="w-full rounded px-3 py-2 text-sm"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>
            </div>

            {{-- Disciplinas (informativo, ajuda a IA a reconhecer o playbook no contexto) --}}
            <div>
                <label class="block text-xs font-semibold mb-2" style="color:var(--muted); letter-spacing:.05em">DISCIPLINAS ENVOLVIDAS</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Models\Project::$disciplines as $key => $label)
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer px-3 py-1.5 rounded"
                               style="border:1px solid var(--border2); color:var(--muted2)">
                            <input type="checkbox" name="disciplines[]" value="{{ $key }}"
                                {{ in_array($key, old('disciplines', $playbook?->disciplines ?? [])) ? 'checked' : '' }}
                                style="accent-color:var(--purple)">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            @if($playbook)
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $playbook->is_active) ? 'checked' : '' }}
                           class="rounded">
                    <span class="text-sm" style="color:var(--text)">Playbook ativo</span>
                </label>
            </div>
            @endif

            {{-- Tarefas do playbook --}}
            <div style="border-top:1px solid var(--border2)" class="pt-5">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-semibold" style="color:var(--muted); letter-spacing:.05em">TAREFAS DO PLAYBOOK *</label>
                    <button type="button" @click="addTask()"
                        class="text-xs font-semibold px-3 py-1.5 rounded"
                        style="border:1px solid var(--border2); color:var(--muted2)">+ Tarefa</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(t, i) in tasks" :key="i">
                        <div class="px-4 py-4 rounded relative" style="background:var(--s2); border:1px solid var(--border2)">
                            <button type="button" @click="removeTask(i)"
                                class="absolute top-2 right-2 text-xs" style="color:var(--muted)">✕</button>

                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div>
                                    <p class="text-xs mb-1" style="color:var(--muted)">Título *</p>
                                    <input type="text" :name="'tasks['+i+'][title]'" x-model="t.title" required
                                        placeholder="Ex: Briefing com o cliente"
                                        class="w-full px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                </div>
                                <div>
                                    <p class="text-xs mb-1" style="color:var(--muted)">Tipo *</p>
                                    <select :name="'tasks['+i+'][task_type]'" x-model="t.task_type" required
                                        class="w-full px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                        <option value="">— selecione —</option>
                                        @foreach(\App\Models\Task::$types as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-2">
                                <p class="text-xs mb-1" style="color:var(--muted)">Descrição</p>
                                <textarea :name="'tasks['+i+'][description]'" x-model="t.description" rows="2"
                                    placeholder="Instruções padrão desta etapa…"
                                    class="w-full px-2 py-1.5 text-xs rounded focus:outline-none resize-none"
                                    style="background:var(--s3); border:1px solid var(--border); color:var(--text)"></textarea>
                            </div>

                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <p class="text-xs mb-1" style="color:var(--muted)">Destino</p>
                                    <select :name="'tasks['+i+'][destination]'" x-model="t.destination"
                                        class="w-full px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                        <option value="">—</option>
                                        @foreach(\App\Models\Task::$destinations as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <p class="text-xs mb-1" style="color:var(--muted)">Prioridade</p>
                                    <select :name="'tasks['+i+'][priority]'" x-model="t.priority"
                                        class="w-full px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                        <option value="">—</option>
                                        @foreach(\App\Models\Task::$priorities as $key => $meta)
                                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <p class="text-xs mb-1" style="color:var(--muted)">Prazo (dias após início)</p>
                                    <input type="number" min="0" max="3650" :name="'tasks['+i+'][due_offset_days]'" x-model="t.due_offset_days"
                                        placeholder="Ex: 5"
                                        class="w-full px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                </div>
                                <div>
                                    <p class="text-xs mb-1" style="color:var(--muted)">Responsável (papel)</p>
                                    <select :name="'tasks['+i+'][functional_role_id]'" x-model="t.functional_role_id"
                                        class="w-full px-2 py-1.5 text-xs rounded focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); color:var(--text)">
                                        <option value="">—</option>
                                        @foreach($functionalRoles as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>
                    <p x-show="tasks.length === 0" class="text-xs py-2" style="color:var(--muted)">
                        Nenhuma tarefa adicionada ainda — clique em "+ Tarefa".
                    </p>
                </div>
            </div>

        </div>

        {{-- Ações --}}
        <div class="flex items-center gap-3 mt-6 pt-5" style="border-top:1px solid var(--border2)">
            <button type="submit"
                    class="px-5 py-2 rounded text-sm font-bold"
                    style="background:var(--purple); color:#fff; border:none; cursor:pointer">
                {{ $playbook ? 'Salvar alterações' : 'Criar playbook' }}
            </button>
            <a href="{{ route('playbooks.index') }}" class="text-sm" style="color:var(--muted)">Cancelar</a>
        </div>
    </form>
</div>
