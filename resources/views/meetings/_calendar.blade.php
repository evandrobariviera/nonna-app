{{-- Visão calendário (mensal) da Agenda — 100% server-rendered, navegação por link
     (mesmo padrão da paginação da lista), sem lib de calendário nova.
     Abaixo de md a grade de 7 colunas vira uma lista vertical "por dia". --}}
@php
    $monthParams = fn (\Carbon\Carbon $month) => array_merge(
        request()->except('month'),
        ['view' => 'calendario', 'month' => $month->format('Y-m')]
    );
    $todayParams = array_merge(request()->except('month'), ['view' => 'calendario']);
    $weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $monthDaysWithEvents = collect($weeks)->flatten()
        ->filter(fn ($d) => $d->month === $refMonth->month && $eventsByDay->get($d->format('Y-m-d'), collect())->isNotEmpty());
@endphp

<div class="card">
    {{-- Cabeçalho: mês + navegação --}}
    <div class="flex items-center justify-between gap-2 px-4 md:px-6 py-4" style="border-bottom:1px solid var(--border)">
        <p class="text-base font-bold" style="color:var(--text)">
            {{ ucfirst($refMonth->translatedFormat('F [de] Y')) }}
        </p>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            <a href="{{ route('meetings.index', $todayParams) }}" class="btn btn-ghost btn-xs">Hoje</a>
            <a href="{{ route('meetings.index', $monthParams($refMonth->copy()->subMonth())) }}" class="btn btn-ghost btn-xs" aria-label="Mês anterior">
                ← <span class="hidden sm:inline">Mês anterior</span>
            </a>
            <a href="{{ route('meetings.index', $monthParams($refMonth->copy()->addMonth())) }}" class="btn btn-ghost btn-xs" aria-label="Mês seguinte">
                <span class="hidden sm:inline">Mês seguinte</span> →
            </a>
        </div>
    </div>

    {{-- ── MOBILE: lista "por dia" ── --}}
    <div class="md:hidden">
        @forelse($monthDaysWithEvents as $day)
            @php $dayEvents = $eventsByDay->get($day->format('Y-m-d'), collect()); @endphp
            <div style="border-bottom:1px solid var(--border2)">
                <p class="px-4 pt-3 pb-1.5 text-xs font-mono uppercase tracking-widest {{ $day->isToday() ? 'font-bold' : '' }}"
                   style="color:{{ $day->isToday() ? 'var(--purple)' : 'var(--muted)' }}">
                    {{ $day->translatedFormat('D, d [de] M') }}{{ $day->isToday() ? ' · hoje' : '' }}
                </p>
                @foreach($dayEvents as $meeting)
                    <a href="{{ route('meetings.show', $meeting) }}"
                       class="flex items-center gap-3 px-4 py-2.5">
                        <span class="text-xs font-mono flex-shrink-0" style="color:var(--purple); width:38px">{{ $meeting->scheduled_at->format('H:i') }}</span>
                        <span class="text-sm font-semibold min-w-0 flex-1 truncate" style="color:var(--text)">{{ $meeting->title }}</span>
                        <span class="badge badge-{{ $meeting->statusColor() }} flex-shrink-0">{{ $meeting->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        @empty
            <p class="px-4 py-10 text-center text-sm" style="color:var(--muted)">
                Nenhuma reunião em {{ $refMonth->translatedFormat('F') }}.
            </p>
        @endforelse
    </div>

    {{-- ── DESKTOP: grade mensal ── --}}
    <div class="hidden md:block">
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
                    <div class="group p-2 flex flex-col gap-1" style="min-height:110px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); {{ $isToday ? 'background:rgba(100, 59, 142,.06)' : '' }}">
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
</div>
