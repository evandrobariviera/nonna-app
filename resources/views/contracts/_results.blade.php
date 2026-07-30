{{-- Fragmento reaproveitado no load inicial (contracts.index) e na busca dinâmica via
     AJAX (ContractController::results(), fetch disparado por live-filter.js). --}}
<div class="card">
    @if($contracts->isEmpty())
        <div class="px-6 py-16 text-center" style="color:var(--muted)">
            <p class="text-sm mb-3">Nenhum contrato encontrado.</p>
            <p class="text-xs">Acesse a página de um cliente e abra a aba "Contratos" para criar o primeiro.</p>
        </div>
    @else
        <table class="nonna-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Título</th>
                    <th>Status</th>
                    <th>Vigência</th>
                    <th>Fee</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($contracts as $contract)
                    @php
                        $isVencido = $contract->end_date
                            && $contract->end_date->isPast()
                            && !in_array($contract->status, ['encerrado', 'cancelado']);
                    @endphp
                    <tr>
                        <td class="font-semibold text-[var(--text)]">
                            <a href="{{ route('clients.show', [$contract->client, 'tab' => 'contratos']) }}"
                               class="hover:underline">{{ $contract->client->company_name }}</a>
                        </td>
                        <td class="text-[var(--text)]">{{ $contract->title }}</td>
                        <x-status-dropdown-cell :options="\App\Models\Contract::$statuses" :current="$contract->status"
                            :action="route('clients.contracts.update-status', [$contract->client, $contract])" :width="130" />
                        <td class="text-xs {{ $isVencido ? 'font-semibold' : '' }}" style="color:{{ $isVencido ? 'var(--red)' : 'var(--muted)' }}">
                            {{ $contract->periodLabel() }}
                            @if($isVencido) · vencido @endif
                        </td>
                        <td class="text-xs font-mono text-[var(--muted2)]">
                            {{ $contract->fee_value ? 'R$ ' . number_format($contract->fee_value, 2, ',', '.') : '—' }}
                            @if($contract->fee_type)
                                <span style="color:var(--muted)">/ {{ $contract->feeTypeLabel() }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('clients.contracts.show', [$contract->client, $contract]) }}"
                               class="text-xs font-mono hover:underline"
                               style="color:var(--purple)">
                                Abrir
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
