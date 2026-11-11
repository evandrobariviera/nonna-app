{{-- Central de Comércio → Propostas (todas). Cada proposta pertence a uma oportunidade. --}}
@php $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header">Propostas</x-slot>

    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('proposals.index') }}" class="badge {{ !$status ? 'badge-purple' : 'badge-muted' }}" style="padding:4px 12px">Todas</a>
            @foreach(\App\Models\Proposal::$statuses as $key => $st)
                <a href="{{ route('proposals.index', ['situacao' => $key]) }}" class="badge {{ $status === $key ? 'badge-purple' : 'badge-muted' }}" style="padding:4px 12px">
                    {{ $st['label'] }}@if($key === 'pending_release' && $pendingRelease) ({{ $pendingRelease }})@endif
                </a>
            @endforeach
        </div>
        <a href="{{ route('proposals.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="14" /> Nova proposta</a>
    </div>

    <div class="card" style="padding:0; overflow-x:auto">
        <table class="nonna-table w-full">
            <thead><tr><th>Proposta</th><th>Oportunidade</th><th>Mensal</th><th>Único</th><th>{{ config('proposals.contract_months') }} meses</th><th>Situação</th><th>Atualizada</th></tr></thead>
            <tbody>
                @forelse($proposals as $p)
                    <tr class="cursor-pointer" onclick="window.location='{{ route('proposals.show', $p) }}'">
                        <td><a href="{{ route('proposals.show', $p) }}" class="font-semibold" style="color:var(--text)">{{ $p->displayTitle() }}</a>
                            <p class="text-xs" style="color:var(--muted)">{{ $p->baseLabel() }}</p></td>
                        <td class="text-sm">{{ $p->opportunity?->title }}<p class="text-xs" style="color:var(--muted)">{{ $p->opportunity?->contact?->name }}</p></td>
                        <td class="num text-sm" style="white-space:nowrap">{{ (float) $p->monthly_total ? $brl($p->monthly_total) : '—' }}</td>
                        <td class="num text-sm" style="white-space:nowrap">{{ (float) $p->one_time_total ? $brl($p->one_time_total) : '—' }}</td>
                        <td class="num text-sm" style="white-space:nowrap">{{ $brl($p->contract_total) }}</td>
                        <td><span class="badge badge-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
                        <td class="text-xs" style="color:var(--muted)">{{ $p->updated_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-sm py-8" style="color:var(--muted)">Nenhuma proposta ainda. Crie a primeira a partir de uma oportunidade.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $proposals->links() }}</div>
</x-app-layout>
