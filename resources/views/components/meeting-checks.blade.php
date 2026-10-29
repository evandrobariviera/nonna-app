{{-- Selinhos "Pauta" e "ATA" da reunião — verde com ✓ quando preenchido, cinza com ○ quando
     falta. Pra saber de relance, sem abrir a reunião. Itens citados na pauta só contam se a
     consulta usou Meeting::withAgendaFlag() (ver Meeting::hasPauta()). --}}
@props(['meeting'])

@foreach(['Pauta' => $meeting->hasPauta(), 'ATA' => $meeting->hasAta()] as $label => $ok)
    <span class="mchk {{ $ok ? 'is-ok' : '' }}" title="{{ $label }} {{ $ok ? 'preenchida' : 'ainda não preenchida' }}">
        <x-icon :name="$ok ? 'check' : 'circle'" size="10" />{{ $label }}
    </span>
@endforeach
