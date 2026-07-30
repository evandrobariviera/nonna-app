{{-- Modal de confirmação global — substitui window.confirm() nativo. Precisa estar
     dentro de uma árvore com algum x-data ancestral (Alpine só inicializa a partir
     daí) e ser incluído uma vez por layout. Uso em qualquer view:
     @submit.prevent="if (await $store.confirmDialog.ask('Mensagem?')) $el.submit()" --}}
<div x-show="$store.confirmDialog.visible" x-cloak
     @keydown.escape.window="$store.confirmDialog.cancel()"
     @keydown.enter.window="$store.confirmDialog.confirm()"
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5)">
    <div class="card w-full max-w-sm" @click.outside="$store.confirmDialog.cancel()">
        <div class="px-5 py-5">
            <p class="text-sm" style="color:var(--text); line-height:1.6" x-text="$store.confirmDialog.message"></p>
        </div>
        <div class="px-5 py-4 flex items-center justify-end gap-3" style="border-top:1px solid var(--border2)">
            <button type="button" @click="$store.confirmDialog.cancel()" class="btn btn-ghost btn-sm">Cancelar</button>
            <button type="button" @click="$store.confirmDialog.confirm()" class="btn btn-primary btn-sm">OK</button>
        </div>
    </div>
</div>
