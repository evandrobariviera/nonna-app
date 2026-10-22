{{-- Gráfico no padrão Mirbal (ApexCharts, ver resources/js/charts.js).
     type:       area | bar | line | donut | pie | radialBar
     series:     [['name' => 'Entradas', 'data' => [..]], ...]  (donut/pie: só os números)
     categories: rótulos do eixo X (donut/pie: use labels)
     colors:     ['var(--green)', '#hex', ...] — var() é lida na hora, segue tema e marca
     format:     number | decimal | currency | currencyShort | percent (tooltip; axisFormat
                 pro eixo Y, se diferente)
     sparkline:  true = mini gráfico sem eixos (cards de número)
     options:    qualquer opção extra do ApexCharts (sobrescreve as padrão) --}}
@props([
    'type' => 'area',
    'series' => [],
    'categories' => [],
    'labels' => [],
    'colors' => null,
    'height' => 260,
    'format' => 'number',
    'axisFormat' => null,
    'sparkline' => false,
    'stacked' => false,
    'legend' => null,
    'columnWidth' => null,
    'options' => [],
])

@php
    $config = array_filter([
        'type' => $type,
        'series' => $series,
        'categories' => $categories,
        'labels' => $labels,
        'colors' => $colors,
        'height' => $height,
        'format' => $format,
        'axisFormat' => $axisFormat,
        'sparkline' => $sparkline,
        'stacked' => $stacked,
        'legend' => $legend,
        'columnWidth' => $columnWidth,
        'options' => $options,
    ], fn ($v) => $v !== null && $v !== []);
@endphp

<div x-data="apexChart(@js($config))" {{ $attributes->merge(['class' => 'w-full']) }} style="min-height: {{ (int) $height }}px">
    <div x-ref="canvas"></div>
</div>
