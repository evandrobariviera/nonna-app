<x-portal-layout>
    <x-slot name="title">Aprovação — {{ $round->task?->title }}</x-slot>

    @php
        $statusColors = [
            'pending'            => ['bg' => 'rgba(100, 59, 142,.08)', 'text' => 'var(--purple)', 'label' => 'Aguardando Decisão'],
            'approved'           => ['bg' => 'rgba(5,150,105,.1)',   'text' => 'var(--green)',  'label' => 'Aprovado'],
            'changes_requested'  => ['bg' => 'rgba(238, 121, 25,.08)',  'text' => 'var(--orange)', 'label' => 'Ajustes Solicitados'],
            'cancelled'          => ['bg' => 'var(--s3)',            'text' => 'var(--muted)',  'label' => 'Cancelado'],
        ];
        $sc = $statusColors[$round->status] ?? $statusColors['pending'];
    @endphp

    <div class="mb-6">
        <a href="{{ route('portal.approvals.index') }}" class="text-xs font-semibold" style="color: var(--muted)">← Aprovações</a>
    </div>

    <div class="card p-6 mb-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h1 class="text-xl font-black" style="color: var(--text)">{{ $round->task?->title }}</h1>
                <p class="text-xs mt-1" style="color: var(--muted)">Rodada #{{ $round->round_number }} · enviado em {{ $round->submitted_at?->format('d/m/Y') }}</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0"
                  style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                {{ $sc['label'] }}
            </span>
        </div>

        @if($round->notes)
            <p class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.7">{{ $round->notes }}</p>
        @endif
    </div>

    @if($round->caption)
        <div class="card p-6 mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-3" style="color: var(--muted)">Legenda</h2>
            <p class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.7">{{ $round->caption }}</p>
        </div>
    @endif

    {{-- QUEM FALTA APROVAR / QUEM JÁ APROVOU (rodada atual) --}}
    @if($round->tokens->count() > 1)
        <div class="card p-6 mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-3" style="color: var(--muted)">Aprovadores desta rodada</h2>
            <div class="flex flex-col gap-2">
                @foreach($round->tokens as $t)
                    @php
                        $tsc = $statusColors[$t->status] ?? $statusColors['pending'];
                        $tLabel = $t->status === 'approved' ? 'Aprovou' : ($t->status === 'changes_requested' ? 'Pediu ajuste' : 'Aguardando');
                    @endphp
                    <div class="flex items-center justify-between text-sm">
                        <span style="color: var(--text)">{{ explode(' ', $t->contact->name)[0] }}</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: {{ $tsc['bg'] }}; color: {{ $tsc['text'] }}">{{ $tLabel }}</span>
                    </div>
                    @if($t->status === 'changes_requested' && $t->overall_comment)
                        <p class="text-xs pl-2" style="color: var(--muted); border-left: 2px solid var(--border2); margin-left: 2px">{{ $t->overall_comment }}</p>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- HISTÓRICO DE RODADAS ANTERIORES --}}
    @php
        $otherRounds = $round->task->approvalRounds->where('id', '!=', $round->id)->sortByDesc('round_number');
    @endphp
    @if($otherRounds->isNotEmpty())
        <div class="card p-6 mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-3" style="color: var(--muted)">Histórico de Rodadas Anteriores</h2>
            <div class="flex flex-col gap-4">
                @foreach($otherRounds as $r)
                    @php $rsc = $statusColors[$r->status] ?? $statusColors['pending']; @endphp
                    <div class="{{ !$loop->last ? 'pb-4' : '' }}" style="{{ !$loop->last ? 'border-bottom: 1px solid var(--border2)' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-semibold" style="color: var(--text)">Rodada #{{ $r->round_number }}</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: {{ $rsc['bg'] }}; color: {{ $rsc['text'] }}">{{ $r->displayStatusLabel() }}</span>
                        </div>
                        @if($r->resolved_at)
                            <p class="text-xs mb-2" style="color: var(--muted)">{{ $r->resolved_at->format('d/m/Y') }}</p>
                        @endif
                        @if($r->caption)
                            <p class="text-xs mb-2 italic" style="color: var(--muted)">"{{ $r->caption }}"</p>
                        @endif
                        @foreach($r->tokens as $t)
                            @if($t->overall_comment)
                                <p class="text-xs pl-2 mb-1" style="color: var(--muted); border-left: 2px solid var(--border2); margin-left: 2px">{{ explode(' ', $t->contact->name)[0] }}: {{ $t->overall_comment }}</p>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($deliverables->isNotEmpty())
        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold uppercase tracking-wide" style="color: var(--muted)">{{ $round->status === 'approved' ? 'Arquivos Aprovados' : 'Arquivos para Aprovação' }}</h2>
                @if($round->status === 'approved')
                    <a href="{{ route('portal.materials.index') }}" class="text-xs font-semibold" style="color: var(--purple)">Ver todos aprovados →</a>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach($deliverables as $file)
                    @if($file->isImage())
                        <a href="{{ $file->url() }}" target="_blank" class="block rounded-lg overflow-hidden" style="border: 1px solid var(--border2)">
                            <img src="{{ $file->url() }}" alt="{{ $file->filename }}" class="w-full h-40 object-cover">
                            <div class="px-3 py-2 flex items-center justify-between" style="background: var(--s2)">
                                <span class="text-xs truncate" style="color: var(--text)">{{ $file->filename }}</span>
                                <span class="text-xs flex-shrink-0" style="color: var(--muted)">{{ $file->sizeForHumans() }}</span>
                            </div>
                        </a>
                    @elseif($file->isVideo())
                        <div class="rounded-lg overflow-hidden" style="border: 1px solid var(--border2)">
                            <video controls preload="metadata" playsinline class="w-full h-40" style="background:#000; object-fit:contain">
                                <source src="{{ $file->url() }}" type="{{ $file->mime_type }}">
                            </video>
                            <a href="{{ $file->url() }}" target="_blank" class="px-3 py-2 flex items-center justify-between" style="background: var(--s2); text-decoration:none">
                                <span class="text-xs truncate" style="color: var(--text)">{{ $file->filename }}</span>
                                <span class="text-xs flex-shrink-0" style="color: var(--muted)">↗ {{ $file->sizeForHumans() }}</span>
                            </a>
                        </div>
                    @else
                        <a href="{{ $file->url() }}" target="_blank"
                           class="flex items-center gap-3 p-3 rounded-lg transition-colors"
                           style="background: var(--s2)">
                            <span>{{ $file->icon() }}</span>
                            <span class="text-sm flex-1 truncate" style="color: var(--text)">{{ $file->filename }}</span>
                            <span class="text-xs flex-shrink-0" style="color: var(--muted)">{{ $file->sizeForHumans() }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @if($round->status === 'pending')
        <div class="card p-6" x-data="{ decision: null }">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-4" style="color: var(--muted)">Sua Decisão</h2>

            <form method="POST" action="{{ route('portal.approvals.decide', $round) }}">
                @csrf
                <input type="hidden" name="decision" x-model="decision">

                <div class="flex gap-3 mb-4">
                    <button type="button" @click="decision = 'approved'"
                            class="flex-1 py-3 rounded-lg text-sm font-bold transition-colors"
                            :style="decision === 'approved'
                                ? 'background: var(--green); color: #fff'
                                : 'background: var(--s2); color: var(--text)'">
                        ✓ Aprovar
                    </button>
                    <button type="button" @click="decision = 'changes_requested'"
                            class="flex-1 py-3 rounded-lg text-sm font-bold transition-colors"
                            :style="decision === 'changes_requested'
                                ? 'background: var(--orange); color: #fff'
                                : 'background: var(--s2); color: var(--text)'">
                        ✎ Solicitar Ajustes
                    </button>
                </div>

                <div x-show="decision !== null" x-cloak class="mb-4">
                    <label class="text-xs font-bold uppercase tracking-widest mb-1 block" style="color: var(--muted)">
                        Comentário <span x-show="decision === 'changes_requested'">(obrigatório)</span>
                    </label>
                    <textarea name="comment" rows="3" class="w-full rounded-lg p-3 text-sm"
                              style="background: var(--s2); color: var(--text); border: 1px solid var(--border2)"
                              :required="decision === 'changes_requested'"
                              placeholder="Descreva o que precisa ajustar..."></textarea>
                </div>

                <button type="submit" x-show="decision !== null" x-cloak
                        class="px-5 py-2.5 rounded-lg text-sm font-bold"
                        style="background: var(--purple); color: #fff">
                    Confirmar Decisão
                </button>
            </form>
        </div>
    @else
        <div class="card p-6">
            <h2 class="text-sm font-bold uppercase tracking-wide mb-3" style="color: var(--muted)">Resultado</h2>
            @if($round->status === 'cancelled')
                <p class="text-sm" style="color: var(--text)">Esta rodada de aprovação foi cancelada pelo time da Nonna.</p>
            @elseif($round->portal_decision)
                <p class="text-sm" style="color: var(--text)">
                    Decidido pelo Portal em {{ $round->portal_decided_at?->format('d/m/Y \à\s H:i') }}.
                </p>
                @if($round->portal_comment)
                    <p class="text-sm whitespace-pre-wrap mt-2" style="color: var(--text); line-height: 1.7">{{ $round->portal_comment }}</p>
                @endif
            @else
                <p class="text-sm" style="color: var(--text)">Decidido pelo link de aprovação enviado ao contato responsável.</p>
            @endif
        </div>
    @endif

</x-portal-layout>
