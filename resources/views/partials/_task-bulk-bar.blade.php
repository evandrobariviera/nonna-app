{{-- Barra de ações em massa — espera $users e $projects no escopo, e um ancestral com x-data="taskBulk()" (ou mesclado via ...taskBulk()) --}}
<div x-show="selected.length > 0"
     class="card px-4 py-3 mb-4 flex flex-wrap items-center gap-2"
     style="border-color:var(--purple)">

    <span class="text-xs font-mono font-semibold" style="color:var(--purple)">
        <span x-text="selected.length"></span> selecionada<span x-show="selected.length !== 1">s</span>
    </span>

    <div class="h-5" style="border-left:1px solid var(--border2)"></div>

    <select x-model="bulkStatus" class="filter-select">
        <option value="">Status…</option>
        @foreach(\App\Models\Task::$statuses as $key => $s)
            <option value="{{ $key }}">{{ $s['label'] }}</option>
        @endforeach
    </select>
    <button @click="apply('status', { status: bulkStatus })" :disabled="!bulkStatus || applying" class="btn btn-ghost btn-xs">Aplicar</button>

    <select x-model="bulkExecutor" class="filter-select">
        <option value="">Executor…</option>
        <option value="null">— Remover executor —</option>
        @foreach($users as $u)
            <option value="{{ $u->id }}">{{ $u->name }}</option>
        @endforeach
    </select>
    <button @click="apply('executor', { executor_id: bulkExecutor === 'null' ? null : bulkExecutor })" :disabled="!bulkExecutor || applying" class="btn btn-ghost btn-xs">Aplicar</button>

    <select x-model="bulkSituation" class="filter-select">
        <option value="">Situação…</option>
        @foreach(\App\Models\Task::$situations as $key => $label)
            @if($key !== '')
                <option value="{{ $key }}">{{ $label }}</option>
            @endif
        @endforeach
    </select>
    <button @click="apply('situation', { situation: bulkSituation })" :disabled="!bulkSituation || applying" class="btn btn-ghost btn-xs">Aplicar</button>

    <select x-model="bulkProject" class="filter-select">
        <option value="">Vincular a projeto…</option>
        @foreach($projects as $p)
            <option value="{{ $p->id }}">{{ $p->title }} — {{ $p->client?->company_name ?? '—' }}</option>
        @endforeach
    </select>
    <button @click="apply('project', { project_id: bulkProject })" :disabled="!bulkProject || applying" class="btn btn-ghost btn-xs">Vincular</button>

    @isset($sprints)
        <select x-model="bulkSprint" class="filter-select">
            <option value="">Enviar p/ sprint…</option>
            @foreach($sprints as $s)
                <option value="{{ $s->id }}">{{ $s->title }}</option>
            @endforeach
        </select>
        <button @click="apply('sprint', { sprint_id: bulkSprint })" :disabled="!bulkSprint || applying" class="btn btn-ghost btn-xs">Enviar</button>
    @endisset

    <button @click="if (await $store.confirmDialog.ask('Excluir ' + selected.length + ' tarefa(s) selecionada(s)? Essa ação não pode ser desfeita.')) apply('delete', {})"
            :disabled="applying" class="btn btn-xs ml-auto" style="color:var(--red); border:1px solid var(--red)">
        Excluir selecionadas
    </button>
</div>
