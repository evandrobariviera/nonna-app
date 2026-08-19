<x-app-layout>
    <x-slot name="header">Monitor de Trabalho</x-slot>

    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <p class="text-sm" style="color:var(--muted)">O que cada pessoa fez no dia — login e ações registradas no sistema (mudança de status, situação, etc). Não substitui o Painel de Produtividade, é o "diário" cronológico por pessoa.</p>

        <form method="GET" action="{{ route('work-monitor.index') }}" class="flex items-center gap-2 flex-wrap">
            <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"
                   onchange="this.form.submit()"
                   class="px-3 py-1.5 text-xs" style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">

            <select name="user_id" onchange="this.form.submit()"
                    class="px-3 py-1.5 text-xs" style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                <option value="">Todos da equipe</option>
                @foreach($members as $member)
                    <option value="{{ $member->id }}" @selected((string) $selectedUserId === (string) $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>

            @if($date->isToday())
                <a href="{{ route('work-monitor.index', ['date' => $date->copy()->subDay()->toDateString(), 'user_id' => $selectedUserId]) }}"
                   class="btn btn-ghost btn-xs">← Ontem</a>
            @else
                <a href="{{ route('work-monitor.index', ['date' => $date->copy()->subDay()->toDateString(), 'user_id' => $selectedUserId]) }}"
                   class="btn btn-ghost btn-xs">← Anterior</a>
                <a href="{{ route('work-monitor.index', ['date' => $date->copy()->addDay()->toDateString(), 'user_id' => $selectedUserId]) }}"
                   class="btn btn-ghost btn-xs">Próximo →</a>
                <a href="{{ route('work-monitor.index', ['user_id' => $selectedUserId]) }}"
                   class="btn btn-ghost btn-xs">Hoje</a>
            @endif
        </form>
    </div>

    @if($timelines->isEmpty())
        <div class="card card-body py-16 text-center">
            <p class="text-sm font-semibold" style="color:var(--muted)">Nenhum usuário encontrado.</p>
        </div>
    @else
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(360px, 1fr))">
            @foreach($timelines as $timeline)
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-sm font-bold" style="color:var(--text)">{{ $timeline->user->name }}</p>
                        @if($timeline->first_login)
                            <span class="text-xs font-semibold px-2 py-0.5" style="background:rgba(34,197,94,.12); color:#22c55e; border:1px solid rgba(34,197,94,.25)">
                                chegou {{ $timeline->first_login->format('H:i') }}
                            </span>
                        @else
                            <span class="text-xs font-semibold px-2 py-0.5" style="background:var(--s2); color:var(--muted); border:1px solid var(--border2)">
                                não logou
                            </span>
                        @endif
                    </div>
                    <p class="text-xs mb-4" style="color:var(--muted2)">{{ $timeline->action_count }} {{ $timeline->action_count === 1 ? 'ação registrada' : 'ações registradas' }}</p>

                    @if($timeline->events->isEmpty())
                        <p class="text-xs" style="color:var(--muted)">Nada registrado nesse dia.</p>
                    @else
                        <div class="flex flex-col" style="max-height:420px; overflow-y:auto">
                            @foreach($timeline->events as $event)
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center flex-shrink-0">
                                        <span class="h-2.5 w-2.5 rounded-full flex-shrink-0"
                                              style="{{ $event->type === 'login' ? 'background:#22c55e' : 'background:var(--purple)' }}"></span>
                                        @if(!$loop->last)
                                            <span class="flex-1" style="width:1px; min-height:10px; background:var(--border2)"></span>
                                        @endif
                                    </div>
                                    <div class="text-xs {{ !$loop->last ? 'pb-4' : '' }}">
                                        @if($event->type === 'login')
                                            <p style="color:var(--text); font-weight:500; line-height:1.4">
                                                Login no sistema
                                                @if($event->model->ip_address)
                                                    <span style="color:var(--muted2)">— {{ $event->model->ip_address }}</span>
                                                @endif
                                            </p>
                                        @else
                                            <p style="color:var(--text); font-weight:500; line-height:1.4">
                                                {{ $event->model->actionLabel() }}
                                                @if($event->model->from_label && $event->model->to_label)
                                                    <span style="color:var(--muted2)">— {{ $event->model->from_label }} → {{ $event->model->to_label }}</span>
                                                @elseif($event->model->to_label)
                                                    <span style="color:var(--muted2)">— {{ $event->model->to_label }}</span>
                                                @endif
                                            </p>
                                            @if($event->model->task)
                                                <a href="{{ route('tasks.show', $event->model->task_id) }}" class="hover:underline" style="color:var(--purple)">
                                                    {{ $event->model->task->title }}
                                                </a>
                                                @if($event->model->task->client)
                                                    <span style="color:var(--muted2)"> · {{ $event->model->task->client->displayName() }}</span>
                                                @endif
                                            @endif
                                        @endif
                                        <p class="mt-0.5" style="color:var(--muted)">{{ $event->at->format('H:i') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
