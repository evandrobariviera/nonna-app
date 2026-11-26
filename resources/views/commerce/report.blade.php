{{-- Raio-x público da prospecção (CommerceReportController / ProspectReport).
     Desenho: tela 5 do protótipo (.claude/docs/prototipos/central-comercio.html), com animações
     (anel da nota, barras, blocos que aparecem ao rolar) — é o primeiro contato da agência.
     Concorrentes só com a nota (nunca o nome). Dados do Google com atribuição "Google Maps".
     Sem dependência externa: CSS e JS daqui mesmo; respeita "reduzir animações" do aparelho. --}}
@php
    $orgName = $org?->name ?: 'Nonna Agência Digital';
    $logo = $org?->logoUrl();
    $fmt = fn ($n) => number_format((float) $n, 1, ',', '');
    $who = $r['type'] ?: 'o seu segmento';
    $tone = fn ($s) => $s === null ? '#cfc6dc' : ($s >= 70 ? '#3DDC97' : ($s >= 45 ? '#FFC145' : '#FF8A3D'));
    $barTone = fn ($s) => $s >= 70 ? '#16a34a' : ($s >= 45 ? '#e0a100' : '#EE7919');
    $showCta = !$optedOut && !session('opted_out');
    $circ = 2 * M_PI * 52;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#643B8E">
    <title>Raio-x de presença digital · {{ $r['name'] }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet"/>
    <style>
        :root { --purple:#643B8E; --purple2:#8E5BC0; --orange:#EE7919; --text:#1d1b22; --muted:#6b6675; --line:#ece9f1; --bg:#f4f1f8; --card:#fff; --green:#16a34a; --wa:#25D366; }
        * { box-sizing:border-box; }
        html { -webkit-text-size-adjust:100%; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Outfit', ui-sans-serif, system-ui, sans-serif; font-size:15px; line-height:1.5; }
        .wrap { max-width:520px; margin:0 auto; padding-bottom:{{ $showCta && $whatsapp ? '96px' : '32px' }}; }

        /* Topo */
        .hero { position:relative; overflow:hidden; background:linear-gradient(155deg, #4e2b73 0%, var(--purple) 45%, var(--purple2) 100%); color:#fff; padding:22px 20px 26px; }
        .hero::before, .hero::after { content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,.07); }
        .hero::before { width:260px; height:260px; right:-90px; top:-110px; }
        .hero::after { width:180px; height:180px; left:-70px; bottom:-90px; background:rgba(238,121,25,.16); }
        .brand { position:relative; display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; }
        .brand .logo { background:#fff; border-radius:10px; padding:5px 10px; display:inline-flex; align-items:center; }
        .brand .logo img { height:18px; display:block; }
        .brand .logo span { color:var(--purple); font-weight:800; font-size:13px; }
        .brand .date { font-size:11px; opacity:.8; }
        .eyebrow { position:relative; font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; opacity:.85; margin:0 0 6px; }
        .hero h1 { position:relative; font-size:25px; font-weight:800; margin:0 0 4px; line-height:1.15; }
        .hero .sub { position:relative; margin:0; opacity:.92; font-size:14px; }
        .gauge { position:relative; display:flex; align-items:center; gap:16px; margin-top:20px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16); border-radius:18px; padding:14px; backdrop-filter:blur(4px); }
        .ring { position:relative; width:124px; height:124px; flex:none; }
        .ring svg { transform:rotate(-90deg); display:block; }
        .ring .track { stroke:rgba(255,255,255,.18); }
        .ring .val { transition:stroke-dashoffset 1.6s cubic-bezier(.2,.8,.2,1); filter:drop-shadow(0 0 6px rgba(255,255,255,.25)); }
        .ring .num { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .ring .num b { font-size:40px; font-weight:800; line-height:1; }
        .ring .num small { font-size:11px; opacity:.85; }
        .gauge p { margin:0; font-size:14px; }
        .gauge .pos { display:inline-block; margin-top:8px; background:rgba(255,255,255,.18); border-radius:999px; padding:3px 10px; font-size:12px; font-weight:600; }

        /* Cartões */
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:18px; margin:14px 14px 0; box-shadow:0 1px 2px rgba(40,20,60,.04); }
        .card h2 { font-size:16px; font-weight:700; margin:0 0 12px; }
        .hint { color:var(--muted); font-size:13px; margin:10px 0 0; }
        .bars .item { margin:10px 0; }
        .bars .lbl { display:flex; justify-content:space-between; font-size:14px; margin-bottom:5px; }
        .bars .lbl b { font-weight:700; }
        .bars .me .lbl { color:var(--orange); font-weight:700; }
        .track2 { height:10px; background:var(--line); border-radius:999px; overflow:hidden; }
        .fill { height:100%; width:0; border-radius:999px; transition:width 1.2s cubic-bezier(.2,.8,.2,1); }
        .me .fill { background:linear-gradient(90deg, #f5a05a, var(--orange)) !important; box-shadow:0 0 0 2px rgba(238,121,25,.15); }

        /* Se você resolver */
        .boost { background:linear-gradient(135deg, #fff7ef 0%, #fff 60%); border-color:#fbd9bb; }
        .boost .cmp { display:flex; align-items:center; justify-content:space-around; gap:8px; margin:6px 0 4px; }
        .boost .cmp div { text-align:center; }
        .boost .cmp b { display:block; font-size:38px; font-weight:800; line-height:1.05; }
        .boost .cmp .now b { color:#9a92a6; }
        .boost .cmp .then b { color:var(--orange); }
        .boost .cmp small { color:var(--muted); font-size:12px; }
        .boost .arrow { font-size:26px; color:var(--orange); animation:nudge 1.6s ease-in-out infinite; }
        @keyframes nudge { 0%,100% { transform:translateX(0) } 50% { transform:translateX(5px) } }

        /* Pontos */
        .pt { display:flex; gap:12px; padding:12px 0; border-top:1px solid var(--line); }
        .pt:first-of-type { border-top:0; padding-top:2px; }
        .pt .n { flex:none; width:26px; height:26px; border-radius:50%; background:rgba(238,121,25,.14); color:var(--orange); font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; }
        .pt b { display:block; font-size:15px; }
        .pt p { margin:2px 0 0; color:var(--muted); font-size:14px; }
        .fix { margin-top:8px; background:#faf8fc; border-radius:10px; padding:9px 11px; font-size:13px; color:#3f3a48; }
        .tag { display:inline-block; font-size:11px; font-weight:700; border-radius:999px; padding:2px 8px; margin-bottom:4px; }
        .tag.voce { background:rgba(22,163,74,.12); color:var(--green); }
        .tag.nos { background:rgba(100,59,142,.12); color:var(--purple); }

        /* Apresentação */
        .intro { margin-top:-18px; position:relative; z-index:2; border:0; box-shadow:0 10px 30px rgba(60,30,90,.12); }
        .intro p { margin:0 0 10px; font-size:15px; color:#3f3a48; }
        .intro .hello { font-size:18px; font-weight:800; color:var(--text); margin-bottom:6px; }
        .intro .sign { margin:4px 0 0; font-size:12px; font-weight:600; color:var(--purple); letter-spacing:.02em; }

        /* Anúncios */
        .adsbox { border:1px solid #f3c9a2; background:linear-gradient(160deg, #fff 0%, #fff8f1 100%); position:relative; overflow:hidden; }
        .adsbox::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; background:linear-gradient(180deg, var(--orange), #f7b267); }
        .kicker { margin:0 0 4px; font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--orange); }
        .adsbox h2 { font-size:19px; line-height:1.25; margin-bottom:6px; }
        .lead { margin:0; color:#4a4453; font-size:14px; }
        .plat { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:14px; }
        .plat div { background:#fff; border:1px solid var(--line); border-radius:12px; padding:10px; }
        .plat span { display:block; font-size:12px; color:var(--muted); }
        .plat em { font-style:normal; font-weight:700; font-size:13px; }
        .plat em.yes { color:var(--green); } .plat em.no { color:#d9480f; } .plat em.na { color:var(--muted); }
        .rivals { margin-top:14px; }
        .rivals p { margin:8px 0 0; font-size:14px; }
        .dots { display:flex; flex-wrap:wrap; gap:6px; }
        .dots i { width:16px; height:16px; border-radius:50%; background:#e7e1ee; transform:scale(.4); opacity:0; transition:transform .5s cubic-bezier(.3,1.6,.5,1), opacity .4s ease, background .4s ease; }
        .in .dots i { transform:scale(1); opacity:1; }
        .in .dots i.on { background:var(--orange); box-shadow:0 0 0 3px rgba(238,121,25,.15); }
        .ready { margin-top:12px; }

        .stars { position:relative; display:inline-block; font-size:22px; letter-spacing:2px; line-height:1; color:#e5e0ec; }
        .stars i { position:absolute; left:0; top:0; overflow:hidden; white-space:nowrap; color:#f5b301; font-style:normal; width:0; transition:width 1.2s ease; }
        .ok { display:flex; gap:8px; align-items:flex-start; font-size:14px; padding:4px 0; }
        .ok i { color:var(--green); font-style:normal; font-weight:800; }

        .cta { background:linear-gradient(140deg, var(--purple) 0%, var(--purple2) 100%); color:#fff; border:0; }
        .cta h2 { font-size:18px; }
        .cta p { margin:0; opacity:.95; }
        .wa { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:14px; background:var(--wa); color:#fff; text-decoration:none; font-weight:700; font-size:16px; border-radius:12px; padding:13px 16px; box-shadow:0 6px 18px rgba(37,211,102,.35); }
        .wa svg { width:20px; height:20px; }
        .sticky { position:fixed; left:0; right:0; bottom:0; z-index:20; padding:10px 14px calc(10px + env(safe-area-inset-bottom)); background:rgba(255,255,255,.92); backdrop-filter:blur(8px); border-top:1px solid var(--line); transform:translateY(110%); transition:transform .35s ease; }
        .sticky.on { transform:translateY(0); }
        .sticky .wa { max-width:492px; margin:0 auto; }

        .foot { margin:18px 14px 0; font-size:12px; color:var(--muted); }
        .foot a, .linkbtn { color:var(--muted); }
        .linkbtn { background:none; border:0; padding:0; text-decoration:underline; font:inherit; cursor:pointer; }
        .banner { background:#fff7ed; border-bottom:1px solid #fed7aa; color:#9a3412; font-size:13px; padding:8px 16px; text-align:center; }

        /* Aparecer ao rolar */
        .reveal { opacity:0; transform:translateY(18px); transition:opacity .6s ease, transform .6s ease; }
        .reveal.in { opacity:1; transform:none; }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity:1; transform:none; transition:none; }
            .fill, .ring .val, .stars i, .dots i { transition:none; }
            .boost .arrow { animation:none; }
        }
    </style>
</head>
<body>
    @if($preview)
        <div class="banner">Pré-visualização da equipe: esta visita (e o clique no WhatsApp) não contam.</div>
    @endif

    <main class="wrap">
        <section class="hero">
            <div class="brand">
                <span class="logo">@if($logo)<img src="{{ $logo }}" alt="{{ $orgName }}">@else<span>{{ $orgName }}</span>@endif</span>
                <span class="date">Análise de {{ $r['date'] }}</span>
            </div>
            <p class="eyebrow">Raio-x de presença digital</p>
            <h1>{{ $r['name'] }}</h1>
            <p class="sub">Como você aparece para quem procura {{ $who }}{{ $r['city'] ? ' em ' . $r['city'] : '' }}</p>

            @if($r['score'] !== null)
                <div class="gauge">
                    <div class="ring" aria-label="Nota {{ $r['score'] }} de 100">
                        <svg width="124" height="124" viewBox="0 0 124 124">
                            <circle class="track" cx="62" cy="62" r="52" fill="none" stroke-width="11"/>
                            <circle class="val" cx="62" cy="62" r="52" fill="none" stroke="{{ $tone($r['score']) }}" stroke-width="11" stroke-linecap="round"
                                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ }}" data-offset="{{ $circ * (1 - $r['score'] / 100) }}"/>
                        </svg>
                        <div class="num"><b data-count="{{ $r['score'] }}">{{ $r['score'] }}</b><small>de 100</small></div>
                    </div>
                    <div>
                        @if($r['position'] === 1 && $r['total'] > 1)
                            <p>A mais bem colocada entre as {{ $r['total'] }} da cidade. Dá para abrir ainda mais vantagem.</p>
                        @elseif($r['topRange'])
                            <p>Os mais bem colocados da cidade têm entre <b>{{ $r['topRange'][0] }}</b> e <b>{{ $r['topRange'][1] }}</b>.</p>
                        @else
                            <p>Sua nota de presença digital no Google e no site.</p>
                        @endif
                        @if($r['position'] && $r['total'] > 1)<span class="pos">{{ $r['position'] }}º de {{ $r['total'] }} na cidade</span>@endif
                    </div>
                </div>
            @endif
        </section>

        @if(session('opted_out') || $optedOut)
            <div class="card">
                <h2>Pronto, você não vai mais receber mensagens nossas.</h2>
                <p class="hint" style="margin:0">Se mudar de ideia, é só responder a mensagem em que veio este link.</p>
            </div>
        @endif

        {{-- Apresentação: por que a empresa está recebendo isto (primeiro contato). --}}
        @unless($optedOut || session('opted_out'))
            <div class="card intro reveal">
                <p class="hello">Olá! 👋</p>
                <p>A <b>{{ $orgName }}</b> fez uma análise da sua presença digital: como <b>{{ $r['name'] }}</b> aparece no Google, no site e nos anúncios, em comparação com outras empresas do seu segmento{{ $r['city'] ? ' em ' . $r['city'] : '' }}.</p>
                <p>Preparamos este raio-x para mostrar, de forma simples, <b>onde estão as oportunidades de atrair mais clientes</b>. Algumas você mesmo resolve em minutos; outras, a gente pode fazer por você.</p>
                <p class="sign">Sem custo e sem compromisso · leitura de 2 minutos</p>
            </div>
        @endunless

        {{-- Anúncios — carro-chefe da agência. Fora da nota; só afirma o que a equipe conferiu. --}}
        @php
            $ads = $r['ads'];
            $pill = fn ($v) => match ($v) {
                'yes'   => ['Anuncia', 'yes'],
                'no'    => ['Não encontramos anúncios', 'no'],
                default => ['Não conferido', 'na'],
            };
            $where = $r['type'] ? $r['type'] : 'o que você oferece';
        @endphp
        <div class="card adsbox reveal">
            <p class="kicker">Anúncios no Google e no Instagram</p>
            @if($ads['case'] === 'none')
                <h2>Não encontramos anúncios seus</h2>
                <p class="lead">Pesquisamos na Biblioteca de Anúncios da Meta (Instagram e Facebook) e na Central de Transparência do Google.</p>
            @elseif($ads['case'] === 'no_tracking')
                <h2>Você anuncia, mas não está medindo o resultado</h2>
                <p class="lead">O anúncio está no ar, mas o site não tem como contar quantos clientes vieram dele. É como pagar a propaganda sem saber se o telefone tocou por causa dela.</p>
            @elseif($ads['case'] === 'measured')
                <h2>Você já anuncia e mede. Ótimo começo.</h2>
                <p class="lead">Dá para ir além: campanhas por bairro e horário, público de quem já visitou o site e relatório de quanto custou cada cliente.</p>
            @else
                <h2>Seu site está pronto para anunciar?</h2>
                <p class="lead">Para anunciar e saber quantos clientes vieram de cada real investido, o site precisa de três medições.</p>
            @endif

            @if($ads['case'] !== 'unchecked')
                <div class="plat">
                    @foreach(['google' => 'Google', 'meta' => 'Instagram e Facebook'] as $k => $label)
                        @php [$txt, $cls] = $pill($ads[$k]); @endphp
                        <div><span>{{ $label }}</span><em class="{{ $cls }}">{{ $txt }}</em></div>
                    @endforeach
                </div>
            @endif

            @if($ads['case'] === 'none' && ($ads['rivals'] > 0 || $ads['rivalsChecked'] >= 3))
                <div class="rivals">
                    <div class="dots" aria-hidden="true">
                        @for($i = 0; $i < $ads['rivalsChecked']; $i++)<i class="{{ $i < $ads['rivals'] ? 'on' : '' }}" style="transition-delay:{{ $i * 90 }}ms"></i>@endfor
                    </div>
                    @if($ads['rivals'] > 0)
                        <p><b>{{ $ads['rivals'] }} {{ $ads['rivals'] === 1 ? 'concorrente' : 'concorrentes' }}</b> de {{ $r['city'] ?: 'sua cidade' }} {{ $ads['rivals'] === 1 ? 'anuncia' : 'anunciam' }} (de {{ $ads['rivalsChecked'] }} que conferimos). Quem procura {{ $where }} no Google ou rola o Instagram vê {{ $ads['rivals'] === 1 ? 'ela' : 'elas' }} primeiro.</p>
                    @else
                        <p>Nenhuma das {{ $ads['rivalsChecked'] }} concorrentes que conferimos em {{ $r['city'] ?: 'sua cidade' }} anuncia. <b>Quem começar primeiro sai na frente.</b></p>
                    @endif
                </div>
            @endif

            @if($ads['ready'])
                <div class="ready">
                    @foreach($ads['ready'] as $it)
                        <div class="ok"><i style="color:{{ $it['ok'] ? 'var(--green)' : '#d9480f' }}">{{ $it['ok'] ? '✓' : '✗' }}</i><span>{{ $it['label'] }}</span></div>
                    @endforeach
                </div>
            @elseif(!$ads['hasSite'])
                <p class="hint">Sem um site no ar, o anúncio não tem para onde levar a pessoa nem como medir o resultado.</p>
            @endif

            @if($ads['case'] !== 'measured')
                <div class="fix" style="margin-top:12px"><span class="tag nos">A gente resolve para você</span><br>
                    Campanhas no Google e no Instagram com a medição instalada desde o primeiro dia: você sabe quanto custou cada cliente.</div>
            @endif
        </div>

        @if($r['potential'])
            <div class="card boost reveal">
                <h2>Se você resolver os {{ count($r['weak']) === 1 ? 'ponto' : count($r['weak']) . ' pontos' }} abaixo</h2>
                <div class="cmp">
                    <div class="now"><b>{{ $r['score'] }}</b><small>{{ $r['position'] ? $r['position'] . 'º hoje' : 'hoje' }}</small></div>
                    <div class="arrow" aria-hidden="true">➜</div>
                    <div class="then"><b data-count="{{ $r['potential']['score'] }}">{{ $r['potential']['score'] }}</b><small>até {{ $r['potential']['position'] }}º lugar</small></div>
                </div>
                <p class="hint">Sua nota pode chegar a até {{ $r['potential']['score'] }}. Com as notas de hoje, isso {{ $r['potential']['position'] === 1 ? 'coloca você em 1º lugar' : 'leva você para o ' . $r['potential']['position'] . 'º lugar' }} entre {{ $r['total'] }} {{ $r['type'] ? 'concorrentes' : 'empresas' }}{{ $r['city'] ? ' de ' . $r['city'] : '' }}.</p>
            </div>
        @endif

        @if($r['weak'])
            <div class="card reveal">
                <h2>{{ count($r['weak']) === 1 ? 'O ponto que mais pesou' : 'Os ' . count($r['weak']) . ' pontos que mais pesaram' }}</h2>
                @foreach($r['weak'] as $i => $w)
                    <div class="pt">
                        <span class="n">{{ $i + 1 }}</span>
                        <div>
                            <b>{{ $w['title'] }}</b>
                            <p>{{ $w['text'] }}</p>
                            <div class="fix">
                                <span class="tag {{ $w['who'] }}">{{ $w['who'] === 'voce' ? 'Você mesmo resolve' : 'A gente resolve para você' }}</span><br>
                                {{ $w['fix'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($r['total'] > 1 && $r['score'] !== null)
            <div class="card reveal">
                <h2>Onde você está</h2>
                <div class="bars">
                    @foreach($r['ranking'] as $t)
                        <div class="item {{ $t['me'] ? 'me' : '' }}">
                            <div class="lbl"><span>{{ $t['pos'] }}º {{ $t['me'] ? '· ' . $r['name'] : 'colocado' }}</span><b>{{ $t['score'] }}</b></div>
                            <div class="track2"><div class="fill" data-w="{{ $t['score'] }}" style="background:#c9b8dc"></div></div>
                        </div>
                    @endforeach
                    @unless($r['inTop'])
                        <div class="item me">
                            <div class="lbl"><span>{{ $r['position'] ? $r['position'] . 'º · ' : '' }}{{ $r['name'] }}</span><b>{{ $r['score'] }}</b></div>
                            <div class="track2"><div class="fill" data-w="{{ $r['score'] }}"></div></div>
                        </div>
                    @endunless
                </div>
                <p class="hint">Comparamos {{ $r['total'] }} empresas do mesmo segmento{{ $r['city'] ? ' em ' . $r['city'] : '' }}. Os nomes das outras não aparecem aqui.</p>
            </div>
        @endif

        @if($r['areas'])
            <div class="card reveal">
                <h2>Sua nota por área</h2>
                <div class="bars">
                    @foreach($r['areas'] as $a)
                        <div class="item">
                            <div class="lbl"><span>{{ $a['label'] }}</span><b>{{ $a['pct'] }}%</b></div>
                            <div class="track2"><div class="fill" data-w="{{ $a['pct'] }}" style="background:{{ $barTone($a['pct']) }}"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($r['rating'] !== null || $r['reviews'] === 0)
            <div class="card reveal">
                <h2>Sua reputação no Google</h2>
                @if($r['reviews'] > 0)
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px">
                        <span class="stars" aria-hidden="true">★★★★★<i data-w="{{ round($r['rating'] / 5 * 100) }}">★★★★★</i></span>
                        <b style="font-size:20px">{{ $fmt($r['rating']) }}</b>
                    </div>
                    <p style="margin:0">{{ $r['reviews'] }} {{ $r['reviews'] === 1 ? 'avaliação' : 'avaliações' }}.
                        @if($r['topReviews']){{ $r['position'] === 1 ? 'As próximas colocadas têm entre' : 'Os mais bem colocados têm entre' }} {{ $r['topReviews'][0] }} e {{ $r['topReviews'][1] }}.@endif</p>
                @else
                    <p style="margin:0">Ainda não há avaliações no seu perfil do Google.</p>
                @endif
            </div>
        @endif

        @if($r['strong'])
            <div class="card reveal">
                <h2>O que já está bom</h2>
                @foreach($r['strong'] as $s)
                    <div class="ok"><i>✓</i><span>{{ $s }}</span></div>
                @endforeach
            </div>
        @endif

        @if($showCta)
            <div class="card cta reveal" id="cta">
                <h2>Quer melhorar o seu raio-x?</h2>
                <p>A {{ $orgName }} ajuda empresas a aparecerem melhor no Google, no site e nos anúncios. Uma conversa rápida, sem compromisso, para mostrar o caminho.</p>
                @if($whatsapp)
                    <a class="wa" href="{{ route('commerce.report.talk', $place->report_token) }}">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3M12 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 0 1 2.2 12C2.2 6.6 6.6 2.2 12 2.2S21.8 6.6 21.8 12 17.4 21.8 12 21.8M12 0C5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6A12 12 0 0 0 12 24c6.6 0 12-5.4 12-12S18.6 0 12 0"/></svg>
                        Quero melhorar meu raio-x
                    </a>
                @else
                    <p style="margin-top:10px; font-weight:600">É só responder a mensagem em que veio este link.</p>
                @endif
            </div>
        @endif

        <div class="foot">
            <p>Dados da empresa e avaliações: <b>Google Maps</b>@if($r['mapsUrl']) · <a href="{{ $r['mapsUrl'] }}" target="_blank" rel="noopener">ver perfil</a>@endif. Velocidade e estrutura do site: análise da {{ $orgName }}. A nota compara presença digital; não é uma avaliação do serviço da empresa.
                <a href="https://maps.google.com/help/terms_maps/" target="_blank" rel="noopener">Termos do Google Maps</a>.</p>
            @if($showCta)
                <details>
                    <summary class="linkbtn" style="list-style:none">Não quero receber mais mensagens</summary>
                    <form method="POST" action="{{ route('commerce.report.opt-out', $place->report_token) }}" style="margin-top:8px">
                        @csrf
                        <span>Tem certeza? Você não vai mais receber mensagens da {{ $orgName }}.</span>
                        <button type="submit" style="margin-left:6px; background:var(--purple); color:#fff; border:0; border-radius:8px; padding:5px 12px; font:inherit; font-size:12px; cursor:pointer">Sim, não quero receber</button>
                    </form>
                </details>
            @endif
        </div>
    </main>

    @if($showCta && $whatsapp)
        <div class="sticky" id="sticky">
            <a class="wa" href="{{ route('commerce.report.talk', $place->report_token) }}">Quero melhorar meu raio-x</a>
        </div>
    @endif

    <script>
    (function () {
        var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function count(el) {
            var to = +el.dataset.count, t0 = null, dur = still ? 0 : 1400;
            if (!dur) { el.textContent = to; return; }
            el.textContent = 0;
            requestAnimationFrame(function step(t) {
                t0 = t0 || t;
                var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(to * e);
                if (p < 1) requestAnimationFrame(step);
            });
        }
        function play(box) {
            box.querySelectorAll('[data-w]').forEach(function (b) { b.style.width = b.dataset.w + '%'; });
            box.querySelectorAll('[data-count]').forEach(count);
            box.querySelectorAll('[data-offset]').forEach(function (c) { c.style.strokeDashoffset = c.dataset.offset; });
        }

        // Topo: anel e número logo ao abrir.
        setTimeout(function () { play(document.querySelector('.hero')); }, still ? 0 : 250);

        // Blocos aparecem (e animam) quando entram na tela.
        var items = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window) || still) {
            items.forEach(function (el) { el.classList.add('in'); play(el); });
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) { e.target.classList.add('in'); play(e.target); io.unobserve(e.target); }
                });
            }, { threshold: .2 });
            items.forEach(function (el) { io.observe(el); });
        }

        // Botão fixo do WhatsApp: aparece depois do topo e some quando o cartão do convite está à vista.
        var sticky = document.getElementById('sticky'), hero = document.querySelector('.hero'), cta = document.getElementById('cta');
        if (sticky && 'IntersectionObserver' in window) {
            var heroOut = false, ctaIn = false;
            var upd = function () { sticky.classList.toggle('on', heroOut && !ctaIn); };
            new IntersectionObserver(function (e) { heroOut = !e[0].isIntersecting; upd(); }).observe(hero);
            if (cta) new IntersectionObserver(function (e) { ctaIn = e[0].isIntersecting; upd(); }).observe(cta);
        }
    })();
    </script>
</body>
</html>
