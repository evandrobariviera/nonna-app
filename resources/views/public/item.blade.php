{{-- Link sem senha do aviso ao cliente (PublicItemController). Só leitura. --}}
@php
    $title  = $isPlan ? $item->clientTitle() : $item->title;
    $start  = $isPlan ? $item->period_start : $item->start_date;
    $end    = $isPlan ? $item->period_end : $item->end_date;
    $period = collect([$start?->format('d/m/Y'), $end?->format('d/m/Y')])->filter()->implode(' a ');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
    <style>
        html, body { height: 100%; margin: 0; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; display: flex; flex-direction: column; }
        .item-head { padding: 14px 16px; background: var(--s1); border-bottom: 1px solid var(--border2); }
        .item-title { font-size: 16px; font-weight: 700; margin: 0; }
        .item-meta { font-size: 13px; color: var(--muted); margin: 3px 0 0; }
        .item-frame { flex: 1; width: 100%; min-height: 0; border: 0; background: #fff; display: block; }
        .item-empty { max-width: 560px; margin: 32px auto; padding: 0 16px; font-size: 15px; line-height: 1.6; }
    </style>
</head>
<body>
    <header class="item-head">
        <p class="item-title">{{ $title }}</p>
        @if($period)<p class="item-meta">{{ $period }}</p>@endif
        @if($plan)<p class="item-meta">Faz parte do {{ $plan->clientTitle() }}</p>@endif
    </header>

    @if($document)
        {{-- Mesmo sandbox da tela interna: o HTML roda, mas não enxerga a sessão do App. --}}
        <iframe class="item-frame" src="{{ $document->url() }}" title="{{ $title }}" sandbox="allow-scripts allow-popups"></iframe>
    @else
        <main class="item-empty">
            <p>{{ $isPlan ? 'Este planejamento está em execução.' : $item->typeLabel() . ' em execução.' }}</p>
            @if(!$isPlan && ($item->client_description || $item->objective))
                <p>{{ $item->client_description ?: $item->objective }}</p>
            @endif
            <p style="color:var(--muted)">O documento completo ainda vai ser publicado aqui.</p>
        </main>
    @endif
</body>
</html>
