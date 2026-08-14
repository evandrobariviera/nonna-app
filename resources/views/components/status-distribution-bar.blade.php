{{--
    Barra empilhada — cada segmento é um status (Task::$statuses, exceto cancelado),
    largura proporcional à contagem, cor = Lei das Cores. Ordem INVERTIDA de propósito:
    Concluído primeiro (esquerda), Backlog por último (direita) — sprint é uma corrida,
    a barra "enche" da esquerda pra direita conforme conclui, igual a barra antiga de
    só "% concluído" fazia. Reaproveitado no Dashboard e na tela de Sprint.
--}}
@props(['counts', 'total'])

<div {{ $attributes->class(['w-full h-2 rounded-full overflow-hidden flex']) }} style="background:var(--border2)">
    @foreach(array_reverse(\App\Models\Task::$statuses, true) as $statusKey => $meta)
        @continue($statusKey === 'cancelado')
        @php
            $cnt = $counts[$statusKey] ?? 0;
            $pct = $total > 0 ? ($cnt / $total * 100) : 0;
        @endphp
        {{-- Segmento sempre presente (largura 0 quando vazio) — permite JS recalcular
             via data-status-segment sem manipular o DOM (ver sprints/show.blade.php). --}}
        <div class="h-2 transition-all" data-status-segment="{{ $statusKey }}"
             style="width:{{ $pct }}%; background:var(--{{ $meta['color'] === 'muted' ? 'muted' : $meta['color'] }})"
             title="{{ $meta['label'] }}: {{ round($pct) }}% ({{ $cnt }})"></div>
    @endforeach
</div>
