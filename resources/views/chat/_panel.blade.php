{{-- Miolo do chat: lista de conversas / escolher pessoa / conversa aberta.
     Estado vem do componente Alpine chatWidget (resources/js/chat-widget.js).

     $section:
       - 'widget'       → widget flutuante: uma coisa por vez (lista OU pessoas OU conversa)
       - 'list'         → tela cheia, coluna da esquerda (lista/pessoas, sempre visível)
       - 'conversation' → tela cheia, coluna da direita (conversa aberta)

     Conteúdo de mensagem SEMPRE chega escapado do servidor (ChatMessage::bodyHtml),
     por isso o x-html em body_html é seguro; nome/arquivo usam x-text. --}}
@php
    $section = $section ?? 'widget';
    $isWidget = $section === 'widget';

    // Condições Alpine de cada bloco, conforme a seção.
    $showListArea = match ($section) { 'widget' => "view === 'list' || view === 'people'", 'list' => 'true', default => 'false' };
    $showList     = $section === 'list' ? "view !== 'people'" : "view === 'list'";
    $showConv     = $section === 'list' ? 'false' : "view === 'conversation'";
@endphp

<div class="flex flex-col h-full min-h-0"
     @if($section !== 'list')
     @dragover.prevent="if (view === 'conversation') dragging = true"
     @dragleave.self="dragging = false"
     @drop.prevent="onDrop($event)"
     @endif>

    {{-- ══ CABEÇALHO ══ --}}
    <div class="flex items-center gap-2 h-14 px-3 flex-shrink-0" style="border-bottom:1px solid var(--border2); background:var(--s1)">
        @if($section !== 'list')
            <template x-if="{{ $isWidget ? "view !== 'list'" : 'true' }}">
                <button type="button" @click="backToList()" class="chat-icon-btn {{ $isWidget ? '' : 'md:hidden' }}" title="Voltar">
                    <x-icon name="arrow-left" size="16" />
                </button>
            </template>
        @else
            <template x-if="view === 'people'">
                <button type="button" @click="backToList()" class="chat-icon-btn" title="Voltar">
                    <x-icon name="arrow-left" size="16" />
                </button>
            </template>
        @endif

        @if($section !== 'list')
            <template x-if="view === 'conversation' && active">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <div class="chat-avatar" style="width:30px; height:30px; font-size:11px">
                        <template x-if="active.avatar"><img :src="active.avatar" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="!active.avatar && active.type !== 'direct'"><x-icon name="hash" size="14" /></template>
                        <template x-if="!active.avatar && active.type === 'direct'"><span x-text="active.initials"></span></template>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate" style="color:var(--text)" x-text="active.name"></p>
                        <p class="text-xs truncate" style="color:var(--muted)"
                           x-text="active.type === 'direct' ? 'Conversa individual' : (active.members + ' participantes')"></p>
                    </div>
                </div>
            </template>
        @endif

        @if($section !== 'conversation')
            <template x-if="{{ $showList }}">
                <p class="flex-1 text-sm font-bold" style="color:var(--text)">Chat da equipe</p>
            </template>
            <template x-if="view === 'people'">
                <p class="flex-1 text-sm font-bold" style="color:var(--text)">Nova conversa</p>
            </template>
        @endif

        <div class="flex items-center gap-0.5 flex-shrink-0 ml-auto">
            @if($section !== 'list')
                <template x-if="view === 'conversation' && active">
                    <button type="button" @click="toggleMute()" class="chat-icon-btn"
                            :title="active.muted ? 'Voltar a receber avisos desta conversa' : 'Silenciar avisos desta conversa'">
                        <span x-show="!active.muted"><x-icon name="bell" size="15" /></span>
                        <span x-show="active.muted" x-cloak style="color:var(--orange)"><x-icon name="bell-off" size="15" /></span>
                    </button>
                </template>
            @endif
            @if($section !== 'conversation')
                <template x-if="{{ $showList }}">
                    <button type="button" @click="showPeople()" class="chat-icon-btn" title="Nova conversa">
                        <x-icon name="plus" size="16" />
                    </button>
                </template>
            @endif
            @if($isWidget)
                <button type="button" @click="expand()" class="chat-icon-btn hidden md:flex" title="Abrir em tela cheia">
                    <x-icon name="maximize-2" size="14" />
                </button>
                <button type="button" @click="close()" class="chat-icon-btn" title="Fechar">
                    <x-icon name="x" size="16" />
                </button>
            @endif
        </div>
    </div>

    {{-- ══ LISTA / PESSOAS ══ --}}
    <template x-if="{{ $showListArea }}">
        <div class="flex flex-col flex-1 min-h-0">
            <div class="p-3 flex-shrink-0">
                <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2" style="color:var(--muted)"><x-icon name="search" size="14" /></span>
                    <input type="text" x-model="search" class="chat-search"
                           :placeholder="view === 'people' ? 'Buscar pessoa…' : 'Buscar conversa…'">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto min-h-0">
                {{-- Conversas --}}
                <template x-if="{{ $showList }}">
                    <div>
                        <template x-for="c in filteredConversations" :key="c.id">
                            <button type="button" @click="openConversation(c)" class="chat-row"
                                    :class="active && active.id === c.id && view === 'conversation' ? 'chat-row-active' : ''">
                                <div class="chat-avatar">
                                    <template x-if="c.avatar"><img :src="c.avatar" alt="" class="w-full h-full object-cover"></template>
                                    <template x-if="!c.avatar && c.type !== 'direct'"><x-icon name="hash" size="16" /></template>
                                    <template x-if="!c.avatar && c.type === 'direct'"><span x-text="c.initials"></span></template>
                                </div>
                                <div class="flex-1 min-w-0 text-left">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm truncate flex-1" style="color:var(--text)"
                                           :class="c.unread ? 'font-bold' : 'font-medium'" x-text="c.name"></p>
                                        <span class="text-[11px] flex-shrink-0" style="color:var(--muted)" x-text="listTime(c.last_preview ? c.last_message_at : null)"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs truncate flex-1" style="color:var(--muted2)"
                                           x-text="c.last_preview ?? (c.type === 'direct' ? 'Diga olá 👋' : 'Canal do setor')"></p>
                                        <span x-show="c.muted" x-cloak style="color:var(--muted)"><x-icon name="bell-off" size="12" /></span>
                                        {{-- :class, nunca :style em string aqui: :style="'...'" sobrescreve o
                                             display:none do x-show e o "0" aparecia sempre. --}}
                                        <span x-show="c.unread > 0" x-cloak class="chat-badge"
                                              :class="c.muted ? 'chat-badge-muted' : ''"
                                              x-text="c.unread > 99 ? '99+' : c.unread"></span>
                                    </div>
                                </div>
                            </button>
                        </template>

                        <div x-show="listLoaded && conversations.length === 0" x-cloak class="px-6 py-10 text-center">
                            <p class="text-sm font-semibold" style="color:var(--text)">Nenhuma conversa ainda</p>
                            <p class="text-xs mt-1" style="color:var(--muted)">Clique no + pra conversar com alguém da equipe.</p>
                        </div>
                        <div x-show="!listLoaded" class="flex justify-center py-10" style="color:var(--muted)">
                            <x-icon name="loader-circle" size="18" class="animate-spin" />
                        </div>
                    </div>
                </template>

                {{-- Pessoas --}}
                <template x-if="view === 'people'">
                    <div>
                        <template x-for="p in filteredPeople" :key="p.id">
                            <button type="button" @click="startDirect(p)" class="chat-row">
                                <div class="chat-avatar">
                                    <template x-if="p.avatar"><img :src="p.avatar" alt="" class="w-full h-full object-cover"></template>
                                    <template x-if="!p.avatar"><span x-text="p.initials"></span></template>
                                </div>
                                <p class="flex-1 text-left text-sm font-medium truncate" style="color:var(--text)" x-text="p.name"></p>
                            </button>
                        </template>
                    </div>
                </template>

                <p x-show="error && view !== 'conversation'" x-cloak class="px-3 py-2 text-xs" style="color:var(--red)" x-text="error"></p>
            </div>
        </div>
    </template>

    {{-- ══ CONVERSA ══ --}}
    <template x-if="{{ $showConv }}">
        <div class="flex flex-col flex-1 min-h-0 relative">
            <div x-ref="messages" @scroll.debounce.100ms="onScroll()" @load.capture="onMediaLoad()" @click="onMessageClick($event)" class="flex-1 overflow-y-auto min-h-0 px-3 py-3" style="background:var(--bg)">
                <div x-show="loadingOlder" class="flex justify-center py-2" style="color:var(--muted)">
                    <x-icon name="loader-circle" size="14" class="animate-spin" />
                </div>
                <div x-show="loadingMessages" class="flex justify-center py-10" style="color:var(--muted)">
                    <x-icon name="loader-circle" size="18" class="animate-spin" />
                </div>
                <div x-show="!loadingMessages && messages.length === 0" x-cloak class="text-center py-10 text-xs" style="color:var(--muted)">
                    Nenhuma mensagem ainda. Mande a primeira!
                </div>

                <template x-for="(m, i) in messages" :key="m.id">
                    <div>
                        <div x-show="showDay(i)" class="flex justify-center my-3">
                            <span class="chat-day" x-text="dayLabel(m.created_at)"></span>
                        </div>
                        <div class="flex mb-1" :class="m.mine ? 'justify-end' : 'justify-start'">
                            <div class="chat-bubble" :class="m.mine ? 'chat-bubble-mine' : 'chat-bubble-theirs'">
                                <p x-show="showAuthor(i)" class="text-[11px] font-bold mb-0.5" style="color:var(--purple)" x-text="m.user_name"></p>

                                <template x-for="a in m.attachments" :key="a.id">
                                    <div class="mb-1">
                                        <template x-if="a.is_image">
                                            <a :href="a.url" target="_blank" rel="noopener">
                                                <img :src="a.url" :alt="a.filename" loading="lazy" class="chat-image">
                                            </a>
                                        </template>
                                        <template x-if="!a.is_image">
                                            <a :href="a.download_url" class="chat-file">
                                                <x-icon name="file" size="16" class="flex-shrink-0" />
                                                <span class="min-w-0">
                                                    <span class="block truncate text-xs font-semibold" x-text="a.filename"></span>
                                                    <span class="block text-[11px] opacity-70" x-text="fileSize(a.size)"></span>
                                                </span>
                                                <x-icon name="download" size="14" class="flex-shrink-0 opacity-70" />
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <div x-show="m.body_html" class="chat-text" x-html="m.body_html"></div>
                                <p class="chat-time" :title="showReceipt(m) ? receiptTitle(m) : null">
                                    <span x-text="time(m.created_at)"></span>
                                    <template x-if="showReceipt(m)">
                                        <span class="chat-receipt" :class="isRead(m) && 'chat-receipt-read'">
                                            <x-icon name="check" size="13" stroke="2.5" x-show="!isRead(m)" />
                                            <x-icon name="check-check" size="13" stroke="2.5" x-show="isRead(m)" />
                                        </span>
                                    </template>
                                </p>
                            </div>
                        </div>
                        <p x-show="isLastReadMine(i)" x-cloak class="chat-read-label" x-text="receiptTitle(m)"></p>
                    </div>
                </template>
            </div>

            {{-- Arraste de arquivo --}}
            <div x-show="dragging" x-cloak class="absolute inset-0 flex items-center justify-center text-sm font-semibold pointer-events-none"
                 style="background:rgba(100,59,142,.12); border:2px dashed var(--purple); color:var(--purple)">
                Solte pra anexar
            </div>

            {{-- Arquivos escolhidos (ainda não enviados) --}}
            <div x-show="files.length" x-cloak class="flex gap-2 px-3 pt-2 overflow-x-auto flex-shrink-0" style="background:var(--s1); border-top:1px solid var(--border2)">
                <template x-for="(f, i) in files" :key="i">
                    <div class="chat-pending">
                        <template x-if="f.previewUrl"><img :src="f.previewUrl" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="!f.previewUrl"><span class="text-[10px] p-1 break-all leading-tight" x-text="f.name"></span></template>
                        <button type="button" @click="removeFile(i)" class="chat-pending-x" title="Remover"><x-icon name="x" size="10" /></button>
                    </div>
                </template>
            </div>

            <p x-show="error" x-cloak class="px-3 py-1.5 text-xs flex-shrink-0" style="color:var(--red); background:var(--s1)" x-text="error"></p>

            {{-- Caixa de envio --}}
            <form @submit.prevent="send()" class="relative flex items-end gap-1.5 p-2 flex-shrink-0" style="background:var(--s1); border-top:1px solid var(--border2)">

                {{-- Menu do atalho "/" — passo 1: tipo; passo 2: busca --}}
                <div x-show="refPicker && (refPicker.step === 'search' || refTypeOptions.length)" x-cloak
                     @click.outside="if (refPicker?.step === 'search') closeRefPicker()"
                     class="absolute left-2 right-2 bottom-full mb-1 chat-ref-menu">
                    <template x-if="refPicker?.step === 'type'">
                        <div>
                            <p class="chat-ref-menu-title">Citar na conversa</p>
                            <template x-for="(t, i) in refTypeOptions" :key="t.key">
                                <button type="button" @mousedown.prevent="chooseRefType(t.key)" @mouseenter="refPicker.index = i"
                                        class="chat-ref-option" :class="refPicker.index === i ? 'chat-ref-option-active' : ''">
                                    <span class="chat-ref-tag" :class="'chat-ref-tag-' + t.key" x-text="t.label"></span>
                                    <span class="text-xs truncate" style="color:var(--muted2)" x-text="t.hint"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <template x-if="refPicker?.step === 'search'">
                        <div>
                            <div class="flex items-center gap-2 px-2 pt-2 pb-1">
                                <span class="chat-ref-tag" :class="'chat-ref-tag-' + refPicker.type" x-text="refTypeLabel(refPicker.type)"></span>
                                <input type="text" x-ref="refSearch" x-model="refPicker.search"
                                       @input="refPicker.loading = true; fetchRefs()" @keydown="onRefSearchKeydown($event)"
                                       class="chat-search" style="padding-left:10px" placeholder="Pesquisar…">
                            </div>
                            <div class="max-h-56 overflow-y-auto pb-1">
                                <template x-for="(item, i) in refPicker.results" :key="item.id">
                                    <button type="button" @mousedown.prevent="pickRef(item)" @mouseenter="refPicker.index = i"
                                            class="chat-ref-option" :class="refPicker.index === i ? 'chat-ref-option-active' : ''">
                                        <span class="min-w-0 text-left">
                                            <span class="block text-sm truncate" style="color:var(--text)" x-text="item.label"></span>
                                            <span class="block text-[11px] truncate" style="color:var(--muted)" x-text="item.sub"></span>
                                        </span>
                                    </button>
                                </template>
                                <p x-show="refPicker.loading" class="px-3 py-2 text-xs" style="color:var(--muted)">Buscando…</p>
                                <p x-show="!refPicker.loading && !refPicker.results.length" class="px-3 py-2 text-xs" style="color:var(--muted)">Nada encontrado.</p>
                            </div>
                        </div>
                    </template>
                    <p class="chat-ref-menu-foot">↑↓ navegar · Enter escolher · Esc fechar</p>
                </div>

                <label class="chat-icon-btn cursor-pointer" title="Anexar arquivo ou imagem">
                    <x-icon name="paperclip" size="16" />
                    <input type="file" multiple class="hidden" @change="addFiles($event.target.files); $event.target.value = ''">
                </label>
                <textarea x-ref="input" x-model="draft" rows="1"
                          @keydown="onInputKeydown($event)" @input="onInput($event)" @paste="onPaste($event)"
                          @blur="setTimeout(() => { if (refPicker?.step === 'type') refPicker = null }, 150)"
                          x-effect="draft; $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                          placeholder="Mensagem… ( / cita tarefa, projeto, campanha ou cliente)" class="chat-input"></textarea>
                <button type="submit" class="chat-send" :disabled="sending || (!draft.trim() && !files.length)" title="Enviar (Enter)">
                    <span x-show="!sending"><x-icon name="send" size="15" /></span>
                    <span x-show="sending" x-cloak><x-icon name="loader-circle" size="15" class="animate-spin" /></span>
                </button>
            </form>
        </div>
    </template>
</div>
