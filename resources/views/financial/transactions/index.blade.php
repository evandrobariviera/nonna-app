<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Financeiro</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Lançamentos</h1>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Navegação de mês ── --}}
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('financial-transactions.index', ['month' => $monthStart->copy()->subMonth()->format('Y-m')]) }}"
           class="btn btn-ghost btn-sm">‹ Anterior</a>
        <span class="text-sm font-semibold" style="color:var(--text)">{{ $monthStart->translatedFormat('F/Y') }}</span>
        <a href="{{ route('financial-transactions.index', ['month' => $monthStart->copy()->addMonth()->format('Y-m')]) }}"
           class="btn btn-ghost btn-sm">Próximo ›</a>
    </div>

    {{-- ── KPIs ── --}}
    <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(4, 1fr)">
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--green)">
                R$ {{ number_format($stats['entradas_previstas'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Entradas previstas</div>
        </div>
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--red)">
                R$ {{ number_format($stats['saidas_previstas'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Saídas previstas</div>
        </div>
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:{{ $stats['saldo_previsto'] >= 0 ? 'var(--text)' : 'var(--red)' }}">
                R$ {{ number_format($stats['saldo_previsto'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Saldo previsto</div>
        </div>
        <div class="card px-4 py-4 text-left">
            <div class="text-2xl font-black mb-1" style="color:var(--purple)">
                R$ {{ number_format($stats['realizado'], 2, ',', '.') }}
            </div>
            <div class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Realizado até agora</div>
        </div>
    </div>

    {{-- ── Novo lançamento ── --}}
    <div class="mb-6" x-data="{ open: false }">
        <button @click="open = !open" type="button" class="btn btn-primary btn-sm mb-3">
            <span x-text="open ? 'Cancelar' : '+ Novo Lançamento'"></span>
        </button>

        <div x-show="open" x-cloak class="card px-5 py-5">
            <form method="POST" action="{{ route('financial-transactions.store') }}" class="grid gap-3"
                  style="grid-template-columns: repeat(4, 1fr)" x-data="{ repeatMode: 'none' }">
                @csrf
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Tipo</label>
                    <select name="type" required class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                        @foreach(\App\Models\FinancialTransaction::$types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Categoria</label>
                    <select name="category_id" class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                        <option value="">—</option>
                        @foreach($categories as $type => $group)
                            <optgroup label="{{ \App\Models\FinancialCategory::$types[$type] ?? $type }}">
                                @foreach($group as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Valor (R$)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required
                           class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Vencimento</label>
                    <input type="date" name="due_date" required
                           class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Forma de Pagamento</label>
                    <select name="payment_method" class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                        <option value="">—</option>
                        <option value="pix">Pix</option>
                        <option value="cartao">Cartão</option>
                        <option value="boleto">Boleto</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Cliente</label>
                    <select name="client_id" class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                        <option value="">—</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Contrato</label>
                    <select name="contract_id" class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                        <option value="">—</option>
                        @foreach($contracts as $contract)
                            <option value="{{ $contract->id }}">{{ $contract->client?->company_name }} — {{ $contract->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Descrição</label>
                    <input type="text" name="description" maxlength="255"
                           class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                </div>
                <div>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Repetição</label>
                    <select name="repeat_mode" x-model="repeatMode" class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                        <option value="none">Nenhuma</option>
                        <option value="recurring">Recorrente — mesmo valor todo mês</option>
                        <option value="installments">Parcelado — divide o valor total</option>
                    </select>
                    <p class="text-xs mt-1" style="color:var(--muted2)" x-show="repeatMode === 'installments'" x-cloak>
                        "Valor" acima é o total — divide entre as parcelas.
                    </p>
                </div>
                <div x-show="repeatMode !== 'none'" x-cloak>
                    <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Quantas vezes</label>
                    <input type="number" name="repeat_count" min="2" max="60" :required="repeatMode !== 'none'"
                           class="w-full" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card overflow-x-auto">
        @if($transactions->isEmpty())
            <div class="px-6 py-16 text-center" style="color:var(--muted)">
                <p class="text-sm">Nenhum lançamento neste mês.</p>
            </div>
        @else
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th>Vencimento</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Cliente / Contrato</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                        @php
                            $statusOptions = collect(\App\Models\FinancialTransaction::$statuses)
                                ->only(['previsto', 'pago', 'cancelado'])
                                ->toArray();
                            if ($transaction->displayStatus() === 'atrasado') {
                                $statusOptions = ['atrasado' => \App\Models\FinancialTransaction::$statuses['atrasado']] + $statusOptions;
                            }
                        @endphp
                        <tr>
                            <td class="text-xs text-[var(--muted2)]">{{ $transaction->due_date->format('d/m/Y') }}</td>
                            <td class="text-xs font-semibold" style="color:{{ $transaction->type === 'entrada' ? 'var(--green)' : 'var(--red)' }}">
                                {{ $transaction->typeLabel() }}
                            </td>
                            <td>
                                @if($transaction->category)
                                    <span class="badge badge-{{ $transaction->category->color }}">{{ $transaction->category->name }}</span>
                                @else
                                    <span style="color:var(--muted)">—</span>
                                @endif
                            </td>
                            <td class="text-xs text-[var(--muted2)]">
                                {{ $transaction->client?->company_name ?? $transaction->contract?->client?->company_name ?? '—' }}
                                @if($transaction->contract)
                                    <span style="color:var(--muted)">· {{ $transaction->contract->title }}</span>
                                @endif
                            </td>
                            <td class="text-xs text-[var(--muted2)]">
                                {{ $transaction->description ?? '—' }}
                                @if($transaction->installment_total)
                                    <span style="color:var(--muted)">({{ $transaction->installment_number }}/{{ $transaction->installment_total }})</span>
                                @endif
                            </td>
                            <td class="text-sm font-mono text-[var(--text)]">
                                R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                            </td>
                            <x-status-dropdown-cell :options="$statusOptions" :current="$transaction->displayStatus()"
                                :action="route('financial-transactions.update', $transaction)" :width="120" />
                            <td class="text-right">
                                <form method="POST" action="{{ route('financial-transactions.destroy', $transaction) }}"
                                      @submit.prevent="if (await $store.confirmDialog.ask('Remover este lançamento?')) $el.submit()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-mono hover:underline" style="color:var(--muted)">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
