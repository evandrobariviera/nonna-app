{{-- Barra de filtros compartilhada por Filas e pela aba Lista da Sprint — precisa ficar
     idêntica nos dois lugares (pedido explícito: "exatamente os mesmos filtros que temos
     nas filas"). Cliente → Projeto é interligado no client-side (Alpine): selecionar um
     cliente esconde no <select> de Projeto qualquer projeto de outro cliente. $projects já
     vem filtrado pra só projetos ativos (não concluído/cancelado) — feito no controller.

     Espera: $formAction (string), $prefix ('' ou 'list_'), $clients, $projects, $groupBy
     (valor atual do group_by), $clearUrl (string). Opcional: $extraHidden (array name=>value). --}}
@php
    $extraHidden = $extraHidden ?? [];
    $filterKeys = array_map(fn ($k) => $prefix . $k, ['search', 'client_id', 'project_id', 'origin', 'task_type', 'atrasadas', 'pendencia', 'mostrar_fechados']);
@endphp

<form method="GET" action="{{ $formAction }}"
      x-data="{ selectedClient: '{{ request($prefix . 'client_id') }}', selectedProject: '{{ request($prefix . 'project_id') }}' }"
      class="card card-body mb-5 flex flex-wrap items-end gap-3">

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
        <input type="text" name="{{ $prefix }}search" value="{{ request($prefix . 'search') }}" placeholder="Buscar por título…"
            class="filter-select w-full" style="cursor:text">
    </div>

    <div class="flex-1 min-w-36">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
        <select name="{{ $prefix }}client_id" x-model="selectedClient" @change="selectedProject = ''" class="filter-select w-full">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex-1 min-w-44">
        <label class="block text-xs font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.08em">Projeto</label>
        <select name="{{ $prefix }}project_id" x-model="selectedProject" class="filter-select w-full">
            <option value="">Todos os projetos</option>
            @foreach($projects as $p)
                <option value="{{ $p->id }}" x-bind:hidden="selectedClient && selectedClient !== '{{ $p->client_id }}'">
                    {{ $p->title }} @if($p->client)({{ $p->client->company_name }})@endif
                </option>
            @endforeach
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

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        @if(request()->hasAny($filterKeys))
            <a href="{{ $clearUrl }}" class="btn btn-ghost btn-sm">✕ Limpar</a>
        @endif
    </div>
</form>
