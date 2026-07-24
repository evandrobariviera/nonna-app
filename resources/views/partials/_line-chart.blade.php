{{-- Gráfico de linha leve via SVG, sem dependência de JS (mesmo espírito do gráfico
     de barras já usado em portal/campaigns/index.blade.php).
     Espera: $points (array de ['x' => label, 'y' => number]), $color (var CSS).
     Opcional: $formatValue (Closure(float): string, formata o valor no tooltip). --}}
@php
    $values = collect($points)->pluck('y');
    $max = $values->max() ?? 0;
    $min = min($values->min() ?? 0, 0);
    $range = max($max - $min, 1);
    $count = count($points);
    $w = 100;
    $h = 32;
    $stepX = $count > 1 ? $w / ($count - 1) : 0;

    $coords = collect($points)->values()->map(function ($p, $i) use ($stepX, $h, $min, $range) {
        return [
            'x'     => round($stepX * $i, 2),
            'y'     => round($h - (($p['y'] - $min) / $range) * $h, 2),
            'label' => $p['x'],
            'value' => $p['y'],
        ];
    });

    $linePoints = $coords->map(fn ($c) => "{$c['x']},{$c['y']}")->implode(' ');
    $areaPoints = $linePoints . " {$w},{$h} 0,{$h}";
@endphp
<div style="height:56px">
    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="width:100%; height:100%; overflow:visible">
        <polygon points="{{ $areaPoints }}" fill="{{ $color }}" opacity=".08"></polygon>
        <polyline points="{{ $linePoints }}" fill="none" stroke="{{ $color }}" stroke-width="1.5"></polyline>
        @foreach($coords as $c)
            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="1.6" fill="{{ $color }}">
                <title>{{ $c['label'] }}: {{ isset($formatValue) ? $formatValue($c['value']) : $c['value'] }}</title>
            </circle>
        @endforeach
    </svg>
</div>
