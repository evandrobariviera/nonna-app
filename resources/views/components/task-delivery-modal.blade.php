{{-- Janela "Entrega" — retorno obrigatório pra mandar tarefa pra Revisão Interna.
     Estado no $store.delivery (resources/js/task-delivery.js); incluída uma vez por layout.
     Quando a trava veio de um formulário comum (sem fetch), o servidor volta com o flash
     'delivery_required' e a janela abre sozinha; entregou → recarrega pra mostrar o novo status. --}}
@php $pendingDelivery = session('delivery_required'); @endphp
<div x-data
     @if($pendingDelivery)
     x-init="$nextTick(async () => { if (await $store.delivery.ask(@js($pendingDelivery))) window.location.reload(); })"
     @endif
     x-show="$store.delivery.visible" x-cloak
     @keydown.escape.window="$store.delivery.visible && $store.delivery.cancel()"
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5)">
    <div class="card w-full max-w-lg max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="px-5 pt-5 pb-2">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--purple); letter-spacing:.08em">Entrega pra Revisão Interna</p>
            <p class="text-base font-bold mt-1" style="color:var(--text)" x-text="$store.delivery.task?.title"></p>
        </div>

        <div class="px-5 py-3 flex flex-col gap-4">
            <div>
                <p class="text-sm font-semibold mb-2" style="color:var(--text)">A tarefa foi executada por completo?</p>
                <div class="flex gap-2">
                    <button type="button" @click="$store.delivery.fullyDone = true; $store.delivery.error = ''"
                            class="btn btn-sm flex-1"
                            :class="$store.delivery.fullyDone === true ? 'btn-primary' : 'btn-ghost'">Sim, por completo</button>
                    <button type="button" @click="$store.delivery.fullyDone = false; $store.delivery.error = ''; $nextTick(() => $refs.missing.focus())"
                            class="btn btn-sm flex-1"
                            :class="$store.delivery.fullyDone === false ? 'btn-primary' : 'btn-ghost'">Parcialmente</button>
                </div>
            </div>

            <div x-show="$store.delivery.fullyDone === false" x-cloak>
                <label class="text-sm font-semibold mb-1.5 block" style="color:var(--text)">O que faltou, e por quê?</label>
                <textarea x-ref="missing" x-model="$store.delivery.missing" rows="2"
                    placeholder="Ex: faltou a versão em vídeo — o cliente ainda não mandou as imagens do produto."
                    class="w-full px-3 py-2 text-sm focus:outline-none resize-y leading-relaxed"
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"></textarea>
            </div>

            <div>
                <label class="text-sm font-semibold block" style="color:var(--text)">Conte o que você fez</label>
                <p class="text-xs mt-0.5 mb-1.5" style="color:var(--muted); line-height:1.5">
                    Escreva como se fosse pro cliente: o que foi feito, por que escolheu esse caminho
                    (técnica, ideia, referência) e o que ele deve observar. Esse texto pode ir junto na aprovação.
                </p>
                <textarea x-model="$store.delivery.body" rows="6"
                    placeholder="Ex: Criamos 3 artes com foco no lançamento. Usamos fotos reais da loja em vez de banco de imagem pra passar proximidade, e a chamada destaca o frete grátis, que foi o que mais converteu no último mês..."
                    class="w-full px-3 py-2 text-sm focus:outline-none resize-y leading-relaxed"
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"></textarea>
                <p class="text-[11px] mt-1 text-right" style="color:var(--muted)"
                   x-text="$store.delivery.body.trim().length < 40 ? 'mínimo 40 caracteres (' + $store.delivery.body.trim().length + ')' : ''"></p>
            </div>

            <p x-show="$store.delivery.error" x-cloak class="text-sm" style="color:var(--red)" x-text="$store.delivery.error"></p>
        </div>

        <div class="px-5 py-4 flex items-center justify-end gap-3" style="border-top:1px solid var(--border2)">
            <button type="button" @click="$store.delivery.cancel()" class="btn btn-ghost btn-sm" :disabled="$store.delivery.saving">Cancelar</button>
            <button type="button" @click="$store.delivery.submit()" class="btn btn-primary btn-sm" :disabled="$store.delivery.saving">
                <span x-text="$store.delivery.saving ? 'Enviando...' : 'Entregar pra Revisão'"></span>
            </button>
        </div>
    </div>
</div>
