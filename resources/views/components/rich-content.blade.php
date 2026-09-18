{{-- Exibe conteúdo salvo por <x-rich-editor>. Usa a classe ProseMirror pra herdar a
     mesma tipografia do editor, então o texto fica idêntico dentro e fora da edição.

     Texto gravado ANTES do editor rico não tem tag nenhuma — aqui ele vira <p>/<br>
     só na exibição (escapado), sem precisar de migration nem backfill. Mesma lógica de
     tiptap-editor.js:normalizeContent e do histórico de comentários da tarefa. --}}
@props(['value' => null])

@php
    $richHtml = (string) ($value ?? '');

    // Só é considerado HTML do editor quando COMEÇA com um bloco que o tiptap produz.
    // Checar "tem alguma coisa entre < e >" seria frouxo demais: um briefing antigo com
    // "faturamento < 100k" ou um <script> colado viraria HTML cru — quebrando a
    // formatação e executando o que não deveria. Texto que não casa aqui é escapado.
    $isEditorHtml = (bool) preg_match(
        '/^\s*<(p|h[1-6]|ul|ol|blockquote|pre|hr|img|figure|table|div)\b/i',
        $richHtml
    );

    if ($richHtml !== '' && ! $isEditorHtml) {
        $richHtml = collect(preg_split('/\n\n+/', $richHtml))
            ->map(fn ($paragraph) => '<p>' . nl2br(e($paragraph)) . '</p>')
            ->implode('');
    }
@endphp

<div {{ $attributes->merge(['class' => 'ProseMirror']) }}>{!! $richHtml !!}</div>
