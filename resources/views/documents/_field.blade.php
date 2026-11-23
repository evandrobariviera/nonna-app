{{-- Um campo do documento em campos (usado dentro de documents/_editor, no escopo Alpine dele).
     $fieldExpr = definição do campo; $pathExpr = caminho; $valueExpr = valor; $itemExpr = item da lista ou null.
     As expressões entram direto no Alpine (sem x-data aninhado). --}}
@php
    $F = $fieldExpr; $P = "({$pathExpr})"; $V = "({$valueExpr})"; $I = $itemExpr;
@endphp
<div>
    <label class="block text-[11px] font-semibold uppercase mb-1" style="letter-spacing:.06em"
           :style="hasIssue({{ $P }}) ? 'color:var(--orange)' : 'color:var(--muted)'"
           x-text="{{ $F }}.label + ({{ $F }}.required || {{ $F }}.input === 'refs' ? '' : ' (opcional)')"></label>

    {{-- Linha --}}
    <template x-if="{{ $F }}.input === 'line'">
        <input type="text" class="field w-full px-3 py-2 text-sm" :placeholder="{{ $F }}.placeholder || ''"
               :value="{{ $V }} ?? ''"
               @input="edit({{ $P }}, $event.target.value, {{ $I }}, {{ $F }}.key)"
               @blur="flush({{ $P }}, $event.target.value)">
    </template>

    {{-- Texto (parágrafos) --}}
    <template x-if="{{ $F }}.input === 'text'">
        <textarea class="field w-full px-3 py-2 text-sm" rows="3" style="resize:vertical; line-height:1.55"
                  :placeholder="{{ $F }}.placeholder || ''"
                  x-init="$nextTick(() => grow($el))"
                  :value="{{ $V }} ?? ''"
                  @input="grow($el); edit({{ $P }}, $event.target.value, {{ $I }}, {{ $F }}.key)"
                  @blur="flush({{ $P }}, $event.target.value)"></textarea>
    </template>

    {{-- Data --}}
    <template x-if="{{ $F }}.input === 'date'">
        <input type="date" class="field px-3 py-2 text-sm" :value="{{ $V }} ?? ''"
               @change="edit({{ $P }}, $event.target.value, {{ $I }}, {{ $F }}.key); flush({{ $P }}, $event.target.value)">
    </template>

    {{-- Dinheiro --}}
    <template x-if="{{ $F }}.input === 'money'">
        <div class="flex items-center gap-2">
            <span class="text-xs" style="color:var(--muted)">R$</span>
            <input type="number" min="0" step="0.01" class="field w-40 px-3 py-2 text-sm" :value="{{ $V }} ?? ''"
                   :placeholder="{{ $F }}.placeholder || ''"
                   @input="edit({{ $P }}, $event.target.value, {{ $I }}, {{ $F }}.key)"
                   @blur="flush({{ $P }}, $event.target.value)">
        </div>
    </template>

    {{-- Ligações com itens de outra lista (ex.: metas que a campanha carrega) --}}
    <template x-if="{{ $F }}.input === 'refs'">
        <div class="flex flex-wrap gap-1.5">
            <template x-for="o in refOptions({{ $F }}, {{ $I }})" :key="o.id">
                <button type="button" class="text-xs px-2.5 py-1 rounded-full font-semibold"
                        :style="({{ $V }} || []).includes(o.id)
                            ? 'background:rgba(100,59,142,.14); border:1px solid rgba(100,59,142,.45); color:var(--purple)'
                            : 'background:var(--s3); border:1px solid var(--border2); color:var(--muted)'"
                        @click="toggleRef({{ $P }}, {{ $I }}, {{ $F }}.key, o.id)">
                    <span x-text="({{ $V }} || []).includes(o.id) ? '✓ ' : '+ '"></span><span x-text="refLabel(o)"></span>
                </button>
            </template>
            <span x-show="!refOptions({{ $F }}, {{ $I }}).length" class="text-xs" style="color:var(--muted2)">Nada para escolher ainda.</span>
        </div>
    </template>
</div>
