{{-- "Revisar pendentes" em andamento (ApprovalReviewQueue): peça X de N,
     tracinhos de progresso, Anterior / Pular / Sair. --}}
@if(!empty($review))
<div style="background:var(--s1); border-bottom:1px solid var(--border); padding:12px 20px">
    <div style="max-width:680px; margin:0 auto; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px">
        <div style="display:flex; flex-direction:column; gap:8px">
            <span style="font-size:14px; font-weight:700">Revisando: peça {{ $review['pos'] }} de {{ $review['total'] }}</span>
            <div style="display:flex; gap:6px; flex-wrap:wrap" aria-hidden="true">
                @foreach($review['dots'] as $dot)
                    @php
                        $bg = match (true) {
                            $dot['current']                        => 'var(--purple)',
                            $dot['status'] === 'approved'          => '#16a34a',
                            $dot['status'] === 'changes_requested' => '#d97706',
                            default                                => 'var(--border2)',
                        };
                    @endphp
                    <span style="width:24px; height:6px; border-radius:99px; background:{{ $bg }}"></span>
                @endforeach
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:13px; font-weight:700">
            @if($review['prev'])
                <a href="{{ route('approval.show', $review['prev']) }}" style="padding:10px 14px; border:1px solid var(--border2); color:var(--text); text-decoration:none">← Anterior</a>
            @endif
            @if($review['next'])
                <a href="{{ route('approval.show', $review['next']) }}" style="padding:10px 14px; border:1px solid var(--border2); color:var(--text); text-decoration:none">Pular →</a>
            @endif
            <a href="{{ route('portal.approvals.review-exit') }}" style="padding:10px 6px; color:var(--purple); text-decoration:none">Sair da revisão</a>
        </div>
    </div>
</div>
@if(session('review_msg'))
    <div style="max-width:680px; margin:16px auto 0; padding:0 16px">
        <div role="status" style="background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3); color:#15803d; padding:10px 14px; font-size:13px; font-weight:600">
            {{ session('review_msg') }}
        </div>
    </div>
@endif
@endif
