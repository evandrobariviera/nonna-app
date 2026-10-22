{{-- Mini gráfico de área (padrão Mirbal, ver <x-chart> / resources/js/charts.js).
     Espera: $points (array de ['x' => label, 'y' => number]), $color (var CSS).
     Opcional: $format (number | decimal | currency | percent) — formato do valor no tooltip;
     $height (padrão 70). --}}
<x-chart type="area" sparkline
         :height="$height ?? 70"
         :colors="[$color]"
         :format="$format ?? 'number'"
         :categories="collect($points)->pluck('x')->all()"
         :series="[['name' => $name ?? 'Valor', 'data' => collect($points)->pluck('y')->map(fn ($v) => round((float) $v, 2))->all()]]" />
