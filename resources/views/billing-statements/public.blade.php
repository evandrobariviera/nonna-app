@php
    $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $monthLabel = fn ($m) => \App\Services\Billing\StatementDeliveryService::monthLabel($m);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Prestação de contas · {{ $client->company_name }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; font-size: 15px; min-height: 100vh; }
        .num { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
    <main class="max-w-3xl mx-auto px-4 py-10">
        <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" alt="Nonna" class="w-10 h-10 mb-4">
        <p class="text-xs uppercase tracking-widest" style="color:var(--muted)">Prestação de contas dos anúncios</p>
        <h1 class="text-2xl font-black mt-1" style="color:var(--text)">{{ $client->company_name }}</h1>
        <p class="text-sm mt-1" style="color:var(--muted2)">Notas fiscais e extrato de {{ $monthLabel($delivery->month) }}. Os meses anteriores ficam logo abaixo.</p>

        @forelse($statements as $accountId => $rows)
            @php $account = $accounts[$accountId]; $hasBalance = \App\Services\Billing\AccountStatement::hasBalance($account); @endphp
            <section class="card p-5 mt-6">
                <h2 class="font-bold" style="color:var(--text)">{{ $account->platformLabel() }} <span class="text-xs font-normal" style="color:var(--muted)">conta {{ $account->account_id }}</span></h2>

                @foreach($rows as $row)
                    <div class="mt-4 pt-4" style="{{ !$loop->first ? 'border-top:1px solid var(--border)' : '' }}">
                        <p class="font-semibold text-sm" style="color:var(--text)">{{ ucfirst($monthLabel($row['month'])) }}</p>
                        <dl class="grid gap-x-6 gap-y-1 text-sm mt-2" style="grid-template-columns:repeat(auto-fit, minmax(130px, 1fr))">
                            @if($hasBalance && $row['opening'] !== null)
                                <div><dt class="text-xs" style="color:var(--muted)">Saldo no início</dt><dd class="num font-semibold">{{ $money($row['opening']) }}</dd></div>
                            @endif
                            @if($row['credits'] > 0)
                                <div><dt class="text-xs" style="color:var(--muted)">Créditos (boleto/PIX)</dt><dd class="num font-semibold" style="color:var(--green)">{{ $money($row['credits']) }}</dd></div>
                            @endif
                            <div><dt class="text-xs" style="color:var(--muted)">Investido nos anúncios</dt><dd class="num font-semibold">{{ $money($row['consumed']) }}</dd></div>
                            @if($row['invoiced'] > 0)
                                <div><dt class="text-xs" style="color:var(--muted)">Total das notas</dt><dd class="num font-semibold">{{ $money($row['invoiced']) }}</dd></div>
                            @endif
                            @if($hasBalance && $row['closing'] !== null)
                                <div><dt class="text-xs" style="color:var(--muted)">Saldo no fim</dt><dd class="num font-semibold">{{ $money($row['closing']) }}</dd></div>
                            @endif
                        </dl>
                        @if($row['notes'])
                            <p class="text-xs mt-2" style="color:var(--orange)">{{ $row['notes'] }}</p>
                        @endif
                        @if($row['documents']->isNotEmpty())
                            <div class="flex flex-col gap-1.5 mt-3">
                                @foreach($row['documents'] as $doc)
                                    @if($doc->hasFile())
                                        <a href="{{ $doc->url() }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-3 py-2 rounded text-sm" style="background:var(--s2)">
                                            <x-icon name="download" size="15" style="color:var(--purple)" />
                                            <span class="flex-1 min-w-0 truncate" style="color:var(--text)">{{ $doc->typeLabel() }} · {{ $doc->filename }}</span>
                                            @if($doc->amount)<span class="num text-xs" style="color:var(--muted)">{{ $money($doc->amount) }}</span>@endif
                                        </a>
                                    @elseif($doc->pix_code)
                                        <div class="px-3 py-2 rounded text-xs break-all" style="background:var(--s2); color:var(--muted2)">PIX: {{ $doc->pix_code }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @empty
            <div class="card p-6 mt-6 text-sm" style="color:var(--muted)">Nenhum documento disponível.</div>
        @endforelse

        <p class="text-xs mt-8" style="color:var(--muted)">"Investido nos anúncios" é o gasto registrado nas campanhas. O valor oficial é o da nota fiscal, que pode ficar um pouco abaixo porque a plataforma desconta cliques inválidos.</p>
        <p class="text-xs mt-2" style="color:var(--muted)">Nonna Agência Digital · dúvidas, fale com a gente pelo WhatsApp de sempre.</p>
    </main>
</body>
</html>
