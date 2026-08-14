{{--
    Quadrado colorido com ícone — cor de fundo = status do item (badge-{color}, mesma
    "Lei das Cores" usada em toda a UI), ícone = tipo/categoria do item. Ver
    business-context.md § Lei das Cores. Reaproveitado por tarefa, reunião, projeto,
    planejamento e campanha — cada um resolve seu próprio icon()/color() antes de chamar.
--}}
@props(['icon', 'color' => 'muted', 'size' => 36])

<span {{ $attributes->class(["badge-{$color}", 'inline-flex items-center justify-center flex-shrink-0']) }}
      style="width:{{ $size }}px; height:{{ $size }}px; border-radius:9px">
    <x-icon :name="$icon" :size="(string) (int) round($size * 0.5)" />
</span>
