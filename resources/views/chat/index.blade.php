{{-- Chat em tela cheia ("expandir" do widget). Mesmo componente do widget em modo
     página: lista à esquerda e conversa à direita. No celular, uma coisa por vez. --}}
<x-app-layout>
    <div x-data="chatWidget({ mode: 'page', openConversation: @js($openConversation) })"
         class="grid md:grid-cols-[320px_1fr] overflow-hidden"
         style="height:calc(100vh - 8rem); background:var(--s1); border:1px solid var(--border2)">

        <div class="min-h-0 flex-col" style="border-right:1px solid var(--border2)"
             :class="view === 'conversation' ? 'hidden md:flex' : 'flex'">
            @include('chat._panel', ['section' => 'list'])
        </div>

        <div class="min-h-0 flex-col" :class="view === 'conversation' ? 'flex' : 'hidden md:flex'">
            <template x-if="view === 'conversation'">
                <div class="flex flex-col h-full min-h-0">
                    @include('chat._panel', ['section' => 'conversation'])
                </div>
            </template>
            <div x-show="view !== 'conversation'" class="flex-1 flex flex-col items-center justify-center text-center p-8" style="color:var(--muted)">
                <x-icon name="messages-square" size="40" />
                <p class="mt-3 text-sm">Escolha uma conversa ao lado ou comece uma nova.</p>
            </div>
        </div>
    </div>
</x-app-layout>
