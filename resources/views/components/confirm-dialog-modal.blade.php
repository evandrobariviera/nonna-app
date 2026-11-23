{{-- Confirmação global (resources/js/confirm-dialog.js) — balão colado no botão que a pessoa
     clicou, sem fundo escuro (24/11). Incluído uma vez por layout. Uso em qualquer view:
     @submit.prevent="if (await $store.confirmDialog.ask('Mensagem?')) $el.submit()" --}}
<div x-show="$store.confirmDialog.visible" x-cloak data-confirm-pop
     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
     @keydown.escape.window="$store.confirmDialog.cancel()"
     @click.outside="Date.now() - $store.confirmDialog.openedAt > 200 && $store.confirmDialog.cancel()"
     role="alertdialog" aria-modal="false" :aria-label="$store.confirmDialog.message"
     class="fixed" :style="'z-index:200; ' + $store.confirmDialog.pos + (!$store.confirmDialog.anchored ? '; width:min(360px, calc(100vw - 16px))' : '')">
    <div class="px-3.5 py-3"
         style="background:var(--s1); border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.16)"
         :style="{ borderTop: '3px solid ' + ($store.confirmDialog.danger ? 'var(--red)' : 'var(--purple)') }">
        <p class="text-sm" style="color:var(--text); line-height:1.5" x-text="$store.confirmDialog.message"></p>
        <div class="mt-3 flex items-center justify-end gap-2">
            <button type="button" @click="$store.confirmDialog.cancel()" class="btn btn-ghost btn-xs">Voltar</button>
            <button type="button" @click="$store.confirmDialog.confirm()" class="btn btn-xs"
                    :class="$store.confirmDialog.danger ? 'btn-danger' : 'btn-primary'"
                    x-text="$store.confirmDialog.okLabel" x-effect="if ($store.confirmDialog.visible) $nextTick(() => $el.focus())"></button>
        </div>
    </div>
</div>
