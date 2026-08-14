{{-- Linha da tabela de campanhas — usada em listagem flat e agrupada.
     Espera: $campaign (com ->stats já calculado pelo CampaignController::index()) --}}
@php
    $rowClass = match(true) {
        $campaign->isOptimizationOverdue()        => 'campaign-overdue',
        $campaign->last_optimized_at?->isToday()  => 'campaign-optimized-today',
        default                                    => '',
    };
@endphp
<tr class="{{ $rowClass }}" x-show="groupOpen">
    <td class="font-semibold text-[var(--text)]">
        {{ $campaign->adAccount?->client?->displayName() ?? '—' }}
    </td>
    <td>
        <div class="flex items-center gap-2 flex-wrap">
            <x-icon-chip :icon="$campaign->platformIcon()" :color="$campaign->managementStatusColor()" size="28" />
            <span class="badge badge-{{ $campaign->optimizationTierColor() }}">{{ $campaign->optimizationTierLabel() }}</span>
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-[var(--text)] hover:underline">{{ $campaign->name }}</a>
        </div>
    </td>
    <td class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $campaign->platform }}</td>
    <x-status-dropdown-cell :options="\App\Models\AdCampaign::$managementStatuses" :current="$campaign->management_status"
        :action="route('campaigns.update-status', $campaign)" field="management_status" :width="150" />
    <td class="text-xs font-mono text-[var(--muted2)]">R$ {{ number_format($campaign->stats->spend, 2, ',', '.') }}</td>
    <td class="text-xs font-mono text-[var(--muted2)]">
        {{ $campaign->stats->cpa !== null ? 'R$ ' . number_format($campaign->stats->cpa, 2, ',', '.') : '—' }}
    </td>
    <td class="text-xs font-mono text-[var(--muted2)]">
        {{ $campaign->stats->ctr !== null ? number_format($campaign->stats->ctr, 2, ',', '.') . '%' : '—' }}
    </td>
    <td class="text-xs font-mono text-[var(--muted2)]">
        {{ $campaign->stats->roas !== null ? number_format($campaign->stats->roas, 2, ',', '.') . 'x' : '—' }}
    </td>
    <td class="text-xs font-mono" style="color:{{ $campaign->isOptimizationOverdue() ? 'var(--red)' : 'var(--muted2)' }}">
        {{ $campaign->last_optimized_at ? 'há ' . $campaign->last_optimized_at->diffForHumans(null, true) : 'nunca otimizada' }}
    </td>
    <td>
        <div class="row-actions flex items-center gap-2">
            <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-primary btn-xs">Abrir</a>
            <a href="{{ route('campaigns.show', $campaign) }}#otimizacao" class="text-xs font-semibold" style="color:var(--red)">Otimizar →</a>
        </div>
    </td>
</tr>
