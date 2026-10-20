{{-- Ampulheta com o nº de pendências abertas da tarefa (some com zero). Custo: 1 consulta
     por página inteira, não por cartão — ver Pendency::openCountForTask(). --}}
@props(['task'])
@php $n = \App\Models\Pendency::openCountForTask((string) $task->id); @endphp
@if($n > 0)
    <span title="{{ $n }} pendência{{ $n > 1 ? 's' : '' }} aberta{{ $n > 1 ? 's' : '' }} — esperando algo"
          {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 font-semibold']) }}
          style="color:var(--orange); font-size:11px; vertical-align:-1px; white-space:nowrap">
        <x-icon name="hourglass" size="11" />{{ $n }}
    </span>
@endif
