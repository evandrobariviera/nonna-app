{{-- Etiqueta do status de uma análise "Atualizar documento com reunião" (DocumentUpdateRun). --}}
@props(['status'])
@php
    [$label, $color] = match ($status) {
        'processando' => ['Analisando…', 'purple'],
        'pronto'      => ['Sugestões prontas', 'orange'],
        'aplicado'    => ['Aplicado', 'green'],
        'descartado'  => ['Descartado', 'muted'],
        default       => ['Erro', 'red'],
    };
@endphp
<span {{ $attributes->merge(['class' => "badge badge-{$color} flex-shrink-0"]) }}>{{ $label }}</span>
