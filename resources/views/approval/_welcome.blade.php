{{-- Boas-vindas à Central de Aprovações — aparece pra quem entra pelo link de
     aprovação, até clicar "Entendi" (lembrado no navegador; trocar a chave
     "v1" faz aparecer de novo pra todo mundo). Usado na página do link e na
     Central do Portal. --}}
<div x-data="{ show: false }"
     x-init="try { show = !localStorage.getItem('nonna_central_welcome_v1') } catch (e) { show = true }"
     x-show="show" x-cloak
     style="max-width:{{ $maxWidth ?? '680px' }}; margin:{{ $margin ?? '20px auto 0' }}; padding:{{ $padding ?? '0 16px' }}">
    <div role="status" style="background:var(--s1); border:1px solid var(--border); border-top:3px solid var(--purple); padding:20px 22px">
        <p style="font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--orange); margin:0 0 6px">Novidade</p>
        <h2 style="font-size:18px; font-weight:800; margin:0 0 8px; color:var(--text)">Bem-vindo à nova Central de Aprovações da Nonna</h2>
        <p style="font-size:14px; line-height:1.6; color:var(--muted2); margin:0 0 12px">
            Agora tudo o que produzimos pra você fica num só lugar.
            {{ ($onCentral ?? false) ? 'Aqui você pode:' : 'Além de aprovar esta peça, você pode:' }}
        </p>
        <ul style="font-size:14px; line-height:1.7; color:var(--text); margin:0 0 16px; padding-left:18px">
            <li>ver o <strong>projeto completo</strong> e cada peça dele, com o que já foi aprovado;</li>
            <li>usar <strong>Revisar pendentes</strong> pra responder uma peça atrás da outra, sem precisar abrir link por link;</li>
            <li>entrar direto pelo link que você recebe no WhatsApp, <strong>sem senha</strong>.</li>
        </ul>
        @unless($onCentral ?? false)
            <p style="font-size:13px; color:var(--muted2); margin:0 0 16px">
                É só clicar em <strong>“← Central de Aprovações”</strong>, no topo da página.
            </p>
        @endunless
        <button type="button"
                @click="show = false; try { localStorage.setItem('nonna_central_welcome_v1', '1') } catch (e) {}"
                style="padding:10px 20px; background:var(--purple); color:#fff; border:none; font-size:14px; font-weight:700; cursor:pointer">
            Entendi
        </button>
    </div>
</div>
