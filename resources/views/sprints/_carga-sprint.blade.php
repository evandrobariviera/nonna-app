<div class="mb-6">
    <h3 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
        <x-icon name="zap" size="15" />
        Carga da Sprint
    </h3>

    {{-- Avatares por executor: total + breakdown por status, reage aos filtros acima --}}
    <div class="grid gap-4 mb-5" style="grid-template-columns: repeat(4, 1fr)">
        @forelse($sprintTasksByExecutor as $group)
            <div class="card px-4 py-4 flex flex-col items-center text-center">
                @if($group['executor'])
                    <x-user-avatar :user="$group['executor']" size="16" color="var(--purple)" title="{{ $group['executor']->name }}" />
                    <p class="text-sm font-bold mt-2" style="color:var(--text)">{{ $group['executor']->name }}</p>
                @else
                    <div class="rounded-full flex items-center justify-center" style="width:64px;height:64px;background:var(--s2);color:var(--muted)">?</div>
                    <p class="text-sm font-bold mt-2" style="color:var(--muted)">Sem executor</p>
                @endif

                <span class="text-2xl font-black mt-1" style="color:var(--text)">{{ $group['total'] }}</span>
                <span class="text-xs font-mono" style="color:var(--muted)">{{ Str::plural('tarefa', $group['total']) }} na sprint</span>

                <div class="w-full h-2 rounded-full overflow-hidden flex mt-3" style="background:var(--border2)">
                    @foreach(\App\Models\Task::$statuses as $key => $meta)
                        @php $count = $group['statusCounts']->get($key, 0); @endphp
                        @if($count > 0)
                            <div style="width:{{ $count / $group['total'] * 100 }}%; background:{{ \App\Models\Task::colorHex($meta['color']) }}"
                                 title="{{ $meta['label'] }}: {{ $count }}"></div>
                        @endif
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 mt-2">
                    @foreach(\App\Models\Task::$statuses as $key => $meta)
                        @php $count = $group['statusCounts']->get($key, 0); @endphp
                        @if($count > 0)
                            <span class="text-xs font-mono flex items-center gap-1" style="color:var(--muted)">
                                <span class="rounded-full" style="width:6px;height:6px;background:{{ \App\Models\Task::colorHex($meta['color']) }}"></span>
                                {{ $count }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-xs" style="color:var(--muted)">Nenhuma tarefa pra mostrar com os filtros atuais.</p>
        @endforelse
    </div>

    {{-- Volume de tarefas por status ao longo dos dias — reconstruído a partir de
         task_status_transitions, sempre inclui concluído/cancelado (ver SprintController::sprintLoadData) --}}
    <div class="card px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">
            Volume de Tarefas por Status
        </p>

        @if($statusVolumeByDay->isEmpty() || $statusVolumeByDay->sum('total') === 0)
            <p class="text-xs" style="color:var(--muted)">Sem histórico de status ainda pra esse período.</p>
        @else
            @php $maxDayTotal = max($statusVolumeByDay->max('total'), 1); @endphp
            <div class="flex items-end gap-1.5 overflow-x-auto" style="height:170px">
                @foreach($statusVolumeByDay as $day)
                    @php
                        $activeStatuses = collect(\App\Models\Task::$statuses)->keys()
                            ->filter(fn ($k) => ($day['counts'][$k] ?? 0) > 0);
                        $topStatus = $activeStatuses->last();
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full" style="min-width:22px">
                        <span class="text-xs font-bold mb-1" style="color:var(--text)">{{ $day['total'] > 0 ? $day['total'] : '' }}</span>
                        <div class="w-full flex flex-col-reverse" style="height:120px">
                            @foreach(\App\Models\Task::$statuses as $key => $meta)
                                @php $c = $day['counts'][$key] ?? 0; @endphp
                                @if($c > 0)
                                    <div class="w-full {{ $key === $topStatus ? 'rounded-t' : '' }}"
                                         style="height:{{ max($c / $maxDayTotal * 100, 3) }}%; background:{{ \App\Models\Task::colorHex($meta['color']) }}; margin-bottom:2px"
                                         title="{{ $meta['label'] }}: {{ $c }}"></div>
                                @endif
                            @endforeach
                        </div>
                        <span class="text-xs mt-1.5 whitespace-nowrap" style="color:var(--muted2)">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-4">
                @foreach(\App\Models\Task::$statuses as $key => $meta)
                    <span class="text-xs font-mono flex items-center gap-1.5" style="color:var(--muted)">
                        <span class="rounded-full flex-shrink-0" style="width:7px;height:7px;background:{{ \App\Models\Task::colorHex($meta['color']) }}"></span>
                        {{ $meta['label'] }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>
