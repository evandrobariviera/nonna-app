<x-app-layout>
    <x-slot name="header">Central de Aprovações</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(255,140,0,.08); border:1px solid rgba(255,140,0,.25); color:var(--orange)">
            ⚠ {{ session('warning') }}
        </div>
    @endif

    {{-- ══ CARDS DE ESTATÍSTICAS ══ --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-5">
        @php
            $statCards = [
                [
                    'label' => 'Aguardando Envio',
                    'value' => $stats['awaiting_send'],
                    'color' => 'var(--muted2)',
                    'bg'    => 'var(--s2)',
                    'border'=> 'var(--border2)',
                    'filter'=> null,
                ],
                [
                    'label' => 'Aguardando Resposta',
                    'value' => $stats['pending'],
                    'color' => 'var(--purple)',
                    'bg'    => 'rgba(106,90,205,.08)',
                    'border'=> 'rgba(106,90,205,.2)',
                    'filter'=> 'pending',
                ],
                [
                    'label' => 'Ajustes Solicitados',
                    'value' => $stats['changes'],
                    'color' => 'var(--orange)',
                    'bg'    => 'rgba(255,140,0,.08)',
                    'border'=> 'rgba(255,140,0,.2)',
                    'filter'=> 'changes_requested',
                ],
                [
                    'label' => 'Aprovados Hoje',
                    'value' => $stats['approved_today'],
                    'color' => 'var(--green)',
                    'bg'    => 'rgba(34,197,94,.08)',
                    'border'=> 'rgba(34,197,94,.2)',
                    'filter'=> null,
                ],
                [
                    'label' => 'Total Aprovados',
                    'value' => $stats['approved_total'],
                    'color' => 'var(--muted2)',
                    'bg'    => 'var(--s2)',
                    'border'=> 'var(--border2)',
                    'filter'=> 'approved',
                ],
            ];
        @endphp

        @foreach($statCards as $card)
            @if($card['filter'])
                <a href="{{ route('approvals.index', ['status' => $card['filter']]) }}"
                   class="card card-body transition-all"
                   style="border-color:{{ request('status') === $card['filter'] ? $card['border'] : 'var(--border2)' }}; text-decoration:none"
                   onmouseover="this.style.borderColor='{{ $card['border'] }}'" onmouseout="this.style.borderColor='{{ request('status') === $card['filter'] ? $card['border'] : 'var(--border2)' }}'">
            @else
                <div class="card card-body">
            @endif
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">{{ $card['label'] }}</p>
                <p class="text-3xl font-bold" style="color:{{ $card['color'] }}; letter-spacing:-.03em">{{ $card['value'] }}</p>
            @if($card['filter'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    {{-- ══ FILTROS ══ --}}
    <form method="GET" action="{{ route('approvals.index') }}" class="flex gap-3 mb-5 flex-wrap items-end">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Status</label>
            <select name="status" class="px-3 py-2 text-sm focus:outline-none"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text); min-width:180px">
                <option value="">Todos os status</option>
                <option value="pending"            {{ request('status') === 'pending'            ? 'selected' : '' }}>Aguardando Resposta</option>
                <option value="changes_requested"  {{ request('status') === 'changes_requested'  ? 'selected' : '' }}>Ajustes Solicitados</option>
                <option value="approved"           {{ request('status') === 'approved'           ? 'selected' : '' }}>Aprovado</option>
                <option value="cancelled"          {{ request('status') === 'cancelled'          ? 'selected' : '' }}>Cancelado</option>
            </select>
        </div>

        @if($clients->count() > 0)
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Cliente</label>
                <select name="client_id" class="px-3 py-2 text-sm focus:outline-none"
                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text); min-width:200px">
                    <option value="">Todos os clientes</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>
                            {{ $c->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <button type="submit" class="px-4 py-2 text-sm font-semibold text-white"
            style="background:var(--purple)">Filtrar</button>

        @if(request()->hasAny(['status','client_id']))
            <a href="{{ route('approvals.index') }}" class="px-4 py-2 text-sm"
               style="color:var(--muted); border:1px solid var(--border2)">Limpar</a>
        @endif
    </form>

    {{-- ══ LISTA DE RODADAS ══ --}}
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
                                      onsubmit="return confirm('Enviar essa rodada para o cliente agora?')">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold px-3 py-1 text-white"
                                            style="background:var(--purple)">
                                        Enviar pro Cliente
                                    </button>
                                </form>
                            @endif
                            <span class="text-xs font-semibold px-2.5 py-1"
                                  style="background:{{ $notSent ? 'var(--s2)' : ($isApproved ? 'rgba(34,197,94,.12)' : ($isChanges ? 'rgba(255,140,0,.12)' : 'rgba(106,90,205,.12)')) }};
                                         color:{{ $notSent ? 'var(--muted2)' : ($isApproved ? '#22c55e' : ($isChanges ? 'var(--orange)' : 'var(--purple)')) }};
                                         border:1px solid {{ $notSent ? 'var(--border2)' : ($isApproved ? 'rgba(34,197,94,.25)' : ($isChanges ? 'rgba(255,140,0,.25)' : 'rgba(106,90,205,.25)')) }}">
                                {{ $round->displayStatusLabel() }}
                            </span>
                            <a href="{{ route('tasks.show', $round->task_id) }}"
                               class="text-xs font-semibold px-3 py-1 transition-colors"
                               style="border:1px solid var(--border2); color:var(--muted2)"
                               onmouseover="this.style.color='var(--purple)'; this.style.borderColor='rgba(106,90,205,.3)'"
                               onmouseout="this.style.color='var(--muted2)'; this.style.borderColor='var(--border2)'">
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

</x-app-layout>
