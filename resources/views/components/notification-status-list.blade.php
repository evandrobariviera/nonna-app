{{-- Lista de notificações internas geradas por uma entidade (Tarefa, Reunião, etc.),
     agrupadas por disparo (mesmo generated_at — NotificationService::notifyUsers() grava
     um now() só por chamada, todas as linhas do mesmo fan-out compartilham o timestamp
     exato) — mostra quem já leu/resolveu e quem ainda está pendente. --}}
@props(['notifications'])

@php
    $groups = $notifications->groupBy(fn ($n) => $n->generated_at?->toISOString() . '|' . $n->title);
@endphp

@if($groups->isNotEmpty())
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="bell" size="14" />
            Notificações geradas
        </h4>
        <div class="flex flex-col gap-3">
            @foreach($groups as $group)
                @php
                    $first = $group->first();
                    $total = $group->count();
                    $resolved = $group->where('status', 'resolvido')->count();
                @endphp
                <div class="px-3 py-2.5" style="background:var(--s2); border-radius:8px">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:var(--text)">{{ $first->title }}</p>
                            <p class="text-xs font-mono" style="color:var(--muted)">{{ $first->generated_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 flex-none">
                            <div class="h-1.5 rounded-full overflow-hidden" style="width:50px; background:var(--s3)">
                                <div class="h-full rounded-full" style="width:{{ $total > 0 ? round($resolved / $total * 100) : 0 }}%; background:var(--green)"></div>
                            </div>
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ $resolved }}/{{ $total }} resolvidos</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        @foreach($group as $n)
                            @php
                                $color = match($n->status) {
                                    'resolvido' => 'var(--green)',
                                    'lido' => 'var(--info, #2E90FA)',
                                    'descartado' => 'var(--muted)',
                                    default => 'var(--purple)',
                                };
                            @endphp
                            <div class="flex items-center gap-1.5" title="{{ $n->user?->name }} — {{ $n->statusLabel() }}">
                                <div class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0"
                                     style="background:{{ $color }}; {{ $n->status === 'novo' ? 'opacity:.55' : '' }}">
                                    {{ strtoupper(substr($n->user?->name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-xs" style="color:var(--muted)">{{ $n->user ? explode(' ', $n->user->name)[0] : '—' }}</span>
                                <span class="text-xs font-semibold" style="color:{{ $color }}">{{ $n->statusLabel() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
