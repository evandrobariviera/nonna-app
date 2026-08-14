{{--
    Ícone solto (sem preenchimento) — cor = status do item (var(--{color}), mesma
    "Lei das Cores" usada em toda a UI), ícone = tipo/categoria do item. Ver
    business-context.md § Lei das Cores. Reaproveitado por tarefa, reunião, projeto,
    planejamento e campanha — cada um resolve seu próprio icon()/color() antes de chamar.
--}}
@props(['icon', 'color' => 'muted', 'size' => 36])

<span {{ $attributes->class(['inline-flex items-center justify-center flex-shrink-0']) }}
      style="width:{{ $size }}px; height:{{ $size }}px; color:var(--{{ $color }})">
    <x-icon :name="$icon" :size="(string) (int) round($size * 0.62)" />
</span>
