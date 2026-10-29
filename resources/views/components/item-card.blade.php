{{--
    Card padrão de item em lista (tarefa, reunião, pendência, planejamento, cliente) — um
    desenho só pro App inteiro. Dois canais de cor, cada um com um trabalho:
      • NATUREZA (o que é) → quadradinho do ícone com fundo suave da cor do tipo
        (tarefa roxo, reunião azul, pendência ciano, planejamento índigo, campanha rosa,
        cliente cinza).
      • ESTADO (precisa de atenção?) → barrinha à esquerda: late = vermelho (venceu),
        soon = laranja (vence logo), now = verde + fundo levemente tingido (é agora).
        Sem estado = card limpo.
    É um <a> de verdade: Ctrl+clique / botão do meio abrem em nova aba sozinhos, e o ↗
    (aparece no hover; no toque fica sempre visível) faz o mesmo pra quem não conhece o atalho.

    Props:
    - href   (obrigatório) destino do card e do ↗
    - kind   tarefa|reuniao|pendencia|planejamento|campanha|cliente (cor do quadradinho)
    - icon   ícone lucide (padrão por kind)
    - title  texto principal (até 2 linhas)
    - state  null|late|soon|now
    - popup  true = clique simples abre a tarefa na janela por cima ($store.taskPopup)
             em vez de navegar (Ctrl/Cmd/botão do meio continuam abrindo em aba)
    - done   true = item concluído (riscado e apagado)
    Slots: $meta (linha de baixo: cliente, data…), $badge (antes do título, ex.: TRAVA).
    Atributos extras (data-kanban-*, x-show…) vão pro <a>.
--}}
@props([
    'href',
    'kind' => 'tarefa',
    'icon' => null,
    'title' => '',
    'state' => null,
    'popup' => false,
    'done' => false,
])

@php
    $icon ??= [
        'tarefa'       => 'square-check',
        'reuniao'      => 'calendar',
        'pendencia'    => 'hourglass',
        'planejamento' => 'compass',
        'cliente'      => 'building-2',
        'campanha'     => 'megaphone',
    ][$kind] ?? 'circle';
@endphp

<a href="{{ $href }}"
   @if($popup) @click="if (!$event.ctrlKey && !$event.metaKey && !$event.shiftKey) { $event.preventDefault(); $store.taskPopup.open('{{ $href }}') }" @endif
   {{ $attributes->class([
       'item-card',
       'ic-' . $kind,
       'is-' . $state => $state,
       'is-done' => $done,
   ]) }}>
    <span class="ic-chip"><x-icon :name="$icon" size="16" /></span>
    <span class="ic-body">
        <span class="ic-title">
            @isset($badge){{ $badge }}@endisset{{ $title }}
        </span>
        @isset($meta)
            <span class="ic-meta">{{ $meta }}</span>
        @endisset
    </span>
    <span class="ic-newtab" role="link" tabindex="-1" title="Abrir em nova aba"
          onclick="event.preventDefault(); event.stopPropagation(); window.open('{{ $href }}', '_blank', 'noopener')">
        <x-icon name="external-link" size="13" />
    </span>
</a>
