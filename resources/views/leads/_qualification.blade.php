{{-- Avaliação do lead: o que a IA leu (resumo + por quê, pela régua do cliente) e
     a confirmação de quem atende com um toque — qualidade (Bom/Fraco/Sem perfil) e
     desfecho (Comprou + valor / Desistiu). Compartilhado entre leads/show e
     portal/leads/show. Espera: $opportunity, $updateRoute (PATCH), $ruleMissing
     (true = cliente ainda sem régua revisada; só o lado Nonna mostra o porquê). --}}
@php
    $opp = $opportunity;
    $suggestedValue = $opp->ai_outcome_value ?? $opp->deal_value;
@endphp
<div class="card p-5">
    <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
        <h3 class="card-title flex items-center gap-2">
            <x-icon name="sparkles" size="15" style="color: var(--purple)" />
            Avaliação do lead
        </h3>
        @include('leads._quality', ['opp' => $opp])
    </div>

    @if($opp->ai_evaluated_at)
        @if($opp->ai_summary)
            <p class="text-sm" style="color: var(--text); line-height: 1.6">{{ $opp->ai_summary }}</p>
        @endif
        @if($opp->ai_reason)
            <p class="text-xs mt-1" style="color: var(--muted2)">
                IA achou <strong>{{ \App\Models\ClientLeadOpportunity::$qualities[$opp->ai_quality]['label'] ?? '—' }}</strong>: {{ $opp->ai_reason }}
            </p>
        @endif
        <p class="text-xs mt-1" style="color: var(--muted)">Avaliado pela IA em {{ $opp->ai_evaluated_at->format('d/m/Y H:i') }} — reavalia sozinha quando a conversa anda.</p>
    @elseif($ruleMissing ?? false)
        <p class="text-sm" style="color: var(--muted)">
            A IA ainda não avalia os leads deste cliente: falta revisar a régua de lead qualificado
            @if($opp->lead->client)
                (<a href="{{ route('clients.show', [$opp->lead->client_id, 'tab' => 'leads']) }}" class="underline" style="color: var(--purple)">ficha do cliente → aba Leads</a>)
            @endif.
        </p>
    @else
        <p class="text-sm" style="color: var(--muted)">A IA ainda não avaliou este lead — acontece em até uma hora depois que ele chega ou a conversa para.</p>
    @endif

    {{-- Qualidade: um toque confirma; tocar de novo no mesmo desfaz. --}}
    <div class="mt-4 pt-4" style="border-top: 1px solid var(--border2)">
        <p class="text-xs font-semibold mb-2" style="color: var(--text)">Na sua avaliação, este lead é…</p>
        <div class="flex flex-wrap gap-2">
            @foreach(\App\Models\ClientLeadOpportunity::$qualities as $key => $q)
                @php $active = $opp->quality === $key; @endphp
                <form method="POST" action="{{ $updateRoute }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="quality" value="{{ $active ? '' : $key }}">
                    <button type="submit" class="badge {{ $active ? 'badge-' . $q['color'] : 'tag-' . $q['color'] }}"
                            style="cursor:pointer; padding:6px 14px; font-size:13px{{ !$active && $opp->ai_quality === $key && !$opp->quality ? '; outline: 1px dashed currentColor; outline-offset: 2px' : '' }}"
                            title="{{ $active ? 'Tocar de novo desfaz' : ($opp->ai_quality === $key ? 'A IA sugeriu este' : '') }}">
                        {{ $q['label'] }}{{ $active ? ' ✓' : '' }}
                    </button>
                </form>
            @endforeach
        </div>
        @if($opp->isQualityConfirmed())
            <p class="text-xs mt-2" style="color: var(--muted)">
                Confirmado{{ $opp->qualityConfirmerName() ? ' por ' . $opp->qualityConfirmerName() : '' }} em {{ $opp->quality_confirmed_at->format('d/m/Y H:i') }}.
            </p>
        @elseif($opp->ai_quality)
            <p class="text-xs mt-2" style="color: var(--muted)">O tracejado é o que a IA sugeriu. Um toque confirma — ou escolha outro.</p>
        @endif
    </div>

    {{-- Desfecho: comprou (com valor) vira Ganho; desistiu vira Perdido. --}}
    @if($opp->isOpen())
        <div class="mt-4 pt-4" style="border-top: 1px solid var(--border2)"
             x-data="{ buying: {{ $opp->hasOutcomeSuggestion() && $opp->ai_outcome === 'comprou' ? 'true' : 'false' }} }">
            @if($opp->hasOutcomeSuggestion())
                <div class="text-sm mb-3 p-3" style="background: rgba(100,59,142,.07); border-left: 3px solid var(--purple); color: var(--text)">
                    Pela conversa, a IA acha que este lead
                    <strong>{{ $opp->ai_outcome === 'comprou' ? 'comprou' : 'desistiu' }}</strong>@if($opp->ai_outcome === 'comprou' && $opp->ai_outcome_value)
                        (R$ {{ number_format((float) $opp->ai_outcome_value, 2, ',', '.') }})@endif. Confirma?
                </div>
            @else
                <p class="text-xs font-semibold mb-2" style="color: var(--text)">Já teve desfecho?</p>
            @endif

            <div class="flex flex-wrap items-center gap-2" x-show="!buying">
                <button type="button" @click="buying = true" class="btn btn-sm" style="background: var(--green); color: #fff">Comprou</button>
                <form method="POST" action="{{ $updateRoute }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="outcome" value="desistiu">
                    <button type="submit" class="btn btn-sm btn-ghost" style="color: var(--red)">Desistiu</button>
                </form>
                @if($opp->hasOutcomeSuggestion())
                    <form method="POST" action="{{ $updateRoute }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="outcome" value="aberto">
                        <button type="submit" class="btn btn-sm btn-ghost">Ainda em aberto</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ $updateRoute }}" x-show="buying" x-cloak class="flex flex-wrap items-center gap-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="outcome" value="comprou">
                <span class="text-sm font-bold" style="color: var(--green)">R$</span>
                <input type="number" name="deal_value" step="0.01" min="0" inputmode="decimal" placeholder="Valor da venda"
                       value="{{ $suggestedValue !== null ? (float) $suggestedValue : '' }}"
                       class="field" style="padding:6px 10px; width:160px">
                <button type="submit" class="btn btn-sm" style="background: var(--green); color: #fff">Confirmar venda</button>
                <button type="button" @click="buying = false" class="btn btn-sm btn-ghost">Cancelar</button>
            </form>
        </div>
    @endif
</div>
