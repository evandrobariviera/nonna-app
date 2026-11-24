{{-- Decisão tomada pelo cliente logado no Portal (não pelo link de um aprovador):
     fica na rodada (portal_decision/portal_comment), não no token — por isso não
     aparecia na tarefa nem na Central (achado real 24/11: pedido de ajuste do
     Catálogo Mundial "sumido"). Espera: $round. --}}
@if($round->portal_decision)
    @php $isChange = $round->portal_decision === 'changes_requested'; @endphp
    <div class="px-3 py-2.5"
         style="background:{{ $isChange ? 'rgba(238, 121, 25,.05)' : 'rgba(34,197,94,.05)' }}; border-left:3px solid {{ $isChange ? 'var(--orange)' : '#22c55e' }}">
        <p class="text-xs font-semibold mb-1.5 flex flex-wrap items-center gap-x-2" style="color:{{ $isChange ? 'var(--orange)' : '#16a34a' }}">
            <span>{{ $isChange ? '✎ Ajuste pedido pelo Portal' : '✓ Aprovado pelo Portal' }}</span>
            <span style="color:var(--muted); font-weight:500">
                {{ $round->portalDecidedByContact?->name ?? 'Cliente' }}@if($round->portal_decided_at) · {{ $round->portal_decided_at->format('d/m/Y H:i') }}@endif
            </span>
        </p>
        @if(filled($round->portal_comment))
            <p class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.65">{{ $round->portal_comment }}</p>
        @else
            <p class="text-xs" style="color:var(--muted)">Sem comentário.</p>
        @endif
    </div>
@endif
