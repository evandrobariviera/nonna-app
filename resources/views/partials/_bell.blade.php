{{-- Sino — identidade visual dos avisos (Direção A, 12/11/2026). Três blocos pelo formato:
     Pede você (card laranja + um botão), Conversas (rosto de quem falou), Para saber (linha
     discreta). Cor do ícone = do que se trata (App\Support\NotificationPresenter).
     O número laranja conta só o que pede você; o pontinho roxo avisa conversa nova. --}}
@use('App\Support\NotificationPresenter')
@php
    $bellBase = \App\Models\InternalNotification::where('user_id', auth()->id())
        ->where('status', 'novo')
        ->inBell();
    // Um GROUP BY só pros contadores (o sino roda em toda página).
    $bellCounts = ['acao' => 0, 'conversa' => 0, 'saber' => 0];
    foreach ((clone $bellBase)->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind') as $kind => $n) {
        $bellCounts[NotificationPresenter::groupOfKind($kind)] += $n;
    }
    $bellItems = NotificationPresenter::present($bellBase->orderByDesc('generated_at')->limit(40)->get())->groupBy('group');
    // 4 de cada: com mais "Pede você" as Conversas sumiam pra baixo da dobra.
    $bellLimits = ['acao' => 4, 'conversa' => 4, 'saber' => 4];
    $bellTotal = array_sum($bellCounts);
@endphp
<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" class="topbar-btn relative"
            title="Notificações{{ $bellCounts['acao'] ? ' — ' . $bellCounts['acao'] . ' pedem você' : '' }}"
            aria-label="Notificações">
        <x-icon name="bell" size="18" />
        @if($bellCounts['acao'] > 0)
            <span class="absolute top-0.5 right-0.5 flex items-center justify-center rounded-full text-white"
                  style="background:var(--orange); font-size:9px; min-width:16px; height:16px; padding:0 4px; line-height:16px; border:2px solid var(--s1)">
                {{ $bellCounts['acao'] > 99 ? '99+' : $bellCounts['acao'] }}
            </span>
        @endif
        @if($bellCounts['conversa'] > 0)
            <span class="absolute rounded-full" style="bottom:3px; right:3px; width:9px; height:9px; background:var(--purple); border:2px solid var(--s1)"
                  title="{{ $bellCounts['conversa'] }} conversa(s) nova(s)"></span>
        @endif
    </button>
    <div x-show="open" @click.outside="open = false" x-cloak
         class="absolute right-0 mt-1 z-30 w-[390px] max-md:fixed max-md:inset-x-3 max-md:top-16 max-md:w-auto overflow-hidden"
         style="background:var(--s1); border:1px solid var(--border2); border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.15)">
        <div class="px-4 py-3 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
            <span class="text-sm font-bold" style="color:var(--text)">Notificações</span>
            <a href="{{ route('notifications.index') }}" class="text-xs" style="color:var(--purple)">Ver todas</a>
        </div>
        <div style="max-height:min(520px, 70vh); overflow-y:auto">
            @if($bellTotal === 0)
                <div class="px-4 py-8 text-center">
                    <p class="text-xs" style="color:var(--muted)">Nada pedindo você agora.</p>
                </div>
            @endif

            @foreach(NotificationPresenter::GROUPS as $group => $label)
                @php $items = ($bellItems[$group] ?? collect())->take($bellLimits[$group]); $hidden = $bellCounts[$group] - $items->count(); @endphp
                @continue($bellCounts[$group] === 0)

                <div class="bell-sec is-{{ $group }} flex items-center justify-between gap-2">
                    <span>{{ $label }} · {{ $bellCounts[$group] }}</span>
                    @if($group !== 'acao')
                        <form method="POST" action="{{ route('notifications.mark-seen') }}">
                            @csrf
                            <input type="hidden" name="group" value="{{ $group }}">
                            <button type="submit" class="font-normal normal-case tracking-normal hover:underline" style="font-size:11px; color:var(--muted2)">marcar como vistos</button>
                        </form>
                    @endif
                </div>
                <div class="bell-list">
                    @foreach($items as $it)
                        @php $n = $it->n; $repeat = ($n->repeat_count ?? 1) > 1 ? ' · ' . $n->repeat_count . '×' : ''; @endphp

                        @if($group === 'acao')
                            <div class="bell-act ic-{{ $it->nature }}">
                                <span class="ic-chip"><x-icon :name="$it->icon" size="16" /></span>
                                <div class="bell-body">
                                    <a href="{{ route('notifications.open', $n) }}" class="bell-title">{{ $n->title }}</a>
                                    <span class="bell-meta">{{ collect([$it->natureLabel, $it->client, $it->when])->filter()->join(' · ') }}{{ $repeat }}</span>
                                </div>
                                <form method="POST" action="{{ route('notifications.update-status', $n) }}" class="flex-shrink-0">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="resolvido">
                                    <button type="submit" class="btn btn-primary btn-xs" title="{{ $n->resolveHint() }}">{{ $n->resolveLabel() }}</button>
                                </form>
                            </div>

                        @elseif($group === 'conversa')
                            <a href="{{ route('notifications.open', $n) }}" class="bell-conv ic-{{ $it->nature }}">
                                <span class="bell-face">
                                    @if($it->actor)
                                        <x-user-avatar :user="$it->actor" size="8" />
                                    @else
                                        <span class="ic-chip" style="border-radius:15px"><x-icon name="message-circle" size="15" /></span>
                                    @endif
                                    <span class="bell-face-tag"><x-icon :name="$it->icon" size="9" /></span>
                                </span>
                                <span class="bell-body">
                                    <span style="font-size:13px; line-height:1.35">
                                        @if($it->actorName)<b style="font-weight:600">{{ \Illuminate\Support\Str::before($it->actorName, ' ') }}</b> @endif{{ $it->headline }}
                                    </span>
                                    @if($n->body)
                                        <span class="bell-snippet">“{{ $n->body }}”</span>
                                    @endif
                                    <span class="bell-meta">{{ collect([$it->natureLabel, $it->client, $it->when])->filter()->join(' · ') }}{{ $repeat ? ' · ' . $n->repeat_count . ' comentários' : '' }}</span>
                                </span>
                            </a>

                        @else
                            <a href="{{ route('notifications.open', $n) }}" class="bell-info ic-{{ $it->nature }}" title="{{ $n->body }}">
                                <span class="ic-chip"><x-icon :name="$it->icon" size="12" /></span>
                                <span class="bell-line" style="{{ $it->nature === 'reuniao' ? '' : 'color:var(--muted2)' }}">{{ $n->title }}</span>
                                <span class="bell-time">{{ $it->when }}</span>
                            </a>
                        @endif
                    @endforeach

                    @if($hidden > 0)
                        <a href="{{ route('notifications.index') }}" class="text-xs px-2 py-1" style="color:var(--muted2)">+ {{ $hidden }} em Ver todas</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
