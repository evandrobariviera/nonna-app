{{-- Fragmento reaproveitado no load inicial (approvals.index) e na busca dinâmica via
     AJAX (ApprovalDashboardController::results(), fetch disparado por live-filter.js).
     Quadros e Lista ficam juntos aqui porque client_id filtra os dois ao mesmo tempo
     (status só filtra a Lista) — trocar de aba é só um toggle Alpine, sem novo fetch. --}}

{{-- ══ QUADROS ══ --}}
<div x-show="tab === 'board'" x-cloak>
    @php
        $boardColumns = [
            ['key' => 'awaiting_send',     'label' => '📤 Aguardando Envio',     'color' => 'var(--muted2)'],
            ['key' => 'pending',           'label' => '⏳ Aguardando Resposta',   'color' => 'var(--purple)'],
            ['key' => 'changes_requested', 'label' => '✎ Ajustes Solicitados',   'color' => 'var(--orange)'],
            ['key' => 'approved',          'label' => '✓ Aprovado',              'color' => 'var(--green)'],
        ];
    @endphp
    <div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4, 1fr)">
        @foreach($boardColumns as $col)
            @php $colRounds = $board[$col['key']]; @endphp
            <div class="card px-4 py-4">
                <h4 class="text-xs font-bold mb-3" style="color:var(--text)">
                    {{ $col['label'] }} ({{ $colRounds->count() }})
                </h4>
                @if($colRounds->isEmpty())
                    <p class="text-xs" style="color:var(--muted)">Nada por aqui.</p>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach($colRounds as $round)
                            <div class="block px-3 py-2" style="background:var(--s2); border-left:2px solid {{ $col['color'] }}">
                                <a href="{{ route('tasks.show', $round->task_id) }}" class="hover:underline">
                                    <p class="text-xs font-semibold leading-snug" style="color:var(--text)">
                                        {{ $round->task?->title ?? '—' }}
                                    </p>
                                </a>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="text-xs font-mono" style="color:var(--muted)">{{ $round->task?->client?->company_name ?? '—' }}</span>
                                    <span class="text-xs font-mono" style="color:var(--muted)">rodada {{ $round->round_number }}</span>
                                </div>
                                @if($col['key'] === 'awaiting_send')
                                    <form method="POST" action="{{ route('approvals.send', $round) }}"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Enviar essa rodada para o cliente agora?')) $el.submit()" class="mt-2">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold px-2.5 py-1 text-white"
                                                style="background:var(--purple)">
                                            Enviar pro Cliente
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

