<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Campanhas</h1>
        </div>
    </x-slot>

    @php
        $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $monthLabel = fn ($m) => \App\Services\Billing\StatementDeliveryService::monthLabel($m);
        $deadlineState = $month->equalTo(now()->subMonthNoOverflow()->startOfMonth()) ? \App\Services\Billing\AccountStatement::deadlineState() : null;
        $expects = \App\Services\Billing\AccountStatement::expectsInvoice($month);
        $pendingClients = $expects ? $groups->filter(fn ($rows) => $rows->contains(fn ($r) => $r->consumed > 0 && $r->invoices === 0))->count() : 0;
        $sentClients = $groups->filter(fn ($rows) => $rows->first()->delivery)->count();
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('billing-statements.index') }}" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="mes" onchange="this.form.submit()" class="filter-select" aria-label="Mês">
                @foreach($months as $m)
                    <option value="{{ $m->format('Y-m') }}" @selected($m->equalTo($month))>Notas de {{ $monthLabel($m) }}</option>
                @endforeach
            </select>
            <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                <option value="">Todos os clientes</option>
                <option value="meus" @selected($mine)>Só os clientes em que sou responsável</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
        </form>

        <div class="grid gap-3 mb-5" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr))">
            <div class="card px-4 py-3">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Faltam notas</p>
                <p class="text-xl font-black mt-1" style="color:{{ $pendingClients && $deadlineState === 'late' ? 'var(--red)' : ($pendingClients && $deadlineState === 'soon' ? 'var(--orange)' : 'var(--text)') }}">{{ $pendingClients }} {{ $pendingClients === 1 ? 'cliente' : 'clientes' }}</p>
                <p class="text-xs mt-0.5" style="color:var(--muted)">de {{ $groups->count() }} que anunciaram em {{ $monthLabel($month) }}</p>
            </div>
            <div class="card px-4 py-3">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Enviadas ao cliente</p>
                <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $sentClients }}</p>
                <p class="text-xs mt-0.5" style="color:var(--muted)">prazo combinado: até o dia {{ \App\Services\Billing\AccountStatement::DEADLINE_DAY }}</p>
            </div>
            <div class="card px-4 py-3">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Consumido no mês</p>
                <p class="text-xl font-black mt-1" style="color:var(--text)">{{ $money($groups->flatten(1)->sum('consumed')) }}</p>
                <p class="text-xs mt-0.5" style="color:var(--muted)">gasto nas campanhas, pela sincronização</p>
            </div>
        </div>

        <section class="card p-5">
            <h2 class="card-title">Notas de {{ $monthLabel($month) }} <small class="font-normal text-xs" style="color:var(--muted)">clique no cliente para subir as notas e ver o extrato</small></h2>
            <div class="overflow-x-auto mt-3 -mx-5">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background:var(--s2); color:var(--muted2)">
                            <th class="text-left font-medium text-xs px-5 py-2">Cliente / conta</th>
                            <th class="text-right font-medium text-xs px-3 py-2">Consumido</th>
                            <th class="text-right font-medium text-xs px-3 py-2">Notas</th>
                            <th class="text-right font-medium text-xs px-3 py-2">Diferença</th>
                            <th class="text-left font-medium text-xs px-5 py-2">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $rows)
                            @php
                                $client = $rows->first()->client;
                                $delivery = $rows->first()->delivery;
                                $missing = $expects && $rows->contains(fn ($r) => $r->consumed > 0 && $r->invoices === 0);
                            @endphp
                            @foreach($rows as $r)
                                @php $diff = $r->invoiced > 0 && $r->consumed > 0 ? ($r->invoiced - $r->consumed) / $r->consumed * 100 : null; @endphp
                                <tr style="border-top:1px solid var(--border)">
                                    <td class="px-5 py-2.5">
                                        @if($loop->first)
                                            <a href="{{ route('billing-statements.show', [$client, 'mes' => $month->format('Y-m')]) }}" class="font-semibold hover:underline" style="color:var(--text)">{{ $client->displayName() }}</a><br>
                                        @endif
                                        <span class="text-xs" style="color:var(--muted)">{{ $r->account->platformLabel() }} · {{ $r->account->account_id }}{{ $r->account->payment_method ? ' · ' . (\App\Models\ClientAdAccount::$paymentMethods[$r->account->payment_method] ?? $r->account->payment_method) : '' }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right whitespace-nowrap" style="font-variant-numeric:tabular-nums">{{ $money($r->consumed) }}</td>
                                    <td class="px-3 py-2.5 text-right whitespace-nowrap" style="font-variant-numeric:tabular-nums">
                                        @if($r->invoices)
                                            {{ $r->invoiced > 0 ? $money($r->invoiced) : '' }} <span class="text-xs" style="color:var(--muted)">({{ $r->invoices }})</span>
                                        @elseif($r->consumed > 0 && \App\Services\Billing\AccountStatement::expectsInvoice($month))
                                            <span class="badge {{ $deadlineState === 'late' ? 'badge-red' : 'badge-orange' }}">Falta subir</span>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right whitespace-nowrap text-xs" style="color:{{ \App\Services\Billing\AccountStatement::diffLooksOff($r->account->platform, $diff) ? 'var(--orange)' : 'var(--muted)' }}">
                                        {{ $diff !== null ? ($diff > 0 ? '+' : '') . number_format($diff, 1, ',', '.') . '%' : '' }}
                                    </td>
                                    <td class="px-5 py-2.5 text-xs">
                                        @if($loop->first)
                                            @if($delivery)
                                                <span class="badge badge-green">Enviada {{ $delivery->sent_at->format('d/m') }}</span>
                                                @if($delivery->viewed_at)<span class="badge badge-blue">Vista {{ $delivery->viewed_at->format('d/m') }}</span>@endif
                                            @elseif($missing)
                                                <a href="{{ route('billing-statements.show', [$client, 'mes' => $month->format('Y-m')]) }}" class="btn btn-primary btn-xs"><x-icon name="upload" size="12" />Subir notas</a>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="5" class="text-center py-6" style="color:var(--muted)">Nenhuma conta anunciou em {{ $monthLabel($month) }} neste filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs mt-3" style="color:var(--muted)">"Consumido" é o gasto nas campanhas pela sincronização; a nota é o valor oficial. Na <b>Meta</b>, desde 2026 a nota inclui os impostos e fica cerca de 14% acima do consumido. No <b>Google</b>, fica igual ou um pouco abaixo (cliques inválidos descontados). Fora disso a diferença fica laranja: vale conferir se faltou nota ou se o mês está certo.</p>
        </section>
    </div>
</x-app-layout>
