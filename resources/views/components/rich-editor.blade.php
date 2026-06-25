@props(['name', 'value' => '', 'minHeight' => '160px'])

<div x-data="richEditor(@js($value ?? ''))"
     x-init="init()"
     class="rich-wrapper">

    {{-- ── Toolbar ── --}}
    <div class="rich-toolbar" @mousedown.prevent>

        {{-- Histórico --}}
        <button type="button" title="Desfazer" @click="run('undo')" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v6h6"/><path d="M3 13C5.5 6.5 14 4 20 8"/></svg>
        </button>
        <button type="button" title="Refazer" @click="run('redo')" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 7v6h-6"/><path d="M21 13C18.5 6.5 10 4 4 8"/></svg>
        </button>

        <div class="rtb-sep"></div>

        {{-- Títulos --}}
        <button type="button" @click="run('h2')" :class="active('heading',{level:2}) ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Título 2" class="rtb-btn font-bold" style="font-size:11px">H2</button>
        <button type="button" @click="run('h3')" :class="active('heading',{level:3}) ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Título 3" class="rtb-btn" style="font-size:11px">H3</button>

        <div class="rtb-sep"></div>

        {{-- Formatação de texto --}}
        <button type="button" @click="run('bold')" :class="active('bold') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Negrito" class="rtb-btn"><b>B</b></button>
        <button type="button" @click="run('italic')" :class="active('italic') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Itálico" class="rtb-btn"><i>I</i></button>
        <button type="button" @click="run('underline')" :class="active('underline') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Sublinhado" class="rtb-btn"><u>U</u></button>
        <button type="button" @click="run('strike')" :class="active('strike') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Tachado" class="rtb-btn"><s>S</s></button>

        <div class="rtb-sep"></div>

        {{-- Listas --}}
        <button type="button" @click="run('ul')" :class="active('bulletList') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Lista com marcadores" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
        </button>
        <button type="button" @click="run('ol')" :class="active('orderedList') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Lista numerada" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4" stroke-width="1.5"/><path d="M4 10h2" stroke-width="1.5"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" stroke-width="1.5"/></svg>
        </button>
        <button type="button" @click="run('blockquote')" :class="active('blockquote') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Citação" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
        </button>

        <div class="rtb-sep"></div>

        {{-- Alinhamento --}}
        <button type="button" @click="run('left')" :class="active('paragraph') && !active('textAlign','center') && !active('textAlign','right') ? 'rtb-btn' : 'rtb-btn'"
                title="Alinhar à esquerda" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <button type="button" @click="run('center')" :class="active('textAlign','center') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Centralizar" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <div class="rtb-sep"></div>

        {{-- Inserir --}}
        <button type="button" @click="run('link')" :class="active('link') ? 'rtb-btn rtb-on' : 'rtb-btn'"
                title="Inserir link" class="rtb-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        </button>
        <button type="button" @click="run('image')" class="rtb-btn" title="Inserir imagem">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </button>
        <button type="button" @click="run('hr')" class="rtb-btn" title="Linha divisória" style="font-size:11px">—</button>
    </div>

    {{-- ── Editor ── --}}
    <div x-ref="editor" class="rich-editor" style="min-height: {{ $minHeight }}"></div>

    {{-- ── Hidden input para FormData ── --}}
    <input type="hidden" name="{{ $name }}" x-ref="input">
</div>
