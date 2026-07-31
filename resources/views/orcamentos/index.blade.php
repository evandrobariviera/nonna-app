<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Rotina</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Orçamentos</h1>
            </div>
            @php
                $toggleInativos = request()->except('mostrar_inativos');
                if (!request()->boolean('mostrar_inativos')) $toggleInativos['mostrar_inativos'] = '1';
            @endphp
            <a href="{{ route('orcamentos.index', $toggleInativos) }}"
               class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
               style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_inativos') ? 'var(--purple)' : 'var(--muted)' }}">
                {{ request()->boolean('mostrar_inativos') ? '⊙ Ocultar clientes inativos' : '○ Mostrar clientes inativos' }}
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @foreach(\App\Http\Controllers\OrcamentoController::$groupOrder as $statusKey)
        @php
            $groupAccounts = $grouped->get($statusKey, collect());
            $s = \App\Models\ClientAdAccount::$budgetStatuses[$statusKey];
        @endphp
        <div class="mb-8" x-data="{ open: true }">
            <button @click="open = !open" type="button"
                class="text-xs font-mono uppercase tracking-widest transition-colors flex items-center gap-2 mb-3"
                style="color:var(--text)">
                <span :class="open ? 'rotate-90' : ''" class="transition-transform" style="color:var(--muted)">▶</span>
                <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:var(--{{ $s['color'] }})"></span>
                {{ $s['label'] }} ({{ $groupAccounts->count() }})
            </button>

            <div x-show="open" x-cloak>
                @if($groupAccounts->isEmpty())
                    <div class="card px-6 py-6 text-center" style="color:var(--muted)">
                        <p class="text-sm">Nenhuma conta neste status.</p>
                    </div>
                @else
                    <div class="card overflow-x-auto">
                        <table class="nonna-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Responsável</th>
                                    <th>Automação</th>
                                    <th>Plataforma</th>
                                    <th>Pagamento</th>
                                    <th>Nº Conta</th>
                                    <th>Saldo</th>
                                    <th>Dias restantes</th>
                                    <th>Boleto enviado</th>
                                    <th>Custo diário</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupAccounts as $account)
                                    <tr>
                                        <td class="font-semibold text-[var(--text)]">
                                            <a href="{{ route('clients.show', [$account->client_id, 'tab' => 'contas']) }}"
                                               style="color:var(--text)">
                                                {{ $account->client?->company_name ?? '—' }}
                                            </a>
                                        </td>

                                        <td>
                                            <select name="responsible_user_id" form="orcamento-{{ $account->id }}" onchange="this.form.submit()"
                                                    class="bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                                <option value="">—</option>
                                                @foreach($users as $user)
                                                    <option value="{{ $user->id }}" {{ $account->responsible_user_id === $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <label class="flex items-center gap-1.5 text-xs" style="color:var(--muted2)">
                                                <input type="checkbox" name="budget_automation_enabled" value="1"
                                                       form="orcamento-{{ $account->id }}" onchange="this.form.submit()"
                                                       {{ $account->budget_automation_enabled ? 'checked' : '' }}>
                                                {{ $account->budget_automation_enabled ? 'Sim' : 'Não' }}
                                            </label>
                                        </td>

                                        <td class="text-sm text-[var(--muted2)]">{{ $account->platformLabel() }}</td>
                                        <td class="text-xs text-[var(--muted2)]">{{ $account->paymentMethodLabel() }}</td>
                                        <td class="font-mono text-sm text-[var(--purple)]">{{ $account->account_id }}</td>
                                        <td class="text-xs text-[var(--text)]">
                                            {{ $account->balance !== null ? 'R$ ' . number_format((float) $account->balance, 2, ',', '.') : '—' }}
                                            @if($account->balanceSourceLabel())
                                                <span class="badge badge-blue" style="white-space:nowrap">{{ $account->balanceSourceLabel() }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $account->balanceStatusColor() }}" style="white-space:nowrap">
                                                {{ $account->balanceStatusLabel() }}
                                            </span>
                                        </td>
                                        <td class="text-xs text-[var(--muted2)]">
                                            {{ $account->last_billing_sent_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="text-xs text-[var(--muted2)]">
                                            R$ {{ number_format($account->avgDailySpend(), 2, ',', '.') }}
                                        </td>

                                        <td>
                                            <select name="budget_status" form="orcamento-{{ $account->id }}" onchange="this.form.submit()"
                                                    class="bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-1.5 focus:outline-none focus:border-[var(--purple)]">
                                                @foreach(\App\Models\ClientAdAccount::$budgetStatuses as $key => $opt)
                                                    <option value="{{ $key }}" {{ $account->budget_status === $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                            {{-- Form oculto — inputs desta linha referenciam via atributo form="", mesmo padrão de clients/show.blade.php (edit-account-{id}) --}}
                                            <form id="orcamento-{{ $account->id }}" method="POST"
                                                  action="{{ route('orcamentos.update-status', $account) }}" class="hidden">
                                                @csrf @method('PATCH')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

</x-app-layout>
