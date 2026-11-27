{{-- Página da proposta pro cliente (PublicProposalController). Também é o PDF: "Baixar PDF" chama
     a impressão do navegador, e o @media print monta a folha A4 (sem botões, sem fundo colorido pesado).
     Só o que é do cliente — nunca preço de tabela, liberação de desconto, sugestão de upgrade, avisos
     internos ou apelido do cliente. Valores sempre do que foi salvo (nunca recalculados aqui).
     Sem dependência externa além da fonte; mesmo visual do raio-x (/rx). --}}
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . '%';
    $orgName = $org?->name ?: 'Nonna Agência Digital';
    $logo = $org?->logoUrl();
    $calc = $proposal->calculation['calc'] ?? [];
    $snap = $proposal->package_snapshot;
    $months = (int) config('proposals.contract_months', 12);
    $monthly = (float) $proposal->monthly_total;
    $oneTime = (float) $proposal->one_time_total;
    $installments = (int) ($proposal->installments ?: 1);
    $installment = (float) ($calc['installment'] ?? 0);
    $lastInstallment = (float) ($calc['last_installment'] ?? $installment);
    $discount = (float) ($calc['discount_value'] ?? 0);
    $groupFixed = $proposal->group_on && $proposal->group_mode === 'fixed';
    $monthlyItems = $proposal->items->where('kind', 'monthly');
    $oneTimeItems = $proposal->items->where('kind', '!=', 'monthly');
    $who = $contact?->company_name ?: $contact?->name;
    $date = $proposal->sent_at ?? $proposal->created_at;
    $deliverables = $calc['deliverables'] ?? null;
    $isOpen = $proposal->status === 'sent';
    $showCta = $isOpen && $whatsapp;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#643B8E">
    <title>Proposta · {{ $proposal->title }}{{ $who ? ' · ' . $who : '' }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet"/>
    <style>
        :root { --purple:#643B8E; --purple2:#8E5BC0; --orange:#EE7919; --text:#1d1b22; --muted:#6b6675; --line:#ece9f1; --bg:#f4f1f8; --card:#fff; --green:#16a34a; --wa:#25D366; }
        * { box-sizing:border-box; }
        html { -webkit-text-size-adjust:100%; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Outfit', ui-sans-serif, system-ui, sans-serif; font-size:15px; line-height:1.5; }
        .wrap { max-width:760px; margin:0 auto; padding:0 0 {{ $showCta ? '104px' : '40px' }}; }

        .note { margin:0; padding:10px 16px; font-size:13px; font-weight:600; text-align:center; }
        .note.preview { background:#fff4e5; color:#9a4d00; }
        .note.done { background:#e8f7ee; color:var(--green); }
        .note.closed { background:#f3f0f6; color:var(--muted); }
        .note a { color:inherit; }

        /* Topo */
        .hero { position:relative; overflow:hidden; background:linear-gradient(155deg, #4e2b73 0%, var(--purple) 45%, var(--purple2) 100%); color:#fff; padding:24px 24px 64px; }
        .hero::before, .hero::after { content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,.07); }
        .hero::before { width:300px; height:300px; right:-100px; top:-130px; }
        .hero::after { width:200px; height:200px; left:-80px; bottom:-110px; background:rgba(238,121,25,.18); }
        .brand { position:relative; display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:28px; }
        .brand .logo { background:#fff; border-radius:10px; padding:6px 12px; display:inline-flex; align-items:center; }
        .brand .logo img { height:20px; display:block; }
        .brand .logo span { color:var(--purple); font-weight:800; font-size:14px; }
        .brand .meta { font-size:12px; opacity:.85; text-align:right; }
        .eyebrow { position:relative; font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; opacity:.85; margin:0 0 8px; }
        .hero h1 { position:relative; font-size:30px; font-weight:800; margin:0 0 6px; line-height:1.15; }
        .hero .sub { position:relative; margin:0; opacity:.92; font-size:15px; }

        /* Resumo do investimento (sobe sobre o topo) */
        .summary { position:relative; z-index:2; margin:-44px 16px 0; background:#fff; border-radius:18px; box-shadow:0 12px 34px rgba(60,30,90,.14); display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); overflow:hidden; }
        .summary > div { padding:18px 20px; border-left:1px solid var(--line); }
        .summary > div:first-child { border-left:0; }
        .summary small { display:block; font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin-bottom:4px; }
        .summary b { display:block; font-size:24px; font-weight:800; line-height:1.15; font-variant-numeric:tabular-nums; }
        .summary b.main { color:var(--purple); }
        .summary span { display:block; font-size:12px; color:var(--muted); margin-top:3px; }

        /* Cartões */
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:20px; margin:16px 16px 0; box-shadow:0 1px 2px rgba(40,20,60,.04); break-inside:avoid; }
        .kicker { margin:0 0 4px; font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--orange); }
        .card h2 { font-size:19px; font-weight:800; margin:0 0 4px; line-height:1.25; }
        .lead { margin:0; color:#4a4453; font-size:14px; }

        .pkg { border:1px solid #d9c9ec; background:linear-gradient(160deg, #fff 0%, #f8f3fd 100%); position:relative; overflow:hidden; }
        .pkg::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; background:linear-gradient(180deg, var(--purple), var(--purple2)); }
        .pkg .head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
        .pkg .price { text-align:right; font-variant-numeric:tabular-nums; }
        .pkg .price b { display:block; font-size:20px; font-weight:800; color:var(--purple); }
        .pkg .price span { font-size:12px; color:var(--muted); }
        .inc { list-style:none; padding:0; margin:16px 0 0; display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:8px 18px; }
        .inc li { display:flex; gap:9px; align-items:flex-start; font-size:14px; }
        .inc li::before { content:'✓'; flex:none; width:20px; height:20px; border-radius:50%; background:rgba(22,163,74,.12); color:var(--green); font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center; margin-top:1px; }

        .tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:8px; margin-top:16px; }
        .tiles div { background:#fff; border:1px solid var(--line); border-radius:12px; padding:12px; text-align:center; }
        .tiles b { display:block; font-size:24px; font-weight:800; color:var(--text); line-height:1.1; }
        .tiles span { font-size:12px; color:var(--muted); }

        .items { margin-top:14px; }
        .item { display:flex; justify-content:space-between; gap:14px; padding:12px 0; border-top:1px solid var(--line); }
        .item:first-child { border-top:0; padding-top:4px; }
        .item .name { font-weight:700; font-size:15px; }
        .item .qty { color:var(--orange); font-weight:800; margin-right:4px; }
        .item p { margin:2px 0 0; color:var(--muted); font-size:13px; }
        .item .val { flex:none; text-align:right; font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .item .val small { display:block; font-weight:500; font-size:12px; color:var(--muted); }
        .grouptag { display:inline-block; font-size:11px; font-weight:700; border-radius:999px; padding:2px 9px; background:rgba(100,59,142,.1); color:var(--purple); margin-bottom:6px; }

        /* Investimento */
        .rows { margin-top:12px; }
        .row { display:flex; justify-content:space-between; gap:14px; padding:9px 0; border-top:1px dashed var(--line); font-size:14px; font-variant-numeric:tabular-nums; }
        .row:first-child { border-top:0; }
        .row span:last-child { font-weight:700; white-space:nowrap; }
        .row.green span:last-child { color:var(--green); }
        .row.total { border-top:2px solid var(--text); margin-top:6px; padding-top:12px; font-size:16px; font-weight:800; }
        .row.total span:last-child { color:var(--purple); font-size:18px; }
        .notes { margin-top:10px; font-size:14.5px; line-height:1.6; }
        .aside { margin-top:14px; background:#faf8fc; border-radius:12px; padding:11px 13px; font-size:13px; color:#4a4453; }

        .steps { counter-reset:s; list-style:none; padding:0; margin:12px 0 0; }
        .steps li { counter-increment:s; display:flex; gap:12px; padding:8px 0; font-size:14px; }
        .steps li::before { content:counter(s); flex:none; width:26px; height:26px; border-radius:50%; background:rgba(238,121,25,.14); color:var(--orange); font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; }

        .actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin:22px 16px 0; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid var(--line); background:#fff; color:var(--text); font:inherit; font-weight:700; font-size:14px; padding:11px 18px; border-radius:12px; text-decoration:none; cursor:pointer; }
        .btn:hover { border-color:#d9c9ec; }
        .foot { text-align:center; color:var(--muted); font-size:12px; margin:22px 16px 0; }

        .cta { position:fixed; left:0; right:0; bottom:0; z-index:10; padding:12px 16px calc(12px + env(safe-area-inset-bottom)); background:linear-gradient(180deg, rgba(244,241,248,0), var(--bg) 35%); }
        .cta a { display:flex; align-items:center; justify-content:center; gap:10px; max-width:520px; margin:0 auto; background:var(--wa); color:#fff; text-decoration:none; font-weight:800; font-size:16px; padding:15px 18px; border-radius:14px; box-shadow:0 10px 24px rgba(37,211,102,.32); }

        @media (max-width:560px) {
            .hero { padding:20px 18px 60px; }
            .hero h1 { font-size:25px; }
            .summary { margin:-42px 12px 0; grid-template-columns:1fr; }
            .summary > div { border-left:0; border-top:1px solid var(--line); }
            .summary > div:first-child { border-top:0; }
            .card { margin:14px 12px 0; padding:18px; }
            .pkg .price { text-align:left; }
        }

        /* Folha impressa / PDF */
        @page { size:A4; margin:14mm 12mm; }
        @media print {
            body { background:#fff; font-size:12.5px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .wrap { max-width:none; padding:0; }
            .note.preview, .actions, .cta { display:none !important; }
            .hero { padding:18px 22px 52px; border-radius:14px; }
            .summary { margin:-36px 14px 0; box-shadow:none; border:1px solid var(--line); }
            .card { margin:12px 0 0; box-shadow:none; padding:16px 18px; }
            .foot { margin-top:14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    @if($preview && !$proposal->isPublic())
        <p class="note preview">Prévia da equipe · esta proposta ainda é {{ mb_strtolower($proposal->statusLabel()) }} e o link só abre pro cliente depois de marcada como enviada.</p>
    @elseif($preview)
        <p class="note preview">Você está vendo como o cliente vê · sua visita não conta.</p>
    @endif
    @if($proposal->status === 'accepted')
        <p class="note done">Proposta aceita em {{ $proposal->accepted_at?->format('d/m/Y') }}. Obrigado pela confiança!</p>
    @elseif($proposal->status === 'rejected')
        <p class="note closed">Esta versão da proposta não está mais valendo.@if($newer) <a href="{{ route('proposals.public', $newer->ensurePublicToken()) }}">Veja a versão mais recente</a>.@endif</p>
    @endif

    <header class="hero">
        <div class="brand">
            <span class="logo">@if($logo)<img src="{{ $logo }}" alt="{{ $orgName }}">@else<span>{{ $orgName }}</span>@endif</span>
            <span class="meta">{{ $date->format('d/m/Y') }}<br>Versão {{ $proposal->version }}</span>
        </div>
        <p class="eyebrow">Proposta comercial</p>
        <h1>{{ $proposal->title }}</h1>
        @if($who)<p class="sub">Preparada para {{ $who }}@if($contact?->company_name && $contact?->name) · aos cuidados de {{ $contact->name }}@endif</p>@endif
    </header>

    <section class="summary" aria-label="Resumo do investimento">
        @if($monthly > 0)
            <div>
                <small>Mensalidade</small>
                <b class="main">{{ $brl($monthly) }}</b>
                <span>por mês</span>
            </div>
        @endif
        @if($oneTime > 0)
            <div>
                <small>{{ $monthly > 0 ? 'Implantação e projetos' : 'Investimento' }}</small>
                <b class="{{ $monthly > 0 ? '' : 'main' }}">{{ $brl($oneTime) }}</b>
                <span>{{ $installments > 1 ? "em {$installments}x de " . $brl($installment) : 'pagamento único' }}</span>
            </div>
        @endif
        @if($monthly > 0)
            <div>
                <small>Contrato de {{ $months }} meses</small>
                <b>{{ $brl($proposal->contract_total) }}</b>
                <span>valor total no período</span>
            </div>
        @endif
    </section>

    @if($proposal->context)
        <section class="card">
            <p class="kicker">Sobre esta proposta</p>
            <p class="lead notes">{!! nl2br(e($proposal->context)) !!}</p>
        </section>
    @endif

    @if($snap)
        <section class="card pkg">
            <div class="head">
                <div>
                    <p class="kicker">O plano</p>
                    <h2>{{ $snap['name'] }}</h2>
                    @if($d = $descriptions[$snap['id'] ?? ''] ?? null)<p class="lead">{{ $d }}</p>@endif
                </div>
                <div class="price">
                    @if(!empty($snap['monthly']))<b>{{ $brl($snap['monthly']) }}<span>/mês</span></b>@endif
                    @if(!empty($snap['one_time']))<span>+ {{ $brl($snap['one_time']) }} de implantação</span>@endif
                </div>
            </div>
            @if(!empty($snap['rules']['includes']))
                <ul class="inc">
                    @foreach($snap['rules']['includes'] as $inc)<li>{{ $inc }}</li>@endforeach
                </ul>
            @endif
            @if($deliverables)
                <div class="tiles">
                    @foreach(['ads' => 'anúncios por mês', 'videos' => 'vídeos por mês', 'campaigns' => 'campanhas ao mesmo tempo', 'attendants' => 'atendentes avisados'] as $k => $label)
                        <div><b>{{ $deliverables[$k] ?? 'Sem limite' }}</b><span>{{ $label }}</span></div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($proposal->items->isNotEmpty())
        <section class="card">
            <p class="kicker">{{ $snap ? 'Somado ao plano' : 'O que está incluído' }}</p>
            <h2>{{ $proposal->group_on && $proposal->group_name ? $proposal->group_name : ($snap ? 'Itens adicionais' : 'Itens da proposta') }}</h2>
            @if($groupFixed)<p class="lead">Estes itens são contratados juntos, como um pacote.</p>@endif
            <div class="items">
                @foreach([$monthlyItems, $oneTimeItems] as $group)
                    @foreach($group as $item)
                        <div class="item">
                            <div>
                                <div class="name">@if($item->quantity > 1)<span class="qty">{{ $item->quantity }}×</span>@endif{{ $item->name }}</div>
                                @if($d = $item->description ?: ($descriptions[$item->catalog_item_id] ?? null))<p>{!! nl2br(e($d)) !!}</p>@endif
                            </div>
                            <div class="val">
                                @unless($groupFixed)
                                    {{ $brl($item->subtotal) }}<small>{{ $item->kind === 'monthly' ? 'por mês' : 'valor único' }}</small>
                                @else
                                    <small>{{ $item->kind === 'monthly' ? 'mensal' : 'único' }}</small>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                @endforeach
                @if($groupFixed)
                    <div class="item">
                        <div class="name">Valor do pacote</div>
                        <div class="val">
                            @if((float) $proposal->group_monthly){{ $brl($proposal->group_monthly) }}<small>por mês</small>@endif
                            @if((float) $proposal->group_one_time){{ $brl($proposal->group_one_time) }}<small>valor único</small>@endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="card">
        <p class="kicker">Investimento</p>
        <h2>Como fica o pagamento</h2>
        <div class="rows">
            @if($monthly > 0)
                <div class="row"><span>Mensalidade</span><span>{{ $brl($monthly) }}/mês</span></div>
                @if($discount > 0)
                    <div class="row green"><span>Desconto de {{ $pct($proposal->discount_percent) }} já aplicado na mensalidade</span><span>−{{ $brl($discount) }}/mês</span></div>
                @endif
                @if(!empty($calc['excess_value']))
                    <div class="row"><span>Inclui a gestão da verba acima do limite do plano</span><span>{{ $brl($calc['excess_value']) }}/mês</span></div>
                @endif
            @endif
            @if($oneTime > 0)
                <div class="row"><span>{{ $monthly > 0 ? 'Implantação e projetos' : 'Valor do projeto' }}</span><span>{{ $brl($oneTime) }}</span></div>
                @if($installments > 1)
                    <div class="row"><span>Parcelamento</span><span>{{ $installments }}x de {{ $brl($installment) }}@if(abs($lastInstallment - $installment) >= 0.01) (última {{ $brl($lastInstallment) }})@endif</span></div>
                @endif
            @endif
            @if($monthly > 0 && $oneTime > 0)
                <div class="row"><span>{{ $installments > 1 ? "Nos primeiros {$installments} meses" : 'No primeiro mês' }}</span><span>{{ $brl($monthly + ($installments > 1 ? $installment : $oneTime)) }}{{ $installments > 1 ? '/mês' : '' }}</span></div>
            @endif
            <div class="row total"><span>{{ $monthly > 0 ? "Total do contrato ({$months} meses)" : 'Total' }}</span><span>{{ $brl($proposal->contract_total) }}</span></div>
        </div>
        @if($proposal->ad_budget !== null && (float) $proposal->ad_budget > 0)
            <p class="aside"><b>Verba de anúncios:</b> {{ $brl($proposal->ad_budget) }}/mês, paga por você direto ao Meta e ao Google. Ela não entra nos valores acima.</p>
        @endif
    </section>

    @if($proposal->notes)
        <section class="card">
            <p class="kicker">Observações</p>
            <h2>Bom saber</h2>
            <p class="lead notes">{!! nl2br(e($proposal->notes)) !!}</p>
        </section>
    @endif

    @if($isOpen)
        <section class="card">
            <p class="kicker">Próximos passos</p>
            <h2>Gostou? É simples começar</h2>
            <ol class="steps">
                <li>Você confirma a proposta com a gente{{ $whatsapp ? ' pelo WhatsApp' : '' }}.</li>
                <li>Enviamos o link de cadastro e o contrato para assinatura.</li>
                <li>Marcamos a conversa de boas-vindas e começamos a implantação.</li>
            </ol>
        </section>
    @endif

    <div class="actions">
        <button type="button" class="btn" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
            Baixar PDF
        </button>
    </div>

    <p class="foot">{{ $orgName }} · proposta v{{ $proposal->version }} de {{ $date->format('d/m/Y') }}</p>
</div>

@if($showCta)
    <div class="cta">
        <a href="{{ route('proposals.public.talk', $proposal->public_token) }}" rel="noopener">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2.1-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.7.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg>
            Quero fechar ou tirar dúvidas
        </a>
    </div>
@endif
</body>
</html>
