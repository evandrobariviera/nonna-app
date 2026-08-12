@props(['name', 'value' => '', 'minHeight' => '160px'])

<div x-data="richEditor(@js($value ?? ''))"
     class="rich-wrapper">

    {{-- ── Toolbar ── --}}
    <div class="rich-toolbar" @mousedown.prevent>

        {{-- Histórico --}}
        <button type="button" title="Desfazer" @click="run('undo')" class="rtb-btn">
            <x-icon name="undo" size="13" />
        </button>
        <button type="button" title="Refazer" @click="run('redo')" class="rtb-btn">
            <x-icon name="redo" size="13" />
        </button>

        <div class="rtb-sep"></div>

        {{-- Títulos --}}
        <button type="button" @click="run('h2')"
                class="rtb-btn" :class="active('heading',{level:2}) ? 'rtb-on' : ''"
                title="Título 2" style="font-size:11px;font-weight:700">H2</button>
        <button type="button" @click="run('h3')"
                class="rtb-btn" :class="active('heading',{level:3}) ? 'rtb-on' : ''"
                title="Título 3" style="font-size:11px">H3</button>

        <div class="rtb-sep"></div>

        {{-- Formatação de texto --}}
        <button type="button" @click="run('bold')"
                class="rtb-btn" :class="active('bold') ? 'rtb-on' : ''"
                title="Negrito"><b>B</b></button>
        <button type="button" @click="run('italic')"
                class="rtb-btn" :class="active('italic') ? 'rtb-on' : ''"
                title="Itálico"><i>I</i></button>
        <button type="button" @click="run('underline')"
                class="rtb-btn" :class="active('underline') ? 'rtb-on' : ''"
                title="Sublinhado"><u>U</u></button>
        <button type="button" @click="run('strike')"
                class="rtb-btn" :class="active('strike') ? 'rtb-on' : ''"
                title="Tachado"><s>S</s></button>

        <div class="rtb-sep"></div>

        {{-- Listas --}}
        <button type="button" @click="run('ul')"
                class="rtb-btn" :class="active('bulletList') ? 'rtb-on' : ''"
                title="Lista com marcadores">
            <x-icon name="list" size="13" />
        </button>
        <button type="button" @click="run('ol')"
                class="rtb-btn" :class="active('orderedList') ? 'rtb-on' : ''"
                title="Lista numerada">
            <x-icon name="list-ordered" size="13" />
        </button>
        <button type="button" @click="run('blockquote')"
                class="rtb-btn" :class="active('blockquote') ? 'rtb-on' : ''"
                title="Citação">
            <x-icon name="quote" size="13" />
        </button>

        <div class="rtb-sep"></div>

        {{-- Alinhamento --}}
        <button type="button" @click="run('left')"
                class="rtb-btn" :class="!activeAlign('center') && !activeAlign('right') ? 'rtb-on' : ''"
                title="Alinhar à esquerda">
            <x-icon name="align-left" size="13" />
        </button>
        <button type="button" @click="run('center')"
                class="rtb-btn" :class="activeAlign('center') ? 'rtb-on' : ''"
                title="Centralizar">
            <x-icon name="align-center" size="13" />
        </button>
        <button type="button" @click="run('right')"
                class="rtb-btn" :class="activeAlign('right') ? 'rtb-on' : ''"
                title="Alinhar à direita">
            <x-icon name="align-right" size="13" />
        </button>

        <div class="rtb-sep"></div>

        {{-- Inserir --}}
        <button type="button" @click="run('link')"
                class="rtb-btn" :class="active('link') ? 'rtb-on' : ''"
                title="Inserir link">
            <x-icon name="link" size="13" />
        </button>
        <button type="button" @click="run('image')" class="rtb-btn" title="Inserir imagem">
            <x-icon name="image" size="13" />
        </button>
        <button type="button" @click="run('hr')" class="rtb-btn" title="Linha divisória" style="font-size:11px">—</button>
    </div>

    {{-- ── Editor ── --}}
    <div x-ref="editor" class="rich-editor" style="min-height: {{ $minHeight }}"></div>

    {{-- ── Hidden inputs ── --}}
    <input type="hidden" name="{{ $name }}" x-ref="input">
    <input type="file" accept="image/*" x-ref="fileInput" @change="onFileChange($event)"
           style="display:none" tabindex="-1">
</div>
