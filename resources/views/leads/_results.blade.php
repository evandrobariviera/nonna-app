{{-- Visão em Lista da Central de Leads — compartilhado entre o app interno
     (LeadController::results()) e o Portal (Portal\LeadController::results()),
     chamado via fetch por live-filter.js. Espera $leads (paginator),
     $showClient, $showAssignee, $showRoute (nome da rota de show). --}}
<p class="text-sm text-right mb-2" style="color:var(--muted)">
    {{ $leads->total() }} lead{{ $leads->total() !== 1 ? 's' : '' }}
</p>

<div class="card overflow-hidden">
<div class="overflow-x-auto">
    <table class="nonna-table">
        <thead>
            <tr>
                <th>Lead</th>
                @if($showClient)<th>Cliente</th>@endif
                <th style="width:130px">Estágio</th>
                <th>Origem</th>
                <th style="width:120px">UTM Source</th>
                @if($showAssignee)<th style="width:130px">Responsável</th>@endif
                <th style="width:110px">Convertido em</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leads as $opp)
                <tr>
                    <td>
                        <a href="{{ route($showRoute, $opp) }}" class="font-semibold hover:underline"
                           style="color:var(--text); font-size:13.5px">
                            {{ $opp->lead->name ?: 'Sem nome' }}
                        </a>
                        <div class="text-xs" style="color:var(--muted)">
                            {{ $opp->lead->phone ?: $opp->lead->email ?: '—' }}
                        </div>
                    </td>
                    @if($showClient)
                        <td class="text-sm" style="color:var(--text)">
                            {{ $opp->lead->client?->displayName() ?? '—' }}
                        </td>
                    @endif
                    <td>
                        <span class="badge badge-{{ $opp->stageColor() }}">{{ $opp->stageLabel() }}</span>
                    </td>
                    <td class="text-sm" style="color:var(--muted2)">
                        {{ $opp->source?->displayName() ?? $opp->channel?->kindLabel() ?? '—' }}
                    </td>
                    <td class="text-xs font-mono" style="color:var(--muted)">
                        {{ $opp->utm_source ?? '—' }}
                    </td>
                    @if($showAssignee)
                        <td class="text-sm" style="color:var(--text)">
                            {{ $opp->assignedTo?->name ?? '—' }}
                        </td>
                    @endif
                    <td class="text-xs" style="color:var(--muted)">
                        {{ ($opp->received_at ?? $opp->created_at)->format('d/m/Y') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="tab-placeholder">
                            <div class="tab-placeholder-icon"><x-icon name="megaphone" size="32" /></div>
                            <div class="tab-placeholder-title">Nenhum lead encontrado</div>
                            <div class="tab-placeholder-desc">Ajuste os filtros pra ver outros resultados.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

<div class="mt-4">
    {{ $leads->links() }}
</div>
