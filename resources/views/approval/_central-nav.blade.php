{{-- Caminho de volta pra Central de Aprovações (login mágico do link) — só
     aparece quando o contato pode entrar na Central (ver PortalMagicAccess). --}}
@if(!empty($centralNav))
<nav aria-label="Caminho" style="background:var(--s1); border-bottom:1px solid var(--border); padding:10px 20px">
    <div style="max-width:680px; margin:0 auto; display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px; font-weight:700">
        <a href="{{ route('portal.approvals.index') }}" style="color:var(--purple); text-decoration:none; padding:6px 0">← Central de Aprovações</a>
        @if($centralNav['group'])
            <span style="color:var(--muted)">›</span>
            <a href="{{ $centralNav['group']['url'] }}" style="color:var(--purple); text-decoration:none; padding:6px 0">{{ $centralNav['group']['title'] }}</a>
        @endif
    </div>
</nav>
@endif
