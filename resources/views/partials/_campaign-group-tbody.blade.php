{{-- Grupo de campanhas (cabeçalho colapsável + linhas) — mesmo padrão de
     partials/_task-group-tbody.blade.php, usado em Filas/Sprint.
     Espera: $groupBy, $groupKey, $groupCampaigns --}}
@php
    $overdueCount = $groupCampaigns->filter(fn ($c) => $c->isOptimizationOverdue())->count();

    if ($groupBy === 'situacao') {
        $situationKey = $groupKey === '__sem_situacao__' ? '' : $groupKey;
        $groupLabel = \App\Models\AdCampaign::$managementSituations[$situationKey] ?? ($situationKey ?: 'Sem situação');
        $groupColor = 'var(--' . (\App\Models\AdCampaign::$managementSituationColors[$situationKey] ?? 'muted') . ')';
    } else {
        $firstCampaign = $groupCampaigns->first();
        $groupLabel = $firstCampaign->adAccount?->client?->displayName() ?? 'Sem cliente';
        $groupColor = 'var(--grad)';
    }
    $groupInitials = strtoupper(substr($groupLabel, 0, 2));
@endphp

<tbody x-data="{ groupOpen: true }">
    <tr class="group-header-row" style="background:var(--s2)">
        <td colspan="10" style="padding:10px 16px">
            <button @click="groupOpen = !groupOpen" type="button" class="flex items-center gap-3 w-full text-left">
                <svg class="h-3 w-3 flex-shrink-0 transition-transform duration-200"
                     :class="groupOpen ? 'rotate-90' : ''"
                     fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"
                     style="color:var(--muted)">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>

                <div class="flex h-6 w-6 items-center justify-center rounded-full text-white flex-shrink-0"
                     style="background:{{ $groupColor }}; font-size:9px; font-weight:700">
                    {{ $groupInitials }}
                </div>

                <span class="text-sm font-semibold" style="color:var(--text)">{{ $groupLabel }}</span>

                <span class="badge" style="background:rgba(100, 59, 142,.08); border-color:rgba(100, 59, 142,.2); color:var(--purple); font-size:11px">
                    {{ $groupCampaigns->count() }} campanha{{ $groupCampaigns->count() !== 1 ? 's' : '' }}
                </span>

                @if($overdueCount > 0)
                    <span class="badge" style="background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.2); color:var(--red); font-size:11px">
                        {{ $overdueCount }} atrasada{{ $overdueCount > 1 ? 's' : '' }}
                    </span>
                @endif
            </button>
        </td>
    </tr>

    @foreach($groupCampaigns as $campaign)
        @include('partials._campaign-tr', ['campaign' => $campaign])
    @endforeach
</tbody>
