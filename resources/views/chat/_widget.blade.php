{{-- Widget flutuante do chat interno — incluído em layouts/app.blade.php em toda
     tela interna (nunca no Portal do Cliente, em página pública ou no modo embed).
     Fica recolhido enquanto o painel lateral de detalhes está aberto, pra não
     brigar com ele. No celular abre em tela cheia e o botão sobe acima da barra
     inferior. --}}
<div x-data="chatWidget({ mode: 'widget' })" x-show="!$store.sidePanel.visible" class="chat-widget-root">

    {{-- Prévia de mensagem nova --}}
    <div x-show="toast && !open" x-cloak x-transition.opacity
         @click="openFromToast(toast.conversationId)"
         class="fixed right-4 bottom-36 md:right-6 md:bottom-24 z-[45] w-72 max-w-[calc(100vw-2rem)] cursor-pointer chat-toast">
        <p class="text-xs font-bold truncate" style="color:var(--text)" x-text="toast?.title"></p>
        <p class="text-xs mt-0.5 line-clamp-2" style="color:var(--muted2)" x-text="toast?.body"></p>
    </div>

    {{-- Botão --}}
    <button type="button" x-show="!open" @click="show()"
            class="fixed right-4 bottom-20 md:right-6 md:bottom-6 z-[45] chat-launcher"
            title="Chat da equipe">
        <x-icon name="message-circle" size="24" />
        <span x-show="$store.chat.unreadTotal > 0" x-cloak class="chat-launcher-badge"
              x-text="$store.chat.unreadTotal > 99 ? '99+' : $store.chat.unreadTotal"></span>
    </button>

    {{-- Painel --}}
    <div x-show="open" x-cloak
         class="fixed z-[45] inset-0 md:inset-auto md:right-6 md:bottom-6 md:w-[380px] md:h-[min(600px,calc(100vh-6rem))] chat-window">
        @include('chat._panel', ['section' => 'widget'])
    </div>
</div>
