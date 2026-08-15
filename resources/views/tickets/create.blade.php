<x-app-layout>
    <x-slot name="header">Novo Ticket</x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="card px-6 py-6">
            <form method="POST" action="{{ route('tickets.store') }}"
                  enctype="multipart/form-data"
                  class="grid grid-cols-2 gap-4 md:grid-cols-3"
                  x-data="{ ...executorPicker(), selectedClient: '{{ old('client_id') }}', selectedProject: '{{ old('project_id') }}', allProjects: @js($projects->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'client_id' => $p->client_id])) }">
                @csrf

                {{-- Título --}}
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" required value="{{ old('title') }}"
                        placeholder="Descreva o pedido..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                    @error('title') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>

                {{-- Cliente --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                        Cliente <span style="color:var(--orange)">*</span>
                    </label>
                    <select name="client_id" required x-model="selectedClient" @change="selectedProject = ''"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— selecione —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ old('client_id') === $c->id ? 'selected' : '' }}>
                                {{ $c->displayName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>

                {{-- Projeto / Campanha --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Projeto / Campanha</label>
                    <select name="project_id" x-model="selectedProject"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— sem vínculo (fica em Tickets) —</option>
                        {{-- Opções montadas via JS (não com x-bind:hidden num <option> fixo) —
                             navegador não reaplica hidden de forma confiável dentro do popup
                             nativo do <select>; só cria de fato as opções do cliente escolhido. --}}
                        <template x-for="p in allProjects.filter(p => !selectedClient || p.client_id === selectedClient)" :key="p.id">
                            <option :value="p.id" x-text="p.title"></option>
                        </template>
                    </select>
                    <p class="text-xs mt-1" style="color:var(--muted)">Selecionar já manda direto pra Fila, pulando a triagem.</p>
                    @error('project_id') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Tipo</label>
                    <select name="task_type"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @foreach(\App\Models\Task::$types as $key => $label)
                            <option value="{{ $key }}" {{ old('task_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Destino --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Destino</label>
                    <select name="destination"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— sem destino —</option>
                        @foreach(\App\Models\Task::$destinations as $key => $label)
                            <option value="{{ $key }}" {{ old('destination') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de Aprovação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Método de Aprovação</label>
                    <select name="approval_method"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— não definido —</option>
                        @foreach(\App\Models\Task::$approvalMethods as $key => $label)
                            <option value="{{ $key }}" {{ old('approval_method') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Sprint --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Sprint</label>
                    <select name="sprint_id"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— sem sprint (backlog) —</option>
                        @foreach($sprints as $s)
                            <option value="{{ $s->id }}" {{ old('sprint_id') === $s->id ? 'selected' : '' }}>
                                {{ $s->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Vencimento --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Vencimento</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Data Aprovação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Dt. Aprovação</label>
                    <input type="date" name="approval_date" value="{{ old('approval_date') }}"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Data Publicação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Dt. Publicação</label>
                    <input type="date" name="publish_date" value="{{ old('publish_date') }}"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Responsável --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Responsável</label>
                    <select name="responsavel_id"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— nenhum —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('responsavel_id') === $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Executores --}}
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Executores</label>
                    <div class="flex flex-wrap gap-2 mb-2" x-show="selected.length > 0">
                        <template x-for="(item, idx) in selected" :key="item.id">
                            <div class="flex items-center gap-1 px-2 py-1 text-xs"
                                 style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                <span x-text="item.name" style="color:var(--text)"></span>
                                <select :name="'executor_roles[' + item.id + ']'" @change="setRole(item, $event.target.value, $event.target)"
                                    class="text-xs focus:outline-none ml-1"
                                    style="background:var(--s3); color:var(--muted); border:none">
                                    <option value="executor" :selected="item.role === 'executor'">Executor</option>
                                    <option value="aprovador" :selected="item.role === 'aprovador'">Aprovador</option>
                                </select>
                                <input type="hidden" :name="'executor_ids[]'" :value="item.id">
                                <button type="button" @click="remove(idx)" class="btn btn-danger btn-xs">✕</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="roleError" x-text="roleError" x-cloak class="text-xs mb-1.5" style="color:var(--red)"></p>
                    <select @change="add($event)"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">+ Adicionar executor</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Aprovação Interna --}}
                <div class="flex items-center gap-2">
                    <input type="hidden" name="internal_approval" value="0">
                    <input type="checkbox" name="internal_approval" value="1" id="ia_ticket"
                        class="w-4 h-4" style="accent-color:var(--purple)">
                    <label for="ia_ticket" class="text-xs font-mono" style="color:var(--muted)">Aprovação Interna</label>
                </div>

                {{-- ── SEÇÃO SOLICITANTE ── --}}
                <div class="col-span-2 md:col-span-3 pt-2" style="border-top:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Solicitante (externo)</p>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Nome</label>
                            <input type="text" name="requester_name" value="{{ old('requester_name') }}"
                                placeholder="Nome do solicitante"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">WhatsApp</label>
                            <input type="text" name="requester_whatsapp" value="{{ old('requester_whatsapp') }}"
                                placeholder="(00) 00000-0000"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Canal</label>
                            <select name="requester_channel"
                                class="w-full px-4 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                <option value="">— selecione —</option>
                                @foreach(\App\Models\Task::$requesterChannels as $key => $label)
                                    <option value="{{ $key }}" {{ old('requester_channel') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Descrição --}}
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Descrição / Briefing</label>
                    <textarea name="description" rows="3"
                        placeholder="Detalhes do pedido, contexto, links..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ old('description') }}</textarea>
                </div>

                {{-- Anexos --}}
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Anexos</label>
                    <input type="file" name="files[]" multiple
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    @error('files') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                    @error('files.*') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>

                <div class="col-span-2 md:col-span-3 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        Criar Ticket
                    </button>
                    <a href="{{ route('tickets.index') }}" class="btn btn-ghost btn-sm">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>

@push('scripts')
<script>
function executorPicker(initial = []) {
    return {
        selected: initial,
        roleError: '',
        // 'executor' e 'responsavel' são papéis capados em 1 por tarefa — 'aprovador'/
        // 'observador' não têm limite aqui.
        countRole(role, excludeId = null) {
            return this.selected.filter(s => s.role === role && s.id != excludeId).length;
        },
        add(event) {
            const id   = event.target.value;
            const name = event.target.selectedOptions[0]?.dataset?.name;
            if (!id || this.selected.find(s => s.id == id)) {
                event.target.value = '';
                return;
            }
            const role = this.countRole('executor') > 0 ? 'aprovador' : 'executor';
            this.selected.push({ id, name, role });
            event.target.value = '';
        },
        setRole(item, newRole, selectEl) {
            if ((newRole === 'executor' || newRole === 'responsavel') && this.countRole(newRole, item.id) > 0) {
                selectEl.value = item.role;
                this.roleError = newRole === 'executor'
                    ? 'Já existe um Executor nesse ticket — troque o outro primeiro.'
                    : 'Já existe um Responsável nesse ticket — troque o outro primeiro.';
                return;
            }
            item.role = newRole;
            this.roleError = '';
        },
        remove(idx) {
            this.selected.splice(idx, 1);
            this.roleError = '';
        }
    }
}
</script>
@endpush
