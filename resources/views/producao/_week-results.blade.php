{{-- Fragmento AJAX da Semana de Produção (ver ProductionPanelController::semanaKanban()) —
     mesmo padrão visual e de arrastar-e-soltar que a aba "Semana" da Sprint tinha, mas cruzando
     a agência inteira (com os filtros do topo) em vez de uma sprint só.

     "Antes desta semana" não é mais coluna, é só uma contagem (ver opinião do Evandro): dá pra
     ver essas tarefas navegando a régua pro passado, seja pelos botões de semana seja andando
     dia a dia pelas setas (carrossel de 5 dias fixos — ver producao-week-scroll.js). Tarefa sem
     data de aprovação, ou com data muito à frente, não aparece em nenhuma coluna — só entra na
     linha informativa abaixo da navegação. --}}
@php
    $foraDoQuadro = collect([
        $semDataCount > 0 ? $semDataCount . ' tarefa' . ($semDataCount !== 1 ? 's' : '') . ' sem data de aprovação' : null,
        $depoisCount > 0 ? $depoisCount . ' com aprovação depois desta semana' : null,
    ])->filter()->implode(' · ');
@endphp

<div class="flex items-center gap-3 mb-4 flex-wrap">
    <button type="button"
        onclick="document.getElementById('producao-week-offset-input').value='{{ $weekOffset - 1 }}'; document.getElementById('producao-week-filter-form')._liveFilterRefresh();"
        class="btn btn-ghost btn-sm">‹ Semana anterior</button>

    <span class="text-xs font-mono font-bold" style="color:var(--text)">
        {{ $dias[0]['data']->format('d/m') }} a {{ end($dias)['data']->format('d/m/Y') }}
    </span>

    @if($weekOffset !== 0)
        <button type="button"
            onclick="document.getElementById('producao-week-offset-input').value='0'; document.getElementById('producao-week-filter-form')._liveFilterRefresh();"
            class="btn btn-ghost btn-sm">Hoje</button>
    @endif

    <button type="button"
        onclick="document.getElementById('producao-week-offset-input').value='{{ $weekOffset + 1 }}'; document.getElementById('producao-week-filter-form')._liveFilterRefresh();"
        class="btn btn-ghost btn-sm">Próxima semana ›</button>

    @if($atrasadasCount > 0)
        <span class="badge" style="background:var(--red); color:#fff; border-color:transparent"
              title="Tarefas abertas com aprovação antes desta semana — arraste a régua pra trás pra ver">
            {{ $atrasadasCount }} atrasada{{ $atrasadasCount !== 1 ? 's' : '' }} antes desta semana
        </span>
    @endif
</div>

@if($foraDoQuadro !== '')
    <p class="text-xs font-mono mb-4" style="color:var(--muted)">
        {{ $foraDoQuadro }}
        — não aparece{{ ($semDataCount + $depoisCount) !== 1 ? 'm' : '' }} no quadro abaixo.
    </p>
@endif

{{-- Os botões ‹ › nas pontas estendem a régua um dia por vez (JS em producao-week-scroll.js),
     limitados a ±7 dias de hoje — além disso, use a navegação de semana acima. Ficam sempre
     como primeiro/último filho: o JS insere as colunas novas ao lado deles, nunca antes. --}}
<div id="producao-week-board" class="flex gap-2 overflow-x-auto pb-2" style="align-items: start;"
     data-kanban-board data-status-field="approval_date">

    @php
        $btnStyle = 'flex-shrink:0; align-self:stretch; width:28px; min-height:120px;
                     background:var(--s2); border:1px solid var(--border2); border-radius:6px;
                     color:var(--muted); font-size:16px; cursor:pointer';
    @endphp

    <button type="button" id="producao-week-extend-before" style="{{ $btnStyle }}; display:none"
            title="Andar um dia pra trás">‹</button>

    @foreach($dias as $dia)
        @include('producao._week-day-column', ['dia' => $dia])
    @endforeach

    <button type="button" id="producao-week-extend-after" style="{{ $btnStyle }}"
            title="Andar um dia pra frente">›</button>
</div>
