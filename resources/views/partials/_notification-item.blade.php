{{-- Um aviso no padrão do sino (Direção A, 12/11/2026) — molde ÚNICO do sino e da página
     Notificações, pra os dois nunca saírem do padrão. $it = item do NotificationPresenter.
     $page = true na página Notificações: mostra o status de quem já saiu do sino, o texto
     inteiro e os controles (lido/não lido, apagar). No sino, só o essencial. --}}
@php
    $n = $it->n;
    $page = $page ?? false;
    $open = $n->status === 'novo';
    $times = ($n->repeat_count ?? 1) > 1 ? $n->repeat_count : null;
    $whenLabel = $page ? $n->generated_at->format('H:i') : $it->when;
    $meta = collect([$it->natureLabel, $it->client, $whenLabel])->filter()->join(' · ');
    $statusLabel = $open ? null : $n->statusLabel();
@endphp

@if($it->group === 'acao')
    <div class="bell-act ic-{{ $it->nature }} {{ $open ? '' : 'is-closed' }}">
        <span class="ic-chip"><x-icon :name="$it->icon" size="16" /></span>
        <div class="bell-body">
            <a href="{{ route('notifications.open', $n) }}" class="bell-title">{{ $n->title }}</a>
            @if($page && $n->body)
                <span class="bell-snippet is-wrap">{{ $n->body }}</span>
            @endif
            <span class="bell-meta">{{ $meta }}@if($times) · {{ $times }}×@endif @if($statusLabel)· <b class="font-semibold">{{ $statusLabel }}</b>@endif</span>
        </div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            @if($open)
                <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="resolvido">
                    <button type="submit" class="btn btn-primary btn-xs" title="{{ $n->resolveHint() }}">{{ $n->resolveLabel() }}</button>
                </form>
            @endif
            @if($page)
                @include('partials._notification-item-controls', ['n' => $n, 'open' => $open, 'offerRead' => false])
            @endif
        </div>
    </div>

@elseif($it->group === 'conversa')
    <div class="bell-row {{ $open ? '' : 'is-closed' }}">
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
                    <span class="bell-snippet {{ $page ? 'is-wrap' : '' }}">“{{ $n->body }}”</span>
                @endif
                <span class="bell-meta">{{ $meta }}@if($times) · {{ $times }} comentários @endif @if($statusLabel)· <b class="font-semibold">{{ $statusLabel }}</b>@endif</span>
            </span>
        </a>
        @if($page)
            <div class="flex items-center gap-1.5 flex-shrink-0">
                @include('partials._notification-item-controls', ['n' => $n, 'open' => $open, 'offerRead' => true])
            </div>
        @endif
    </div>

@else
    <div class="bell-row {{ $open ? '' : 'is-closed' }}">
        <a href="{{ route('notifications.open', $n) }}" class="bell-info ic-{{ $it->nature }}" title="{{ $n->body }}">
            <span class="ic-chip"><x-icon :name="$it->icon" size="12" /></span>
            <span class="bell-line" style="{{ $it->nature === 'reuniao' && $open ? '' : 'color:var(--muted2)' }}">{{ $n->title }}@if($page && $statusLabel) <b class="font-semibold">· {{ $statusLabel }}</b>@endif</span>
            <span class="bell-time">{{ $whenLabel }}</span>
        </a>
        @if($page)
            <div class="flex items-center gap-1.5 flex-shrink-0">
                @include('partials._notification-item-controls', ['n' => $n, 'open' => $open, 'offerRead' => true])
            </div>
        @endif
    </div>
@endif
