{{-- Barra de filtros compartilhada por Filas e pela aba Lista da Sprint — precisa ficar
     idêntica nos dois lugares (pedido explícito: "exatamente os mesmos filtros que temos
     nas filas"). Cliente → Projeto é interligado no client-side (Alpine): selecionar um
     cliente esconde no <select> de Projeto qualquer projeto de outro cliente. $projects já
     vem filtrado pra só projetos ativos (não concluído/cancelado) — feito no controller.

     Espera: $formAction (string), $prefix ('' ou 'list_'), $clients, $projects, $users,
     $groupBy (valor atual do group_by), $clearUrl (string). Opcional: $extraHidden (array
     name=>value). Filtro por Executor/Responsável/Status é independente do agrupamento —
     dá pra agrupar por Cliente e ainda assim filtrar só as tarefas de um Executor específico. --}}
@php
    $extraHidden = $extraHidden ?? [];
    $filterKeys = array_map(fn ($k) => $prefix . $k, ['search', 'client_id', 'project_id', 'origin', 'task_type', 'status', 'executor_id', 'responsavel_id', 'atrasadas', 'pendencia', 'mostrar_fechados', 'mostrar_inativos']);
@endphp

@php
    // Opções do Projeto são montadas via JS (não com x-bind:hidden num <option> fixo) —
    // o navegador não reaplica hidden de forma confiável dentro do popup nativo do
    // <select>; só cria de fato as opções do cliente escolhido.
    $projectOptions = $projects->map(fn ($p) => [
        'id'        => $p->id,
        'client_id' => $p->client_id,
        'label'     => $p->title . ($p->client ? ' (' . $p->client->displayName() . ')' : ''),
    ]);
@endphp

<form method="GET" action="{{ $formAction }}"
      x-data="{ selectedClient: '{{ request($prefix . 'client_id') }}', selectedProject: '{{ request($prefix . 'project_id') }}', allProjects: @js($projectOptions) }"
      class="card card-body mb-5 flex flex-wrap items-end gap-3"
      @if(isset($resultsUrl) && isset($resultsTarget))
          data-live-filter data-results-url="{{ $resultsUrl }}" data-target="{{ $resultsTarget }}"
      @endif>

    @foreach($extraHidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    {{-- Agrupar por --}}
    <div class="min-w-40">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Agrupar por</label>
        <select name="{{ $prefix }}group_by" class="filter-select w-full" onchange="this.form.submit()">
            <option value="cliente"    {{ $groupBy === 'cliente'    ? 'selected' : '' }}>Cliente</option>
            <option value="executor"   {{ $groupBy === 'executor'   ? 'selected' : '' }}>Executor</option>
            <option value="responsavel"{{ $groupBy === 'responsavel'? 'selected' : '' }}>Responsável</option>
            <option value="status"     {{ $groupBy === 'status'     ? 'selected' : '' }}>Status</option>
        </select>
    </div>

    <div class="w-px self-stretch" style="background:var(--border2)"></div>

    <div class="flex-1 min-w-44">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Buscar</label>
        <input type="text" name="{{ $prefix }}search" value="{{ request($prefix . 'search') }}" placeholder="Buscar por título ou ID do ClickUp…"
            class="filter-select w-full" style="cursor:text">
    </div>

    <div class="flex-1 min-w-36">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
        <select name="{{ $prefix }}client_id" x-model="selectedClient" @change="selectedProject = ''" class="filter-select w-full">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex-1 min-w-44">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Projeto</label>
        <select name="{{ $prefix }}project_id" x-model="selectedProject" class="filter-select w-full">
            <option value="">Todos os projetos</option>
            <template x-for="p in allProjects.filter(p => !selectedClient || p.client_id === selectedClient)" :key="p.id">
                <option :value="p.id" x-text="p.label"></option>
            </template>
        </select>
    </div>

    <div class="min-w-36">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Origem</label>
        <select name="{{ $prefix }}origin" class="filter-select w-full">
            <option value="">Todas</option>
            @foreach(\App\Models\Task::$origins as $key => $label)
                <option value="{{ $key }}" {{ request($prefix . 'origin') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="min-w-44">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Tipo</label>
        <select name="{{ $prefix }}task_type" class="filter-select w-full">
            <option value="">Todos os tipos</option>
            @foreach(\App\Models\Task::$types as $key => $label)
                <option value="{{ $key }}" {{ request($prefix . 'task_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="w-px self-stretch" style="background:var(--border2)"></div>

    <div class="min-w-40">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Status</label>
        <select name="{{ $prefix }}status" class="filter-select w-full">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Task::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request($prefix . 'status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="min-w-44">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Executor</label>
        <select name="{{ $prefix }}executor_id" class="filter-select w-full">
            <option value="">Todos os executores</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ (string) request($prefix . 'executor_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="min-w-44">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Responsável</label>
        <select name="{{ $prefix }}responsavel_id" class="filter-select w-full">
            <option value="">Todos os responsáveis</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ (string) request($prefix . 'responsavel_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center gap-2 pb-0.5">
        <input type="checkbox" name="{{ $prefix }}atrasadas" value="1" id="{{ $prefix }}chk_atrasadas"
            {{ request($prefix . 'atrasadas') ? 'checked' : '' }}
            class="w-4 h-4" style="accent-color:var(--red)">
        <label for="{{ $prefix }}chk_atrasadas" class="text-sm font-medium cursor-pointer" style="color:var(--red)">
            Só atrasadas
        </label>
    </div>

    <div class="flex items-center gap-2 pb-0.5">
        <input type="checkbox" name="{{ $prefix }}pendencia" value="1" id="{{ $prefix }}chk_pendencia"
            {{ request($prefix . 'pendencia') ? 'checked' : '' }}
            class="w-4 h-4" style="accent-color:var(--red)">
        <label for="{{ $prefix }}chk_pendencia" class="text-sm font-medium cursor-pointer" style="color:var(--red)">
            Só pendências
        </label>
    </div>

    <div class="flex items-center gap-2 pb-0.5">
        <input type="checkbox" name="{{ $prefix }}mostrar_fechados" value="1" id="{{ $prefix }}chk_mostrar_fechados"
            {{ request()->boolean($prefix . 'mostrar_fechados') ? 'checked' : '' }}
            class="w-4 h-4" style="accent-color:var(--purple)">
        <label for="{{ $prefix }}chk_mostrar_fechados" class="text-sm font-medium cursor-pointer" style="color:var(--muted)">
            Mostrar concluídas/canceladas
        </label>
    </div>

    <div class="flex items-center gap-2 pb-0.5">
        <input type="checkbox" name="{{ $prefix }}mostrar_inativos" value="1" id="{{ $prefix }}chk_mostrar_inativos"
            {{ request()->boolean($prefix . 'mostrar_inativos') ? 'checked' : '' }}
            class="w-4 h-4" style="accent-color:var(--muted)">
        <label for="{{ $prefix }}chk_mostrar_inativos" class="text-sm font-medium cursor-pointer" style="color:var(--muted)">
            Mostrar clientes inativos
        </label>
    </div>

    <div class="flex gap-2">
        @if(request()->hasAny($filterKeys))
            <a href="{{ $clearUrl }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
        @endif
    </div>
</form>
