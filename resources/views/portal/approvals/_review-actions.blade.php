{{-- "Revisar pendentes" + "Aprovar todas" — só o que espera a resposta de
     QUEM está logado (o link dele em cada peça). Ver ApprovalReviewQueue. --}}
@if($myPending > 0)
    <div class="flex flex-wrap items-center gap-3 mt-4">
        <a href="{{ route('portal.approvals.review', $scopeArgs) }}" class="btn btn-primary btn-sm">
            Revisar pendentes ({{ $myPending }})
        </a>
        <form method="POST" action="{{ route('portal.approvals.approve-all', $scopeArgs) }}"
              @submit.prevent="if (await $store.confirmDialog.ask('Aprovar {{ $myPending === 1 ? 'a peça que espera' : 'as ' . $myPending . ' peças que esperam' }} a sua resposta, sem abrir uma por uma?')) $el.submit()">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Aprovar todas</button>
        </form>
    </div>
@endif
