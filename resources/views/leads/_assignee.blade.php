{{-- Etiqueta do responsável: cor fixa por pessoa + iniciais (.assignee-chip em app.css).
     Espera $opp e $markNonna (Portal: responsável da Nonna aparece como "Nonna · Fulano").
     Sem responsável, nada. --}}
@if($name = $opp->assigneeName($markNonna ?? false))
    <span class="assignee-chip" style="--h: {{ $opp->assigneeHue() }}" title="Responsável: {{ $name }}">
        <span class="assignee-chip-initials">{{ $opp->assigneeInitials() }}</span>
        <span class="truncate">{{ $name }}</span>
    </span>
@endif
