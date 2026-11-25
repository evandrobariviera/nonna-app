{{-- Trava de custo do Google (GoogleUsage + CommerceProspectingController::paidGate).
     Aparece quando a coleta pedida passa da franquia grátis do mês: diz quantas seriam pagas
     e o custo estimado; só admin/dono confirma (manda de novo com aceito_pago=1). --}}
@if($gp = session('google_paid'))
    <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.35); border-radius:var(--radius)">
        <p class="font-semibold flex items-center gap-1.5" style="color:var(--text)"><x-icon name="circle-dollar-sign" size="15" style="color:var(--orange)" /> Essa coleta passa da franquia grátis do Google</p>
        <p class="mt-1" style="color:var(--muted2)">
            Você pediu {{ $gp['n'] }} coleta(s). Restam <b>{{ $gp['free_left'] }}</b> grátis neste mês,
            então <b>{{ $gp['paid'] }}</b> seriam pagas: cerca de <b>US$ {{ number_format($gp['usd'], 2, ',', '.') }}</b> na conta do Google.
            @unless($gp['can']) Só admin ou dono da organização pode liberar. @endunless
        </p>
        <div class="flex gap-2 mt-2.5 flex-wrap">
            @if($gp['can'])
                <form method="POST" action="{{ $gp['url'] }}">
                    @csrf
                    <input type="hidden" name="aceito_pago" value="1">
                    @foreach($gp['hidden'] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <button type="submit" class="btn btn-primary btn-xs">Coletar mesmo assim (uso pago)</button>
                </form>
            @endif
            <span class="text-xs self-center" style="color:var(--muted)">Ou espere o mês virar: a franquia volta no dia 1.</span>
        </div>
    </div>
@endif
