<div class="grid gap-4" style="grid-template-columns: repeat(4, 1fr)">
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
        <p class="text-xs" style="color:var(--muted)">Nenhuma tarefa na sprint atual.</p>
    @endforelse
</div>
