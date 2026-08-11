{{-- Visão calendário (mensal) da Agenda — 100% server-rendered, navegação por link
     (mesmo padrão da paginação da lista), sem lib de calendário nova. --}}
@php
    $monthParams = fn (\Carbon\Carbon $month) => array_merge(
        request()->except('month'),
        ['view' => 'calendario', 'month' => $month->format('Y-m')]
    );
    $todayParams = array_merge(request()->except('month'), ['view' => 'calendario']);
    $weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
@endphp

<div class="card">
    {{-- Cabeçalho: mês + navegação --}}
    <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid var(--border)">
        <p class="text-base font-bold" style="color:var(--text)">
            {{ ucfirst($refMonth->translatedFormat('F [de] Y')) }}
        </p>
        <div class="flex items-center gap-2">
            <a href="{{ route('meetings.index', $todayParams) }}" class="btn btn-ghost btn-xs">Hoje</a>
            <a href="{{ route('meetings.index', $monthParams($refMonth->copy()->subMonth())) }}" class="btn btn-ghost btn-xs">← Mês anterior</a>
            <a href="{{ route('meetings.index', $monthParams($refMonth->copy()->addMonth())) }}" class="btn btn-ghost btn-xs">Mês seguinte →</a>
        </div>
    </div>

    {{-- Cabeçalho dos dias da semana --}}
    <div class="grid grid-cols-7" style="border-bottom:1px solid var(--border)">
        @foreach($weekDays as $wd)
            <div class="px-2 py-2 text-center text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                {{ $wd }}
            </div>
        @endforeach
    </div>

    {{-- Grade de semanas --}}
    <div class="grid grid-cols-7">
        @foreach($weeks as $week)
            @foreach($week as $day)
                @php
                    $dayKey = $day->format('Y-m-d');
                    $dayEvents = $eventsByDay->get($dayKey, collect());
                    $inMonth = $day->month === $refMonth->month;
                    $isToday = $day->isToday();
                @endphp
                <div class="group p-2 flex flex-col gap-1" style="min-height:110px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); {{ $isToday ? 'background:rgba(106,90,205,.06)' : '' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono {{ $isToday ? 'font-bold' : '' }}"
                              style="color:{{ $isToday ? 'var(--purple)' : ($inMonth ? 'var(--muted2)' : 'var(--muted)') }}; {{ $inMonth ? '' : 'opacity:.5' }}">
                            {{ $day->day }}
                        </span>
                        <a href="{{ route('meetings.create', ['scheduled_at' => $dayKey]) }}"
                           class="text-xs font-bold opacity-0 group-hover:opacity-100 transition-opacity"
                           style="color:var(--purple)" title="Nova reunião neste dia">
                            +
                        </a>
                    </div>

                    <div class="flex flex-col gap-1 overflow-y-auto" style="max-height:110px">
                        @foreach($dayEvents as $meeting)
                            <a href="{{ route('meetings.show', $meeting) }}"
                               @click="if(!$event.ctrlKey && !$event.metaKey){ $event.preventDefault(); $store.sidePanel.open('{{ route('meetings.preview', $meeting) }}') }"
                               class="badge badge-{{ $meeting->statusColor() }} block truncate text-left"
                               title="{{ $meeting->scheduled_at->format('H:i') }} — {{ $meeting->title }}">
                                {{ $meeting->scheduled_at->format('H:i') }} {{ $meeting->title }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