{{-- ══ LISTA ══ --}}
<div x-show="tab === 'list'" x-cloak>

    @if($rounds->isEmpty())
        <div class="card card-body py-16 text-center">
            <p class="text-3xl mb-3" style="opacity:.3">✓</p>
            <p class="text-sm font-semibold" style="color:var(--muted)">Nenhuma rodada encontrada para os filtros aplicados.</p>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach($rounds as $round)
                @php
                    $isPending  = $round->status === 'pending';
                    $isChanges  = $round->status === 'changes_requested';
                    $isApproved = $round->status === 'approved';
                    $notSent    = $isPending && !$round->sent_at;
                    $total      = $round->tokens->count();
                    $responded  = $round->tokens->whereNotNull('reviewed_at')->count();
                    $hasChange  = $round->tokens->contains('status', 'changes_requested');
                @endphp

                <div class="card transition-all"
                     style="border-left:3px solid {{ $notSent ? 'var(--border2)' : ($isChanges ? 'var(--orange)' : ($isApproved ? 'var(--green)' : 'var(--purple)')) }}">
                    <div class="flex items-start justify-between gap-4 px-5 py-4 flex-wrap">

                        {{-- Lado esquerdo: cliente + tarefa + meta --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-xs font-semibold uppercase tracking-widest"
                                      style="color:var(--purple)">
                                    {{ $round->task?->client?->company_name ?? '—' }}
                                </span>
                                <span style="color:var(--border2)">·</span>
                                <span class="text-xs" style="color:var(--muted)">Rodada #{{ $round->round_number }}</span>
                                <span style="color:var(--border2)">·</span>
                                <span class="text-xs" style="color:var(--muted)">
                                    {{ $round->submitted_at->format('d/m/Y H:i') }}
                                </span>
                                @if($round->submitted_at->diffInHours() < 24)
                                    <span class="text-xs" style="color:var(--muted2)">
                                        ({{ $round->submitted_at->diffForHumans() }})
                                    </span>
                                @endif
                            </div>

                            <a href="{{ route('tasks.show', $round->task_id) }}"
                               class="text-base font-semibold leading-snug hover:underline"
                               style="color:var(--text)">
                                {{ $round->task?->title ?? '—' }}
                            </a>

                            {{-- Aprovadores --}}
                            @if($total > 0)
                                <div class="flex items-center gap-3 mt-2.5 flex-wrap">
                                    @foreach($round->tokens as $token)
                                        <div class="flex items-center gap-1.5">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0"
                                                 style="background:var(--grad); {{ !$token->reviewed_at ? 'opacity:.4' : '' }}">
                                                {{ strtoupper(substr($token->contact->name, 0, 1)) }}
                                            </div>
                                            <span class="text-xs" style="color:var(--muted)">{{ explode(' ', $token->contact->name)[0] }}</span>
                                            <span class="text-xs font-semibold"
                                                  style="color:{{ $token->status === 'approved' ? 'var(--green)' : ($token->status === 'changes_requested' ? 'var(--orange)' : 'var(--muted)') }}">
                                                {{ $token->status === 'approved' ? '✓' : ($token->status === 'changes_requested' ? '✎' : '·') }}
                                            </span>
                                            @if($round->sent_at && $token->isPending())
                                                <form method="POST" action="{{ route('approvals.resend-token', $token) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs" style="color:var(--muted)"
                                                            title="Reenviar só pra {{ $token->contact->name }}"
                                                            onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                                        ↻
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach

                                    {{-- Barra de progresso --}}
                                    <div class="flex items-center gap-1.5 ml-1">
                                        <div class="h-1.5 rounded-full overflow-hidden" style="width:60px; background:var(--s3)">
                                            <div class="h-full rounded-full transition-all"
                                                 style="width:{{ $total > 0 ? round($responded/$total*100) : 0 }}%;
                                                        background:{{ $isApproved ? 'var(--green)' : ($isChanges ? 'var(--orange)' : 'var(--purple)') }}">
                                            </div>
                                        </div>
                                        <span class="text-xs" style="color:var(--muted)">{{ $responded }}/{{ $total }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Lado direito: status + ação --}}
                        <div class="flex items-start gap-3 flex-shrink-0">
                            @if($notSent)
                                <form method="POST" action="{{ route('approvals.send', $round) }}"
                                      @submit.prevent="if (await $store.confirmDialog.ask('Enviar essa rodada para o cliente agora?')) $el.submit()">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold px-3 py-1 text-white"
                                            style="background:var(--purple)">
                                        Enviar pro Cliente
                                    </button>
                                </form>
                            @elseif($isPending && $round->sent_at)
                                <form method="POST" action="{{ route('approvals.resend', $round) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold px-3 py-1"
                                            style="color:var(--purple); border:1px solid var(--purple)"
                                            title="Reenviar só pra quem ainda não respondeu">
                                        ↻ Reenviar
                                    </button>
                                </form>
                            @endif
                            <span class="text-xs font-semibold px-2.5 py-1"
                                  style="background:{{ $notSent ? 'var(--s2)' : ($isApproved ? 'rgba(34,197,94,.12)' : ($isChanges ? 'rgba(255,140,0,.12)' : 'rgba(106,90,205,.12)')) }};
                                         color:{{ $notSent ? 'var(--muted2)' : ($isApproved ? '#22c55e' : ($isChanges ? 'var(--orange)' : 'var(--purple)')) }};
                                         border:1px solid {{ $notSent ? 'var(--border2)' : ($isApproved ? 'rgba(34,197,94,.25)' : ($isChanges ? 'rgba(255,140,0,.25)' : 'rgba(106,90,205,.25)')) }}">
                                {{ $round->displayStatusLabel() }}
                            </span>
                            <a href="{{ route('tasks.show', $round->task_id) }}" class="btn btn-ghost btn-xs">
                                Ver Tarefa →
                            </a>
                        </div>
                    </div>

                    {{-- Feedback expandido quando há ajustes solicitados --}}
                    @if($isChanges)
                        @foreach($round->tokens->where('status', 'changes_requested') as $token)
                            @if($token->overall_comment || $token->feedbacks->where('status','changes_requested')->count() > 0)
                                <div class="px-5 pb-4 pt-0"
                                     x-data="{ open: false }">
                                    <button type="button" @click="open = !open"
                                        class="flex items-center gap-2 text-xs font-semibold transition-colors"
                                        style="color:var(--orange)"
                                        onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                                        <span x-text="open ? '▴ Ocultar ajustes' : '▾ Ver ajustes de {{ addslashes(explode(' ', $token->contact->name)[0]) }}'"></span>
                                    </button>

                                    <div x-show="open" x-cloak class="mt-3 flex flex-col gap-2">
                                        @if($token->overall_comment)
                                            <div class="px-3 py-2.5"
                                                 style="background:rgba(255,140,0,.04); border-left:3px solid var(--orange)">
                                                <p class="text-xs font-semibold uppercase tracking-widest mb-1"
                                                   style="color:var(--muted); letter-spacing:.07em">Comentário Geral</p>
                                                <p class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.6">{{ $token->overall_comment }}</p>
                                            </div>
                                        @endif

                                        @foreach($token->feedbacks->where('status','changes_requested') as $fb)
                                            <div class="flex gap-2.5 px-3 py-2"
                                                 style="background:rgba(255,140,0,.03); border:1px solid rgba(255,140,0,.15)">
                                                <span class="flex-shrink-0 mt-0.5">{{ $fb->attachment?->icon() ?? '📎' }}</span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-semibold mb-0.5" style="color:var(--text)">
                                                        {{ $fb->attachment?->filename ?? '—' }}
                                                    </p>
                                                    @if($fb->comment)
                                                        <p class="text-sm whitespace-pre-wrap" style="color:var(--muted2); line-height:1.55">{{ $fb->comment }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Paginação --}}
        @if($rounds->hasPages())
            <div class="mt-6">
                {{ $rounds->links() }}
            </div>
        @endif
    @endif

</div>{{-- /x-show list --}}
