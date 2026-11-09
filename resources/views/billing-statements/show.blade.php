<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('billing-statements.index') }}" class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">← Prestação de contas</a>
            <h1 class="text-2xl font-black text-[var(--text)]">{{ $client->displayName() }}</h1>
        </div>
    </x-slot>

    @php
        $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
        $monthLabel = fn ($m) => \App\Services\Billing\StatementDeliveryService::monthLabel($m);
        $start = \Illuminate\Support\Carbon::parse(\App\Services\Billing\AccountStatement::START);
        $monthOptions = collect(range(0, 5))->map(fn ($i) => now()->subMonthsNoOverflow($i)->startOfMonth());
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red)">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 items-start lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-5 min-w-0">
                @forelse($accounts as $account)
                    <section class="card p-5 min-w-0">
                        <h2 class="card-title flex flex-wrap items-center justify-between gap-2">
                            <span>{{ $account->platformLabel() }} <small class="font-normal text-xs" style="color:var(--muted)">conta {{ $account->account_id }}{{ $account->payment_method ? ' · ' . (\App\Models\ClientAdAccount::$paymentMethods[$account->payment_method] ?? $account->payment_method) : '' }}</small></span>
                            @if(\App\Services\Billing\AccountStatement::hasBalance($account))
                                <span class="text-xs font-normal" style="color:var(--muted2)">Saldo hoje na plataforma: <b style="color:var(--text)">{{ $money($account->balance) }}</b></span>
                            @else
                                <span class="text-xs font-normal" style="color:var(--muted)">{{ $account->payment_method === 'cartao' ? 'Cartão: sem saldo para controlar' : 'Saldo não informado pela plataforma' }}</span>
                            @endif
                        </h2>

                        <div class="overflow-x-auto mt-3 -mx-5">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr style="background:var(--s2); color:var(--muted2)">
                                        <th class="text-left font-medium text-xs px-5 py-2">Mês</th>
                                        <th class="text-right font-medium text-xs px-3 py-2">Saldo no início</th>
                                        <th class="text-right font-medium text-xs px-3 py-2">Créditos</th>
                                        <th class="text-right font-medium text-xs px-3 py-2">Consumido</th>
                                        <th class="text-right font-medium text-xs px-3 py-2">Notas</th>
                                        <th class="text-right font-medium text-xs px-5 py-2">Saldo no fim</th>
                                    </tr>
                                </thead>
                                @foreach($statements[$account->id] as $row)
                                    <tbody x-data="{ open: {{ $row['month']->equalTo($defaultMonth) ? 'true' : 'false' }} }">
                                        <tr class="cursor-pointer hover:bg-[var(--s2)]" style="border-top:1px solid var(--border)" @click="open = !open">
                                            <td class="px-5 py-2.5 whitespace-nowrap">
                                                <span class="font-semibold" style="color:var(--text)">{{ ucfirst($monthLabel($row['month'])) }}</span>
                                                @if($row['notes'])<x-icon name="message-square" size="12" class="inline ml-1" style="color:var(--orange)" />@endif
                                            </td>
                                            <td class="px-3 py-2.5 text-right whitespace-nowrap" style="font-variant-numeric:tabular-nums">{{ $money($row['opening']) }}@if($row['opening_manual'])<span class="text-[10px] ml-1" style="color:var(--muted)">manual</span>@endif</td>
                                            <td class="px-3 py-2.5 text-right whitespace-nowrap" style="font-variant-numeric:tabular-nums; color:{{ $row['credits'] > 0 ? 'var(--green)' : 'var(--muted)' }}">{{ $row['credits'] > 0 ? $money($row['credits']) : '—' }}</td>
                                            <td class="px-3 py-2.5 text-right whitespace-nowrap" style="font-variant-numeric:tabular-nums; color:{{ $row['consumed'] > 0 ? 'var(--red)' : 'var(--muted)' }}">{{ $row['consumed'] > 0 ? $money($row['consumed']) : '—' }}</td>
                                            <td class="px-3 py-2.5 text-right whitespace-nowrap" style="font-variant-numeric:tabular-nums">
                                                @php $nf = $row['documents']->where('type', 'nota'); @endphp
                                                @if($nf->isNotEmpty())
                                                    {{ $row['invoiced'] > 0 ? $money($row['invoiced']) : '' }} <span class="text-xs" style="color:var(--muted)">({{ $nf->count() }})</span>
                                                @elseif($row['consumed'] > 0 && \App\Services\Billing\AccountStatement::expectsInvoice($row['month']))
                                                    <span class="badge badge-orange">Falta</span>
                                                @else
                                                    <span style="color:var(--muted)">—</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 text-right whitespace-nowrap font-semibold" style="font-variant-numeric:tabular-nums">{{ $money($row['closing']) }}</td>
                                        </tr>
                                        <tr x-show="open" x-cloak>
                                            <td colspan="6" class="px-5 pb-4 pt-1">
                                                <div class="rounded p-3 flex flex-col gap-3" style="background:var(--s2)">
                                                    @if($row['documents']->isNotEmpty())
                                                        <div class="flex flex-col gap-1.5">
                                                            @foreach($row['documents'] as $doc)
                                                                <div class="flex items-center gap-2 text-sm">
                                                                    <span class="badge {{ $doc->type === 'nota' ? 'badge-purple' : 'badge-green' }}">{{ $doc->typeLabel() }}</span>
                                                                    @if($doc->hasFile())
                                                                        <a href="{{ $doc->url() }}" target="_blank" rel="noopener" class="hover:underline min-w-0 truncate" style="color:var(--text)">{{ $doc->filename }}</a>
                                                                    @else
                                                                        <span class="min-w-0 truncate" style="color:var(--muted2)">{{ $doc->pix_code ? 'Código PIX' : 'sem arquivo' }}</span>
                                                                    @endif
                                                                    <span class="text-xs whitespace-nowrap" style="color:var(--muted)">{{ $doc->amount ? $money($doc->amount) . ' · ' : '' }}{{ $doc->created_at->format('d/m') }}{{ $doc->notified_at ? ' · cliente avisado' : '' }}</span>
                                                                    <form method="POST" action="{{ route('clients.ad-accounts.billing.destroy', [$client, $account, $doc]) }}" class="ml-auto"
                                                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover {{ addslashes($doc->filename ?? $doc->typeLabel()) }}?')) $el.submit()">
                                                                        @csrf @method('DELETE')
                                                                        <button type="submit" class="btn btn-ghost btn-xs" aria-label="Remover"><x-icon name="trash-2" size="13" /></button>
                                                                    </form>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-xs" style="color:var(--muted)">Nenhum documento neste mês.</p>
                                                    @endif

                                                    <form method="POST" action="{{ route('billing-statements.statement.update', $account) }}" class="flex flex-wrap items-end gap-2">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="month" value="{{ $row['month']->format('Y-m') }}">
                                                        @if(\App\Services\Billing\AccountStatement::hasBalance($account) && $row['month']->gte($start))
                                                            <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Saldo no início (corrigir)
                                                                <input type="text" name="opening_balance" inputmode="decimal" class="text-sm w-32"
                                                                       value="{{ $row['opening_manual'] ? number_format((float) $row['opening'], 2, ',', '.') : '' }}" placeholder="{{ $row['opening'] !== null ? number_format($row['opening'], 2, ',', '.') : '' }}">
                                                            </label>
                                                        @endif
                                                        <label class="text-xs flex flex-col gap-1 flex-1 min-w-[12rem]" style="color:var(--muted2)">Observação do mês
                                                            <input type="text" name="notes" class="text-sm w-full" value="{{ $row['notes'] }}" placeholder="Ex.: precisamos trocar o CNPJ">
                                                        </label>
                                                        <button type="submit" class="btn btn-sm">Salvar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                        @if(\App\Services\Billing\AccountStatement::hasBalance($account))
                            <p class="text-xs mt-3" style="color:var(--muted)">O saldo começa em {{ $monthLabel($start) }}, a partir do saldo real da conta. Depois encadeia sozinho: início + créditos − consumido = fim.</p>
                        @endif
                    </section>
                @empty
                    <div class="card p-6 text-sm" style="color:var(--muted)">Este cliente não tem conta de anúncio cadastrada.</div>
                @endforelse
            </div>

            <div class="flex flex-col gap-5 min-w-0">
                {{-- Subir notas --}}
                @if($accounts->isNotEmpty())
                    <section class="card p-5" x-data="{ files: [] }">
                        <h2 class="card-title">Subir notas fiscais</h2>
                        <form method="POST" action="{{ route('billing-statements.invoices.store', $client) }}" enctype="multipart/form-data" class="flex flex-col gap-3 mt-3">
                            @csrf
                            <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Conta
                                <select name="client_ad_account_id" class="text-sm w-full" required>
                                    @foreach($accounts as $a)
                                        <option value="{{ $a->id }}">{{ $a->platformLabel() }} · {{ $a->account_id }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Mês de referência (o mês do gasto)
                                <select name="month" class="text-sm w-full" required>
                                    @foreach($monthOptions as $m)
                                        <option value="{{ $m->format('Y-m') }}" @selected($m->equalTo($defaultMonth))>{{ ucfirst($monthLabel($m)) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Arquivos (pode escolher vários)
                                <input type="file" name="files[]" multiple required accept=".pdf,.xml,.png,.jpg,.jpeg" class="text-sm"
                                       @change="files = Array.from($event.target.files).map(f => f.name)">
                            </label>
                            <template x-for="(name, i) in files" :key="name + i">
                                <label class="text-xs flex items-center gap-2" style="color:var(--muted2)">
                                    <span class="flex-1 min-w-0 truncate" x-text="name"></span>
                                    <input type="text" :name="'amounts[' + i + ']'" inputmode="decimal" placeholder="Valor (R$)" class="text-sm w-28">
                                </label>
                            </template>
                            <label class="flex items-start gap-2 text-sm cursor-pointer">
                                <input type="hidden" name="notify" value="0">
                                <input type="checkbox" name="notify" value="1" checked class="mt-0.5" style="accent-color:var(--purple)">
                                <span>Avisar o cliente agora <span class="block text-xs" style="color:var(--muted)">Um aviso só (WhatsApp/e-mail) dizendo que as notas estão no Portal. Os arquivos só abrem lá, com login.</span></span>
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm self-start"><x-icon name="upload" size="14" />Salvar notas</button>
                        </form>
                    </section>
                @endif

                {{-- Envios --}}
                <section class="card p-5">
                    <h2 class="card-title">Envios ao cliente</h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @forelse($deliveries as $d)
                            <div class="text-sm rounded px-3 py-2" style="background:var(--s2)">
                                <p style="color:var(--text)"><b>{{ ucfirst($monthLabel($d->month)) }}</b> · {{ count($d->document_ids ?? []) }} {{ count($d->document_ids ?? []) === 1 ? 'nota' : 'notas' }}{{ $d->total ? ' · ' . $money($d->total) : '' }}</p>
                                <p class="text-xs mt-0.5" style="color:var(--muted)">
                                    Enviado {{ $d->sent_at?->format('d/m H:i') }}{{ $d->sentBy ? ' por ' . \Illuminate\Support\Str::before($d->sentBy->name, ' ') : '' }}
                                    · @if($d->viewed_at)<span style="color:var(--green)">visto em {{ $d->viewed_at->format('d/m H:i') }}</span>@else ainda não abriu @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-sm" style="color:var(--muted)">Nenhum envio ainda.</p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('billing-statements.send', $client) }}" class="flex items-end gap-2 mt-3"
                          @submit.prevent="if (await $store.confirmDialog.ask('Enviar de novo ao cliente todas as notas do mês escolhido?')) $el.submit()">
                        @csrf
                        <select name="month" class="text-sm flex-1" aria-label="Mês">
                            @foreach($monthOptions as $m)
                                <option value="{{ $m->format('Y-m') }}" @selected($m->equalTo($defaultMonth))>{{ ucfirst($monthLabel($m)) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm"><x-icon name="send" size="13" />Reenviar notas do mês</button>
                    </form>
                </section>

                {{-- Quem recebe --}}
                <section class="card p-5">
                    <h2 class="card-title">Quem recebe o aviso</h2>
                    <p class="text-xs mt-1 mb-3" style="color:var(--muted2)">As notas só abrem no Portal do Cliente, com login. O aviso por WhatsApp/e-mail só avisa e leva até lá.</p>
                    @php $withPortal = $recipients->where('portal_access_enabled', true)->count(); @endphp
                    <div class="flex flex-col gap-1.5">
                        @forelse($recipients as $rc)
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate" style="color:var(--text)">{{ $rc->contact?->name }}</span>
                                <span class="badge {{ $rc->portal_access_enabled ? 'badge-green' : 'badge-orange' }}">{{ $rc->portal_access_enabled ? 'acessa o Portal' : 'sem acesso ao Portal' }}</span>
                            </div>
                        @empty
                            <p class="text-sm" style="color:var(--red)">Ninguém deste cliente recebe o aviso de prestação de contas.</p>
                        @endforelse
                    </div>
                    @if($recipients->isNotEmpty() && $withPortal === 0)
                        <p class="text-xs mt-3" style="color:var(--orange)">Ninguém que recebe o aviso tem acesso ao Portal: o cliente vai ser avisado mas não vai conseguir abrir as notas.</p>
                    @endif
                    <a href="{{ route('clients.show', [$client, 'tab' => 'contatos']) }}" class="btn btn-ghost btn-sm mt-3">Ajustar na ficha do cliente</a>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
