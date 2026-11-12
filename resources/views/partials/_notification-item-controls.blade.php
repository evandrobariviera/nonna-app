{{-- Controles da página Notificações (não aparecem no sino): lido / não lido e apagar.
     "Li a ATA" não tem nenhum dos dois — só sai com a confirmação de leitura. --}}
@if($n->canDiscard())
    @if($open && $offerRead)
        <form method="POST" action="{{ route('notifications.update-status', $n) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="lido">
            <button type="submit" class="btn btn-ghost btn-xs" title="Tira do sino sem abrir">Lido</button>
        </form>
    @elseif(! $open)
        <form method="POST" action="{{ route('notifications.update-status', $n) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="novo">
            <button type="submit" class="btn btn-ghost btn-xs" title="Volta pro sino">Não lido</button>
        </form>
    @endif
    <form method="POST" action="{{ route('notifications.destroy', $n) }}">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-ghost btn-xs" title="Apagar este aviso" aria-label="Apagar este aviso" style="color:var(--muted2)">✕</button>
    </form>
@endif
