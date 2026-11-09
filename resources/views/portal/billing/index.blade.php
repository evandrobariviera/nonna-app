<x-portal-layout>
    <x-slot name="title">Prestação de contas</x-slot>

    @php
        $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
        $monthLabel = fn ($m) => \App\Services\Billing\StatementDeliveryService::monthLabel($m);
    @endphp

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Prestação de contas</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Notas fiscais, boletos/PIX e o extrato mensal dos anúncios de {{ $client->company_name }}.</p>
    </div>

    @forelse($statements as $accountId => $rows)
        @php $account = $accounts[$accountId]; $hasBalance = \App\Services\Billing\AccountStatement::hasBalance($account); @endphp
        <section class="card p-5 mb-5">
            <h2 class="font-bold" style="color:var(--text)">{{ $account->platformLabel() }} <span class="text-xs font-normal" style="color:var(--muted)">conta {{ $account->account_id }}</span></h2>

            @foreach($rows as $row)
                <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="mt-3 pt-3" style="{{ !$loop->first ? 'border-top:1px solid var(--border)' : '' }}">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 text-left">
                        <span class="font-semibold text-sm" style="color:var(--text)">{{ ucfirst($monthLabel($row['month'])) }}</span>
                        <span class="text-xs" style="color:var(--muted)">
                            {{ $money($row['consumed']) }} investidos
                            @php $nf = $row['documents']->where('type', 'nota')->count(); @endphp
                            · {{ $nf ? $nf . ($nf === 1 ? ' nota' : ' notas') : 'nota ainda não emitida' }}
                        </span>
                    </button>
                    <div x-show="open" x-cloak class="mt-3">
                        <dl class="grid gap-x-6 gap-y-1 text-sm" style="grid-template-columns:repeat(auto-fit, minmax(130px, 1fr))">
                            @if($hasBalance && $row['opening'] !== null)
                                <div><dt class="text-xs" style="color:var(--muted)">Saldo no início</dt><dd class="font-semibold">{{ $money($row['opening']) }}</dd></div>
                            @endif
                            @if($row['credits'] > 0)
                                <div><dt class="text-xs" style="color:var(--muted)">Créditos (boleto/PIX)</dt><dd class="font-semibold" style="color:var(--green)">{{ $money($row['credits']) }}</dd></div>
                            @endif
                            <div><dt class="text-xs" style="color:var(--muted)">Investido nos anúncios</dt><dd class="font-semibold">{{ $money($row['consumed']) }}</dd></div>
                            @if($row['invoiced'] > 0)
                                <div><dt class="text-xs" style="color:var(--muted)">Total das notas</dt><dd class="font-semibold">{{ $money($row['invoiced']) }}</dd></div>
                            @endif
                            @if($hasBalance && $row['closing'] !== null)
                                <div><dt class="text-xs" style="color:var(--muted)">Saldo no fim</dt><dd class="font-semibold">{{ $money($row['closing']) }}</dd></div>
                            @endif
                        </dl>
                        @if($row['documents']->isNotEmpty())
                            <div class="flex flex-col gap-1.5 mt-3">
                                @foreach($row['documents'] as $doc)
                                    @if($doc->hasFile())
                                        <a href="{{ $doc->url() }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-3 py-2 rounded text-sm" style="background:var(--s2)">
                                            <x-icon name="download" size="15" style="color:var(--purple)" />
                                            <span class="flex-1 min-w-0 truncate" style="color:var(--text)">{{ $doc->typeLabel() }} · {{ $doc->filename }}</span>
                                            <span class="text-xs whitespace-nowrap" style="color:var(--muted)">{{ $doc->amount ? $money($doc->amount) : '' }}@if($doc->due_date) · vence {{ $doc->due_date->format('d/m') }}@endif</span>
                                        </a>
                                    @elseif($doc->pix_code)
                                        <div class="flex items-center gap-3 px-3 py-2 rounded" style="background:var(--s2)">
                                            <code class="text-xs flex-1 break-all" style="color:var(--purple)">{{ $doc->pix_code }}</code>
                                            <button type="button" onclick="navigator.clipboard.writeText(`{{ $doc->pix_code }}`)" class="btn btn-sm">Copiar PIX</button>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm font-semibold" style="color: var(--muted)">Nenhuma nota fiscal ou boleto ainda.</p>
        </div>
    @endforelse

    <p class="text-xs mt-2" style="color:var(--muted)">"Investido nos anúncios" é o gasto registrado nas campanhas. O valor oficial é o da nota fiscal, que pode ficar um pouco abaixo porque a plataforma desconta cliques inválidos.</p>
</x-portal-layout>
