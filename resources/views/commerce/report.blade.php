{{-- Raio-x público da prospecção (CommerceReportController / ProspectReport).
     Desenho: tela 5 do protótipo (.claude/docs/prototipos/central-comercio.html).
     Página aberta pelo link da mensagem de abordagem, pensada para o celular.
     Concorrentes só com a nota (nunca o nome). Dados do Google com atribuição "Google Maps". --}}
@php
    $orgName = $org?->name ?: 'Nonna Agência Digital';
    $fmt = fn ($n) => number_format((float) $n, 1, ',', '');
    $who = $r['type'] ? $r['type'] : 'o seu segmento';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Raio-x de presença digital · {{ $r['name'] }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet"/>
    <style>
        :root { --purple:#643B8E; --purple2:#8E5BC0; --orange:#EE7919; --text:#1d1b22; --muted:#6b6675; --line:#ece9f1; --bg:#f6f4f9; --card:#fff; --green:#16a34a; --red:#dc2626; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Outfit', ui-sans-serif, system-ui, sans-serif; font-size:15px; line-height:1.5; }
        .wrap { max-width:520px; margin:0 auto; padding:0 0 32px; }
        .hero { background:linear-gradient(160deg, var(--purple) 0%, var(--purple2) 100%); color:#fff; padding:28px 20px 24px; }
        .eyebrow { font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; opacity:.85; margin:0 0 6px; }
        .hero h1 { font-size:24px; font-weight:800; margin:0 0 4px; line-height:1.2; }
        .hero p { margin:0; opacity:.92; font-size:14px; }
        .scorebox { margin-top:18px; background:rgba(255,255,255,.14); border-radius:14px; padding:14px 16px; display:flex; gap:14px; align-items:center; }
        .scorebox b { font-size:44px; font-weight:800; line-height:1; }
        .scorebox span { font-size:13px; opacity:.95; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; margin:14px 14px 0; }
        .card h2 { font-size:16px; font-weight:700; margin:0 0 12px; }
        .row { display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid var(--line); font-size:14px; }
        .row:last-child { border-bottom:0; }
        .row.me { color:var(--purple); font-weight:700; }
        .pt { display:flex; gap:12px; padding:10px 0; }
        .pt .n { flex:none; width:24px; height:24px; border-radius:50%; background:rgba(238,121,25,.14); color:var(--orange); font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; }
        .pt b { display:block; font-size:14px; }
        .pt p { margin:2px 0 0; color:var(--muted); font-size:14px; }
        .ok { display:flex; gap:8px; align-items:flex-start; font-size:14px; padding:4px 0; }
        .ok i { color:var(--green); font-style:normal; font-weight:700; }
        .muted { color:var(--muted); font-size:13px; }
        .cta { background:rgba(100,59,142,.07); border-color:rgba(100,59,142,.2); }
        .foot { margin:18px 14px 0; font-size:12px; color:var(--muted); }
        .foot a { color:var(--muted); }
        .linkbtn { background:none; border:0; padding:0; color:var(--muted); text-decoration:underline; font:inherit; cursor:pointer; }
        .banner { background:#fff7ed; border-bottom:1px solid #fed7aa; color:#9a3412; font-size:13px; padding:8px 16px; text-align:center; }
    </style>
</head>
<body>
    @if($preview)
        <div class="banner">Pré-visualização da equipe: esta visita não conta como abertura da empresa.</div>
    @endif

    <main class="wrap">
        <section class="hero">
            <p class="eyebrow">Raio-x de presença digital</p>
            <h1>{{ $r['name'] }}</h1>
            <p>Como você aparece para quem procura {{ $who }}{{ $r['city'] ? ' em ' . $r['city'] : '' }}</p>
            @if($r['score'] !== null)
                <div class="scorebox">
                    <b>{{ $r['score'] }}</b>
                    <span>de 100 pontos
                        @if($r['position'] === 1 && $r['total'] > 1)<br>A mais bem colocada entre as {{ $r['total'] }} da cidade.
                        @elseif($r['topRange'])<br>Os mais bem colocados da cidade têm entre {{ $r['topRange'][0] }} e {{ $r['topRange'][1] }}.@endif
                    </span>
                </div>
            @endif
        </section>

        @if(session('opted_out') || $optedOut)
            <div class="card">
                <h2>Pronto, você não vai mais receber mensagens nossas.</h2>
                <p class="muted" style="margin:0">Se mudar de ideia, é só responder a mensagem em que veio este link.</p>
            </div>
        @endif

        @if($r['total'] > 1 && $r['score'] !== null)
            <div class="card">
                <h2>Onde você está</h2>
                @foreach($r['ranking'] as $t)
                    @if($t['me'])
                        <div class="row me"><span>{{ $t['pos'] }}º · {{ $r['name'] }}</span><span>{{ $t['score'] }}</span></div>
                    @else
                        <div class="row"><span>{{ $t['pos'] }}º colocado</span><span>{{ $t['score'] }}</span></div>
                    @endif
                @endforeach
                @unless($r['inTop'])
                    <div class="row me"><span>{{ $r['position'] ? $r['position'] . 'º · ' : '' }}{{ $r['name'] }}</span><span>{{ $r['score'] }}</span></div>
                @endunless
                <p class="muted" style="margin:10px 0 0">Comparamos {{ $r['total'] }} empresas do mesmo segmento{{ $r['city'] ? ' em ' . $r['city'] : '' }}. Os nomes das outras não aparecem aqui.</p>
            </div>
        @endif

        @if($r['weak'])
            <div class="card">
                <h2>{{ count($r['weak']) === 1 ? 'O ponto que mais pesou' : 'Os ' . count($r['weak']) . ' pontos que mais pesaram' }}</h2>
                @foreach($r['weak'] as $i => $w)
                    <div class="pt"><span class="n">{{ $i + 1 }}</span><div><b>{{ $w['title'] }}</b><p>{{ $w['text'] }}</p></div></div>
                @endforeach
            </div>
        @endif

        @if($r['rating'] !== null || $r['reviews'] === 0)
            <div class="card">
                <h2>Sua reputação no Google</h2>
                @if($r['reviews'] > 0)
                    <p style="margin:0">Nota <b>{{ $fmt($r['rating']) }}</b> com <b>{{ $r['reviews'] }}</b> {{ $r['reviews'] === 1 ? 'avaliação' : 'avaliações' }}.
                        @if($r['topReviews']){{ $r['position'] === 1 ? 'As próximas colocadas têm entre' : 'Os mais bem colocados têm entre' }} {{ $r['topReviews'][0] }} e {{ $r['topReviews'][1] }} avaliações.@endif</p>
                @else
                    <p style="margin:0">Ainda não há avaliações no seu perfil do Google.</p>
                @endif
            </div>
        @endif

        @if($r['strong'])
            <div class="card">
                <h2>O que já está bom</h2>
                @foreach($r['strong'] as $s)
                    <div class="ok"><i>✓</i><span>{{ $s }}</span></div>
                @endforeach
            </div>
        @endif

        @unless($optedOut || session('opted_out'))
            <div class="card cta">
                <h2>Quer melhorar esses pontos?</h2>
                <p style="margin:0">A {{ $orgName }} ajuda empresas a aparecerem melhor no Google, no site e nos anúncios. É só responder a mensagem em que veio este link que a gente conversa.</p>
            </div>
        @endunless

        <div class="foot">
            <p>Dados da empresa e avaliações: <b>Google Maps</b>@if($r['mapsUrl']) · <a href="{{ $r['mapsUrl'] }}" target="_blank" rel="noopener">ver perfil</a>@endif. Velocidade e estrutura do site: análise da {{ $orgName }}. A nota é uma comparação simples de presença digital, não uma avaliação do serviço da empresa.
                <a href="https://maps.google.com/help/terms_maps/" target="_blank" rel="noopener">Termos do Google Maps</a>.</p>
            @unless($optedOut || session('opted_out'))
                <details>
                    <summary class="linkbtn" style="list-style:none">Não quero receber mais mensagens</summary>
                    <form method="POST" action="{{ route('commerce.report.opt-out', $place->report_token) }}" style="margin-top:8px">
                        @csrf
                        <span>Tem certeza? Você não vai mais receber mensagens da {{ $orgName }}.</span>
                        <button type="submit" style="margin-left:6px; background:var(--purple); color:#fff; border:0; border-radius:8px; padding:5px 12px; font:inherit; font-size:12px; cursor:pointer">Sim, não quero receber</button>
                    </form>
                </details>
            @endunless
        </div>
    </main>
</body>
</html>
