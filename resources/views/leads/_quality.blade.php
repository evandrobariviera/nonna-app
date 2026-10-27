{{-- Etiqueta de qualidade do lead: clara = avaliação da IA (falta confirmar),
     cheia = confirmada por quem atende. Espera $opp. --}}
@if($opp->qualityLabel())
    @if($opp->isQualityConfirmed())
        <span class="badge badge-{{ $opp->qualityColor() }}" title="Confirmado{{ $opp->quality_confirmed_at ? ' em ' . $opp->quality_confirmed_at->format('d/m/Y') : '' }}">{{ $opp->qualityLabel() }} ✓</span>
    @else
        <span class="badge tag-{{ $opp->qualityColor() }}" title="Avaliação da IA — falta alguém confirmar">IA · {{ $opp->qualityLabel() }}</span>
    @endif
@endif
