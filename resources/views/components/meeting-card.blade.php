{{-- Card de reunião — sempre as mesmas 4 linhas, pra lista ler como tabela:
       1. cliente (destaque)   2. título da reunião (discreto)
       3. data + motivo do atraso   4. selos Pauta/ATA
     Estado (vermelho/laranja/verde) = Meeting::cardState(). Slot $extra entra na linha 3
     (ex.: tipo e organizador na esteira do Planejamento). Atributos extras
     (data-kanban-*, x-show…) seguem pro <a> do <x-item-card>.
     Selos de pauta precisam de Meeting::withAgendaFlag() na consulta. --}}
@props(['meeting'])

@php
    [$state, $hint] = $meeting->cardState();
    $color = ['now' => 'var(--green)', 'late' => 'var(--red)', 'soon' => 'var(--orange)'][$state] ?? 'var(--blue)';
    $when  = $state === 'now' ? 'Hoje ' . $meeting->scheduled_at->format('H:i') : $meeting->scheduled_at?->format('d/m H:i');
@endphp

<x-item-card kind="reuniao" :icon="$meeting->typeIcon()" :state="$state"
             :title="$meeting->client?->displayName() ?? 'Sem cliente'"
             :href="route('meetings.show', $meeting)" {{ $attributes }}>
    <x-slot:sub>{{ $meeting->title }}</x-slot:sub>
    <x-slot:meta>
        @if($when)<span class="font-semibold" style="color:{{ $color }}">{{ $when }}</span>@endif
        @if($hint)<span class="font-semibold" style="color:{{ $color }}">{{ $hint }}</span>@endif
        {{ $extra ?? '' }}
        {{-- Selos sempre na própria linha (a última), no mesmo lugar em todo card. --}}
        <span class="flex gap-1 mt-1" style="flex-basis:100%"><x-meeting-checks :meeting="$meeting" /></span>
    </x-slot:meta>
</x-item-card>
