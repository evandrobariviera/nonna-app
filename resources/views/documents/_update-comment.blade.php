{{-- Comentário da equipe num item da análise (sugestão, movimento, ponto em aberto). Salva sozinho
     (saveComment no x-data da página) e entra na "Refazer com meus comentários". --}}
@if($run->isReady())
    <div x-data="{ open: !!(comments[@js($key)] || '').trim() }" class="mt-2">
        <button type="button" x-show="!open" @click="open = true; $nextTick(() => $refs.ta.focus())"
                class="text-xs font-semibold flex items-center gap-1" style="color:var(--purple)">
            <x-icon name="message-circle" size="12" /> {{ $label ?? 'Comentar' }}
        </button>
        <div x-show="open" x-cloak>
            <textarea x-ref="ta" rows="2" maxlength="3000" class="field w-full px-3 py-2 text-xs"
                      x-model="comments[@js($key)]"
                      @input.debounce.800ms="saveComment(@js($key))" @blur="saveComment(@js($key))"
                      placeholder="{{ $placeholder }}"></textarea>
            <p class="text-[11px] mt-0.5" style="color:var(--muted)" x-text="saveState[@js($key)] || 'Salva sozinho. Entra quando você pedir para a IA refazer.'"></p>
        </div>
    </div>
@endif
