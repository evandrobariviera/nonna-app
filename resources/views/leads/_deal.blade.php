{{-- Negociação: valor e responsável do lead, em destaque no topo da ficha —
     compartilhado entre o app interno (leads/show) e o Portal (portal/leads/show).
     Espera: $opportunity, $updateRoute (PATCH), $assigneeOptions (['Grupo' => [chave => nome]],
     chave "contact:{uuid}" ou "user:{id}"). Cada campo é um form próprio: o controller só
     mexe no que veio. --}}
@php
    $currentKey = $opportunity->assigneeKey();
    $known = collect($assigneeOptions)->flatMap(fn ($group) => $group)->has($currentKey);
@endphp
<div class="card p-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

        <form method="POST" action="{{ $updateRoute }}">
            @csrf
            @method('PATCH')
            <label for="deal_value" class="card-title block mb-2">Valor da negociação</label>
            <div class="flex items-center gap-2">
                <span class="text-lg font-black" style="color: var(--green)">R$</span>
                <input type="number" id="deal_value" name="deal_value" step="0.01" min="0" inputmode="decimal"
                       value="{{ $opportunity->deal_value }}" placeholder="0,00"
                       class="field flex-1 min-w-0 text-lg font-bold" style="padding:8px 12px; color: var(--green)">
                <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
            </div>
            <p class="text-xs mt-1.5" style="color: var(--muted)">Quanto essa venda vale (ou valeu, se ganha). É o que vai mostrar o retorno dos anúncios.</p>
        </form>

        <form method="POST" action="{{ $updateRoute }}">
            @csrf
            @method('PATCH')
            <label for="assignee" class="card-title block mb-2">Responsável</label>
            <select id="assignee" name="assignee" onchange="this.form.submit()" class="field w-full" style="padding:9px 12px; font-size:14px; cursor:pointer">
                <option value="">Sem responsável</option>
                @if($currentKey && !$known)
                    <option value="{{ $currentKey }}" selected>{{ $opportunity->assigneeName(true) }}</option>
                @endif
                @foreach($assigneeOptions as $group => $options)
                    <optgroup label="{{ $group }}">
                        @foreach($options as $key => $name)
                            <option value="{{ $key }}" {{ $currentKey === $key ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="text-xs mt-1.5" style="color: var(--muted)">
                @if($opportunity->assigned_at)
                    Desde {{ $opportunity->assigned_at->format('d/m/Y H:i') }}.
                @endif
                Quem passa o lead para "Em Contato" vira o responsável, se ainda não houver um.
            </p>
        </form>

    </div>
</div>
